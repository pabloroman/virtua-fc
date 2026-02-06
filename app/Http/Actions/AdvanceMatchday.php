<?php

namespace App\Http\Actions;

use App\Game\Commands\AdvanceMatchday as AdvanceMatchdayCommand;
use App\Game\DTO\MatchEventData;
use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Game\Game as GameAggregate;
use App\Game\Services\LineupService;
use App\Game\Services\MatchdayService;
use App\Game\Services\MatchSimulator;
use App\Game\Services\ScoutingService;
use App\Game\Services\TransferService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchdayService $matchdayService,
        private readonly LineupService $lineupService,
        private readonly MatchSimulator $matchSimulator,
        private readonly TransferService $transferService,
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

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

        // Prepare lineups for all matches
        $this->lineupService->ensureLineupsForMatches($matches, $game);

        // Simulate all matches (pass game for medical tier effects on injuries)
        $matchResults = $this->simulateMatches($matches, $game);

        // Record results via event sourcing
        $this->recordMatchResults($gameId, $matchday, $currentDate, $matchResults);

        // Process post-match actions
        $game->refresh();
        $this->processPostMatchActions($game, $matches, $handler);

        return redirect()->to($handler->getRedirectRoute($game, $matches, $matchday));
    }

    private function simulateMatches($matches, Game $game): array
    {
        $allPlayers = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->get()
            ->groupBy('team_id');

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

    private function processPostMatchActions(Game $game, $matches, $handler): void
    {
        // Process transfers when window is open
        if ($game->isTransferWindowOpen()) {
            $this->transferService->completeAgreedTransfers($game);
            $this->transferService->completeIncomingTransfers($game);
        }

        // Generate transfer offers (can happen anytime, but more during windows)
        if ($game->isTransferWindowOpen()) {
            $this->transferService->generateOffersForListedPlayers($game);
            $this->transferService->generateUnsolicitedOffers($game);
        }

        // Pre-contract offers (January onwards for expiring contracts)
        $this->transferService->generatePreContractOffers($game);

        // Tick scout search progress
        $scoutReport = $this->scoutingService->tickSearch($game);
        if ($scoutReport?->isCompleted()) {
            session()->flash('scout_complete', 'Your scout has finished their search! Check the Scouting tab for results.');
        }

        // Competition-specific post-match actions
        $allPlayers = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->get()
            ->groupBy('team_id');

        $handler->afterMatches($game, $matches, $allPlayers);
    }
}
