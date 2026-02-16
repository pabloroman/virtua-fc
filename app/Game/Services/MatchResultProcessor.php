<?php

namespace App\Game\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     */
    public function processAll(string $gameId, int $matchday, string $currentDate, array $matchResults): void
    {
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
        $this->batchProcessPlayerStats($game, $matchResults, $matches, $competitions);

        // 6. Bulk update appearances across all matches
        $this->bulkUpdateAppearances($matches);

        // 7. Batch update conditions for all matches
        $this->batchUpdateConditions($matches, $matchResults);

        // 8. Batch update goalkeeper stats
        $this->batchUpdateGoalkeeperStats($matches, $matchResults);

        // 9. Update standings per league
        foreach ($matchResults as $result) {
            $competition = $competitions->get($result['competitionId']);
            $match = $matches->get($result['matchId']);
            $isCupTie = $match?->cup_tie_id !== null;

            if ($competition?->isLeague() && ! $isCupTie) {
                $this->standingsCalculator->updateAfterMatch(
                    gameId: $gameId,
                    competitionId: $result['competitionId'],
                    homeTeamId: $result['homeTeamId'],
                    awayTeamId: $result['awayTeamId'],
                    homeScore: $result['homeScore'],
                    awayScore: $result['awayScore'],
                );
            }
        }
    }

    /**
     * Update match scores — one query per match (each has different scores).
     */
    private function bulkUpdateMatchScores(array $matchResults): void
    {
        foreach ($matchResults as $result) {
            GameMatch::where('id', $result['matchId'])->update([
                'home_score' => $result['homeScore'],
                'away_score' => $result['awayScore'],
                'played' => true,
            ]);
        }
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

            foreach ($suspensions as $suspension) {
                $suspension->serveMatch();
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
    private function batchProcessPlayerStats(Game $game, array $matchResults, $matches, $competitions): void
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
                            'competitionId' => $result['competitionId'],
                            'matchDate' => $match?->scheduled_date,
                        ]);
                        break;

                    case 'red_card':
                        $statIncrements[$playerId]['red_cards'] = ($statIncrements[$playerId]['red_cards'] ?? 0) + 1;
                        $specialEvents[] = array_merge($eventData, [
                            'competitionId' => $result['competitionId'],
                            'matchDate' => $match?->scheduled_date,
                        ]);
                        break;

                    case 'injury':
                        $specialEvents[] = array_merge($eventData, [
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

        // Apply stat increments (1 save per player, not per event)
        foreach ($statIncrements as $playerId => $increments) {
            $player = $players->get($playerId);
            if (! $player) {
                continue;
            }

            foreach ($increments as $column => $amount) {
                $player->{$column} += $amount;
            }
            $player->save();
        }

        // Process special events (cards -> suspensions, injuries)
        foreach ($specialEvents as $eventData) {
            $player = $players->get($eventData['game_player_id']);
            if (! $player) {
                continue;
            }

            $isUserTeamPlayer = $player->team_id === $game->team_id;

            switch ($eventData['event_type']) {
                case 'yellow_card':
                    // Reload the player to get accurate yellow_cards count after stat increments
                    $freshPlayer = $player->fresh();
                    $suspension = $this->eligibilityService->checkYellowCardAccumulation($freshPlayer);
                    if ($suspension) {
                        $this->eligibilityService->applySuspension($player, $suspension, $eventData['competitionId']);

                        if ($isUserTeamPlayer) {
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

                    if ($isUserTeamPlayer) {
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

                    if ($isUserTeamPlayer) {
                        $this->notificationService->notifyInjury($game, $player, $injuryType, $weeksOut);
                    }
                    break;
            }
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
     * Batch update fitness and morale for all matches.
     */
    private function batchUpdateConditions($matches, array $matchResults): void
    {
        foreach ($matches as $match) {
            // Find the matching result for events
            $result = collect($matchResults)->firstWhere('matchId', $match->id);
            $events = $result['events'] ?? [];

            // Get previous match dates for each team
            $homePreviousDate = $this->conditionService->getPreviousMatchDate(
                $match->game_id,
                $match->home_team_id,
                $match->id
            );

            $awayPreviousDate = $this->conditionService->getPreviousMatchDate(
                $match->game_id,
                $match->away_team_id,
                $match->id
            );

            $previousDate = null;
            if ($homePreviousDate && $awayPreviousDate) {
                $previousDate = $homePreviousDate->gt($awayPreviousDate) ? $homePreviousDate : $awayPreviousDate;
            } else {
                $previousDate = $homePreviousDate ?? $awayPreviousDate;
            }

            $this->conditionService->updateAfterMatch($match, $events, $previousDate);
        }
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

        foreach ($matchResults as $result) {
            $match = $matches->get($result['matchId']);
            if (! $match) {
                continue;
            }

            foreach ($goalkeepers as $gk) {
                if (in_array($gk->id, $match->home_lineup ?? [])) {
                    $gk->goals_conceded += $result['awayScore'];
                    if ($result['awayScore'] === 0) {
                        $gk->clean_sheets += 1;
                    }
                } elseif (in_array($gk->id, $match->away_lineup ?? [])) {
                    $gk->goals_conceded += $result['homeScore'];
                    if ($result['homeScore'] === 0) {
                        $gk->clean_sheets += 1;
                    }
                }
            }
        }

        foreach ($goalkeepers as $gk) {
            if ($gk->isDirty()) {
                $gk->save();
            }
        }
    }
}
