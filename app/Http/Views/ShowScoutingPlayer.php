<?php

namespace App\Http\Views;

use App\Game\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\TransferOffer;

class ShowScoutingPlayer
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        $player = GamePlayer::with(['player', 'team'])->findOrFail($playerId);

        // Verify player is in scout results
        $report = $this->scoutingService->getActiveReport($game);
        if (!$report || !$report->isCompleted() || !in_array($playerId, $report->player_ids ?? [])) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', 'Player not found in scout results.');
        }

        $detail = $this->scoutingService->getPlayerScoutingDetail($player, $game);

        // Check for existing offers on this player from user
        $existingOffer = TransferOffer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
            ->latest()
            ->first();

        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();

        return view('scouting-player', [
            'game' => $game,
            'player' => $player,
            'detail' => $detail,
            'existingOffer' => $existingOffer,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
        ]);
    }
}
