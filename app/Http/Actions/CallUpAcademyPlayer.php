<?php

namespace App\Http\Actions;

use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\Transfer\Services\ContractService;
use App\Models\AcademyPlayer;
use App\Models\Game;

class CallUpAcademyPlayer
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $academy = AcademyPlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->firstOrFail();

        $playerName = $academy->name;

        if ($academy->is_on_loan) {
            return redirect()->route('game.squad.academy', $gameId)
                ->with('error', __('messages.academy_player_on_loan'));
        }

        if ($academy->is_called_up) {
            return redirect()->route('game.squad.academy', $gameId)
                ->with('error', __('messages.academy_player_already_called_up'));
        }

        if (ContractService::isSquadFull($game)) {
            return redirect()->route('game.squad.academy', $gameId)
                ->with('error', __('messages.squad_full', ['max' => ContractService::MAX_SQUAD_SIZE]));
        }

        if (! $this->youthAcademyService->canCallUp($game)) {
            return redirect()->route('game.squad.academy', $gameId)
                ->with('error', __('messages.callup_limit_reached'));
        }

        $this->youthAcademyService->callUpToFirstTeam($academy, $game);

        return redirect()->route('game.squad.academy', $gameId)
            ->with('success', __('messages.academy_player_called_up', ['player' => $playerName]));
    }
}
