<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Finance\Services\SalaryCapService;
use App\Modules\Stadium\Services\NamingRightsService;

/**
 * Commercial hub: where the manager proactively seeks naming-rights
 * sponsors. Reads the commercial panel from NamingRightsService and layers
 * on the salary-cap impact of each offer — "+€X wage room" — so the lever's
 * whole point (more recurring revenue → a higher wage ceiling) is visible at
 * the point of decision. The cap math lives here (HTTP layer) because the
 * Stadium module must not depend on Finance's SalaryCapService.
 */
class ShowClubCommercial
{
    public function __construct(
        private readonly NamingRightsService $namingRightsService,
        private readonly SalaryCapService $salaryCapService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $panel = $this->namingRightsService->buildCommercialPanel($game)['namingRights'];

        // Each offer's realised income maps to a wage-ceiling lift via the cap
        // ratio — the honest "this is what it buys you" number.
        $panel['offers'] = array_map(function (array $offer) use ($game) {
            $offer['cap_delta_cents'] = $this->salaryCapService->capDelta($game, $offer['estimated_annual_cents']);

            return $offer;
        }, $panel['offers']);

        if ($panel['activeDeal'] !== null) {
            $panel['activeDeal']['cap_delta_cents'] = $this->salaryCapService->capDelta(
                $game,
                $panel['activeDeal']['estimated_annual_cents'],
            );
        }

        $panel['seek']['feeAffordable'] =
            $panel['seek']['feeCents'] <= $panel['seek']['availableCashCents'];

        return view('club.commercial', [
            'game' => $game,
            'namingRights' => $panel,
            'capCents' => $this->salaryCapService->cap($game),
            'capRemainingCents' => $this->salaryCapService->remainingRoom($game),
        ]);
    }
}
