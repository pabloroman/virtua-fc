<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\ReserveTeam\Exceptions\FirstTeamSquadMinimumException;
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

        $playerName = $player->name ?? '';

        try {
            $this->reserveTeamService->sendBackToReserve($player, $game);
        } catch (FirstTeamSquadMinimumException $e) {
            return redirect()->route('game.squad.reserve', $gameId)
                ->with('error', $this->formatBreachMessage($e));
        }

        return redirect()->route('game.squad.reserve', $gameId)
            ->with('success', __('messages.reserve_player_sent_back', ['player' => $playerName]));
    }

    private function formatBreachMessage(FirstTeamSquadMinimumException $e): string
    {
        if ($e->type() === 'too_small') {
            return __('messages.demote_squad_too_small', ['min' => $e->min()]);
        }

        return __('messages.demote_position_minimum', [
            'group' => __('squad.' . strtolower($e->group()) . 's'),
            'min'   => $e->min(),
        ]);
    }
}
