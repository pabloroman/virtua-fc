<?php

namespace App\Http\Views;

use App\Game\Services\ScoutingService;
use App\Models\Game;
use App\Models\TransferOffer;

class ShowScouting
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        $report = $this->scoutingService->getActiveReport($game);

        $scoutedPlayers = collect();
        $playerDetails = [];
        $existingOffers = [];

        if ($report && $report->isCompleted()) {
            $scoutedPlayers = $report->players;

            foreach ($scoutedPlayers as $player) {
                $playerDetails[$player->id] = $this->scoutingService->getPlayerScoutingDetail($player, $game);

                $existingOffers[$player->id] = TransferOffer::where('game_id', $gameId)
                    ->where('game_player_id', $player->id)
                    ->where('direction', TransferOffer::DIRECTION_INCOMING)
                    ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
                    ->latest()
                    ->first();
            }
        }

        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();

        return view('scouting', [
            'game' => $game,
            'report' => $report,
            'scoutedPlayers' => $scoutedPlayers,
            'playerDetails' => $playerDetails,
            'existingOffers' => $existingOffers,
            'teamCountry' => $game->team->country,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
        ]);
    }
}
