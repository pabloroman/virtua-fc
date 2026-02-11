<?php

namespace App\Http\Actions;

use App\Game\Commands\AdvanceMatchday as AdvanceMatchdayCommand;
use App\Game\DTO\MatchEventData;
use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Game\Game as GameAggregate;
use App\Game\Services\LineupService;
use App\Game\Services\LoanService;
use App\Game\Services\MatchdayService;
use App\Game\Services\MatchSimulator;
use App\Game\Services\NotificationService;
use App\Game\Services\ScoutingService;
use App\Game\Services\StandingsCalculator;
use App\Game\Services\TransferService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Models\TransferOffer;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchdayService $matchdayService,
        private readonly LineupService $lineupService,
        private readonly MatchSimulator $matchSimulator,
        private readonly TransferService $transferService,
        private readonly ScoutingService $scoutingService,
        private readonly StandingsCalculator $standingsCalculator,
        private readonly NotificationService $notificationService,
        private readonly LoanService $loanService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Mark all existing notifications as read before processing new matchday
        $this->notificationService->markAllAsRead($gameId);

        // Get next batch of matches to play
        $batch = $this->matchdayService->getNextMatchBatch($game);

        if (! $batch) {
            return redirect()->route('show-game', $gameId)
                ->with('message', 'Season complete!');
        }

        $matches = $batch['matches'];
        $handler = $batch['handler'];
        $matchday = $batch['matchday'];
        $currentDate = $batch['currentDate'];

        // Load players only for teams in this match batch (avoids loading entire game)
        $teamIds = $matches->pluck('home_team_id')
            ->merge($matches->pluck('away_team_id'))
            ->push($game->team_id)
            ->unique()
            ->values();

        $allPlayers = GamePlayer::with(['player', 'transferOffers', 'activeLoan'])
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

        // Record results via event sourcing
        $this->recordMatchResults($gameId, $matchday, $currentDate, $matchResults);

        // Recalculate standings positions once per league competition (not per match)
        $this->recalculateLeaguePositions($gameId, $matches);

        // Process post-match actions
        $game->refresh();
        $this->processPostMatchActions($game, $matches, $handler, $allPlayers);

        // If the player's team played, redirect to the live match view
        $playerMatch = $matches->first(fn ($m) => $m->involvesTeam($game->team_id));

        if ($playerMatch) {
            return redirect()->route('game.live-match', [
                'gameId' => $game->id,
                'matchId' => $playerMatch->id,
            ]);
        }

        return redirect()->to($handler->getRedirectRoute($game, $matches, $matchday));
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

    private function recordMatchResults(string $gameId, int $matchday, string $currentDate, array $matchResults): void
    {
        $command = new AdvanceMatchdayCommand(
            matchday: $matchday,
            currentDate: $currentDate,
            matchResults: $matchResults,
        );

        $aggregate = GameAggregate::retrieve($gameId);
        $aggregate->advanceMatchday($command);
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

    private function processPostMatchActions(Game $game, $matches, $handler, $allPlayers): void
    {
        // Process transfers when window is open
        if ($game->isTransferWindowOpen()) {
            $this->transferService->completeAgreedTransfers($game);
            $this->transferService->completeIncomingTransfers($game);
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

        // Pre-contract offers (January onwards for expiring contracts)
        $preContractOffers = $this->transferService->generatePreContractOffers($game, $allPlayers);

        // Create notifications for pre-contract offers
        foreach ($preContractOffers as $offer) {
            $this->notificationService->notifyTransferOffer($game, $offer);
        }

        // Tick scout search progress
        $scoutReport = $this->scoutingService->tickSearch($game);
        if ($scoutReport?->isCompleted()) {
            // Create notification instead of session flash
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

        // Check for recovered players
        $this->checkRecoveredPlayers($game, $allPlayers);

        // Check for low fitness players
        $this->checkLowFitnessPlayers($game, $allPlayers);

        // Clean up old read notifications
        $this->notificationService->cleanupOldNotifications($game);

        // Competition-specific post-match actions
        $handler->afterMatches($game, $matches, $allPlayers);
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
}
