<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\ReserveTeam\Exceptions\FirstTeamSquadFullException;
use App\Modules\ReserveTeam\Exceptions\ReserveSquadMinimumException;
use App\Modules\ReserveTeam\Services\ReserveTeamService;

class CallUpReservePlayer
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
            ->where('team_id', $game->reserve_team_id)
            ->with('player')
            ->firstOrFail();

        $playerName = $player->name ?? '';

        try {
            $this->reserveTeamService->callUpToFirstTeam($player, $game);
        } catch (FirstTeamSquadFullException $e) {
            return redirect()->route('game.squad.reserve', $gameId)
                ->with('error', __('messages.reserve_player_call_up_blocked_full'));
        } catch (ReserveSquadMinimumException $e) {
            return redirect()->route('game.squad.reserve', $gameId)
                ->with('error', $this->formatBreachMessage($e));
        }

        return redirect()->route('game.squad.reserve', $gameId)
            ->with('success', __('messages.reserve_player_called_up', ['player' => $playerName]));
    }

    private function formatBreachMessage(ReserveSquadMinimumException $e): string
    {
        if ($e->type() === 'too_small') {
            return __('messages.promote_squad_too_small', ['min' => $e->min()]);
        }

        return __('messages.promote_position_minimum', [
            'group' => __('squad.' . strtolower($e->group()) . 's'),
            'min'   => $e->min(),
        ]);
    }
}
