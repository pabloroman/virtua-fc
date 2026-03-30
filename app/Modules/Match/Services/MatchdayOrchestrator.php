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
use App\Modules\Match\DTOs\MatchResult;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Modules\Match\Jobs\ProcessRemainingBatches;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Squad\Services\EligibilityService;
use App\Modules\Player\Services\InjuryService;
use App\Models\Competition;
use App\Models\TeamReputation;
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

        // --- Load players ---
        $teamIds = $matches->pluck('home_team_id')
            ->merge($matches->pluck('away_team_id'))
            ->push($game->team_id)
            ->unique()
            ->values();

        $allPlayers = GamePlayer::select([
                'id', 'game_id', 'player_id', 'team_id', 'position',
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

        $clubProfiles = TeamReputation::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)->get()->keyBy('team_id');

        // --- Ensure lineups ---
        $this->lineupService->ensureLineupsForMatches($matches, $game, $allPlayers, $suspendedByCompetition, $clubProfiles);

        // --- Check for forfeit (user's team has < 7 available players) ---
        // $playerMatch was already resolved above (line 158) — reuse it after filtering
        $forfeitResult = null;

        if ($playerMatch) {
            $isUserHome = $playerMatch->isHomeTeam($game->team_id);
            $userLineupField = $isUserHome ? 'home_lineup' : 'away_lineup';
            $userLineupCount = count($playerMatch->$userLineupField ?? []);
            $userSquadSize = $allPlayers->get($game->team_id, collect())->count();

            // Only forfeit if the team actually has players but too few available.
            // A squad of 0 means the game is in a test/setup state — let the simulator handle it.
            if ($userSquadSize > 0 && $userLineupCount < 7) {
                // Forfeit: 0-3 loss for the user's team
                $forfeitResult = [
                    'matchId' => $playerMatch->id,
                    'homeTeamId' => $playerMatch->home_team_id,
                    'awayTeamId' => $playerMatch->away_team_id,
                    'homeScore' => $isUserHome ? 0 : 3,
                    'awayScore' => $isUserHome ? 3 : 0,
                    'homePossession' => 50,
                    'awayPossession' => 50,
                    'competitionId' => $playerMatch->competition_id,
                    'events' => [],
                ];

                $this->notificationService->notifyMatchForfeit($game);
            }
        }

        // --- Simulate matches (skip forfeited match) ---
        $forfeitedMatchId = $forfeitResult ? $playerMatch->id : null;
        $matchesToSimulate = $forfeitedMatchId
            ? $matches->reject(fn ($m) => $m->id === $forfeitedMatchId)
            : $matches;
        $matchResults = $this->simulateMatches($matchesToSimulate, $game, $allPlayers);

        if ($forfeitResult) {
            $matchResults[] = $forfeitResult;
            // Forfeited match is not a live match — process all effects immediately
            $playerMatch = null;
        }

        // Identify user's match — its score-dependent effects are deferred to finalization
        $deferMatchId = $playerMatch?->id;

        // --- Process results ---
        $this->matchResultProcessor->processAll($game->id, $matchday, $currentDate, $matchResults, $deferMatchId, $allPlayers);

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

            // If the only remaining pre-season match is the player's current match, it counts as done
            if (! $hasFriendlies || ($playerMatch && ! GameMatch::where('game_id', $game->id)
                ->where('competition_id', 'PRESEASON')
                ->where('played', false)
                ->where('id', '!=', $playerMatch->id)
                ->exists())) {
                $game->endPreSeason();
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

        // Don't pass bench players for the user's team — they make their own
        // substitution decisions during the live match. The simulator already
        // guards with `$benchPlayers !== null`, so injury events are still
        // generated but no auto-substitution follows.
        $isUserMatch = $match->involvesTeam($game->team_id);
        $isUserHome = $isUserMatch && $match->isHomeTeam($game->team_id);

        $homeBenchPlayers = $isUserHome ? null : $this->getBenchPlayers($match, $allPlayers, 'home', $game);
        $awayBenchPlayers = ($isUserMatch && ! $isUserHome) ? null : $this->getBenchPlayers($match, $allPlayers, 'away', $game);

        $homeFormation = Formation::tryFrom($match->home_formation) ?? Formation::F_4_3_3;
        $awayFormation = Formation::tryFrom($match->away_formation) ?? Formation::F_4_3_3;
        $homeMentality = Mentality::tryFrom($match->home_mentality ?? '') ?? Mentality::BALANCED;
        $awayMentality = Mentality::tryFrom($match->away_mentality ?? '') ?? Mentality::BALANCED;

        $homePlayingStyle = PlayingStyle::tryFrom($match->home_playing_style ?? '') ?? PlayingStyle::BALANCED;
        $awayPlayingStyle = PlayingStyle::tryFrom($match->away_playing_style ?? '') ?? PlayingStyle::BALANCED;
        $homePressing = PressingIntensity::tryFrom($match->home_pressing ?? '') ?? PressingIntensity::STANDARD;
        $awayPressing = PressingIntensity::tryFrom($match->away_pressing ?? '') ?? PressingIntensity::STANDARD;
        $homeDefLine = DefensiveLineHeight::tryFrom($match->home_defensive_line ?? '') ?? DefensiveLineHeight::NORMAL;
        $awayDefLine = DefensiveLineHeight::tryFrom($match->away_defensive_line ?? '') ?? DefensiveLineHeight::NORMAL;

        $output = $this->matchSimulator->simulate(
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
            $homeBenchPlayers,
            $awayBenchPlayers,
            matchSeed: $match->id,
            neutralVenue: $match->competition_id === 'WC2026',
        );

        $result = $output->result;
        $performances = $output->performances;
        $mvpPlayerId = $this->calculateMvp(
            $result,
            $performances,
            $homePlayers,
            $awayPlayers,
            $match->home_team_id,
            $match->away_team_id,
        );

        return [
            'matchId' => $match->id,
            'homeTeamId' => $match->home_team_id,
            'awayTeamId' => $match->away_team_id,
            'homeScore' => $result->homeScore,
            'awayScore' => $result->awayScore,
            'homePossession' => $result->homePossession,
            'awayPossession' => $result->awayPossession,
            'competitionId' => $match->competition_id,
            'mvpPlayerId' => $mvpPlayerId,
            'events' => $result->events->map(fn (MatchEventData $e) => $e->toArray())->all(),
        ];
    }

    /**
     * Get bench players for a team from the already-loaded player collection.
     * Filters out lineup players and injured players. Zero additional queries.
     */
    private function getBenchPlayers(GameMatch $match, $allPlayers, string $side, Game $game): \Illuminate\Support\Collection
    {
        $lineupField = $side . '_lineup';
        $teamIdField = $side . '_team_id';

        $lineupIds = $match->$lineupField ?? [];
        $teamPlayers = $allPlayers->get($match->$teamIdField, collect());

        return $teamPlayers
            ->reject(fn ($player) => in_array($player->id, $lineupIds))
            ->reject(fn ($player) => $player->isInjured($game->current_date))
            ->values();
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
        // Career-mode only: count tick for background processing
        if ($game->isCareerMode()) {
            $this->careerActionTicks++;
        }

        // Roll for training injuries (non-playing squad members)
        $this->processTrainingInjuries($game, $matches, $allPlayers);

        // Batch-load recent recovery + low-fitness notifications to avoid per-player queries
        $recentNotificationPlayerIds = GameNotification::where('game_id', $game->id)
            ->whereIn('type', [GameNotification::TYPE_PLAYER_RECOVERED, GameNotification::TYPE_LOW_FITNESS])
            ->where('game_date', '>', $game->current_date->copy()->subDays(7))
            ->pluck('metadata')
            ->map(fn ($m) => $m['player_id'] ?? null)
            ->filter()
            ->toArray();

        // Check for recovered players
        $this->checkRecoveredPlayers($game, $allPlayers, $recentNotificationPlayerIds);

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
     * Check for players who have recovered from injuries.
     */
    private function checkRecoveredPlayers(Game $game, $allPlayers, array $recentNotificationPlayerIds): void
    {
        $userTeamPlayers = $allPlayers->get($game->team_id, collect());

        foreach ($userTeamPlayers as $player) {
            // Check if player was injured but is now recovered
            if ($player->injury_until && $player->injury_until->lte($game->current_date)) {
                // Clear the injury fields so this doesn't trigger again on future matchdays
                $this->eligibilityService->clearInjury($player);

                if (! in_array($player->id, $recentNotificationPlayerIds)) {
                    $this->notificationService->notifyRecovery($game, $player);
                }
            }
        }
    }

    /**
     * Check for players with low fitness and notify.
     */
    private function checkLowFitnessPlayers(Game $game, $allPlayers, array $recentNotificationPlayerIds): void
    {
        $userTeamPlayers = $allPlayers->get($game->team_id, collect());

        foreach ($userTeamPlayers as $player) {
            // Skip injured players
            if ($player->injury_until && $player->injury_until->gt($game->current_date)) {
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
     * Calculate the MVP (Most Valuable Player) for a match.
     *
     * Uses position-aware scoring so all position groups can realistically win:
     * - Normalized performance (0.0-1.0) is the primary factor
     * - Goal/assist bonuses scale by position rarity (a defender scoring is more noteworthy)
     * - Goalkeepers and defenders earn clean sheet bonuses
     * - Goalkeepers are penalized for conceding many goals
     * - Players on the winning team get a small edge
     *
     * @param  MatchResult  $result  The match result with events
     * @param  array<string, float>  $performances  Map of player ID to performance modifier (0.7-1.3)
     * @param  \Illuminate\Support\Collection  $homePlayers  Home team lineup players
     * @param  \Illuminate\Support\Collection  $awayPlayers  Away team lineup players
     * @param  string  $homeTeamId  Home team ID
     * @param  string  $awayTeamId  Away team ID
     */
    private function calculateMvp(
        MatchResult $result,
        array $performances,
        \Illuminate\Support\Collection $homePlayers,
        \Illuminate\Support\Collection $awayPlayers,
        string $homeTeamId,
        string $awayTeamId,
    ): ?string {
        if (empty($performances)) {
            return null;
        }

        // Build lookup maps for position group and team membership
        $positionGroups = [];
        $playerTeams = [];
        foreach ($homePlayers as $player) {
            $positionGroups[$player->id] = $player->position_group;
            $playerTeams[$player->id] = $homeTeamId;
        }
        foreach ($awayPlayers as $player) {
            $positionGroups[$player->id] = $player->position_group;
            $playerTeams[$player->id] = $awayTeamId;
        }

        $goalsConceded = [
            $homeTeamId => $result->awayScore,
            $awayTeamId => $result->homeScore,
        ];

        $winningTeamId = match (true) {
            $result->homeScore > $result->awayScore => $homeTeamId,
            $result->awayScore > $result->homeScore => $awayTeamId,
            default => null,
        };

        // Position-scaled event bonuses (rarer contributions score higher)
        $goalBonuses = ['Goalkeeper' => 0.50, 'Defender' => 0.35, 'Midfielder' => 0.25, 'Forward' => 0.20];
        $assistBonuses = ['Goalkeeper' => 0.25, 'Defender' => 0.15, 'Midfielder' => 0.15, 'Forward' => 0.15];

        // Count events per player
        $goals = [];
        $assists = [];
        $yellowCards = [];
        $redCards = [];

        foreach ($result->events as $event) {
            match ($event->type) {
                'goal' => $goals[$event->gamePlayerId] = ($goals[$event->gamePlayerId] ?? 0) + 1,
                'assist' => $assists[$event->gamePlayerId] = ($assists[$event->gamePlayerId] ?? 0) + 1,
                'yellow_card' => $yellowCards[$event->gamePlayerId] = ($yellowCards[$event->gamePlayerId] ?? 0) + 1,
                'red_card' => $redCards[$event->gamePlayerId] = ($redCards[$event->gamePlayerId] ?? 0) + 1,
                default => null,
            };
        }

        // Score each player
        $bestPlayerId = null;
        $bestScore = -INF;
        $bestIsWinner = false;

        foreach ($performances as $playerId => $performance) {
            $group = $positionGroups[$playerId] ?? 'Midfielder';
            $teamId = $playerTeams[$playerId] ?? null;
            $teamConceded = $teamId ? ($goalsConceded[$teamId] ?? 0) : 0;

            // Normalized performance: map 0.70-1.30 to 0.0-1.0
            $score = ($performance - 0.70) / 0.60;

            // Position-scaled goal/assist bonuses
            $score += ($goals[$playerId] ?? 0) * ($goalBonuses[$group] ?? 0.15);
            $score += ($assists[$playerId] ?? 0) * ($assistBonuses[$group] ?? 0.10);

            // Card penalties
            $score -= ($yellowCards[$playerId] ?? 0) * 0.10;
            $score -= ($redCards[$playerId] ?? 0) * 0.30;

            // Clean sheet bonus for goalkeepers and defenders
            if ($teamConceded === 0) {
                $score += match ($group) {
                    'Goalkeeper' => 0.20,
                    'Defender' => 0.15,
                    default => 0.0,
                };
            } elseif ($teamConceded === 1) {
                $score += match ($group) {
                    'Goalkeeper' => 0.05,
                    'Defender' => 0.05,
                    default => 0.0,
                };
            }

            // Goals conceded penalty for goalkeepers
            if ($group === 'Goalkeeper') {
                $score -= match (true) {
                    $teamConceded >= 4 => 0.20,
                    $teamConceded >= 3 => 0.10,
                    default => 0.0,
                };
            }

            // Winning team edge
            $isWinner = $winningTeamId !== null && $teamId === $winningTeamId;
            if ($isWinner) {
                $score += 0.08;
            }

            // Tiebreak: prefer the player from the winning team
            if ($score > $bestScore || ($score === $bestScore && $isWinner && ! $bestIsWinner)) {
                $bestScore = $score;
                $bestPlayerId = $playerId;
                $bestIsWinner = $isWinner;
            }
        }

        return $bestPlayerId;
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
