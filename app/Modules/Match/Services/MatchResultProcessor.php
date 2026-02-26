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
use App\Modules\Squad\Services\PlayerConditionService;
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
        // Capture previous date BEFORE updating game state (used for recovery calculation)
        $previousDate = Game::where('id', $gameId)->value('current_date');
        $previousDate = $previousDate ? Carbon::parse($previousDate) : null;

        // 1. Update game state (replaces onMatchdayAdvanced projector)
        Game::where('id', $gameId)->update([
            'current_matchday' => $matchday,
            'current_date' => Carbon::parse($currentDate)->toDateString(),
        ]);

        // 2. Bulk update match records (scores + played)
        $this->bulkUpdateMatchScores($matchResults);

        // Load shared context once
        $game = Game::find($gameId);
        $matchIds = array_column($matchResults, 'matchId');
        $matches = GameMatch::whereIn('id', $matchIds)->get()->keyBy('id');

        // Load competitions once (typically 1-2 unique)
        $competitionIds = collect($matchResults)->pluck('competitionId')->unique();
        $competitions = Competition::whereIn('id', $competitionIds)->get()->keyBy('id');

        // 3. Serve suspensions for all matches (batch)
        $this->batchServeSuspensions($gameId, $matches, $matchResults);

        // 4. Bulk insert all match events across all matches
        $this->bulkInsertMatchEvents($gameId, $matchResults);

        // 5. Batch process player stats across all matches
        $this->batchProcessPlayerStats($game, $matchResults, $matches, $competitions, $deferMatchId);

        // 6. Bulk update appearances across all matches
        $this->bulkUpdateAppearances($matches);

        // 7. Batch update conditions for all matches
        $this->batchUpdateConditions($matches, $matchResults, $allPlayers ?? collect(), $previousDate);

        // 8. Batch update goalkeeper stats (skip deferred match)
        $gkResults = $deferMatchId
            ? array_filter($matchResults, fn ($r) => $r['matchId'] !== $deferMatchId)
            : $matchResults;
        $gkMatches = $deferMatchId
            ? $matches->except($deferMatchId)->keyBy('id')
            : $matches;
        $this->batchUpdateGoalkeeperStats($gkMatches, $gkResults);

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

        foreach ($matchResults as $result) {
            $id = $result['matchId'];
            $ids[] = $id;
            $homeCases[] = "WHEN id = '{$id}' THEN {$result['homeScore']}";
            $awayCases[] = "WHEN id = '{$id}' THEN {$result['awayScore']}";
        }

        $idList = "'" . implode("','", $ids) . "'";

        DB::statement("
            UPDATE game_matches
            SET home_score = CASE " . implode(' ', $homeCases) . " END,
                away_score = CASE " . implode(' ', $awayCases) . " END,
                played = true
            WHERE id IN ({$idList})
        ");
    }

    /**
     * Serve suspensions for all matches in the batch.
     * Decrements matches_remaining for suspended players on teams that played.
     */
    private function batchServeSuspensions(string $gameId, $matches, array $matchResults): void
    {
        // Group matches by competition for efficient suspension queries
        $matchesByCompetition = [];
        foreach ($matchResults as $result) {
            $match = $matches->get($result['matchId']);
            if (! $match) {
                continue;
            }
            $competitionId = $result['competitionId'];
            if (! isset($matchesByCompetition[$competitionId])) {
                $matchesByCompetition[$competitionId] = [];
            }
            $matchesByCompetition[$competitionId][] = $match;
        }

        foreach ($matchesByCompetition as $competitionId => $competitionMatches) {
            // Collect all team IDs that played in this competition
            $teamIds = [];
            foreach ($competitionMatches as $match) {
                $teamIds[] = $match->home_team_id;
                $teamIds[] = $match->away_team_id;
            }
            $teamIds = array_unique($teamIds);

            // Find all suspensions for these teams in this competition
            $suspensions = PlayerSuspension::where('competition_id', $competitionId)
                ->where('matches_remaining', '>', 0)
                ->whereHas('gamePlayer', function ($query) use ($gameId, $teamIds) {
                    $query->where('game_id', $gameId)
                        ->whereIn('team_id', $teamIds);
                })
                ->get();

            $suspensionIds = $suspensions->pluck('id')->all();
            if (!empty($suspensionIds)) {
                PlayerSuspension::whereIn('id', $suspensionIds)->decrement('matches_remaining');
                // Ensure matches_remaining doesn't go negative
                PlayerSuspension::whereIn('id', $suspensionIds)
                    ->where('matches_remaining', '<', 0)
                    ->update(['matches_remaining' => 0]);
            }
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
    private function batchProcessPlayerStats(Game $game, array $matchResults, $matches, $competitions, ?string $deferMatchId = null): void
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

        $players = GamePlayer::whereIn('id', $allPlayerIds)->get()->keyBy('id');

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

        // Process special events (cards -> suspensions, injuries)
        foreach ($specialEvents as $eventData) {
            $player = $players->get($eventData['game_player_id']);
            if (! $player) {
                continue;
            }

            $isUserTeamPlayer = $player->team_id === $game->team_id;
            $isDeferredMatch = $eventData['matchId'] === $deferMatchId;

            switch ($eventData['event_type']) {
                case 'yellow_card':
                    $competition = $competitions->get($eventData['competitionId']);
                    $handlerType = $competition->handler_type ?? 'league';
                    $suspension = $this->eligibilityService->processYellowCard(
                        $player->id, $eventData['competitionId'], $handlerType
                    );
                    if ($suspension) {
                        // Notifications for the deferred match are created during finalization
                        if ($isUserTeamPlayer && ! $isDeferredMatch) {
                            $this->notificationService->notifySuspension(
                                $game,
                                $player,
                                $suspension,
                                __('notifications.reason_yellow_accumulation')
                            );
                        }
                    }
                    break;

                case 'red_card':
                    $isSecondYellow = $eventData['metadata']['second_yellow'] ?? false;
                    $this->eligibilityService->processRedCard($player, $isSecondYellow, $eventData['competitionId']);

                    // Notifications for the deferred match are created during finalization
                    if ($isUserTeamPlayer && ! $isDeferredMatch) {
                        $suspensionMatches = $isSecondYellow ? 1 : 1;
                        $this->notificationService->notifySuspension(
                            $game,
                            $player,
                            $suspensionMatches,
                            __('notifications.reason_red_card')
                        );
                    }
                    break;

                case 'injury':
                    $injuryType = $eventData['metadata']['injury_type'] ?? 'Unknown injury';
                    $weeksOut = $eventData['metadata']['weeks_out'] ?? 2;
                    $this->eligibilityService->applyInjury(
                        $player,
                        $injuryType,
                        $weeksOut,
                        Carbon::parse($eventData['matchDate'])
                    );

                    // Notifications for the deferred match are created during finalization
                    if ($isUserTeamPlayer && ! $isDeferredMatch) {
                        $this->notificationService->notifyInjury($game, $player, $injuryType, $weeksOut);
                    }
                    break;
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
     * Bulk update appearances — 1 query for all lineup players across all matches.
     */
    private function bulkUpdateAppearances($matches): void
    {
        $allLineupIds = [];
        foreach ($matches as $match) {
            $allLineupIds = array_merge($allLineupIds, $match->home_lineup ?? [], $match->away_lineup ?? []);
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
     * Batch update fitness and morale for all matches in a single query.
     *
     * Uses the game's previous current_date (before this matchday's update) to compute
     * recovery days uniformly for all teams, instead of per-team lookups.
     */
    private function batchUpdateConditions($matches, array $matchResults, $allPlayers, ?Carbon $previousDate): void
    {
        if ($matches->isEmpty()) {
            return;
        }

        $daysSinceLastMatchday = 7; // default: full recovery for first matchday
        if ($previousDate) {
            $daysSinceLastMatchday = (int) $previousDate->diffInDays($matches->first()->scheduled_date);
        }

        $this->conditionService->batchUpdateAfterMatchday($matches, $matchResults, $allPlayers, $daysSinceLastMatchday);
    }

    /**
     * Batch update goalkeeper stats (goals conceded, clean sheets).
     */
    private function batchUpdateGoalkeeperStats($matches, array $matchResults): void
    {
        // Collect all lineup IDs across all matches
        $allLineupIds = [];
        foreach ($matches as $match) {
            $allLineupIds = array_merge($allLineupIds, $match->home_lineup ?? [], $match->away_lineup ?? []);
        }

        // Load all goalkeepers in one query
        $goalkeepers = GamePlayer::whereIn('id', array_unique($allLineupIds))
            ->where('position', 'Goalkeeper')
            ->get()
            ->keyBy('id');

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
