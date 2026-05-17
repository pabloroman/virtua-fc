<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Exceptions\SquadMinimumException;
use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ListPlayerForTransfer
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->whereIn('team_id', $game->userTeamIds())
            ->firstOrFail();

        if (! $player->isUserOwned($game)) {
            abort(403, 'Cannot list a loaned player for transfer.');
        }

        if ($player->isInSaleCooldown($game)) {
            return redirect()->back()->with('error', __('messages.cannot_sell_same_window', [
                'player' => $player->name,
            ]));
        }

        try {
            $this->transferService->listPlayer($player);
        } catch (SquadMinimumException $e) {
            return redirect()->back()->with('error', $this->formatBreachMessage($e));
        }

        return redirect()
            ->back()
            ->with('success', __('messages.player_listed', ['player' => $player->name]));
    }

    private function formatBreachMessage(SquadMinimumException $e): string
    {
        if ($e->type() === 'too_small') {
            return __('messages.list_for_sale_squad_too_small', ['min' => $e->min()]);
        }

        return __('messages.list_for_sale_position_minimum', [
            'group' => __('squad.' . strtolower($e->group()) . 's'),
            'min'   => $e->min(),
        ]);
    }
}
