<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\ReserveTeam\Exceptions\FirstTeamSquadMinimumException;
use App\Modules\ReserveTeam\Services\ReserveTeamService;

class SendDownToReserve
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
            ->firstOrFail();

        $playerName = $player->name ?? '';

        try {
            $this->reserveTeamService->sendDownToReserve($player, $game);
        } catch (FirstTeamSquadMinimumException $e) {
            return redirect()->route('game.squad', $gameId)
                ->with('error', $this->formatBreachMessage($e));
        } catch (\DomainException $e) {
            return redirect()->route('game.squad', $gameId)
                ->with('error', __('messages.send_down_not_allowed'));
        }

        return redirect()->route('game.squad', $gameId)
            ->with('success', __('messages.player_sent_down_to_reserve', ['player' => $playerName]));
    }

    private function formatBreachMessage(FirstTeamSquadMinimumException $e): string
    {
        if ($e->type() === 'too_small') {
            return __('messages.send_down_squad_too_small', ['min' => $e->min()]);
        }

        return __('messages.send_down_position_minimum', [
            'group' => __('squad.' . strtolower($e->group()) . 's'),
            'min'   => $e->min(),
        ]);
    }
}
