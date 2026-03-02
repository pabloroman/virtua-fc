<?php

namespace App\Modules\Match\Services;

use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Squad\Services\EligibilityService;
use App\Modules\Squad\Services\InjuryService;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchdayOrchestrator
{
    private array $batchTimings = [];
    private int $totalAdvanceQueries = 0;
    private float $totalAdvanceTime = 0;
    private int $careerActionTicks = 0;

    public function __construct(
        private readonly MatchdayService $matchdayService,
        private readonly LineupService $lineupService,
        private readonly MatchSimulator $matchSimulator,
        private readonly MatchResultProcessor $matchResultProcessor,
        private readonly MatchFinalizationService $finalizationService,
        private readonly StandingsCalculator $standingsCalculator,
        private readonly NotificationService $notificationService,
        private readonly EligibilityService $eligibilityService,
        private readonly InjuryService $injuryService,
    ) {}

    public function advance(Game $game): MatchdayAdvanceResult
    {
        $this->batchTimings = [];
        $this->totalAdvanceQueries = 0;
        $this->totalAdvanceTime = 0;
        $this->careerActionTicks = 0;
        $advanceStart = microtime(true);

        DB::enableQueryLog();

        $result = DB::transaction(function () use ($game) {
            // Lock the game row to prevent concurrent matchday advancement
            $game = Game::where('id', $game->id)->lockForUpdate()->first();

            // Safety net: finalize any pending match from a previous matchday
            // (e.g. user closed browser without clicking "Continue")
            $this->finalizePendingMatch($game);

            // Block advancement if career actions from a previous advance are still processing
            if ($game->isProcessingCareerActions()) {
                $game->clearStuckCareerActions();
            }
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
                $result = $this->processBatch($game, $batch);

                if ($result['playerMatch']) {
                    $this->autoSimulateRemainingBatches($game);

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

        // Dispatch career actions to background after transaction commits
        if ($this->careerActionTicks > 0) {
            $updated = Game::where('id', $game->id)
                ->whereNull('career_actions_processing_at')
                ->update(['career_actions_processing_at' => now()]);

            if ($updated) {
                try {
                    ProcessCareerActions::dispatch($game->id, $this->careerActionTicks);
                } catch (\Throwable $e) {
                    Game::where('id', $game->id)->update(['career_actions_processing_at' => null]);
                    Log::error('Failed to dispatch career actions job', [
                        'game_id' => $game->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->totalAdvanceTime = (microtime(true) - $advanceStart) * 1000;
        $this->logAdvanceSummary();

        DB::disableQueryLog();
        DB::flushQueryLog();

        return $result;
    }

    /**
     * Process a single batch of matches: load players, simulate, process results.
     *
     * @return array{playerMatch: ?GameMatch}
     */
    private function processBatch(Game $game, array $batch): array
    {
        $batchNumber = count($this->batchTimings) + 1;
        $timings = [];
        $matches = $batch['matches'];
        $handlers = $batch['handlers'];
        $matchday = $batch['matchday'];
        $currentDate = $batch['currentDate'];
        $matchCount = $matches->count();

        // --- Load players ---
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());

        $teamIds = $matches->pluck('home_team_id')
            ->merge($matches->pluck('away_team_id'))
            ->push($game->team_id)
            ->unique()
            ->values();

        $allPlayers = GamePlayer::with(['player', 'transferOffers', 'activeLoan', 'activeRenewalNegotiation'])
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
        $suspendedPlayerIds = PlayerSuspension::whereIn('competition_id', $competitionIds)
            ->where('matches_remaining', '>', 0)
            ->pluck('game_player_id')
            ->toArray();

        $clubProfiles = ClubProfile::whereIn('team_id', $teamIds)->get()->keyBy('team_id');
        $timings['loadPlayers'] = $this->capturePhase($t0, $q0);

        // --- Ensure lineups ---
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $this->lineupService->ensureLineupsForMatches($matches, $game, $allPlayers, $suspendedPlayerIds, $clubProfiles);
        $timings['ensureLineups'] = $this->capturePhase($t0, $q0);

        // --- Simulate matches ---
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $matchResults = $this->simulateMatches($matches, $game, $allPlayers);
        $timings['simulateMatches'] = $this->capturePhase($t0, $q0);

        // Identify user's match — its score-dependent effects are deferred to finalization
        $playerMatch = $matches->first(fn ($m) => $m->involvesTeam($game->team_id));
        $deferMatchId = $playerMatch?->id;

        // --- Process results ---
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $this->matchResultProcessor->processAll($game->id, $matchday, $currentDate, $matchResults, $deferMatchId, $allPlayers);
        $timings['processResults'] = $this->capturePhase($t0, $q0);

        // --- Recalculate positions ---
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $this->recalculateLeaguePositions($game->id, $matches);
        $timings['recalcPositions'] = $this->capturePhase($t0, $q0);

        // Mark user's match as pending finalization BEFORE post-match actions
        if ($playerMatch) {
            $game->update(['pending_finalization_match_id' => $playerMatch->id]);
        }

        // --- Post-match actions ---
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $game->refresh()->setRelations([]);
        $this->processPostMatchActions($game, $matches, $handlers, $allPlayers, $deferMatchId);
        $timings['postMatchActions'] = $this->capturePhase($t0, $q0);

        // Store batch timings
        $batchTotal = array_sum(array_column($timings, 'ms'));
        $batchQueries = array_sum(array_column($timings, 'queries'));
        $this->totalAdvanceQueries += $batchQueries;

        $this->batchTimings[] = [
            'batch' => $batchNumber,
            'matchCount' => $matchCount,
            'phases' => $timings,
            'totalMs' => $batchTotal,
            'totalQueries' => $batchQueries,
        ];

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

    private function simulateMatches($matches, Game $game, $allPlayers): array
    {
        $results = [];
        foreach ($matches as $match) {
            $results[] = $this->simulateMatch($match, $allPlayers, $game);
        }

        return $results;
    }

    private function simulateMatch(GameMatch $match, $allPlayers, Game $game): array
    {
        $homePlayers = $this->getLineupPlayers($match, $allPlayers, 'home');
        $awayPlayers = $this->getLineupPlayers($match, $allPlayers, 'away');

        $homeFormation = Formation::tryFrom($match->home_formation) ?? Formation::F_4_4_2;
        $awayFormation = Formation::tryFrom($match->away_formation) ?? Formation::F_4_4_2;
        $homeMentality = Mentality::tryFrom($match->home_mentality ?? '') ?? Mentality::BALANCED;
        $awayMentality = Mentality::tryFrom($match->away_mentality ?? '') ?? Mentality::BALANCED;

        $homePlayingStyle = PlayingStyle::tryFrom($match->home_playing_style ?? '') ?? PlayingStyle::BALANCED;
        $awayPlayingStyle = PlayingStyle::tryFrom($match->away_playing_style ?? '') ?? PlayingStyle::BALANCED;
        $homePressing = PressingIntensity::tryFrom($match->home_pressing ?? '') ?? PressingIntensity::STANDARD;
        $awayPressing = PressingIntensity::tryFrom($match->away_pressing ?? '') ?? PressingIntensity::STANDARD;
        $homeDefLine = DefensiveLineHeight::tryFrom($match->home_defensive_line ?? '') ?? DefensiveLineHeight::NORMAL;
        $awayDefLine = DefensiveLineHeight::tryFrom($match->away_defensive_line ?? '') ?? DefensiveLineHeight::NORMAL;

        $result = $this->matchSimulator->simulate(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeFormation,
            $awayFormation,
            $homeMentality,
            $awayMentality,
            $game,
            $homePlayingStyle,
            $awayPlayingStyle,
            $homePressing,
            $awayPressing,
            $homeDefLine,
            $awayDefLine,
        );

        return [
            'matchId' => $match->id,
            'homeTeamId' => $match->home_team_id,
            'awayTeamId' => $match->away_team_id,
            'homeScore' => $result->homeScore,
            'awayScore' => $result->awayScore,
            'competitionId' => $match->competition_id,
            'events' => $result->events->map(fn (MatchEventData $e) => $e->toArray())->all(),
        ];
    }

    private function getLineupPlayers(GameMatch $match, $allPlayers, string $side)
    {
        $lineupField = $side.'_lineup';
        $teamIdField = $side.'_team_id';

        $lineupIds = $match->$lineupField ?? [];
        $teamPlayers = $allPlayers->get($match->$teamIdField, collect());

        if (empty($lineupIds)) {
            return $teamPlayers;
        }

        return $teamPlayers->filter(fn ($p) => in_array($p->id, $lineupIds));
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
        $postTimings = [];

        // Career-mode only: count tick for background processing
        if ($game->isCareerMode()) {
            $this->careerActionTicks++;
        }

        // Roll for training injuries (non-playing squad members)
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $this->processTrainingInjuries($game, $matches, $allPlayers);
        $postTimings['trainingInjuries'] = $this->capturePhase($t0, $q0);

        // Check for recovered players
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $this->checkRecoveredPlayers($game, $allPlayers);
        $postTimings['recoveredPlayers'] = $this->capturePhase($t0, $q0);

        // Check for low fitness players
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $this->checkLowFitnessPlayers($game, $allPlayers);
        $postTimings['lowFitnessCheck'] = $this->capturePhase($t0, $q0);

        // Clean up old read notifications
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $this->notificationService->cleanupOldNotifications($game);
        $postTimings['notificationCleanup'] = $this->capturePhase($t0, $q0);

        // Competition-specific post-match actions for each handler
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        foreach ($handlers as $competitionId => $handler) {
            $competitionMatches = $matches->filter(fn ($m) => $m->competition_id === $competitionId);
            if ($deferMatchId) {
                $competitionMatches = $competitionMatches->reject(fn ($m) => $m->id === $deferMatchId);
            }
            if ($competitionMatches->isNotEmpty()) {
                $handler->afterMatches($game, $competitionMatches, $allPlayers);
            }
        }
        $postTimings['handlerAfterMatches'] = $this->capturePhase($t0, $q0);

        // Check competition progress (advancement/elimination) after handlers have resolved ties
        $t0 = microtime(true);
        $q0 = count(DB::getQueryLog());
        $matchesForProgress = $deferMatchId
            ? $matches->reject(fn ($m) => $m->id === $deferMatchId)
            : $matches;
        $this->checkCompetitionProgress($game, $matchesForProgress, $handlers);
        $postTimings['competitionProgress'] = $this->capturePhase($t0, $q0);

        // Log post-match action sub-timings
        $lines = ['  [PostMatchActions breakdown]:'];
        foreach ($postTimings as $phase => $data) {
            $lines[] = sprintf('    %-25s %6.1fms (%d queries)', $phase . ':', $data['ms'], $data['queries']);
        }
        Log::channel('single')->info(implode("\n", $lines));
    }

    /**
     * Check for players who have recovered from injuries.
     */
    private function checkRecoveredPlayers(Game $game, $allPlayers): void
    {
        $userTeamPlayers = $allPlayers->get($game->team_id, collect());

        foreach ($userTeamPlayers as $player) {
            // Check if player was injured but is now recovered
            if ($player->injury_until && $player->injury_until->lte($game->current_date)) {
                // Clear the injury fields so this doesn't trigger again on future matchdays
                $this->eligibilityService->clearInjury($player);

                // Check if we haven't already notified about this recovery
                if (! $this->notificationService->hasRecentNotification(
                    $game->id,
                    GameNotification::TYPE_PLAYER_RECOVERED,
                    ['player_id' => $player->id],
                    7,
                    $game->current_date,
                )) {
                    $this->notificationService->notifyRecovery($game, $player);
                }
            }
        }
    }

    /**
     * Check for players with low fitness and notify.
     */
    private function checkLowFitnessPlayers(Game $game, $allPlayers): void
    {
        $userTeamPlayers = $allPlayers->get($game->team_id, collect());

        foreach ($userTeamPlayers as $player) {
            // Skip injured players
            if ($player->injury_until && $player->injury_until->gt($game->current_date)) {
                continue;
            }

            // Check if player has low fitness (below 60%)
            if ($player->fitness < 60) {
                // Only notify once per week per player
                if (! $this->notificationService->hasRecentNotification(
                    $game->id,
                    GameNotification::TYPE_LOW_FITNESS,
                    ['player_id' => $player->id],
                    7,
                    $game->current_date,
                )) {
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
                if ($player->injury_until && $player->injury_until->gt($game->current_date)) {
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

    // ==========================================
    // Profiling Helpers
    // ==========================================

    /**
     * Capture wall-clock time (ms) and query count for a phase.
     *
     * @return array{ms: float, queries: int}
     */
    private function capturePhase(float $startTime, int $startQueryCount): array
    {
        return [
            'ms' => round((microtime(true) - $startTime) * 1000, 1),
            'queries' => count(DB::getQueryLog()) - $startQueryCount,
        ];
    }

    /**
     * Log a structured summary of all batch timings for this advance() call.
     */
    private function logAdvanceSummary(): void
    {
        $lines = [];

        foreach ($this->batchTimings as $batch) {
            $lines[] = sprintf('[AdvanceMatchday] Batch %d (%d matches):', $batch['batch'], $batch['matchCount']);
            foreach ($batch['phases'] as $phase => $data) {
                $lines[] = sprintf('  %-25s %6.1fms (%d queries)', $phase . ':', $data['ms'], $data['queries']);
            }
            $lines[] = sprintf('  %-25s %6.1fms (%d queries)', 'BATCH TOTAL:', $batch['totalMs'], $batch['totalQueries']);
        }

        $batchCount = count($this->batchTimings);
        $lines[] = sprintf(
            '[AdvanceMatchday] Total: %.0fms (%d queries, %d %s)',
            $this->totalAdvanceTime,
            $this->totalAdvanceQueries,
            $batchCount,
            $batchCount === 1 ? 'batch' : 'batches'
        );

        Log::channel('single')->info(implode("\n", $lines));
    }
}
