<?php

namespace App\Http\Actions;

use App\Game\Services\ScoutingService;
use App\Game\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;

class AdvancePreseasonWeek
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (!$game->isInPreseason()) {
            return redirect()->route('show-game', $gameId);
        }

        // Advance the week
        $game->advancePreseasonWeek();
        $game->refresh();

        // Process events for this week
        $this->processWeeklyEvents($game);

        // Check if pre-season is complete
        if ($game->isPreseasonComplete()) {
            $game->endPreseason();

            return redirect()->route('show-game', $gameId)
                ->with('message', 'Pre-season complete! The competitive season begins.');
        }

        return redirect()->route('game.preseason', $gameId);
    }

    private function processWeeklyEvents(Game $game): void
    {
        // Week 1: Financial events (TV rights, wages, transfers complete)
        if ($game->getPreseasonWeek() === 1) {
            $this->processFirstWeekEvents($game);
        }

        // Every week: Generate transfer offers, update fitness
        $this->generateTransferActivity($game);
        $this->updateSquadFitness($game);
        $this->expireOldOffers($game);
    }

    private function processFirstWeekEvents(Game $game): void
    {
        // Complete any agreed transfers from previous season
        $this->transferService->completeAgreedTransfers($game);
        $this->transferService->completeIncomingTransfers($game);
    }

    private function generateTransferActivity(Game $game): void
    {
        // Generate offers for listed players
        $this->transferService->generateOffersForListedPlayers($game);

        // Generate unsolicited offers for star players
        $this->transferService->generateUnsolicitedOffers($game);

        // Tick scout search progress
        $scoutReport = $this->scoutingService->tickSearch($game);
        if ($scoutReport?->isCompleted()) {
            session()->flash('scout_complete', 'Your scout has finished their search! Check the Scouting tab for results.');
        }
    }

    private function updateSquadFitness(Game $game): void
    {
        // During pre-season, players gradually recover fitness
        // Target: everyone at 90-100% by season start
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        foreach ($players as $player) {
            $currentFitness = $player->fitness;

            // Increase fitness by 5-10 points per week, max 100
            $increase = rand(5, 10);
            $newFitness = min(100, $currentFitness + $increase);

            $player->update(['fitness' => $newFitness]);
        }
    }

    private function expireOldOffers(Game $game): void
    {
        $this->transferService->expireOffers($game);
    }
}
