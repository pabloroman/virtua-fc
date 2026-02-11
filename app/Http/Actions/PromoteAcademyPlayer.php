<?php

namespace App\Http\Actions;

use App\Game\Services\YouthAcademyService;
use App\Models\AcademyPlayer;
use App\Models\Game;

class PromoteAcademyPlayer
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);

        $academy = AcademyPlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->firstOrFail();

        $playerName = $academy->name;

        $this->youthAcademyService->promoteToFirstTeam($academy, $game);

        return redirect()->route('game.squad.academy', $gameId)
            ->with('success', __('messages.academy_player_promoted', ['player' => $playerName]));
    }
}
