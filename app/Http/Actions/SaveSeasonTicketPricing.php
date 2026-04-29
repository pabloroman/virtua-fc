<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use Illuminate\Http\Request;

/**
 * Save the user's per-area season ticket prices. Locked once the first
 * competitive league match has been played; the service re-checks the
 * lock as defence-in-depth in case the user submits a stale form.
 */
class SaveSeasonTicketPricing
{
    public function __construct(
        private readonly SeasonTicketPricingService $pricingService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        if (! $this->pricingService->canEdit($game)) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __('messages.season_tickets_locked'));
        }

        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.*' => 'required|integer|min:0',
        ]);

        try {
            $this->pricingService->apply($game, $validated['prices']);
        } catch (\DomainException) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __('messages.season_tickets_locked'));
        }

        return redirect()->route('game.club.stadium', $gameId)
            ->with('success', __('messages.season_tickets_saved'));
    }
}
