<?php

namespace App\Game\Services;

use App\Game\DTO\MatchEventData;
use App\Game\DTO\MatchResult;
use App\Game\DTO\ResimulationResult;
use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\FinancialTransaction;
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
        private readonly CupTieResolver $cupTieResolver,
    ) {}

    /**
     * Revert events after a given minute, re-simulate the match remainder,
     * apply new events, update score and standings.
     *
     * @param  array  $allSubstitutions  All subs (previous + new) [{playerOutId, playerInId, minute}]
     */
    public function resimulate(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
        array $allSubstitutions = [],
    ): ResimulationResult {
        return DB::transaction(function () use ($match, $game, $minute, $homePlayers, $awayPlayers, $allSubstitutions) {
            return $this->doResimulate($match, $game, $minute, $homePlayers, $awayPlayers, $allSubstitutions);
        });
    }

    private function doResimulate(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
        array $allSubstitutions = [],
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

        // 7. Build entry minute maps from substitutions
        $isUserHome = $match->isHomeTeam($game->team_id);
        $homeEntryMinutes = [];
        $awayEntryMinutes = [];
        foreach ($allSubstitutions as $sub) {
            if ($isUserHome) {
                $homeEntryMinutes[$sub['playerInId']] = $sub['minute'];
            } else {
                $awayEntryMinutes[$sub['playerInId']] = $sub['minute'];
            }
        }

        // 8. Re-simulate the remainder
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
            $homeEntryMinutes,
            $awayEntryMinutes,
        );

        // 9. Calculate new final score
        $newHomeScore = $scoreAtMinute['home'] + $remainderResult->homeScore;
        $newAwayScore = $scoreAtMinute['away'] + $remainderResult->awayScore;

        // 10. Apply the new remainder events
        $this->applyNewEvents($match, $game, $remainderResult, $competitionId);

        // 11. Update match score
        $match->update([
            'home_score' => $newHomeScore,
            'away_score' => $newAwayScore,
        ]);

        // 12. Fix standings if league match and score changed
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

        // 12b. Reset and re-resolve cup tie if knockout match and score changed
        if ($isCupTie && ($oldHomeScore !== $newHomeScore || $oldAwayScore !== $newAwayScore)) {
            $this->resetAndReresolveCupTie($match, $game, $competition, $homePlayers, $awayPlayers);
        }

        // 13. Update goalkeeper stats
        $this->updateGoalkeeperStats($match, $oldHomeScore, $oldAwayScore, $newHomeScore, $newAwayScore);

        return new ResimulationResult($newHomeScore, $newAwayScore, $oldHomeScore, $oldAwayScore);
    }

    /**
     * Revert all match events after a given minute and rebuild affected player stats.
     *
     * Instead of manually decrementing stats (fragile mirror of applyNewEvents),
     * we delete the events, clear side-effects (suspensions/injuries), then
     * recalculate each affected player's stats from all their remaining events.
     */
    private function revertEventsAfterMinute(GameMatch $match, int $minute, string $competitionId): void
    {
        $eventsToRevert = MatchEvent::where('game_match_id', $match->id)
            ->where('minute', '>', $minute)
            ->get();

        if ($eventsToRevert->isEmpty()) {
            return;
        }

        $affectedPlayerIds = $eventsToRevert->pluck('game_player_id')->unique()->values()->all();

        // Clear side-effects that can't be recalculated from events alone
        foreach ($eventsToRevert as $event) {
            if (in_array($event->event_type, ['yellow_card', 'red_card'])) {
                $suspension = PlayerSuspension::forPlayerInCompetition($event->game_player_id, $competitionId);
                $suspension?->delete();
            }

            if ($event->event_type === 'injury') {
                GamePlayer::where('id', $event->game_player_id)
                    ->update(['injury_type' => null, 'injury_until' => null]);
            }
        }

        // Delete the events
        MatchEvent::where('game_match_id', $match->id)
            ->where('minute', '>', $minute)
            ->delete();

        // Recalculate stats for affected players from all their remaining events
        $this->recalculatePlayerStats($affectedPlayerIds, $match->game_id);
    }

    /**
     * Recalculate season stats for the given players from their match events.
     */
    private function recalculatePlayerStats(array $playerIds, string $gameId): void
    {
        if (empty($playerIds)) {
            return;
        }

        // Count each stat type per player from all remaining events
        $statCounts = MatchEvent::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->whereIn('event_type', ['goal', 'own_goal', 'assist', 'yellow_card', 'red_card'])
            ->selectRaw('game_player_id, event_type, count(*) as cnt')
            ->groupBy('game_player_id', 'event_type')
            ->get();

        // Build a map: [playerId => [column => count]]
        $statsMap = [];
        $columnMap = [
            'goal' => 'goals',
            'own_goal' => 'own_goals',
            'assist' => 'assists',
            'yellow_card' => 'yellow_cards',
            'red_card' => 'red_cards',
        ];

        /** @var object{game_player_id: string, event_type: string, cnt: int} $row */
        foreach ($statCounts as $row) {
            $column = $columnMap[$row->event_type] ?? null;
            if ($column) {
                $statsMap[$row->game_player_id][$column] = $row->cnt;
            }
        }

        // Update each affected player — set stats to counted values (0 if no events remain)
        $players = GamePlayer::whereIn('id', $playerIds)->get();
        foreach ($players as $player) {
            $counts = $statsMap[$player->id] ?? [];
            $player->goals = $counts['goals'] ?? 0;
            $player->own_goals = $counts['own_goals'] ?? 0;
            $player->assists = $counts['assists'] ?? 0;
            $player->yellow_cards = $counts['yellow_cards'] ?? 0;
            $player->red_cards = $counts['red_cards'] ?? 0;
            $player->save();
        }
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
     * Reset a completed cup tie and re-resolve it with the new match score.
     */
    private function resetAndReresolveCupTie(
        GameMatch $match,
        Game $game,
        ?Competition $competition,
        Collection $homePlayers,
        Collection $awayPlayers,
    ): void {
        $cupTie = CupTie::find($match->cup_tie_id);

        if (! $cupTie || ! $cupTie->completed) {
            return;
        }

        $oldWinnerId = $cupTie->winner_id;

        // Reset the cup tie
        $cupTie->update([
            'winner_id' => null,
            'completed' => false,
            'resolution' => null,
        ]);

        // Clear extra time and penalty data from the match
        $match->update([
            'is_extra_time' => false,
            'home_score_et' => null,
            'away_score_et' => null,
            'home_score_penalties' => null,
            'away_score_penalties' => null,
        ]);

        // Delete cup prize money if user team won previously
        if ($oldWinnerId === $game->team_id) {
            $competitionName = $competition->name ?? 'Cup';
            FinancialTransaction::where('game_id', $game->id)
                ->where('category', FinancialTransaction::CATEGORY_CUP_BONUS)
                ->where('description', "{$competitionName} - Round {$cupTie->round_number} advancement")
                ->delete();
        }

        // Re-resolve the tie with new scores
        $allPlayersForTie = collect([
            $match->home_team_id => $homePlayers,
            $match->away_team_id => $awayPlayers,
        ]);
        $newWinnerId = $this->cupTieResolver->resolve($cupTie->fresh(), $allPlayersForTie);

        // Re-award prize money if user team now advances
        if ($newWinnerId === $game->team_id) {
            $this->awardCupPrizeMoney($game, $competition, $cupTie->round_number);
        }

        // If winner changed and next round draw exists, update it
        if ($oldWinnerId && $newWinnerId && $oldWinnerId !== $newWinnerId) {
            $this->updateNextRoundDraw($cupTie, $oldWinnerId, $newWinnerId);
        }
    }

    /**
     * Award cup prize money for advancing in a knockout round.
     */
    private function awardCupPrizeMoney(Game $game, ?Competition $competition, int $roundNumber): void
    {
        $prizeAmounts = [
            1 => 10_000_000,
            2 => 20_000_000,
            3 => 30_000_000,
            4 => 50_000_000,
            5 => 100_000_000,
            6 => 200_000_000,
        ];

        $amount = $prizeAmounts[$roundNumber] ?? $prizeAmounts[1];
        $competitionName = $competition->name ?? 'Cup';

        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_CUP_BONUS,
            amount: $amount,
            description: "{$competitionName} - Round {$roundNumber} advancement",
            transactionDate: $game->current_date->toDateString(),
        );
    }

    /**
     * Update the next round draw if the winner of a cup tie changed.
     */
    private function updateNextRoundDraw(CupTie $currentTie, string $oldWinnerId, string $newWinnerId): void
    {
        $nextRoundTie = CupTie::where('game_id', $currentTie->game_id)
            ->where('competition_id', $currentTie->competition_id)
            ->where('round_number', $currentTie->round_number + 1)
            ->where(function ($q) use ($oldWinnerId) {
                $q->where('home_team_id', $oldWinnerId)
                    ->orWhere('away_team_id', $oldWinnerId);
            })
            ->first();

        if (! $nextRoundTie) {
            return;
        }

        $isHome = $nextRoundTie->home_team_id === $oldWinnerId;
        $teamField = $isHome ? 'home_team_id' : 'away_team_id';
        $nextRoundTie->update([$teamField => $newWinnerId]);

        // Update first leg match
        if ($nextRoundTie->first_leg_match_id) {
            GameMatch::where('id', $nextRoundTie->first_leg_match_id)
                ->update([$teamField => $newWinnerId]);
        }

        // Update second leg match (teams are swapped in second leg)
        if ($nextRoundTie->second_leg_match_id) {
            $secondLegField = $isHome ? 'away_team_id' : 'home_team_id';
            GameMatch::where('id', $nextRoundTie->second_leg_match_id)
                ->update([$secondLegField => $newWinnerId]);
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
