<?php

namespace App\Game\Services;

use App\Game\DTO\MatchEventData;
use App\Game\DTO\MatchResult;
use App\Game\DTO\ResimulationResult;
use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MatchResimulationService
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
        private readonly StandingsCalculator $standingsCalculator,
        private readonly EligibilityService $eligibilityService,
    ) {}

    /**
     * Revert events after a given minute, re-simulate the match remainder,
     * apply new events, update score and standings.
     */
    public function resimulate(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
    ): ResimulationResult {
        return DB::transaction(function () use ($match, $game, $minute, $homePlayers, $awayPlayers) {
            return $this->doResimulate($match, $game, $minute, $homePlayers, $awayPlayers);
        });
    }

    private function doResimulate(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
    ): ResimulationResult {
        $competitionId = $match->competition_id;

        // 1. Capture old scores
        $oldHomeScore = $match->home_score;
        $oldAwayScore = $match->away_score;

        // 2. Revert all events after the minute
        $this->revertEventsAfterMinute($match, $minute, $competitionId);

        // 3. Calculate score at the minute (from remaining events)
        $scoreAtMinute = $this->calculateScoreAtMinute($match);

        // 4. Read formation/mentality from match record (already updated by caller)
        $homeFormation = Formation::tryFrom($match->home_formation) ?? Formation::F_4_4_2;
        $awayFormation = Formation::tryFrom($match->away_formation) ?? Formation::F_4_4_2;
        $homeMentality = Mentality::tryFrom($match->home_mentality ?? '') ?? Mentality::BALANCED;
        $awayMentality = Mentality::tryFrom($match->away_mentality ?? '') ?? Mentality::BALANCED;

        // 5. Exclude red-carded players
        $redCardedPlayerIds = MatchEvent::where('game_match_id', $match->id)
            ->where('event_type', 'red_card')
            ->where('minute', '<=', $minute)
            ->pluck('game_player_id')
            ->all();

        $homePlayers = $homePlayers->reject(fn ($p) => in_array($p->id, $redCardedPlayerIds));
        $awayPlayers = $awayPlayers->reject(fn ($p) => in_array($p->id, $redCardedPlayerIds));

        // 6. Get existing injuries/yellows for context
        $existingInjuryTeamIds = MatchEvent::where('game_match_id', $match->id)
            ->where('minute', '<=', $minute)
            ->where('event_type', 'injury')
            ->pluck('team_id')
            ->unique()
            ->all();

        $existingYellowPlayerIds = MatchEvent::where('game_match_id', $match->id)
            ->where('minute', '<=', $minute)
            ->where('event_type', 'yellow_card')
            ->pluck('game_player_id')
            ->unique()
            ->all();

        // 7. Re-simulate the remainder
        $remainderResult = $this->matchSimulator->simulateRemainder(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeFormation,
            $awayFormation,
            $homeMentality,
            $awayMentality,
            $minute,
            $game,
            $existingInjuryTeamIds,
            $existingYellowPlayerIds,
        );

        // 8. Calculate new final score
        $newHomeScore = $scoreAtMinute['home'] + $remainderResult->homeScore;
        $newAwayScore = $scoreAtMinute['away'] + $remainderResult->awayScore;

        // 9. Apply the new remainder events
        $this->applyNewEvents($match, $game, $remainderResult, $competitionId);

        // 10. Update match score
        $match->update([
            'home_score' => $newHomeScore,
            'away_score' => $newAwayScore,
        ]);

        // 11. Fix standings if league match and score changed
        $competition = Competition::find($competitionId);
        $isCupTie = $match->cup_tie_id !== null;
        if ($competition?->isLeague() && ! $isCupTie) {
            if ($oldHomeScore !== $newHomeScore || $oldAwayScore !== $newAwayScore) {
                $this->standingsCalculator->reverseMatchResult(
                    $game->id, $competitionId,
                    $match->home_team_id, $match->away_team_id,
                    $oldHomeScore, $oldAwayScore,
                );
                $this->standingsCalculator->updateAfterMatch(
                    $game->id, $competitionId,
                    $match->home_team_id, $match->away_team_id,
                    $newHomeScore, $newAwayScore,
                );
                $this->standingsCalculator->recalculatePositions($game->id, $competitionId);
            }
        }

        // 12. Update goalkeeper stats
        $this->updateGoalkeeperStats($match, $oldHomeScore, $oldAwayScore, $newHomeScore, $newAwayScore);

        return new ResimulationResult($newHomeScore, $newAwayScore, $oldHomeScore, $oldAwayScore);
    }

    /**
     * Revert all match events after a given minute and undo their stat impact.
     */
    private function revertEventsAfterMinute(GameMatch $match, int $minute, string $competitionId): void
    {
        $events = MatchEvent::where('game_match_id', $match->id)
            ->where('minute', '>', $minute)
            ->get();

        // Group stat decrements by player to minimize queries
        $statDecrements = [];
        $specialReverts = [];

        foreach ($events as $event) {
            $playerId = $event->game_player_id;

            if (! isset($statDecrements[$playerId])) {
                $statDecrements[$playerId] = [];
            }

            switch ($event->event_type) {
                case 'goal':
                    $statDecrements[$playerId]['goals'] = ($statDecrements[$playerId]['goals'] ?? 0) + 1;
                    break;
                case 'own_goal':
                    $statDecrements[$playerId]['own_goals'] = ($statDecrements[$playerId]['own_goals'] ?? 0) + 1;
                    break;
                case 'assist':
                    $statDecrements[$playerId]['assists'] = ($statDecrements[$playerId]['assists'] ?? 0) + 1;
                    break;
                case 'yellow_card':
                    $statDecrements[$playerId]['yellow_cards'] = ($statDecrements[$playerId]['yellow_cards'] ?? 0) + 1;
                    $specialReverts[] = $event;
                    break;
                case 'red_card':
                    $statDecrements[$playerId]['red_cards'] = ($statDecrements[$playerId]['red_cards'] ?? 0) + 1;
                    $specialReverts[] = $event;
                    break;
                case 'injury':
                    $specialReverts[] = $event;
                    break;
            }
        }

        // Batch-load affected players
        $allPlayerIds = array_unique(array_merge(
            array_keys($statDecrements),
            array_column($specialReverts, 'game_player_id'),
        ));
        $players = GamePlayer::whereIn('id', $allPlayerIds)->get()->keyBy('id');

        // Apply stat decrements
        foreach ($statDecrements as $playerId => $decrements) {
            $player = $players->get($playerId);
            if (! $player) {
                continue;
            }

            foreach ($decrements as $column => $amount) {
                $player->{$column} = max(0, $player->{$column} - $amount);
            }
            $player->save();
        }

        // Undo special events
        foreach ($specialReverts as $event) {
            $player = $players->get($event->game_player_id);
            if (! $player) {
                continue;
            }

            switch ($event->event_type) {
                case 'yellow_card':
                    $suspension = PlayerSuspension::forPlayerInCompetition($player->id, $competitionId);
                    if ($suspension) {
                        $suspension->delete();
                    }
                    break;

                case 'red_card':
                    $suspension = PlayerSuspension::forPlayerInCompetition($player->id, $competitionId);
                    if ($suspension) {
                        $suspension->delete();
                    }
                    break;

                case 'injury':
                    $player->update([
                        'injury_type' => null,
                        'injury_until' => null,
                    ]);
                    break;
            }
        }

        // Delete the events
        MatchEvent::where('game_match_id', $match->id)
            ->where('minute', '>', $minute)
            ->delete();
    }

    /**
     * Calculate the score at a given minute from remaining events.
     */
    private function calculateScoreAtMinute(GameMatch $match): array
    {
        $events = MatchEvent::where('game_match_id', $match->id)->get();

        $homeScore = 0;
        $awayScore = 0;

        foreach ($events as $event) {
            if ($event->event_type === 'goal') {
                if ($event->team_id === $match->home_team_id) {
                    $homeScore++;
                } else {
                    $awayScore++;
                }
            } elseif ($event->event_type === 'own_goal') {
                if ($event->team_id === $match->home_team_id) {
                    $awayScore++;
                } else {
                    $homeScore++;
                }
            }
        }

        return ['home' => $homeScore, 'away' => $awayScore];
    }

    /**
     * Apply new events from re-simulation to the database.
     */
    private function applyNewEvents(GameMatch $match, Game $game, MatchResult $result, string $competitionId): void
    {
        $now = now();
        $events = $result->events;

        // Bulk insert match events
        $rows = $events->map(fn (MatchEventData $e) => [
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $e->gamePlayerId,
            'team_id' => $e->teamId,
            'minute' => $e->minute,
            'event_type' => $e->type,
            'metadata' => $e->metadata ? json_encode($e->metadata) : null,
            'created_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 50) as $chunk) {
            MatchEvent::insert($chunk);
        }

        // Update player stats
        $statIncrements = [];
        $specialEvents = [];

        foreach ($events as $event) {
            $playerId = $event->gamePlayerId;
            $type = $event->type;

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
                    };
                    $statIncrements[$playerId][$column] = ($statIncrements[$playerId][$column] ?? 0) + 1;
                    break;
                case 'yellow_card':
                    $statIncrements[$playerId]['yellow_cards'] = ($statIncrements[$playerId]['yellow_cards'] ?? 0) + 1;
                    $specialEvents[] = $event;
                    break;
                case 'red_card':
                    $statIncrements[$playerId]['red_cards'] = ($statIncrements[$playerId]['red_cards'] ?? 0) + 1;
                    $specialEvents[] = $event;
                    break;
                case 'injury':
                    $specialEvents[] = $event;
                    break;
            }
        }

        // Batch-load players
        $allPlayerIds = array_unique(array_merge(
            array_keys($statIncrements),
            $specialEvents ? array_map(fn ($e) => $e->gamePlayerId, $specialEvents) : [],
        ));
        $players = GamePlayer::whereIn('id', $allPlayerIds)->get()->keyBy('id');

        // Apply stat increments
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

        // Process special events
        foreach ($specialEvents as $event) {
            $player = $players->get($event->gamePlayerId);
            if (! $player) {
                continue;
            }

            switch ($event->type) {
                case 'yellow_card':
                    $suspension = $this->eligibilityService->checkYellowCardAccumulation($player->fresh());
                    if ($suspension) {
                        $this->eligibilityService->applySuspension($player, $suspension, $competitionId);
                    }
                    break;
                case 'red_card':
                    $isSecondYellow = $event->metadata['second_yellow'] ?? false;
                    $this->eligibilityService->processRedCard($player, $isSecondYellow, $competitionId);
                    break;
                case 'injury':
                    $injuryType = $event->metadata['injury_type'] ?? 'Unknown injury';
                    $weeksOut = $event->metadata['weeks_out'] ?? 2;
                    $this->eligibilityService->applyInjury(
                        $player,
                        $injuryType,
                        $weeksOut,
                        Carbon::parse($match->scheduled_date),
                    );
                    break;
            }
        }
    }

    /**
     * Update goalkeeper stats when the score changes.
     */
    private function updateGoalkeeperStats(GameMatch $match, int $oldHomeScore, int $oldAwayScore, int $newHomeScore, int $newAwayScore): void
    {
        if ($oldHomeScore === $newHomeScore && $oldAwayScore === $newAwayScore) {
            return;
        }

        $match->refresh();

        // Recalculate home goalkeeper stats
        $homeLineupIds = $match->home_lineup ?? [];
        if (! empty($homeLineupIds)) {
            $homeGk = GamePlayer::whereIn('id', $homeLineupIds)
                ->where('position', 'Goalkeeper')
                ->first();

            if ($homeGk) {
                $homeGk->goals_conceded = max(0, $homeGk->goals_conceded - $oldAwayScore + $newAwayScore);

                $wasCleanSheet = $oldAwayScore === 0;
                $isCleanSheet = $newAwayScore === 0;
                if ($wasCleanSheet && ! $isCleanSheet) {
                    $homeGk->clean_sheets = max(0, $homeGk->clean_sheets - 1);
                } elseif (! $wasCleanSheet && $isCleanSheet) {
                    $homeGk->clean_sheets++;
                }

                $homeGk->save();
            }
        }

        // Recalculate away goalkeeper stats
        $awayLineupIds = $match->away_lineup ?? [];
        if (! empty($awayLineupIds)) {
            $awayGk = GamePlayer::whereIn('id', $awayLineupIds)
                ->where('position', 'Goalkeeper')
                ->first();

            if ($awayGk) {
                $awayGk->goals_conceded = max(0, $awayGk->goals_conceded - $oldHomeScore + $newHomeScore);

                $wasCleanSheet = $oldHomeScore === 0;
                $isCleanSheet = $newHomeScore === 0;
                if ($wasCleanSheet && ! $isCleanSheet) {
                    $awayGk->clean_sheets = max(0, $awayGk->clean_sheets - 1);
                } elseif (! $wasCleanSheet && $isCleanSheet) {
                    $awayGk->clean_sheets++;
                }

                $awayGk->save();
            }
        }
    }

    /**
     * Build formatted events response for the frontend after re-simulation.
     */
    public function buildEventsResponse(GameMatch $match, int $minute): array
    {
        $newEvents = MatchEvent::with('gamePlayer.player')
            ->where('game_match_id', $match->id)
            ->where('minute', '>', $minute)
            ->orderBy('minute')
            ->get();

        $formattedEvents = $newEvents
            ->filter(fn ($e) => $e->event_type !== 'assist')
            ->map(fn ($e) => [
                'minute' => $e->minute,
                'type' => $e->event_type,
                'playerName' => $e->gamePlayer->player->name ?? '',
                'teamId' => $e->team_id,
                'gamePlayerId' => $e->game_player_id,
                'metadata' => $e->metadata,
            ])
            ->values()
            ->all();

        // Pair assists with goals
        $assists = $newEvents
            ->filter(fn ($e) => $e->event_type === 'assist')
            ->keyBy('minute');

        return array_map(function ($event) use ($assists) {
            if (in_array($event['type'], ['goal', 'own_goal']) && isset($assists[$event['minute']])) {
                $event['assistPlayerName'] = $assists[$event['minute']]->gamePlayer->player->name ?? null;
            }

            return $event;
        }, $formattedEvents);
    }
}
