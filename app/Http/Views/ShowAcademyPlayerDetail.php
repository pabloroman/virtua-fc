<?php

namespace App\Http\Views;

use App\Modules\Academy\Services\YouthAcademyService;
use App\Models\AcademyPlayer;
use App\Models\Game;

class ShowAcademyPlayerDetail
{
    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);

        $academyPlayer = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        $revealPhase = $academyPlayer->seasons_in_academy > 1
            ? 2
            : YouthAcademyService::getRevealPhase($game);

        return view('partials.academy-player-detail', [
            'game' => $game,
            'academyPlayer' => $academyPlayer,
            'revealPhase' => $revealPhase,
        ]);
    }
}
