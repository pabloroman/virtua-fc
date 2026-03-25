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

        $tier = $game->currentInvestment->youth_academy_tier ?? 0;
        $canCallUp = ! $academyPlayer->is_on_loan
            && ! $academyPlayer->is_called_up
            && YouthAcademyService::getCalledUpCount($game) < YouthAcademyService::getMaxCallups($tier)
            && ! \App\Modules\Transfer\Services\ContractService::isSquadFull($game);

        return view('partials.academy-player-detail', [
            'game' => $game,
            'academyPlayer' => $academyPlayer,
            'canCallUp' => $canCallUp,
        ]);
    }
}
