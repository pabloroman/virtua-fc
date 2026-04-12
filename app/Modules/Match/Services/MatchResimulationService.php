<?php

namespace App\Modules\Match\Services;

use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\DTOs\MatchResult;
use App\Modules\Match\DTOs\ResimulationResult;
use App\Modules\Match\DTOs\TacticalConfig;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Modules\Squad\Services\EligibilityService;

class MatchResimulationService
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
        private readonly EligibilityService $eligibilityService,
        private readonly MatchEventRepository $matchEventRepository,
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
        ?Collection $homeBenchPlayers = null,
        ?Collection $awayBenchPlayers = null,
    ): ResimulationResult {
        return DB::transaction(function () use ($match, $game, $minute, $homePlayers, $awayPlayers, $allSubstitutions, $homeBenchPlayers, $awayBenchPlayers) {
            return $this->doResimulate($match, $game, $minute, $homePlayers, $awayPlayers, $allSubstitutions, $homeBenchPlayers, $awayBenchPlayers);
        });
    }

    private function doResimulate(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
        array $allSubstitutions = [],
        ?Collection $homeBenchPlayers = null,
        ?Collection $awayBenchPlayers = null,
    ): ResimulationResult {
        $competitionId = $match->competition_id;

        // 1. Capture old scores
        $oldHomeScore = $match->home_score;
        $oldAwayScore = $match->away_score;

        // 2. Revert all events after the minute
        $this->revertEventsAfterMinute($match, $minute, $competitionId);

        // 3. Calculate score at the minute (from remaining events)
        $scoreAtMinute = $this->calculateScoreAtMinute($match);

        // 4. Read formation/mentality/instructions from match record (already updated by caller)
        $tc = TacticalConfig::fromMatch($match);

        // 5. Exclude red-carded and substituted-out players
        $unavailablePlayerIds = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('event_type', ['red_card', 'substitution'])
            ->where('minute', '<=', $minute)
            ->pluck('game_player_id')
            ->all();

        $homePlayers = $homePlayers->reject(fn ($p) => in_array($p->id, $unavailablePlayerIds));
        $awayPlayers = $awayPlayers->reject(fn ($p) => in_array($p->id, $unavailablePlayerIds));

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
        // User's substitutions
        foreach ($allSubstitutions as $sub) {
            if ($isUserHome) {
                $homeEntryMinutes[$sub['playerInId']] = $sub['minute'];
            } else {
                $awayEntryMinutes[$sub['playerInId']] = $sub['minute'];
            }
        }
        // Opponent's substitutions up to the resimulation minute. Read from match_events
        // (source of truth after any prior resimulations) rather than $match->substitutions,
        // which is populated by the initial simulation and not updated by subsequent resims
        // — leaving it stale and out of sync with the actual events.
        $opponentTeamId = $isUserHome ? $match->away_team_id : $match->home_team_id;
        $opponentSubEvents = MatchEvent::where('game_match_id', $match->id)
            ->where('team_id', $opponentTeamId)
            ->where('event_type', 'substitution')
            ->where('minute', '<=', $minute)
            ->get();
        foreach ($opponentSubEvents as $subEvent) {
            $playerInId = $subEvent->metadata['player_in_id'] ?? null;
            if ($playerInId === null) {
                continue;
            }
            if ($isUserHome) {
                $awayEntryMinutes[$playerInId] = $subEvent->minute;
            } else {
                $homeEntryMinutes[$playerInId] = $subEvent->minute;
            }
        }

        // 8. Count existing substitutions and windows per team to enforce limits.
        // Opponent counts come from match_events (post-revert state) so repeated
        // resimulations can't push the opponent past the sub/window caps by
        // counting against a stale $match->substitutions snapshot.
        $userSubCount = count($allSubstitutions);
        $opponentSubCount = $opponentSubEvents->count();
        $homeExistingSubs = $isUserHome ? $userSubCount : $opponentSubCount;
        $awayExistingSubs = $isUserHome ? $opponentSubCount : $userSubCount;

        $opponentWindowsUsed = $opponentSubEvents
            ->pluck('minute')
            ->unique()
            ->count();
        $homeWindowsUsed = $isUserHome ? 0 : $opponentWindowsUsed;
        $awayWindowsUsed = $isUserHome ? $opponentWindowsUsed : 0;

        // 9. Re-simulate the remainder with AI substitutions for the opponent
        $hasOpponentBench = $isUserHome
            ? ($awayBenchPlayers !== null && $awayBenchPlayers->isNotEmpty())
            : ($homeBenchPlayers !== null && $homeBenchPlayers->isNotEmpty());

        $aiSubMode = config('match_simulation.ai_substitutions.mode', 'all');
        $aiSubsActive = $hasOpponentBench && match ($aiSubMode) {
            'all' => true,
            'ai_only' => false, // user is in the match, so skip in ai_only mode
            default => false,
        };

        if ($aiSubsActive) {
            $remainderOutput = $this->matchSimulator->simulateRemainderWithAISubs(
                $match->homeTeam,
                $match->awayTeam,
                $homePlayers,
                $awayPlayers,
                $tc->homeFormation,
                $tc->awayFormation,
                $tc->homeMentality,
                $tc->awayMentality,
                $minute,
                $game,
                $existingInjuryTeamIds,
                $existingYellowPlayerIds,
                $homeEntryMinutes,
                $awayEntryMinutes,
                $tc->homePlayingStyle,
                $tc->awayPlayingStyle,
                $tc->homePressing,
                $tc->awayPressing,
                $tc->homeDefLine,
                $tc->awayDefLine,
                $homeBenchPlayers,
                $awayBenchPlayers,
                homeExistingSubstitutions: $homeExistingSubs,
                awayExistingSubstitutions: $awayExistingSubs,
                homeWindowsUsed: $homeWindowsUsed,
                awayWindowsUsed: $awayWindowsUsed,
                scoreHomeAtMinute: $scoreAtMinute['home'],
                scoreAwayAtMinute: $scoreAtMinute['away'],
                userTeamId: $game->team_id,
            );
        } else {
            $remainderOutput = $this->matchSimulator->simulateRemainder(
                $match->homeTeam,
                $match->awayTeam,
                $homePlayers,
                $awayPlayers,
                $tc->homeFormation,
                $tc->awayFormation,
                $tc->homeMentality,
                $tc->awayMentality,
                $minute,
                $game,
                $existingInjuryTeamIds,
                $existingYellowPlayerIds,
                $homeEntryMinutes,
                $awayEntryMinutes,
                $tc->homePlayingStyle,
                $tc->awayPlayingStyle,
                $tc->homePressing,
                $tc->awayPressing,
                $tc->homeDefLine,
                $tc->awayDefLine,
                $homeBenchPlayers,
                $awayBenchPlayers,
                homeExistingSubstitutions: $homeExistingSubs,
                awayExistingSubstitutions: $awayExistingSubs,
                neutralVenue: $match->isNeutralVenue(),
            );
        }

        // 10. Calculate new final score
        $remainderResult = $remainderOutput->result;
        $newHomeScore = $scoreAtMinute['home'] + $remainderResult->homeScore;
        $newAwayScore = $scoreAtMinute['away'] + $remainderResult->awayScore;

        // 11. Merge performances with cached values (preserves subbed-out players' data)
        $cachedPerformances = Cache::get("match_performances:{$match->id}", []);
        $mergedPerformances = array_merge($cachedPerformances, $remainderOutput->performances);
        Cache::put("match_performances:{$match->id}", $mergedPerformances, now()->addHours(24));

        // 12. Apply the new remainder events
        $this->applyNewEvents($match, $game, $remainderResult, $competitionId);

        // 13. Recalculate MVP from all events and merged performances
        $allEvents = MatchEvent::where('game_match_id', $match->id)->get();

        // Rebuild full player collections including substituted-out players
        // (they were excluded from resimulation but still have performances)
        $performancePlayerIds = array_keys($mergedPerformances);
        $allMvpPlayers = GamePlayer::whereIn('id', $performancePlayerIds)->get();
        $allHomePlayers = $allMvpPlayers->filter(fn ($p) => $p->team_id === $match->home_team_id);
        $allAwayPlayers = $allMvpPlayers->filter(fn ($p) => $p->team_id === $match->away_team_id);

        $mvpPlayerId = MvpCalculator::calculate(
            $mergedPerformances,
            $allHomePlayers,
            $allAwayPlayers,
            $match->home_team_id,
            $match->away_team_id,
            $newHomeScore,
            $newAwayScore,
            $allEvents,
        );

        // 14. Update match score, possession, and MVP
        // Note: Score-dependent side effects (standings, cup ties, GK stats, prize money)
        // are NOT handled here. They are deferred to FinalizeMatch, which applies them
        // once after the user finishes the live match. This eliminates the need for
        // fragile reversal logic on every resimulation.
        $match->update([
            'home_score' => $newHomeScore,
            'away_score' => $newAwayScore,
            'home_possession' => $remainderResult->homePossession,
            'away_possession' => $remainderResult->awayPossession,
            'mvp_player_id' => $mvpPlayerId,
        ]);

        return new ResimulationResult(
            $newHomeScore, $newAwayScore, $oldHomeScore, $oldAwayScore,
            $remainderResult->homePossession, $remainderResult->awayPossession,
            $mergedPerformances,
            $mvpPlayerId,
        );
    }

    /**
     * Re-simulate extra time from a given minute (after an ET substitution or tactical change).
     * Same structure as doResimulate() but targets ET scores and uses simulateExtraTime().
     */
    public function resimulateExtraTime(
        GameMatch $match,
        Game $game,
        int $minute,
        Collection $homePlayers,
        Collection $awayPlayers,
        array $allSubstitutions = [],
        ?Collection $homeBenchPlayers = null,
        ?Collection $awayBenchPlayers = null,
    ): ResimulationResult {
        return DB::transaction(function () use ($match, $game, $minute, $homePlayers, $awayPlayers, $allSubstitutions, $homeBenchPlayers, $awayBenchPlayers) {
            $competitionId = $match->competition_id;

            // 1. Capture old ET scores
            $oldHomeScore = $match->home_score_et ?? 0;
            $oldAwayScore = $match->away_score_et ?? 0;

            // 2. Revert all events after the minute
            $this->revertEventsAfterMinute($match, $minute, $competitionId);

            // 3. Calculate ET-only score at minute (events with minute > 90 that remain)
            $scoreAtMinute = $this->calculateScoreAtMinute($match, 90);

            // 4. Read formation/mentality/instructions from match record
            $tc = TacticalConfig::fromMatch($match);

            // 5. Exclude red-carded and substituted-out players
            $unavailablePlayerIds = MatchEvent::where('game_match_id', $match->id)
                ->whereIn('event_type', ['red_card', 'substitution'])
                ->where('minute', '<=', $minute)
                ->pluck('game_player_id')
                ->all();

            $homePlayers = $homePlayers->reject(fn ($p) => in_array($p->id, $unavailablePlayerIds));
            $awayPlayers = $awayPlayers->reject(fn ($p) => in_array($p->id, $unavailablePlayerIds));

            // 6. Build entry minute maps from substitutions
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

            // 7. Re-simulate extra time remainder
            $remainderResult = $this->matchSimulator->simulateExtraTime(
                $match->homeTeam,
                $match->awayTeam,
                $homePlayers,
                $awayPlayers,
                $homeEntryMinutes,
                $awayEntryMinutes,
                fromMinute: $minute,
                homeFormation: $tc->homeFormation,
                awayFormation: $tc->awayFormation,
                homeMentality: $tc->homeMentality,
                awayMentality: $tc->awayMentality,
                homePlayingStyle: $tc->homePlayingStyle,
                awayPlayingStyle: $tc->awayPlayingStyle,
                homePressing: $tc->homePressing,
                awayPressing: $tc->awayPressing,
                homeDefLine: $tc->homeDefLine,
                awayDefLine: $tc->awayDefLine,
                neutralVenue: $match->isNeutralVenue(),
            );

            // 8. Calculate new ET score
            $newHomeScore = $scoreAtMinute['home'] + $remainderResult->homeScore;
            $newAwayScore = $scoreAtMinute['away'] + $remainderResult->awayScore;

            // 9. Apply the new remainder events
            $this->applyNewEvents($match, $game, $remainderResult, $competitionId);

            // 10. Recalculate MVP from all events (regular + ET)
            $cachedPerformances = Cache::get("match_performances:{$match->id}", []);
            $allEvents = MatchEvent::where('game_match_id', $match->id)->get();
            $performancePlayerIds = array_keys($cachedPerformances);
            $allMvpPlayers = GamePlayer::whereIn('id', $performancePlayerIds)->get();

            $totalHomeScore = $match->home_score + $newHomeScore;
            $totalAwayScore = $match->away_score + $newAwayScore;

            $mvpPlayerId = MvpCalculator::calculate(
                $cachedPerformances,
                $allMvpPlayers->filter(fn ($p) => $p->team_id === $match->home_team_id),
                $allMvpPlayers->filter(fn ($p) => $p->team_id === $match->away_team_id),
                $match->home_team_id,
                $match->away_team_id,
                $totalHomeScore,
                $totalAwayScore,
                $allEvents,
            );

            // 11. Update ET scores, possession, and MVP
            $match->update([
                'home_score_et' => $newHomeScore,
                'away_score_et' => $newAwayScore,
                'home_possession' => $remainderResult->homePossession,
                'away_possession' => $remainderResult->awayPossession,
                'mvp_player_id' => $mvpPlayerId,
            ]);

            return new ResimulationResult(
                $newHomeScore, $newAwayScore, $oldHomeScore, $oldAwayScore,
                $remainderResult->homePossession, $remainderResult->awayPossession,
                $cachedPerformances,
                $mvpPlayerId,
            );
        });
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
        $competition = \App\Models\Competition::find($competitionId);
        $handlerType = $competition->handler_type ?? 'league';
        $rules = $this->eligibilityService->rulesForHandlerType($handlerType);

        // Skip card suspension reversal for pre-season matches (no suspensions to revert)
        $isPreseason = $handlerType === 'preseason';

        if (! $isPreseason) {
            // Pre-load all suspensions for affected players in this competition (single query)
            $suspensionsByPlayer = PlayerSuspension::where('competition_id', $competitionId)
                ->whereIn('game_player_id', $affectedPlayerIds)
                ->get()
                ->keyBy('game_player_id');
        }

        foreach ($eventsToRevert as $event) {
            if (! $isPreseason && $event->event_type === 'yellow_card') {
                // Check if this yellow was at a suspension threshold before reverting
                $record = $suspensionsByPlayer->get($event->game_player_id);
                $yellowsBefore = $record->yellow_cards ?? 0;
                $wasAtThreshold = $rules->checkAccumulation($yellowsBefore) !== null;

                PlayerSuspension::revertYellowCard($event->game_player_id, $competitionId);

                // Only clear suspension if this specific yellow caused it
                if ($wasAtThreshold && $record && $record->fresh()->matches_remaining > 0) {
                    $record->update(['matches_remaining' => 0]);
                }
            }

            if (! $isPreseason && $event->event_type === 'red_card') {
                $suspension = $suspensionsByPlayer->get($event->game_player_id);
                if ($suspension && $suspension->matches_remaining > 0) {
                    $suspension->update(['matches_remaining' => 0]);
                }
            }

            if ($event->event_type === 'injury') {
                GamePlayer::where('id', $event->game_player_id)
                    ->update(['injury_type' => null, 'injury_until' => null]);
            }
        }

        // Decrement appearances for players who were subbed in via events being reverted
        $subbedInPlayerIds = $eventsToRevert
            ->filter(fn ($e) => $e->event_type === 'substitution' && isset($e->metadata['player_in_id']))
            ->pluck('metadata.player_in_id')
            ->unique()
            ->values()
            ->all();

        if (! empty($subbedInPlayerIds)) {
            $clamp = ['GREATEST(appearances - 1, 0)', 'GREATEST(season_appearances - 1, 0)'];

            GamePlayer::whereIn('id', $subbedInPlayerIds)
                ->where('appearances', '>', 0)
                ->update([
                    'appearances' => DB::raw($clamp[0]),
                    'season_appearances' => DB::raw($clamp[1]),
                ]);
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
     * Calculate the score from remaining events.
     *
     * @param  int  $afterMinute  Only count events with minute > this value (0 = all events)
     */
    private function calculateScoreAtMinute(GameMatch $match, int $afterMinute = 0): array
    {
        $query = MatchEvent::where('game_match_id', $match->id);

        if ($afterMinute > 0) {
            $query->where('minute', '>', $afterMinute);
        }

        $events = $query->get();

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
        $events = $result->events;
        $competition = \App\Models\Competition::find($competitionId);
        $handlerType = $competition->handler_type ?? 'league';

        $this->matchEventRepository->bulkInsert($events, $game->id, $match->id);

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
        // Skip card suspensions for pre-season matches (cards are recorded but don't carry over)
        $isPreseason = $handlerType === 'preseason';

        foreach ($specialEvents as $event) {
            $player = $players->get($event->gamePlayerId);
            if (! $player) {
                continue;
            }

            switch ($event->type) {
                case 'yellow_card':
                    if (! $isPreseason) {
                        $this->eligibilityService->processYellowCard($player->id, $competitionId, $handlerType);
                    }
                    break;
                case 'red_card':
                    if (! $isPreseason) {
                        $isSecondYellow = $event->metadata['second_yellow'] ?? false;
                        $this->eligibilityService->processRedCard($player, $isSecondYellow, $competitionId);
                    }
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
     * Build formatted events response for the frontend after re-simulation.
     */
    public function buildEventsResponse(GameMatch $match, int $minute): array
    {
        $newEvents = MatchEvent::with('gamePlayer.player')
            ->where('game_match_id', $match->id)
            ->where('minute', '>', $minute)
            ->orderBy('minute')
            ->get();

        return self::formatMatchEvents($newEvents);
    }

    /**
     * Format a collection of MatchEvent models for the frontend.
     *
     * Resolves player-in names for substitution events, pairs assists with goals,
     * and returns a sorted array ready for JSON serialization.
     */
    public static function formatMatchEvents(Collection $events): array
    {
        // Batch-load player-in names for substitution events
        $playerInIds = $events
            ->filter(fn ($e) => $e->event_type === 'substitution')
            ->map(fn ($e) => $e->metadata['player_in_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $playerInNames = [];
        if (! empty($playerInIds)) {
            $playerInNames = GamePlayer::with('player')
                ->whereIn('id', $playerInIds)
                ->get()
                ->mapWithKeys(fn ($gp) => [$gp->id => $gp->player->name ?? ''])
                ->all();
        }

        $formatted = $events
            ->filter(fn ($e) => $e->event_type !== 'assist')
            ->map(function ($e) use ($playerInNames) {
                $data = [
                    'minute' => $e->minute,
                    'type' => $e->event_type,
                    'playerName' => $e->gamePlayer->player->name ?? '',
                    'teamId' => $e->team_id,
                    'gamePlayerId' => $e->game_player_id,
                    'metadata' => $e->metadata,
                ];

                if ($e->event_type === 'substitution') {
                    $playerInId = $e->metadata['player_in_id'] ?? null;
                    $data['playerInName'] = $playerInNames[$playerInId] ?? '';
                }

                return $data;
            })
            ->sortBy('minute')
            ->values()
            ->all();

        // Pair assists with their goals (keyed by minute:team_id to avoid cross-team misattribution)
        $assists = $events
            ->filter(fn ($e) => $e->event_type === 'assist')
            ->keyBy(fn ($e) => $e->minute.':'.$e->team_id);

        return array_map(function ($event) use ($assists) {
            if ($event['type'] === 'goal') {
                $key = $event['minute'].':'.$event['teamId'];
                if (isset($assists[$key])) {
                    $event['assistPlayerName'] = $assists[$key]->gamePlayer->player->name ?? null;
                    $event['assistPlayerId'] = $assists[$key]->game_player_id;
                }
            }

            return $event;
        }, $formatted);
    }
}
