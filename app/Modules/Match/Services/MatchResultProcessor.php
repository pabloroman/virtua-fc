<?php

namespace App\Modules\Match\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Squad\Services\EligibilityService;
use App\Modules\Player\Services\PlayerConditionService;
use App\Modules\Notification\Services\NotificationService;

class MatchResultProcessor
{
    public function __construct(
        private readonly StandingsCalculator $standingsCalculator,
        private readonly EligibilityService $eligibilityService,
        private readonly PlayerConditionService $conditionService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Process all match results for a matchday in batched operations.
     *
     * @param  string|null  $deferMatchId  Match ID to skip standings and GK stats for (deferred to finalization)
     */
    public function processAll(string $gameId, int $matchday, string $currentDate, array $matchResults, ?string $deferMatchId = null, $allPlayers = null): void
    {
        // Load game once for previous date capture and date guard
        $game = Game::find($gameId);

        // 1. Update game state (replaces onMatchdayAdvanced projector)
        // Only advance current_date forward — background batch processing must not
        // regress the date that was already set by the player's batch.
        $newDate = Carbon::parse($currentDate);
        $updateData = ['current_matchday' => $matchday];
        if (! $game->current_date || $newDate->gte($game->current_date)) {
            $updateData['current_date'] = $newDate->toDateString();
        }
        Game::where('id', $gameId)->update($updateData);

        // 2. Bulk update match records (scores + played)
        $this->bulkUpdateMatchScores($matchResults);

        // Reload game with post-update state for the rest of the method
        $game = Game::find($gameId);
        $matchIds = array_column($matchResults, 'matchId');
        $matches = GameMatch::whereIn('id', $matchIds)->get()->keyBy('id');

        // Load competitions once (typically 1-2 unique)
        $competitionIds = collect($matchResults)->pluck('competitionId')->unique();
        $competitions = Competition::whereIn('id', $competitionIds)->get()->keyBy('id');

        // 3. Serve suspensions for all matches (batch, using pre-loaded player IDs)
        // Exclude players from the deferred match's teams — their suspensions
        // will be served during finalization, so they remain ineligible for
        // substitution while the user plays the live match.
        $preLoadedPlayerIds = $allPlayers ? $allPlayers->flatten()->pluck('id')->toArray() : [];
        if ($deferMatchId && $allPlayers) {
            $deferredMatch = $matches->get($deferMatchId);
            if ($deferredMatch) {
                $deferredPlayerIds = [];
                foreach ([$deferredMatch->home_team_id, $deferredMatch->away_team_id] as $teamId) {
                    $deferredPlayerIds = array_merge(
                        $deferredPlayerIds,
                        $allPlayers->get($teamId, collect())->pluck('id')->toArray()
                    );
                }
                $preLoadedPlayerIds = array_values(array_diff($preLoadedPlayerIds, $deferredPlayerIds));
            }
        }
        $this->batchServeSuspensions($matches, $matchResults, $preLoadedPlayerIds, $allPlayers);

        // 4. Bulk insert all match events across all matches
        $this->bulkInsertMatchEvents($gameId, $matchResults);

        // 5. Batch process player stats across all matches
        $this->batchProcessPlayerStats($game, $matchResults, $matches, $competitions, $deferMatchId, $allPlayers);

        // 6. Bulk update appearances across all matches (including auto-subbed-in players)
        $this->bulkUpdateAppearances($matches, $matchResults);

        // 6b. Record auto-substitutions in match substitutions JSON
        $this->recordAutoSubstitutions($matches, $matchResults);

        // 7. Batch update conditions (exclude deferred match — finalization handles it)
        $conditionMatches = $deferMatchId ? $matches->except($deferMatchId) : $matches;
        $conditionResults = $deferMatchId
            ? array_filter($matchResults, fn ($r) => $r['matchId'] !== $deferMatchId)
            : $matchResults;
        $this->batchUpdateConditions($conditionMatches, $conditionResults, $allPlayers ?? collect());

        // 8. Batch update goalkeeper stats (skip deferred match)
        $gkResults = $deferMatchId
            ? array_filter($matchResults, fn ($r) => $r['matchId'] !== $deferMatchId)
            : $matchResults;
        $gkMatches = $deferMatchId
            ? $matches->except($deferMatchId)->keyBy('id')
            : $matches;
        $this->batchUpdateGoalkeeperStats($gkMatches, $gkResults, $allPlayers);

        // 9. Update standings per league in bulk (skip deferred match)
        $leagueResultsByCompetition = [];
        foreach ($matchResults as $result) {
            if ($result['matchId'] === $deferMatchId) {
                continue;
            }

            $competition = $competitions->get($result['competitionId']);
            $match = $matches->get($result['matchId']);
            $isCupTie = $match?->cup_tie_id !== null;

            if ($competition?->isLeague() && ! $isCupTie) {
                $leagueResultsByCompetition[$result['competitionId']][] = $result;
            }
        }

        foreach ($leagueResultsByCompetition as $competitionId => $results) {
            $this->standingsCalculator->bulkUpdateAfterMatches($gameId, $competitionId, $results);
        }

    }

    /**
     * Update match scores in a single query using CASE WHEN.
     */
    private function bulkUpdateMatchScores(array $matchResults): void
    {
        if (empty($matchResults)) {
            return;
        }

        $ids = [];
        $homeCases = [];
        $awayCases = [];
        $homePossCases = [];
        $awayPossCases = [];
        $mvpCases = [];
        $isPostgres = DB::getDriverName() === 'pgsql';

        foreach ($matchResults as $result) {
            $id = $result['matchId'];
            $ids[] = $id;
            $homeCases[] = "WHEN id = '{$id}' THEN {$result['homeScore']}";
            $awayCases[] = "WHEN id = '{$id}' THEN {$result['awayScore']}";
            $homePoss = $result['homePossession'] ?? 50;
            $awayPoss = $result['awayPossession'] ?? 50;
            $homePossCases[] = "WHEN id = '{$id}' THEN {$homePoss}";
            $awayPossCases[] = "WHEN id = '{$id}' THEN {$awayPoss}";
            $mvpId = $result['mvpPlayerId'] ?? null;
            $mvpCases[] = $mvpId
                ? "WHEN id = '{$id}' THEN " . ($isPostgres ? "'{$mvpId}'::uuid" : "'{$mvpId}'")
                : "WHEN id = '{$id}' THEN NULL";
        }

        $idList = "'" . implode("','", $ids) . "'";

        DB::statement("
            UPDATE game_matches
            SET home_score = CASE " . implode(' ', $homeCases) . " END,
                away_score = CASE " . implode(' ', $awayCases) . " END,
                home_possession = CASE " . implode(' ', $homePossCases) . " END,
                away_possession = CASE " . implode(' ', $awayPossCases) . " END,
                mvp_player_id = CASE " . implode(' ', $mvpCases) . " END,
                played = true
            WHERE id IN ({$idList})
        ");
    }

    /**
     * Serve suspensions for all matches in the batch.
     * Decrements matches_remaining for suspended players whose team actually
     * played a match in the competition the suspension belongs to.
     *
     * @param  array  $preLoadedPlayerIds  Eligible player IDs (deferred match teams already excluded by caller)
     * @param  \Illuminate\Support\Collection|null  $allPlayers  Players grouped by team_id (for team lookup)
     */
    private function batchServeSuspensions($matches, array $matchResults, array $preLoadedPlayerIds, $allPlayers = null): void
    {
        $competitionIds = [];
        foreach ($matchResults as $result) {
            $competitionIds[$result['competitionId']] = true;
        }

        if (empty($competitionIds) || empty($preLoadedPlayerIds)) {
            return;
        }

        // Build a map of which competitions each team played in this batch.
        // Uses match result data (homeTeamId/awayTeamId) when available,
        // falls back to the GameMatch models for backward compatibility.
        $teamCompetitions = []; // [team_id => [competition_id => true]]
        foreach ($matchResults as $result) {
            $homeTeamId = $result['homeTeamId'] ?? null;
            $awayTeamId = $result['awayTeamId'] ?? null;

            if (! $homeTeamId || ! $awayTeamId) {
                $match = $matches->get($result['matchId']);
                $homeTeamId = $homeTeamId ?? $match?->home_team_id;
                $awayTeamId = $awayTeamId ?? $match?->away_team_id;
            }

            if ($homeTeamId) {
                $teamCompetitions[$homeTeamId][$result['competitionId']] = true;
            }
            if ($awayTeamId) {
                $teamCompetitions[$awayTeamId][$result['competitionId']] = true;
            }
        }

        // Build player → team mapping from pre-loaded data
        $playerTeamMap = []; // [player_id => team_id]
        if ($allPlayers) {
            foreach ($allPlayers as $teamId => $teamPlayers) {
                foreach ($teamPlayers as $player) {
                    if (in_array($player->id, $preLoadedPlayerIds)) {
                        $playerTeamMap[$player->id] = $teamId;
                    }
                }
            }
        }

        // Load suspensions with player and competition info for filtering
        $suspensions = PlayerSuspension::whereIn('competition_id', array_keys($competitionIds))
            ->where('matches_remaining', '>', 0)
            ->whereIn('game_player_id', $preLoadedPlayerIds)
            ->get(['id', 'game_player_id', 'competition_id']);

        // Filter: only serve if the player's team played in this specific competition.
        // When team mapping is unavailable, fall back to serving (preserving old behavior).
        $suspensionIds = [];
        foreach ($suspensions as $suspension) {
            $teamId = $playerTeamMap[$suspension->game_player_id] ?? null;
            if (! $teamId || empty($teamCompetitions)) {
                // Fallback: no team data available, serve as before
                $suspensionIds[] = $suspension->id;
            } elseif (isset($teamCompetitions[$teamId][$suspension->competition_id])) {
                $suspensionIds[] = $suspension->id;
            }
        }

        if (! empty($suspensionIds)) {
            PlayerSuspension::whereIn('id', $suspensionIds)->decrement('matches_remaining');
            PlayerSuspension::whereIn('id', $suspensionIds)
                ->where('matches_remaining', '<', 0)
                ->update(['matches_remaining' => 0]);
        }
    }

    /**
     * Bulk insert all match events across ALL matches in one chunked insert.
     */
    private function bulkInsertMatchEvents(string $gameId, array $matchResults): void
    {
        $now = now();
        $allRows = [];

        foreach ($matchResults as $result) {
            foreach ($result['events'] as $eventData) {
                $allRows[] = [
                    'id' => Str::uuid()->toString(),
                    'game_id' => $gameId,
                    'game_match_id' => $result['matchId'],
                    'game_player_id' => $eventData['game_player_id'],
                    'team_id' => $eventData['team_id'],
                    'minute' => $eventData['minute'],
                    'event_type' => $eventData['event_type'],
                    'metadata' => isset($eventData['metadata']) ? json_encode($eventData['metadata']) : null,
                    'created_at' => $now,
                ];
            }
        }

        foreach (array_chunk($allRows, 100) as $chunk) {
            MatchEvent::insert($chunk);
        }
    }

    /**
     * Batch process player stats (goals, assists, cards, injuries) across all matches.
     * Loads all affected players once, aggregates increments, saves once per player.
     */
    /**
     * @param  string|null  $deferMatchId  Match ID to skip notifications for (deferred to finalization)
     */
    private function batchProcessPlayerStats(Game $game, array $matchResults, $matches, $competitions, ?string $deferMatchId = null, $allPlayers = null): void
    {
        // Aggregate stat increments across ALL matches
        $statIncrements = []; // [player_id => [goals => N, assists => N, ...]]
        $specialEvents = [];  // Events requiring individual processing (cards, injuries)

        foreach ($matchResults as $result) {
            $match = $matches->get($result['matchId']);

            foreach ($result['events'] as $eventData) {
                $playerId = $eventData['game_player_id'];
                $type = $eventData['event_type'];

                if (! isset($statIncrements[$playerId])) {
                    $statIncrements[$playerId] = [];
                }

                switch ($type) {
                    case 'goal':
                    case 'own_goal':
                    case 'assist':
                        $column = match ($type) {
                            'goal' => 'goals',
                            'own_goal' => 'own_goals',
                            'assist' => 'assists',
                            default => null,
                        };
                        if ($column === null) {
                            break;
                        }
                        $statIncrements[$playerId][$column] = ($statIncrements[$playerId][$column] ?? 0) + 1;
                        break;

                    case 'yellow_card':
                        $statIncrements[$playerId]['yellow_cards'] = ($statIncrements[$playerId]['yellow_cards'] ?? 0) + 1;
                        $specialEvents[] = array_merge($eventData, [
                            'matchId' => $result['matchId'],
                            'competitionId' => $result['competitionId'],
                            'matchDate' => $match?->scheduled_date,
                        ]);
                        break;

                    case 'red_card':
                        $statIncrements[$playerId]['red_cards'] = ($statIncrements[$playerId]['red_cards'] ?? 0) + 1;
                        $specialEvents[] = array_merge($eventData, [
                            'matchId' => $result['matchId'],
                            'competitionId' => $result['competitionId'],
                            'matchDate' => $match?->scheduled_date,
                        ]);
                        break;

                    case 'injury':
                        $specialEvents[] = array_merge($eventData, [
                            'matchId' => $result['matchId'],
                            'competitionId' => $result['competitionId'],
                            'matchDate' => $match?->scheduled_date,
                        ]);
                        break;
                }
            }
        }

        // Load all affected players in ONE query
        $allPlayerIds = array_unique(array_merge(
            array_keys($statIncrements),
            array_column($specialEvents, 'game_player_id')
        ));

        if (empty($allPlayerIds)) {
            return;
        }

        $players = $allPlayers
            ? $allPlayers->flatten()->keyBy('id')->only($allPlayerIds)
            : GamePlayer::whereIn('id', $allPlayerIds)->get()->keyBy('id');

        // Apply stat increments in memory (for special events processing below)
        foreach ($statIncrements as $playerId => $increments) {
            $player = $players->get($playerId);
            if (! $player) {
                continue;
            }

            foreach ($increments as $column => $amount) {
                $player->{$column} += $amount;
            }
        }

        // Bulk update all stat increments in a single query
        $this->bulkUpdatePlayerStats($statIncrements);

        // Separate card events from injury events
        // Skip card suspensions for pre-season matches (cards are recorded but don't carry over)
        $cardEvents = [];
        $injuryEvents = [];
        foreach ($specialEvents as $eventData) {
            if (in_array($eventData['event_type'], ['yellow_card', 'red_card'])) {
                $competition = $competitions->get($eventData['competitionId']);
                if ($competition?->handler_type !== 'preseason') {
                    $cardEvents[] = $eventData;
                }
            } elseif ($eventData['event_type'] === 'injury') {
                $injuryEvents[] = $eventData;
            }
        }

        // Batch process all card events (~4-5 queries total instead of ~3 per card)
        $cardResults = $this->eligibilityService->batchProcessCards($cardEvents, $competitions);

        // Build a lookup of which match each card event came from (for notification filtering)
        $cardEventMatchLookup = [];
        foreach ($cardEvents as $eventData) {
            $key = $eventData['game_player_id'] . '|' . $eventData['competitionId'];
            $cardEventMatchLookup[$key] = $eventData['matchId'];
        }

        // Send suspension notifications from batch results
        foreach ($cardResults['suspensions'] as $suspension) {
            $player = $players->get($suspension['game_player_id']);
            if (! $player) {
                continue;
            }

            $isUserTeamPlayer = $player->team_id === $game->team_id;
            $matchKey = $suspension['game_player_id'] . '|' . $suspension['competition_id'];
            $isDeferredMatch = ($cardEventMatchLookup[$matchKey] ?? null) === $deferMatchId;

            if ($isUserTeamPlayer && ! $isDeferredMatch) {
                $reason = $suspension['reason'] === 'red_card'
                    ? __('notifications.reason_red_card')
                    : __('notifications.reason_yellow_accumulation');
                $this->notificationService->notifySuspension(
                    $game,
                    $player,
                    $suspension['ban_length'],
                    $reason,
                    $competitions->get($suspension['competition_id'])->name,
                );
            }
        }

        // Process injury events individually (already efficient — one save per injury)
        foreach ($injuryEvents as $eventData) {
            $player = $players->get($eventData['game_player_id']);
            if (! $player) {
                continue;
            }

            $injuryType = $eventData['metadata']['injury_type'] ?? 'Unknown injury';
            $weeksOut = $eventData['metadata']['weeks_out'] ?? 2;
            $this->eligibilityService->applyInjury(
                $player,
                $injuryType,
                $weeksOut,
                Carbon::parse($eventData['matchDate'])
            );

            $isUserTeamPlayer = $player->team_id === $game->team_id;
            $isDeferredMatch = $eventData['matchId'] === $deferMatchId;

            // Notifications for the deferred match are created during finalization
            if ($isUserTeamPlayer && ! $isDeferredMatch) {
                $this->notificationService->notifyInjury($game, $player, $injuryType, $weeksOut);
            }
        }
    }

    /**
     * Bulk update player stat increments in a single query using CASE WHEN.
     *
     * @param  array<string, array<string, int>>  $statIncrements  [playerId => [column => increment]]
     */
    private function bulkUpdatePlayerStats(array $statIncrements): void
    {
        if (empty($statIncrements)) {
            return;
        }

        // Collect all columns that need updating
        $columns = [];
        foreach ($statIncrements as $increments) {
            foreach (array_keys($increments) as $col) {
                $columns[$col] = true;
            }
        }

        $ids = array_keys($statIncrements);
        $idList = "'" . implode("','", $ids) . "'";

        $setClauses = [];
        foreach (array_keys($columns) as $column) {
            $cases = [];
            foreach ($statIncrements as $playerId => $increments) {
                $amount = $increments[$column] ?? 0;
                if ($amount !== 0) {
                    $cases[] = "WHEN id = '{$playerId}' THEN {$column} + {$amount}";
                }
            }
            if (! empty($cases)) {
                $setClauses[] = "{$column} = CASE " . implode(' ', $cases) . " ELSE {$column} END";
            }
        }

        if (! empty($setClauses)) {
            DB::statement("UPDATE game_players SET " . implode(', ', $setClauses) . " WHERE id IN ({$idList})");
        }
    }

    /**
     * Bulk update appearances — 1 query for all lineup + auto-subbed-in players across all matches.
     */
    private function bulkUpdateAppearances($matches, array $matchResults): void
    {
        $allLineupIds = [];
        foreach ($matches as $match) {
            $allLineupIds = array_merge($allLineupIds, $match->home_lineup ?? [], $match->away_lineup ?? []);
        }

        // Include auto-subbed-in players (they also made an appearance)
        foreach ($matchResults as $result) {
            foreach ($result['events'] as $eventData) {
                if ($eventData['event_type'] === 'substitution' && isset($eventData['metadata']['player_in_id'])) {
                    $allLineupIds[] = $eventData['metadata']['player_in_id'];
                }
            }
        }

        $allLineupIds = array_unique($allLineupIds);

        if (! empty($allLineupIds)) {
            GamePlayer::whereIn('id', $allLineupIds)->update([
                'appearances' => DB::raw('appearances + 1'),
                'season_appearances' => DB::raw('season_appearances + 1'),
            ]);
        }
    }

    /**
     * Record auto-substitutions from match simulation in each match's substitutions JSON column.
     */
    private function recordAutoSubstitutions($matches, array $matchResults): void
    {
        $updates = []; // [matchId => merged substitutions array]

        foreach ($matchResults as $result) {
            $autoSubs = [];
            foreach ($result['events'] as $eventData) {
                if ($eventData['event_type'] === 'substitution' && isset($eventData['metadata']['player_in_id'])) {
                    $autoSubs[] = [
                        'team_id' => $eventData['team_id'],
                        'player_out_id' => $eventData['game_player_id'],
                        'player_in_id' => $eventData['metadata']['player_in_id'],
                        'minute' => $eventData['minute'],
                        'auto' => true,
                    ];
                }
            }

            if (! empty($autoSubs)) {
                $match = $matches->get($result['matchId']);
                if ($match) {
                    $existing = $match->substitutions ?? [];
                    $updates[$result['matchId']] = json_encode(array_merge($existing, $autoSubs));
                }
            }
        }

        if (empty($updates)) {
            return;
        }

        // Build a single CASE WHEN query for all matches
        $cases = [];
        $ids = [];
        foreach ($updates as $matchId => $subsJson) {
            $escaped = str_replace("'", "''", $subsJson);
            $cases[] = "WHEN id = '{$matchId}' THEN '{$escaped}'::json";
            $ids[] = $matchId;
        }

        $idList = "'" . implode("','", $ids) . "'";

        // Branch on driver for SQLite compatibility in tests
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE game_matches SET substitutions = CASE ' . implode(' ', $cases) . ' ELSE substitutions END WHERE id IN (' . $idList . ')'
            );
        } else {
            // SQLite: use json() instead of ::jsonb cast
            $sqliteCases = [];
            foreach ($updates as $matchId => $subsJson) {
                $escaped = str_replace("'", "''", $subsJson);
                $sqliteCases[] = "WHEN id = '{$matchId}' THEN json('{$escaped}')";
            }
            DB::statement(
                'UPDATE game_matches SET substitutions = CASE ' . implode(' ', $sqliteCases) . ' ELSE substitutions END WHERE id IN (' . $idList . ')'
            );
        }
    }

    /**
     * Batch update fitness and morale for all matches in a single query.
     *
     * Computes per-team recovery days based on each team's last played match,
     * not the global matchday gap (which is wrong for tournaments with staggered schedules).
     */
    private function batchUpdateConditions($matches, array $matchResults, $allPlayers): void
    {
        if ($matches->isEmpty()) {
            return;
        }

        $currentMatchDate = $matches->first()->scheduled_date;
        $gameId = $matches->first()->game_id;

        // Collect all team IDs from this batch
        $teamIds = $matches->flatMap(fn ($m) => [$m->home_team_id, $m->away_team_id])->unique()->values();

        // Query each team's most recent played match BEFORE this batch's date
        $lastMatchDates = DB::table('game_matches')
            ->where('game_id', $gameId)
            ->where('played', true)
            ->where('scheduled_date', '<', $currentMatchDate->toDateString())
            ->where(fn ($q) => $q
                ->whereIn('home_team_id', $teamIds)
                ->orWhereIn('away_team_id', $teamIds))
            ->get(['home_team_id', 'away_team_id', 'scheduled_date']);

        // Build per-team last-match lookup
        $lastPlayedByTeam = [];
        foreach ($lastMatchDates as $row) {
            $date = Carbon::parse($row->scheduled_date);
            foreach ([$row->home_team_id, $row->away_team_id] as $tid) {
                if ($teamIds->contains($tid)) {
                    if (! isset($lastPlayedByTeam[$tid]) || $date->gt($lastPlayedByTeam[$tid])) {
                        $lastPlayedByTeam[$tid] = $date;
                    }
                }
            }
        }

        // Compute recovery days per team
        $recoveryDaysByTeam = [];
        foreach ($teamIds as $tid) {
            if (isset($lastPlayedByTeam[$tid])) {
                $recoveryDaysByTeam[$tid] = (int) $lastPlayedByTeam[$tid]->diffInDays($currentMatchDate);
            } else {
                $recoveryDaysByTeam[$tid] = 7; // first match of the season: full recovery
            }
        }

        $this->conditionService->batchUpdateAfterMatchday($matches, $matchResults, $allPlayers, $recoveryDaysByTeam, $currentMatchDate);
    }

    /**
     * Batch update goalkeeper stats (goals conceded, clean sheets).
     */
    private function batchUpdateGoalkeeperStats($matches, array $matchResults, $allPlayers = null): void
    {
        // Collect all lineup IDs across all matches
        $allLineupIds = [];
        foreach ($matches as $match) {
            $allLineupIds = array_merge($allLineupIds, $match->home_lineup ?? [], $match->away_lineup ?? []);
        }
        $allLineupIds = array_unique($allLineupIds);

        // Filter goalkeepers from pre-loaded players if available, otherwise query
        if ($allPlayers) {
            $goalkeepers = $allPlayers->flatten()
                ->filter(fn ($p) => in_array($p->id, $allLineupIds) && $p->position === 'Goalkeeper')
                ->keyBy('id');
        } else {
            $goalkeepers = GamePlayer::whereIn('id', $allLineupIds)
                ->where('position', 'Goalkeeper')
                ->get()
                ->keyBy('id');
        }

        if ($goalkeepers->isEmpty()) {
            return;
        }

        // Aggregate stat increments in memory
        $increments = []; // [gkId => [goals_conceded => N, clean_sheets => N]]

        foreach ($matchResults as $result) {
            $match = $matches->get($result['matchId']);
            if (! $match) {
                continue;
            }

            foreach ($goalkeepers as $gk) {
                if (in_array($gk->id, $match->home_lineup ?? [])) {
                    if (! isset($increments[$gk->id])) {
                        $increments[$gk->id] = ['goals_conceded' => 0, 'clean_sheets' => 0];
                    }
                    $increments[$gk->id]['goals_conceded'] += $result['awayScore'];
                    if ($result['awayScore'] === 0) {
                        $increments[$gk->id]['clean_sheets'] += 1;
                    }
                } elseif (in_array($gk->id, $match->away_lineup ?? [])) {
                    if (! isset($increments[$gk->id])) {
                        $increments[$gk->id] = ['goals_conceded' => 0, 'clean_sheets' => 0];
                    }
                    $increments[$gk->id]['goals_conceded'] += $result['homeScore'];
                    if ($result['homeScore'] === 0) {
                        $increments[$gk->id]['clean_sheets'] += 1;
                    }
                }
            }
        }

        if (empty($increments)) {
            return;
        }

        // Bulk update using CASE WHEN
        $ids = array_keys($increments);
        $idList = "'" . implode("','", $ids) . "'";
        $setClauses = [];

        foreach (['goals_conceded', 'clean_sheets'] as $column) {
            $cases = [];
            foreach ($increments as $gkId => $values) {
                if ($values[$column] !== 0) {
                    $cases[] = "WHEN id = '{$gkId}' THEN {$column} + {$values[$column]}";
                }
            }
            if (! empty($cases)) {
                $setClauses[] = "{$column} = CASE " . implode(' ', $cases) . " ELSE {$column} END";
            }
        }

        if (! empty($setClauses)) {
            DB::statement('UPDATE game_players SET ' . implode(', ', $setClauses) . " WHERE id IN ({$idList})");
        }
    }
}
