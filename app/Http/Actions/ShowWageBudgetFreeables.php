<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\WageBudgetService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Return a ranked list of user-owned players whose wages would free up
 * cap space. Used by the "free space first" modal when a signing was
 * rejected by the wage-cap gate.
 */
class ShowWageBudgetFreeables
{
    public function __construct(
        private readonly WageBudgetService $wageBudgetService,
    ) {}

    public function __invoke(Request $request, string $gameId): JsonResponse
    {
        $validated = $request->validate([
            'shortfall' => ['nullable', 'integer', 'min:0'],
        ]);

        $game = Game::findOrFail($gameId);
        $shortfallCents = (int) ($validated['shortfall'] ?? 0);

        $players = $this->wageBudgetService->freeableWages($game, $shortfallCents);

        $rows = $players->map(fn ($player) => [
            'id' => $player->id,
            'name' => $player->name,
            'position' => $player->position,
            'team' => $player->team?->name,
            'annual_wage' => (int) $player->annual_wage,
            'annual_wage_formatted' => Money::format((int) $player->annual_wage),
            'release_url' => route('game.squad', $game->id) . '#player-' . $player->id,
            'list_url' => route('game.transfers', $game->id) . '#player-' . $player->id,
        ])->values();

        return response()->json([
            'status' => 'ok',
            'shortfall_cents' => $shortfallCents,
            'shortfall_formatted' => Money::format($shortfallCents),
            'players' => $rows,
        ]);
    }
}
