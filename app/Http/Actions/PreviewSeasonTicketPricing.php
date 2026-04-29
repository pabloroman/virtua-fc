<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoint feeding the live fill-rate preview on the Stadium page.
 * Runs the same prediction logic as the persistence path so what the user
 * sees while dragging prices matches exactly what gets saved.
 */
class PreviewSeasonTicketPricing
{
    public function __construct(
        private readonly SeasonTicketPricingService $pricingService,
    ) {}

    public function __invoke(Request $request, string $gameId): JsonResponse
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.*' => 'required|integer|min:0',
        ]);

        $prediction = $this->pricingService->predict($game, $game->team, $validated['prices']);

        return response()->json($prediction);
    }
}
