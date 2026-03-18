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
                }
            }
        }

        return $result;
    }

    /**
     * Process a single batch of matches: load players, simulate, process results.
     *
     * @return array{playerMatch: ?GameMatch}
     */
    private function processBatch(Game $game, array $batch): array
    {
        $matches = $batch['matches'];
        $handlers = $batch['handlers'];
        $matchday = $batch['matchday'];
        $currentDate = $batch['currentDate'];

        // --- Load players ---
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

        $clubProfiles = TeamReputation::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)->get()->keyBy('team_id');

        // --- Ensure lineups ---
        $this->lineupService->ensureLineupsForMatches($matches, $game, $allPlayers, $suspendedPlayerIds, $clubProfiles);

        // --- Check for forfeit (user's team has < 7 available players) ---
        $playerMatch = $matches->first(fn ($m) => $m->involvesTeam($game->team_id));
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
            $homeBenchPlayers,
            $awayBenchPlayers,
            matchSeed: $match->id,
        );

        // Capture performance data before next simulation overwrites it
        $performances = $this->matchSimulator->getMatchPerformances();
        $mvpPlayerId = $this->calculateMvp($result, $performances);

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

        // Check for recovered players
        $this->checkRecoveredPlayers($game, $allPlayers);

        // Check for low fitness players
        $this->checkLowFitnessPlayers($game, $allPlayers);

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
     * Calculate the MVP (Most Valuable Player) for a match.
     *
     * Combines each player's match performance modifier with event-based bonuses
     * (goals, assists) and penalties (cards). The player with the highest
     * combined score is selected as MVP.
     *
     * @param  MatchResult  $result  The match result with events
     * @param  array<string, float>  $performances  Map of player ID to performance modifier (0.7-1.3)
     */
    private function calculateMvp(MatchResult $result, array $performances): ?string
    {
        if (empty($performances)) {
            return null;
        }

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

        // Score each player: base performance + event bonuses
        $bestPlayerId = null;
        $bestScore = -INF;

        foreach ($performances as $playerId => $performance) {
            $score = $performance
                + (($goals[$playerId] ?? 0) * 0.15)
                + (($assists[$playerId] ?? 0) * 0.08)
                - (($yellowCards[$playerId] ?? 0) * 0.03)
                - (($redCards[$playerId] ?? 0) * 0.10);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPlayerId = $playerId;
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
