<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameInvestment;
use Illuminate\Http\Request;

class CompleteOnboarding
{
    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Ensure onboarding is still needed
        if (!$game->needsOnboarding()) {
            return redirect()->route('show-game', $gameId);
        }

        $finances = $game->currentFinances;
        if (!$finances) {
            return redirect()->route('game.onboarding', $gameId)
                ->with('error', 'No financial projections found.');
        }

        $availableSurplus = $finances->available_surplus;

        // Validate request
        $validated = $request->validate([
            'youth_academy' => 'required|integer|min:0',
            'medical' => 'required|integer|min:0',
            'scouting' => 'required|integer|min:0',
            'facilities' => 'required|integer|min:0',
            'transfer_budget' => 'required|integer|min:0',
        ]);

        // Convert from euros to cents (form sends values in euros)
        $youthAcademy = $validated['youth_academy'] * 100;
        $medical = $validated['medical'] * 100;
        $scouting = $validated['scouting'] * 100;
        $facilities = $validated['facilities'] * 100;
        $transferBudget = $validated['transfer_budget'] * 100;

        $total = $youthAcademy + $medical + $scouting + $facilities + $transferBudget;

        // Validate total doesn't exceed available surplus
        if ($total > $availableSurplus) {
            return redirect()->route('game.onboarding', $gameId)
                ->with('error', 'Total allocation exceeds available surplus.');
        }

        // Validate minimum tier requirements
        $youthTier = GameInvestment::calculateTier('youth_academy', $youthAcademy);
        $medicalTier = GameInvestment::calculateTier('medical', $medical);
        $scoutingTier = GameInvestment::calculateTier('scouting', $scouting);
        $facilitiesTier = GameInvestment::calculateTier('facilities', $facilities);

        if ($youthTier < 1 || $medicalTier < 1 || $scoutingTier < 1 || $facilitiesTier < 1) {
            return redirect()->route('game.onboarding', $gameId)
                ->with('error', 'All infrastructure areas must be at least Tier 1.');
        }

        // Create the investment record
        GameInvestment::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'available_surplus' => $availableSurplus,
            'youth_academy_amount' => $youthAcademy,
            'youth_academy_tier' => $youthTier,
            'medical_amount' => $medical,
            'medical_tier' => $medicalTier,
            'scouting_amount' => $scouting,
            'scouting_tier' => $scoutingTier,
            'facilities_amount' => $facilities,
            'facilities_tier' => $facilitiesTier,
            'transfer_budget' => $transferBudget,
        ]);

        // Complete onboarding
        $game->completeOnboarding();

        return redirect()->route('show-game', $gameId)
            ->with('success', 'Welcome to ' . $game->team->name . '! Your season awaits.');
    }
}
