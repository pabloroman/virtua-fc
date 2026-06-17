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

        // The default allocation is applied during season setup
        // (DefaultInvestmentProcessor) and adjusted on the Club investment page.
        // This is a safety net for games saved before that processor existed,
        // so a transfer budget always exists before the season proceeds.
        if (!$game->currentInvestment) {
            $this->budgetService->applyDefaultAllocation($game);
        }

        $game->completeNewSeasonSetup();

        $this->activationTracker->record($game->user_id, ActivationEvent::EVENT_ONBOARDING_COMPLETED, $gameId, $game->game_mode);

        return redirect()->route('show-game', $gameId)
            ->with('success', __('messages.welcome_to_team', ['team_a' => $game->team->nameWithA()]));
    }
}
