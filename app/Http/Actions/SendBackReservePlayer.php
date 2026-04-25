<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\ReserveTeam\Services\ReserveTeamService;

class SendBackReservePlayer
{
    public function __construct(
        private readonly ReserveTeamService $reserveTeamService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->reserve_team_id === null, 404);

        $player = GamePlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->whereHas('activeLoan', fn ($q) => $q->where('parent_team_id', $game->reserve_team_id))
            ->with('player')
            ->firstOrFail();

        $playerName = $player->player->name ?? '';

        $this->reserveTeamService->sendBackToReserve($player, $game);

        return redirect()->route('game.squad.reserve', $gameId)
            ->with('success', __('messages.reserve_player_sent_back', ['player' => $playerName]));
    }
}
