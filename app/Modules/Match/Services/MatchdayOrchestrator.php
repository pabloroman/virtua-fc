<?php

namespace App\Modules\Match\Services;

use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Squad\Services\EligibilityService;
use App\Modules\Player\PlayerAge;
use App\Modules\Player\Services\InjuryService;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\GameStanding;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        private readonly MatchAttendanceService $matchAttendanceService,
        private readonly AIMatchResolver $aiMatchResolver = new AIMatchResolver,
    ) {}

    public function advance(Game $game, bool $fastForward = false): MatchdayAdvanceResult
    {
        $this->careerActionTicks = 0;
        $advanceStart = microtime(true);
        $batchIndex = 0;

        $result = DB::transaction(function () use ($game, $fastForward, &$batchIndex) {
            // Lock the game row to prevent concurrent matchday advancement
            $t0 = microtime(true);
            $game = Game::where('id', $game->id)->lockForUpdate()->first();
            Log::info('[MatchdayAdvance] lockForUpdate completed in '.(int) round((microtime(true) - $t0) * 1000).'ms');

            // Safety net: finalize any pending match from a previous matchday
            // (e.g. user closed browser without clicking "Continue")
            $t0 = microtime(true);
            $this->finalizePendingMatch($game);
            Log::info('[MatchdayAdvance] finalizePendingMatch completed in '.(int) round((microtime(true) - $t0) * 1000).'ms');

            // Block advancement if career actions from a previous advance are still processing
            $game->clearStuckCareerActions();
            if ($game->isProcessingCareerActions()) {
                return MatchdayAdvanceResult::blocked(null);
            }

            // Block advancement on pending actions in the normal flow — the
            // user must resolve them before the season calendar ticks. Fast
            // mode opts out: each click plays a single match, and the
            // fast-mode view itself surfaces pending actions via an inline
            // banner so the user can resolve them between clicks or ignore
            // them and keep simulating.
            if (! $fastForward && $game->hasPendingActions()) {
                return MatchdayAdvanceResult::blocked($game->getFirstPendingAction());
            }

            // Mark all existing notifications as read before processing new matchday
            $t0 = microtime(true);
            $this->notificationService->markAllAsRead($game->id);
            Log::info('[MatchdayAdvance] markAllAsRead completed in '.(int) round((microtime(true) - $t0) * 1000).'ms');

            // Process batches until one involves the player's team or the season ends
            while (true) {
                $t0 = microtime(true);
                $batch = $this->matchdayService->getNextMatchBatch($game);
                Log::info('[MatchdayAdvance] getNextMatchBatch completed in '.(int) round((microtime(true) - $t0) * 1000).'ms');
                if (! $batch) {
                    break;
                }
                $batchIndex++;
                // Simulate every match in the batch inline so the live-match
                // "other scores" ticker has real sibling events to reveal.
                // FullMatchSimulationService routes siblings through the fast
                // statistical AIMatchResolver so only the user's match pays the
                // full MatchSimulator cost.
                $batchStart = microtime(true);
                $result = $this->processBatch($game, $batch, $fastForward);
                $batchMs = (int) round((microtime(true) - $batchStart) * 1000);
                Log::info(sprintf(
                    '[MatchdayAdvance] batch #%d (%d matches, player=%s) completed in %dms',
                    $batchIndex,
                    $batch['matches']->count(),
                    $result['playerMatch'] ? 'yes' : 'no',
                    $batchMs,
                ));

                if ($result['playerMatch']) {
                    if ($fastForward) {
                        // Finalize the user's match in-place — no live-UI handoff.
                        // This advances current_date forward and fires
                        // GameDateAdvanced listeners (transfer windows, squad
                        // enrollment, wage-gap drip, etc.) exactly as in the
                        // normal flow.
                        $playerMatch = GameMatch::find($result['playerMatch']->id);
                        if ($playerMatch) {
                            $t0 = microtime(true);
                            $this->finalizationService->finalize($playerMatch, $game->refresh());
                            Log::info('[MatchdayAdvance] finalize (fastForward) completed in '.(int) round((microtime(true) - $t0) * 1000).'ms');
                        }

                        return MatchdayAdvanceResult::done();
                    }

                    return MatchdayAdvanceResult::liveMatch($result['playerMatch']->id);
                }

                // AI-only batch — check if the player still has upcoming matches
                $playerHasMoreMatches = GameMatch::where('game_id', $game->id)
                    ->where('played', false)
                    ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                        ->orWhere('away_team_id', $game->team_id))
                    ->exists();

                if (! $playerHasMoreMatches) {
                    $t0 = microtime(true);
                    $this->autoSimulateRemainingBatches($game);
                    Log::info('[MatchdayAdvance] autoSimulateRemainingBatches completed in '.(int) round((microtime(true) - $t0) * 1000).'ms');

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

        // Run any future AI-only batches (e.g. mid-week cup nights between
        // the user's just-played match and their next one) inline now that
        // the live-match transaction is committed. In ~99% of matchdays this
        // is a no-op — getNextMatchBatch returns the player's next match and
        // the loop exits immediately. The 1% that does work (heavy European
        // weeks) stays sub-second on the AI fast path. Running synchronously
        // serializes batch processing per game, so we no longer need the
        // 40P01 deadlock retry that the previous queued path had to carry.
        if ($result->type === 'live_match') {
            $t0 = microtime(true);
            $this->processRemainingBatches($game, $this->careerActionTicks);
            Log::info('[MatchdayAdvance] processRemainingBatches (inline) completed in '.(int) round((microtime(true) - $t0) * 1000).'ms');
        } elseif ($this->careerActionTicks > 0) {
            $t0 = microtime(true);
            $this->dispatchCareerActions($game->id, $this->careerActionTicks);
            Log::info('[MatchdayAdvance] dispatchCareerActions completed in '.(int) round((microtime(true) - $t0) * 1000).'ms');
        }

        $totalMs = (int) round((microtime(true) - $advanceStart) * 1000);
        Log::info(sprintf(
            '[MatchdayAdvance] advance() total %dms (game %s, type %s, batches %d)',
            $totalMs,
            $game->id,
            $result->type,
            $batchIndex,
        ));

        return $result;
    }

    /**
     * Process a single batch of matches: load players, simulate, process results.
     *
     * @return array{playerMatch: ?GameMatch}
     */
    private function processBatch(Game $game, array $batch, bool $fastForward = false): array
    {
        $matches = $batch['matches'];
        $handlers = $batch['handlers'];
        $matchday = $batch['matchday'];
        $currentDate = $batch['currentDate'];

        // Clear cached match dates from prior batches (played matches changed)
        InjuryService::clearMatchDateCache();

        // Persist MatchAttendance rows for every fixture in the batch before
        // simulation so the live-match screen and downstream consumers read a
        // stable figure instead of re-computing (and potentially drifting from)
        // the demand curve at view time. Idempotent — matches that already
        // have a row from an earlier path are a no-op.
        $t0 = microtime(true);
        foreach ($matches as $match) {
            $this->matchAttendanceService->resolveForMatch($match, $game);
        }
        $attendanceMs = (microtime(true) - $t0) * 1000;

        $playerMatch = $matches->first(fn ($m) => $m->involvesTeam($game->team_id));

        // Determine if this is a pure AI-only batch eligible for fast resolution
        $isAIOnlyBatch = ! $playerMatch && config('match_simulation.ai_resolver_enabled', false);

        // --- Load players ---
        $t0 = microtime(true);
        $teamIds = $matches->pluck('home_team_id')
            ->merge($matches->pluck('away_team_id'))
            ->push($game->team_id)
            ->unique()
            ->values();

        $allPlayers = GamePlayer::select([
                'id', 'game_id', 'player_id', 'team_id', 'number', 'position',
                'durability',
                'game_technical_ability', 'game_physical_ability',
            ])
            ->with([
                'player:id,name,date_of_birth,technical_ability,physical_ability',
                'matchState',
            ])
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
        $loadMs = (microtime(true) - $t0) * 1000;

        $t0 = microtime(true);
        if ($isAIOnlyBatch) {
            // --- Fast AI resolution path ---
            // Skips: FormationRecommender, full LineupService, MatchSimulator,
            // AISubstitutionService, EnergyCalculator, tactical instruction selection.
            // The AIMatchResolver handles lineup selection (with rotation) and
            // statistical result generation in a single lightweight pass.
            $matchResults = $this->aiMatchResolver->resolveMatches($matches, $allPlayers, $game, $suspendedByCompetition);
            $simPath = 'ai';
        } else {
            // --- Full simulation path (player-involved batches) ---
            // Fast mode rides this same path — the live-match engine — but
            // delegates the user's in-match substitutions to the assistant
            // coach (AISubstitutionService) since there is no live UI.
            $resolution = $this->fullMatchSimulation->resolveMatches($matches, $game, $allPlayers, $suspendedByCompetition, $fastForward);
            $matchResults = $resolution['matchResults'];
            $playerMatch = $resolution['playerMatch'];
            $simPath = 'full';
        }
        $simulateMs = (microtime(true) - $t0) * 1000;

        // Identify user's match — its score-dependent effects are deferred to finalization
        $deferMatchId = $playerMatch?->id;

        // --- Process results ---
        $t0 = microtime(true);
        // Derive competitions from already-loaded match relations to avoid re-querying
        $competitions = $matches->pluck('competition')->filter()->unique('id')->keyBy('id');
        $this->matchResultProcessor->processAll($game, $currentDate, $matchResults, $deferMatchId, $allPlayers, $matches, $competitions);
        $processMs = (microtime(true) - $t0) * 1000;

        // --- Recalculate positions ---
        $t0 = microtime(true);
        $this->recalculateLeaguePositions($game->id, $matches);
        $positionsMs = (microtime(true) - $t0) * 1000;

        // Mark user's match as pending finalization BEFORE post-match actions
        if ($playerMatch) {
            $game->update(['pending_finalization_match_id' => $playerMatch->id]);

            // Cache raw performances for the user's match (used for client-side player ratings)
            $userResult = collect($matchResults)->firstWhere('matchId', $playerMatch->id);
            if ($userResult && ! empty($userResult['performances'])) {
                Cache::put("match_performances:{$playerMatch->id}", $userResult['performances'], now()->addHours(24));
            }
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
        $t0 = microtime(true);
        $game->refresh()->setRelations([]);
        $this->processPostMatchActions($game, $matches, $handlers, $allPlayers, $deferMatchId);
        $postMs = (microtime(true) - $t0) * 1000;

        Log::info(sprintf(
            '[MatchdayAdvance]   processBatch breakdown: path=%s | attendance %dms | load %dms | simulate %dms | process %dms | positions %dms | post %dms',
            $simPath,
            (int) round($attendanceMs),
            (int) round($loadMs),
            (int) round($simulateMs),
            (int) round($processMs),
            (int) round($positionsMs),
            (int) round($postMs),
        ));

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
     * Process remaining AI-only batches between the user's just-played match
     * and their next match. Called inline by advance() after the live-match
     * transaction commits — typically a no-op (no other unplayed matches yet
     * for the calendar), occasionally simulates a midweek European night.
     *
     * Each batch runs in its own transaction to limit lock duration and WAL
     * accumulation. Inline execution serializes per game, so the cross-process
     * concurrency that previously needed a 40P01 deadlock retry can no longer
     * happen.
     */
    private function processRemainingBatches(Game $game, int $priorCareerActionTicks): void
    {
        $this->careerActionTicks = 0;
        $gameId = $game->id;

        while ($nextBatch = $this->matchdayService->getNextMatchBatch($game)) {
            $involvesPlayer = $nextBatch['matches']->contains(
                fn ($m) => $m->involvesTeam($game->team_id)
            );

            if ($involvesPlayer) {
                break;
            }

            DB::transaction(function () use ($gameId, $nextBatch) {
                $lockedGame = Game::where('id', $gameId)->lockForUpdate()->first();
                $this->processBatch($lockedGame, $nextBatch);
            });

            $game = Game::find($gameId);
        }

        // Total ticks = prior (from advance's synchronous batches) + new (from this loop)
        $totalTicks = $priorCareerActionTicks + $this->careerActionTicks;

        $this->dispatchCareerActions($gameId, $totalTicks);
    }

    private function recalculateLeaguePositions(string $gameId, $matches): void
    {
        // Get unique league competition IDs from league-phase matches only.
        // Knockout matches (cup_tie_id set) must not trigger recalculation —
        // non-deterministic sort order for tied teams can swap positions,
        // breaking bracket seedings that depend on stable positions.
        $leagueCompetitionIds = $matches
            ->filter(fn ($match) => $match->competition?->isLeague() && $match->cup_tie_id === null)
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
