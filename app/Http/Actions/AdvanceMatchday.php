<?php

namespace App\Http\Actions;

use App\Game\DTO\MatchEventData;
use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Game\Services\ContractService;
use App\Game\Services\EligibilityService;
use App\Game\Services\InjuryService;
use App\Game\Services\LineupService;
use App\Game\Services\LoanService;
use App\Game\Services\MatchdayService;
use App\Game\Services\MatchResultProcessor;
use App\Game\Services\MatchSimulator;
use App\Game\Services\NotificationService;
use App\Game\Services\ScoutingService;
use App\Game\Services\StandingsCalculator;
use App\Game\Services\TransferService;
use App\Game\Services\YouthAcademyService;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\PlayerSuspension;
use App\Models\TransferOffer;
use Carbon\Carbon;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchdayService $matchdayService,
        private readonly LineupService $lineupService,
        private readonly MatchSimulator $matchSimulator,
        private readonly MatchResultProcessor $matchResultProcessor,
        private readonly TransferService $transferService,
        private readonly ContractService $contractService,
        private readonly ScoutingService $scoutingService,
        private readonly StandingsCalculator $standingsCalculator,
        private readonly NotificationService $notificationService,
        private readonly LoanService $loanService,
        private readonly YouthAcademyService $youthAcademyService,
        private readonly EligibilityService $eligibilityService,
        private readonly InjuryService $injuryService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Safety net: finalize any pending match from a previous matchday
        // (e.g. user closed browser without clicking "Continue")
        $this->finalizePendingMatch($game);

        // Block advancement if there are pending actions the user must resolve
        if ($game->hasPendingActions()) {
            $action = $game->getFirstPendingAction();
            if ($action && $action['route']) {
                return redirect()->route($action['route'], $gameId)
                    ->with('warning', __('messages.action_required'));
            }

            return redirect()->route('show-game', $gameId)
                ->with('warning', __('messages.action_required'));
        }

        // Mark all existing notifications as read before processing new matchday
        $this->notificationService->markAllAsRead($gameId);

        // Get next batch of matches to play
        $batch = $this->matchdayService->getNextMatchBatch($game);

        if (! $batch) {
            return redirect()->route('show-game', $gameId)
                ->with('message', 'Season complete!');
        }

        // Process the current batch
        $result = $this->processBatch($game, $batch);

        // If the player's team played, check for remaining matches and auto-simulate
        if ($result['playerMatch']) {
            $this->autoSimulateRemainingBatches($game);

            return redirect()->route('game.live-match', [
                'gameId' => $game->id,
                'matchId' => $result['playerMatch']->id,
            ]);
        }

        // AI-only batch mid-season — check if player still has upcoming matches
        $playerHasMoreMatches = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->exists();

        if ($playerHasMoreMatches) {
            // Mid-season AI-only day — redirect to results
            $handlers = $batch['handlers'];
            $primaryHandler = reset($handlers);

            return redirect()->to($primaryHandler->getRedirectRoute(
                $game, $batch['matches'], $batch['matchday']
            ));
        }

        // Player has no more matches — simulate all remaining AI batches
        $this->autoSimulateRemainingBatches($game);

        return redirect()->route('show-game', $gameId);
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

        // Load players only for teams in this match batch (avoids loading entire game)
        $teamIds = $matches->pluck('home_team_id')
            ->merge($matches->pluck('away_team_id'))
            ->push($game->team_id)
            ->unique()
            ->values();

        $allPlayers = GamePlayer::with(['player', 'transferOffers', 'activeLoan', 'activeRenewalNegotiation'])
            ->where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->get()
            ->groupBy('team_id');

        // Batch load suspensions for all competitions in the match batch
        $competitionIds = $matches->pluck('competition_id')->unique()->toArray();
        $suspendedPlayerIds = PlayerSuspension::whereIn('competition_id', $competitionIds)
            ->where('matches_remaining', '>', 0)
            ->pluck('game_player_id')
            ->toArray();

        // Prepare lineups for all matches (pass pre-loaded data)
        $this->lineupService->ensureLineupsForMatches($matches, $game, $allPlayers, $suspendedPlayerIds);

        // Simulate all matches (pass game for medical tier effects on injuries)
        $matchResults = $this->simulateMatches($matches, $game, $allPlayers);

        // Identify user's match — its score-dependent effects are deferred to finalization
        $playerMatch = $matches->first(fn ($m) => $m->involvesTeam($game->team_id));
        $deferMatchId = $playerMatch?->id;

        // Process all match results (standings + GK stats skipped for user's match)
        $this->matchResultProcessor->processAll($game->id, $matchday, $currentDate, $matchResults, $deferMatchId);

        // Recalculate standings positions once per league competition (not per match)
        $this->recalculateLeaguePositions($game->id, $matches);

        // Process post-match actions (clear cached relations so season-scoped
        // relationships like currentInvestment lazy-load correctly after refresh)
        $game->refresh()->setRelations([]);
        $this->processPostMatchActions($game, $matches, $handlers, $allPlayers, $deferMatchId);

        // Mark user's match as pending finalization
        if ($playerMatch) {
            $game->update(['pending_finalization_match_id' => $playerMatch->id]);
        }

        return ['playerMatch' => $playerMatch];
    }

    /**
     * Auto-simulate all remaining AI batches when the player has no more matches.
     */
    private function autoSimulateRemainingBatches(Game $game): void
    {
        $playerHasMoreMatches = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->exists();

        if ($playerHasMoreMatches) {
            return;
        }

        // Simulate all remaining AI-only batches
        while ($nextBatch = $this->matchdayService->getNextMatchBatch($game)) {
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
        // Career-mode only: transfers, scouting, loans, academy
        if ($game->isCareerMode()) {
            $this->processCareerModeActions($game, $matches, $allPlayers);
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
        // Skip user's match for knockout handlers (cup tie resolved at finalization)
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
        // Skip knockout tie check for user's deferred match (handled in FinalizeMatch)
        $matchesForProgress = $deferMatchId
            ? $matches->reject(fn ($m) => $m->id === $deferMatchId)
            : $matches;
        $this->checkCompetitionProgress($game, $matchesForProgress, $handlers);
    }

    private function processCareerModeActions(Game $game, $matches, $allPlayers): void
    {
        // Process transfers when window is open
        if ($game->isTransferWindowOpen()) {
            $completedOutgoing = $this->transferService->completeAgreedTransfers($game);
            $completedIncoming = $this->transferService->completeIncomingTransfers($game);

            foreach ($completedOutgoing->merge($completedIncoming) as $offer) {
                $this->notificationService->notifyTransferComplete($game, $offer);
            }
        }

        // Generate transfer offers (can happen anytime, but more during windows)
        if ($game->isTransferWindowOpen()) {
            $listedOffers = $this->transferService->generateOffersForListedPlayers($game, $allPlayers);
            $unsolicitedOffers = $this->transferService->generateUnsolicitedOffers($game, $allPlayers);

            // Create notifications for new offers
            foreach ($listedOffers->merge($unsolicitedOffers) as $offer) {
                $this->notificationService->notifyTransferOffer($game, $offer);
            }
        }

        // Resolve pending renewal negotiations
        $renewalResults = $this->contractService->resolveRenewalNegotiations($game);
        foreach ($renewalResults as $result) {
            $this->notificationService->notifyRenewalResult($game, $result['negotiation'], $result['result']);
        }

        // Pre-contract offers (January onwards for expiring contracts)
        $preContractOffers = $this->transferService->generatePreContractOffers($game, $allPlayers);

        // Create notifications for pre-contract offers
        foreach ($preContractOffers as $offer) {
            $this->notificationService->notifyTransferOffer($game, $offer);
        }

        // Resolve pending incoming pre-contract offers (after response delay)
        $resolvedPreContracts = $this->transferService->resolveIncomingPreContractOffers($game, $this->scoutingService);
        foreach ($resolvedPreContracts as $result) {
            $this->notificationService->notifyPreContractResult($game, $result['offer']);
        }

        // Tick scout search progress
        $scoutReport = $this->scoutingService->tickSearch($game);
        if ($scoutReport?->isCompleted()) {
            $this->notificationService->notifyScoutComplete($game, $scoutReport);
        }

        // Process loan searches
        $loanResults = $this->loanService->processLoanSearches($game);
        foreach ($loanResults['found'] as $result) {
            $this->notificationService->notifyLoanDestinationFound(
                $game,
                $result['player'],
                $result['destination'],
                $result['windowOpen'],
            );
        }
        foreach ($loanResults['expired'] as $result) {
            $this->notificationService->notifyLoanSearchFailed($game, $result['player']);
        }

        // Check for expiring transfer offers (2 days or less)
        $this->checkExpiringOffers($game);

        // Check for new academy prospect
        $prospect = $this->youthAcademyService->trySpawnProspect($game);
        if ($prospect) {
            $this->notificationService->notifyAcademyProspect($game, $prospect);
        }
    }

    /**
     * Check for transfer offers that are about to expire and notify.
     */
    private function checkExpiringOffers(Game $game): void
    {
        $expiringOffers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', fn($q) => $q->where('team_id', $game->team_id))
            ->get()
            ->filter(fn($offer) => $offer->days_until_expiry <= 2 && $offer->days_until_expiry > 0);

        foreach ($expiringOffers as $offer) {
            // Check if we already have a recent expiring notification for this offer
            if (!$this->notificationService->hasRecentNotification(
                $game->id,
                GameNotification::TYPE_TRANSFER_OFFER_EXPIRING,
                ['offer_id' => $offer->id],
                1
            )) {
                $this->notificationService->notifyExpiringOffer($game, $offer);
            }
        }
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
                if (!$this->notificationService->hasRecentNotification(
                    $game->id,
                    GameNotification::TYPE_PLAYER_RECOVERED,
                    ['player_id' => $player->id],
                    7
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
                if (!$this->notificationService->hasRecentNotification(
                    $game->id,
                    GameNotification::TYPE_LOW_FITNESS,
                    ['player_id' => $player->id],
                    7
                )) {
                    $this->notificationService->notifyLowFitness($game, $player);
                }
            }
        }
    }

    /**
     * Roll for training injuries among non-playing squad members.
     * Each team gets at most one training injury per matchday.
     */
    private function processTrainingInjuries(Game $game, $matches, $allPlayers): void
    {
        // Collect all lineup player IDs from this batch
        $allLineupIds = [];
        foreach ($matches as $match) {
            $allLineupIds = array_merge($allLineupIds, $match->home_lineup ?? [], $match->away_lineup ?? []);
        }
        $allLineupIds = array_unique($allLineupIds);

        foreach ($allPlayers as $teamId => $teamPlayers) {
            // Filter to non-playing, non-injured squad members
            $eligible = $teamPlayers->filter(function ($player) use ($allLineupIds, $game) {
                if (in_array($player->id, $allLineupIds)) {
                    return false;
                }
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
        $this->checkResolvedKnockoutTies($game, $matches);
        $this->checkSwissLeaguePhaseCompletion($game, $matches, $handlers);
        $this->checkLeagueWithPlayoffSeasonEnd($game, $matches, $handlers);
    }

    /**
     * Check resolved knockout ties involving the player's team.
     */
    private function checkResolvedKnockoutTies(Game $game, $matches): void
    {
        $cupTieIds = $matches->pluck('cup_tie_id')->filter()->unique();

        if ($cupTieIds->isEmpty()) {
            return;
        }

        $resolvedTies = CupTie::with('competition')
            ->whereIn('id', $cupTieIds)
            ->where('completed', true)
            ->where(function ($query) use ($game) {
                $query->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->get();

        foreach ($resolvedTies as $tie) {
            $competition = $tie->competition;
            $roundConfig = $tie->getRoundConfig();
            $roundName = $roundConfig->name ?? $tie->firstLegMatch->round_name ?? '';

            if ($tie->winner_id === $game->team_id) {
                $this->notificationService->notifyCompetitionAdvancement(
                    $game,
                    $competition->id,
                    $competition->name,
                    __('cup.advanced_past_round', ['round' => $roundName]),
                );
            } else {
                $this->notificationService->notifyCompetitionElimination(
                    $game,
                    $competition->id,
                    $competition->name,
                    __('cup.eliminated_in_round', ['round' => $roundName]),
                );
            }
        }
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

            // League phase just completed — check player's team position
            $standing = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->where('team_id', $game->team_id)
                ->first();

            if (!$standing) {
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
     * Safety net: finalize any match whose side effects were deferred but not yet applied.
     * This handles the case where a user closed their browser without clicking "Continue".
     */
    private function finalizePendingMatch(Game $game): void
    {
        if (! $game->pending_finalization_match_id) {
            return;
        }

        app(FinalizeMatch::class)->finalizeMatch(
            GameMatch::find($game->pending_finalization_match_id),
            $game,
        );
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

            // Regular season just completed — check player's team position
            $standing = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->where('team_id', $game->team_id)
                ->first();

            if (!$standing) {
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
}
