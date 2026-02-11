<?php

namespace App\Http\Views;

use App\Game\Services\ScoutingService;
use App\Models\Game;
use App\Models\ScoutReport;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class ShowScouting
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        $searchingReport = $this->scoutingService->getActiveReport($game);
        $searchHistory = $this->scoutingService->getSearchHistory($game);

        // Determine what to show based on query params
        $showForm = false;
        $selectedReport = null;

        if ($request->has('new')) {
            // Force-show the search form
            $showForm = true;
        } elseif ($request->has('report')) {
            // View a specific completed report
            $selectedReport = ScoutReport::where('game_id', $game->id)
                ->where('id', $request->query('report'))
                ->where('status', ScoutReport::STATUS_COMPLETED)
                ->first();
            // Fall back to form if report not found
            if (!$selectedReport) {
                $showForm = true;
            }
        } elseif ($searchingReport) {
            // Active search in progress — show progress (no form, no selected report)
        } elseif ($searchHistory->isNotEmpty()) {
            // Auto-show latest completed report
            $selectedReport = $searchHistory->first();
        } else {
            // No history, no searching — show form
            $showForm = true;
        }

        // Load player data for the selected report
        $scoutedPlayers = collect();
        $playerDetails = [];
        $existingOffers = [];

        if ($selectedReport && $selectedReport->isCompleted()) {
            $scoutedPlayers = $selectedReport->players;

            foreach ($scoutedPlayers as $player) {
                $playerDetails[$player->id] = $this->scoutingService->getPlayerScoutingDetail($player, $game);

                $existingOffers[$player->id] = TransferOffer::where('game_id', $gameId)
                    ->where('game_player_id', $player->id)
                    ->where('direction', TransferOffer::DIRECTION_INCOMING)
                    ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
                    ->orderByDesc('game_date')
                    ->first();
            }
        }

        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();
        $isPreContractPeriod = $game->isPreContractPeriod();
        $seasonEndDate = $game->getSeasonEndDate();

        return view('scouting', [
            'game' => $game,
            'searchingReport' => $searchingReport,
            'selectedReport' => $selectedReport,
            'showForm' => $showForm,
            'searchHistory' => $searchHistory,
            'scoutedPlayers' => $scoutedPlayers,
            'playerDetails' => $playerDetails,
            'existingOffers' => $existingOffers,
            'teamCountry' => $game->team->country,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
            'isPreContractPeriod' => $isPreContractPeriod,
            'seasonEndDate' => $seasonEndDate,
        ]);
    }
}
