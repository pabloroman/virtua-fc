<?php

namespace App\Http\Actions;

use App\Modules\Academy\Services\YouthAcademyService;
use App\Models\Game;
use App\Models\GamePlayer;

class SendToAcademy
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $gamePlayer = GamePlayer::with('player')
            ->where('id', $playerId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->firstOrFail();

        $playerName = $gamePlayer->player->name;

        if (! $this->youthAcademyService->canSendToAcademy($gamePlayer, $game)) {
            return redirect()->route('game.squad', $gameId)
                ->with('error', __('messages.cannot_send_to_academy'));
        }

        $this->youthAcademyService->sendToAcademy($gamePlayer, $game);

        return redirect()->route('game.squad.academy', $gameId)
            ->with('success', __('messages.player_sent_to_academy', ['player' => $playerName]));
    }
}
