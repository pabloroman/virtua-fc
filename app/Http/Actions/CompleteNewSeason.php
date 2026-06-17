<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Modules\Finance\Services\BudgetAllocationService;
use App\Modules\Season\Services\ActivationTracker;
use Illuminate\Http\Request;

class CompleteNewSeason
{
    public function __construct(
        private BudgetAllocationService $budgetService,
        private ActivationTracker $activationTracker,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // The soft "board's starting plan" screen is shown once; if it's already
        // been dismissed just bounce to the dashboard.
        if (!$game->needsNewSeasonSetup()) {
            return redirect()->route('show-game', $gameId);
        }

        // The new-season screen still lets the manager set an allocation (until
        // the Club investment page takes over editing). Honour an explicit
        // submission — it overrides the default applied during season setup.
        // When that screen goes read-only it posts no amounts and this no-ops,
        // falling back to the auto-applied default (or applying one for saves
        // created before DefaultInvestmentProcessor existed).
        if ($request->filled(['youth_academy', 'medical', 'scouting', 'facilities', 'transfer_budget'])) {
            $validated = $request->validate([
                'youth_academy' => 'required|numeric|min:0',
                'medical' => 'required|numeric|min:0',
                'scouting' => 'required|numeric|min:0',
                'facilities' => 'required|numeric|min:0',
                'transfer_budget' => 'required|numeric|min:0',
            ]);

            try {
                $this->budgetService->allocate($game, $validated);
            } catch (\InvalidArgumentException $e) {
                return redirect()->route('game.new-season', $gameId)
                    ->with('error', __($e->getMessage()));
            }
        } elseif (!$game->currentInvestment) {
            $this->budgetService->applyDefaultAllocation($game);
        }

        $game->completeNewSeasonSetup();

        $this->activationTracker->record($game->user_id, ActivationEvent::EVENT_ONBOARDING_COMPLETED, $gameId, $game->game_mode);

        return redirect()->route('show-game', $gameId)
            ->with('success', __('messages.welcome_to_team', ['team_a' => $game->team->nameWithA()]));
    }
}
