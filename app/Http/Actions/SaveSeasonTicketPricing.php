<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Save the user's season ticket pricing preset. Locked once the first
 * competitive league match has been played; the service re-checks the lock as
 * defence-in-depth in case the user submits a stale form. After persisting,
 * the budget projection is refreshed so the new season-ticket and walk-up
 * matchday revenue land on the finances row.
 */
class SaveSeasonTicketPricing
{
    public function __construct(
        private readonly SeasonTicketPricingService $pricingService,
        private readonly BudgetProjectionService $budgetProjectionService,
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
            'preset' => ['required', 'string', Rule::in(array_keys($this->pricingService->presets()))],
        ]);

        try {
            $this->pricingService->apply($game, $validated['preset']);
        } catch (\DomainException) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __('messages.season_tickets_locked'));
        }

        $this->budgetProjectionService->refreshTicketingProjection($game);

        return redirect()->route('game.club.stadium', $gameId)
            ->with('success', __('messages.season_tickets_saved'));
    }
}
