<?php

namespace App\Modules\Match\Services;

use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Modules\Match\Jobs\ProcessRemainingBatches;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Squad\Services\EligibilityService;
use App\Modules\Player\PlayerAge;
use App\Modules\Player\Services\InjuryService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MatchdayOrchestrator
{
    private int $careerActionTicks = 0;

    public function __construct(
        private readonly MatchdayService $matchdayService,
        private readonly FullMatchSimulationService $fullMatchSimulation,
        private readonly MatchResultProcessor $matchResultProcessor,
        private readonly MatchFinalizationService $finalizationService,
        private readonly StandingsCalculator $standingsCalculator,
        private readonly NotificationService $notificationService,
        private readonly EligibilityService $eligibilityService,
        private readonly InjuryService $injuryService,
        private readonly AIMatchResolver $aiMatchResolver = new AIMatchResolver,
    ) {}

    public function advance(Game $game): MatchdayAdvanceResult
    {
        $this->careerActionTicks = 0;

        $result = DB::transaction(function () use ($game) {
            // Lock the game row to prevent concurrent matchday advancement
            $game = Game::where('id', $game->id)->lockForUpdate()->first();

            // Safety net: finalize any pending match from a previous matchday
            // (e.g. user closed browser without clicking "Continue")
            $this->finalizePendingMatch($game);

            // Block advancement if career actions from a previous advance are still processing
            $game->clearStuckCareerActions();
            if ($game->isProcessingCareerActions()) {
                return MatchdayAdvanceResult::blocked(null);
            }

            // Block advancement if there are pending actions the user must resolve
            if ($game->hasPendingActions()) {
                return MatchdayAdvanceResult::blocked($game->getFirstPendingAction());
            }

            // Mark all existing notifications as read before processing new matchday
            $this->notificationService->markAllAsRead($game->id);

            // Process batches until one involves the player's team or the season ends
            while ($batch = $this->matchdayService->getNextMatchBatch($game)) {
                // Check if this batch contains the player's match
                $batchHasPlayerMatch = $batch['matches']->contains(
                    fn ($m) => $m->involvesTeam($game->team_id)
                );

                // When the player's match is in the batch, only simulate their match
                // — sibling AI matches are deferred to background processing
                $result = $this->processBatch($game, $batch, $batchHasPlayerMatch);

                if ($result['playerMatch']) {
                    return MatchdayAdvanceResult::liveMatch($result['playerMatch']->id);
                }

                // AI-only batch — check if the player still has upcoming matches
                $playerHasMoreMatches = GameMatch::where('game_id', $game->id)
                    ->where('played', false)
                    ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                        ->orWhere('away_team_id', $game->team_id))
                    ->exists();

                if (! $playerHasMoreMatches) {
                    $this->autoSimulateRemainingBatches($game);

                    // Re-check: new matches (e.g. playoffs) may have been generated
                    $playerNowHasMatches = GameMatch::where('game_id', $game->id)
                        ->where('played', false)
                        ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                            ->orWhere('away_team_id', $game->team_id))
                        ->exists();

                    if ($playerNowHasMatches) {
                        $game->refresh()->setRelations([]);

                        continue;
                    }

                    return MatchdayAdvanceResult::done();
                }

                // Player has matches coming but not in this batch — continue silently
                $game->refresh()->setRelations([]);
            }

            return MatchdayAdvanceResult::seasonComplete();
        });

        // Dispatch post-transaction work now that all DB changes are committed
        if ($result->type === 'live_match') {
            // Defer remaining batches to background — user sees live match immediately.
            // Career actions are dispatched by processRemainingBatches() after all batches complete.
            $this->deferRemainingBatches($game);
        } elseif ($this->careerActionTicks > 0) {
            $this->dispatchCareerActions($game->id, $this->careerActionTicks);
        }

        return $result;
    }

    /**
     * Process a single batch of matches: load players, simulate, process results.
     *
     * @return array{playerMatch: ?GameMatch}
     */
    private function processBatch(Game $game, array $batch, bool $playerMatchOnly = false): array
    {
        $matches = $batch['matches'];
        $handlers = $batch['handlers'];
        $matchday = $batch['matchday'];
        $currentDate = $batch['currentDate'];

        // When playerMatchOnly is true, filter batch to only the player's match
        // (sibling AI matches in the same batch are deferred to background processing)
        $playerMatch = $matches->first(fn ($m) => $m->involvesTeam($game->team_id));
        if ($playerMatchOnly && $playerMatch) {
            $matches = collect([$playerMatch]);
            $handlers = array_intersect_key($handlers, [$playerMatch->competition_id => true]);
        }

        // Determine if this is a pure AI-only batch eligible for fast resolution
        $isAIOnlyBatch = ! $playerMatch && config('match_simulation.ai_resolver_enabled', false);

        // --- Load players ---
        $teamIds = $matches->pluck('home_team_id')
            ->merge($matches->pluck('away_team_id'))
            ->push($game->team_id)
            ->unique()
            ->values();

        $allPlayers = GamePlayer::select([
                'id', 'game_id', 'player_id', 'team_id', 'number', 'position',
                'fitness', 'morale', 'durability',
                'game_technical_ability', 'game_physical_ability',
                'injury_until', 'injury_type',
                'appearances', 'goals', 'own_goals', 'assists',
                'yellow_cards', 'red_cards',
                'goals_conceded', 'clean_sheets',
                'season_appearances',
            ])
            ->with(['player:id,name,date_of_birth,technical_ability,physical_ability'])
            ->where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->get();

        // Set game relation in-memory to prevent lazy-loading per player
        // (avoids ~220 queries from the age accessor)
        foreach ($allPlayers as $player) {
            $player->setRelation('game', $game);
        }

        $allPlayers = $allPlayers->groupBy('team_id');

        $competitionIds = $matches->pluck('competition_id')->unique()->toArray();
        $suspendedByCompetition = PlayerSuspension::whereIn('competition_id', $competitionIds)
            ->where('matches_remaining', '>', 0)
            ->get(['game_player_id', 'competition_id'])
            ->groupBy('competition_id')
            ->map(fn ($group) => $group->pluck('game_player_id')->toArray())
            ->toArray();

        if ($isAIOnlyBatch) {
            // --- Fast AI resolution path ---
            // Skips: FormationRecommender, full LineupService, MatchSimulator,
            // AISubstitutionService, EnergyCalculator, tactical instruction selection.
            // The AIMatchResolver handles lineup selection (with rotation) and
            // statistical result generation in a single lightweight pass.
            $matchResults = $this->aiMatchResolver->resolveMatches($matches, $allPlayers, $game, $suspendedByCompetition);
        } else {
            // --- Full simulation path (player-involved batches) ---
            $resolution = $this->fullMatchSimulation->resolveMatches($matches, $game, $allPlayers, $suspendedByCompetition);
            $matchResults = $resolution['matchResults'];
            $playerMatch = $resolution['playerMatch'];
        }

        // Identify user's match — its score-dependent effects are deferred to finalization
        $deferMatchId = $playerMatch?->id;

        // --- Process results ---
        $this->matchResultProcessor->processAll($game->id, $currentDate, $matchResults, $deferMatchId, $allPlayers);

        // --- Recalculate positions ---
        $this->recalculateLeaguePositions($game->id, $matches);

        // Mark user's match as pending finalization BEFORE post-match actions
        if ($playerMatch) {
            $game->update(['pending_finalization_match_id' => $playerMatch->id]);
        }

        // End pre-season when no more pre-season matches remain
        if ($game->isInPreSeason()) {
            $hasFriendlies = GameMatch::where('game_id', $game->id)
                ->where('competition_id', 'PRESEASON')
                ->where('played', false)
                ->exists();

            if (! $hasFriendlies || ($playerMatch && ! GameMatch::where('game_id', $game->id)
                ->where('competition_id', 'PRESEASON')
                ->where('played', false)
                ->where('id', '!=', $playerMatch->id)
                ->exists())) {
                $game->endPreSeason();

                if ($game->squad_registration_enabled) {
                    $unenrolledCount = GamePlayer::where('game_id', $game->id)
                        ->where('team_id', $game->team_id)
                        ->whereNull('number')
                        ->whereHas('player', fn ($q) => $q->where(
                            'date_of_birth', '<=', PlayerAge::dateOfBirthCutoff(PlayerAge::YOUNG_END, $game->current_date)
                        ))
                        ->count();

                    if ($unenrolledCount > 0) {
                        $this->notificationService->notifySquadRegistrationRequired($game, $unenrolledCount);
                    }
                }
            }
        }

        // --- Post-match actions ---
        $game->refresh()->setRelations([]);
        $this->processPostMatchActions($game, $matches, $handlers, $allPlayers, $deferMatchId);

        return ['playerMatch' => $playerMatch];
    }

    /**
     * Auto-simulate remaining AI-only batches. Stops if a batch involves
     * the player's team (e.g. newly generated playoff matches).
     */
    private function autoSimulateRemainingBatches(Game $game): void
    {
        while ($nextBatch = $this->matchdayService->getNextMatchBatch($game)) {
            // Stop if this batch involves the player — they need to play it
            $involvesPlayer = $nextBatch['matches']->contains(
                fn ($m) => $m->involvesTeam($game->team_id)
            );

            if ($involvesPlayer) {
                return;
            }

            $this->processBatch($game, $nextBatch);
            $game->refresh()->setRelations([]);
        }
    }

    /**
     * Atomically set a processing flag and dispatch a career actions job.
     */
    private function dispatchCareerActions(string $gameId, int $ticks): void
    {
        if ($ticks <= 0) {
            return;
        }

        $updated = Game::where('id', $gameId)
            ->whereNull('career_actions_processing_at')
            ->update(['career_actions_processing_at' => now()]);

        if ($updated) {
            try {
                ProcessCareerActions::dispatch($gameId, $ticks);
            } catch (\Throwable $e) {
                Game::where('id', $gameId)->update(['career_actions_processing_at' => null]);
            }
        }
    }

    /**
     * Set flag and dispatch background job to process remaining batches.
     * Called after the transaction commits in advance().
     */
    private function deferRemainingBatches(Game $game): void
    {
        $updated = Game::where('id', $game->id)
            ->whereNull('remaining_batches_processing_at')
            ->update(['remaining_batches_processing_at' => now()]);

        if ($updated) {
            try {
                ProcessRemainingBatches::dispatch($game->id, $this->careerActionTicks);
            } catch (\Throwable $e) {
                Game::where('id', $game->id)->update(['remaining_batches_processing_at' => null]);
            }
        }
    }

    /**
     * Process remaining batches in the background (called by ProcessRemainingBatches job).
     * Simulates all unplayed batches and dispatches career actions when done.
     */
    public function processRemainingBatches(Game $game, int $priorCareerActionTicks): void
    {
        $this->careerActionTicks = 0;

        DB::transaction(function () use ($game) {
            $game = Game::where('id', $game->id)->lockForUpdate()->first();

            $this->autoSimulateRemainingBatches($game);
        });

        // Total career action ticks = prior (from advance's synchronous batches) + new (from background batches)
        $totalTicks = $priorCareerActionTicks + $this->careerActionTicks;

        // Clear the remaining batches flag
        Game::where('id', $game->id)->update(['remaining_batches_processing_at' => null]);

        // Dispatch career actions if any ticks accumulated
        $this->dispatchCareerActions($game->id, $totalTicks);
    }

    private function recalculateLeaguePositions(string $gameId, $matches): void
    {
        // Get unique league competition IDs from this batch
        $leagueCompetitionIds = $matches
            ->filter(fn ($match) => $match->competition?->isLeague())
            ->pluck('competition_id')
            ->unique();

        // Recalculate positions once per league
        foreach ($leagueCompetitionIds as $competitionId) {
            $this->standingsCalculator->recalculatePositions($gameId, $competitionId);
        }
    }

    private function processPostMatchActions(Game $game, $matches, array $handlers, $allPlayers, ?string $deferMatchId = null): void
    {
        // Career-mode only: count tick for background processing
        if ($game->isCareerMode()) {
            $this->careerActionTicks++;
        }

        // Roll for training injuries (non-playing squad members)
        $this->processTrainingInjuries($game, $matches, $allPlayers);

        // Batch-load recent low-fitness notifications to avoid per-player queries
        $recentNotificationPlayerIds = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_LOW_FITNESS)
            ->where('game_date', '>', $game->current_date->copy()->subDays(7))
            ->pluck('metadata')
            ->map(fn ($m) => $m['player_id'] ?? null)
            ->filter()
            ->toArray();

        // Check for low fitness players
        $this->checkLowFitnessPlayers($game, $allPlayers, $recentNotificationPlayerIds);

        // Clean up old read notifications
        $this->notificationService->cleanupOldNotifications($game);

        // Competition-specific post-match actions for each handler
        foreach ($handlers as $competitionId => $handler) {
            $competitionMatches = $matches->filter(fn ($m) => $m->competition_id === $competitionId);
            if ($deferMatchId) {
                $competitionMatches = $competitionMatches->reject(fn ($m) => $m->id === $deferMatchId);
            }
            if ($competitionMatches->isNotEmpty()) {
                $handler->afterMatches($game, $competitionMatches, $allPlayers);
            }
        }

        // Check competition progress (advancement/elimination) after handlers have resolved ties
        $matchesForProgress = $deferMatchId
            ? $matches->reject(fn ($m) => $m->id === $deferMatchId)
            : $matches;
        $this->checkCompetitionProgress($game, $matchesForProgress, $handlers);
    }

    /**
     * Check for players with low fitness and notify.
     */
    private function checkLowFitnessPlayers(Game $game, $allPlayers, array $recentNotificationPlayerIds): void
    {
        $userTeamPlayers = $allPlayers->get($game->team_id, collect());

        foreach ($userTeamPlayers as $player) {
            // Skip injured players
            if ($player->injury_until && $player->injury_until->gte($game->current_date)) {
                continue;
            }

            // Check if player has low fitness (below 60%)
            if ($player->fitness < 60) {
                if (! in_array($player->id, $recentNotificationPlayerIds)) {
                    $this->notificationService->notifyLowFitness($game, $player);
                }
            }
        }
    }

    /**
     * Roll for training injuries among all squad members.
     * Each team gets at most one training injury per matchday.
     */
    private function processTrainingInjuries(Game $game, $matches, $allPlayers): void
    {
        foreach ($allPlayers as $teamId => $teamPlayers) {
            // Filter to non-injured squad members (playing and non-playing)
            $eligible = $teamPlayers->filter(function ($player) use ($game) {
                if ($player->injury_until && $player->injury_until->gte($game->current_date)) {
                    return false;
                }

                return true;
            });

            if ($eligible->isEmpty()) {
                continue;
            }

            $injury = $this->injuryService->rollTrainingInjuries($eligible, $game);

            if (! $injury) {
                continue;
            }

            // Skip injuries that wouldn't cause the player to miss any games
            $projectedUntil = Carbon::parse($game->current_date)->addWeeks($injury['weeks']);
            $missedData = InjuryService::getMatchesMissed($game->id, $teamId, $game->current_date, $projectedUntil);
            if ($missedData['count'] === 0) {
                continue;
            }

            $this->eligibilityService->applyInjury(
                $injury['player'],
                $injury['type'],
                $injury['weeks'],
                Carbon::parse($game->current_date),
            );

            if ($teamId === $game->team_id) {
                $this->notificationService->notifyInjury(
                    $game,
                    $injury['player'],
                    $injury['type'],
                    $injury['weeks'],
                    duringMatch: false,
                );
            }
        }
    }

    /**
     * Check competition progress and notify about advancement or elimination.
     */
    private function checkCompetitionProgress(Game $game, $matches, array $handlers): void
    {
        $this->checkSwissLeaguePhaseCompletion($game, $matches, $handlers);
        $this->checkLeagueWithPlayoffSeasonEnd($game, $matches, $handlers);
        $this->checkGroupStageCompletion($game, $matches, $handlers);
    }

    /**
     * Check if a swiss format league phase just completed.
     */
    private function checkSwissLeaguePhaseCompletion(Game $game, $matches, array $handlers): void
    {
        foreach ($handlers as $competitionId => $handler) {
            if ($handler->getType() !== 'swiss_format') {
                continue;
            }

            // Only check if league-phase matches were played (not knockout)
            $leaguePhaseMatches = $matches->filter(
                fn ($m) => $m->competition_id === $competitionId && $m->cup_tie_id === null
            );

            if ($leaguePhaseMatches->isEmpty()) {
                continue;
            }

            // Check if any unplayed league-phase matches remain
            $hasUnplayed = GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->whereNull('cup_tie_id')
                ->where('played', false)
                ->exists();

            if ($hasUnplayed) {
                continue;
            }

            // Defer notification if a match is pending finalization — standings
            // are incomplete. The notification will be sent after finalization.
            if ($game->hasPendingFinalizationForCompetition($competitionId)) {
                continue;
            }

            // League phase just completed — check player's team position
            $standing = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->where('team_id', $game->team_id)
                ->first();

            if (! $standing) {
                continue;
            }

            $competition = Competition::find($competitionId);

            if ($standing->position <= 8) {
                $this->notificationService->notifyCompetitionAdvancement(
                    $game, $competitionId, $competition->name,
                    __('cup.swiss_direct_r16'),
                );
            } elseif ($standing->position <= 24) {
                $this->notificationService->notifyCompetitionAdvancement(
                    $game, $competitionId, $competition->name,
                    __('cup.swiss_knockout_playoff'),
                );
            } else {
                $this->notificationService->notifyCompetitionElimination(
                    $game, $competitionId, $competition->name,
                    __('cup.swiss_eliminated'),
                );
            }
        }
    }

    /**
     * Check if a league_with_playoff regular season just ended.
     */
    private function checkLeagueWithPlayoffSeasonEnd(Game $game, $matches, array $handlers): void
    {
        foreach ($handlers as $competitionId => $handler) {
            if ($handler->getType() !== 'league_with_playoff') {
                continue;
            }

            // Only check if league matches were played (not playoff ties)
            $leagueMatches = $matches->filter(
                fn ($m) => $m->competition_id === $competitionId && $m->cup_tie_id === null
            );

            if ($leagueMatches->isEmpty()) {
                continue;
            }

            // Check if any unplayed league matches remain
            $hasUnplayed = GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->whereNull('cup_tie_id')
                ->where('played', false)
                ->exists();

            if ($hasUnplayed) {
                continue;
            }

            // Defer notification if a match is pending finalization — standings
            // are incomplete. The notification will be sent after finalization.
            if ($game->hasPendingFinalizationForCompetition($competitionId)) {
                continue;
            }

            // Regular season just completed — check player's team position
            $standing = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->where('team_id', $game->team_id)
                ->first();

            if (! $standing) {
                continue;
            }

            $competition = Competition::find($competitionId);

            if ($standing->position <= 2) {
                $this->notificationService->notifyCompetitionAdvancement(
                    $game, $competitionId, $competition->name,
                    __('cup.direct_promotion'),
                );
            } elseif ($standing->position <= 6) {
                $this->notificationService->notifyCompetitionAdvancement(
                    $game, $competitionId, $competition->name,
                    __('cup.promotion_playoff'),
                );
            }
        }
    }

    /**
     * Check if a group_stage_cup group phase just completed.
     */
    private function checkGroupStageCompletion(Game $game, $matches, array $handlers): void
    {
        foreach ($handlers as $competitionId => $handler) {
            if ($handler->getType() !== 'group_stage_cup') {
                continue;
            }

            // Only check if group-stage matches were played (not knockout ties)
            $groupMatches = $matches->filter(
                fn ($m) => $m->competition_id === $competitionId && $m->cup_tie_id === null
            );

            if ($groupMatches->isEmpty()) {
                continue;
            }

            // Check if any unplayed group-stage matches remain
            $hasUnplayed = GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->whereNull('cup_tie_id')
                ->where('played', false)
                ->exists();

            if ($hasUnplayed) {
                continue;
            }

            // Defer notification if a match is pending finalization — standings
            // are incomplete. The notification will be sent after finalization.
            if ($game->hasPendingFinalizationForCompetition($competitionId)) {
                continue;
            }

            // Group stage just completed — check player's team position
            $standing = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->where('team_id', $game->team_id)
                ->first();

            if (! $standing) {
                continue;
            }

            $competition = Competition::find($competitionId);

            if ($standing->position <= 2) {
                $this->notificationService->notifyCompetitionAdvancement(
                    $game, $competitionId, $competition->name,
                    __('cup.group_stage_qualified', ['group' => $standing->group_label]),
                );
            } else {
                $this->notificationService->notifyCompetitionElimination(
                    $game, $competitionId, $competition->name,
                    __('cup.group_stage_eliminated', ['group' => $standing->group_label]),
                );
            }
        }
    }

    /**
     * Safety net: finalize any match whose side effects were deferred but not yet applied.
     * This handles the case where a user closed their browser without clicking "Continue".
     */
    private function finalizePendingMatch(Game $game): void
    {
        if (! $game->pending_finalization_match_id) {
            return;
        }

        $match = GameMatch::find($game->pending_finalization_match_id);

        if ($match) {
            $this->finalizationService->finalize($match, $game);
        }
    }

}
