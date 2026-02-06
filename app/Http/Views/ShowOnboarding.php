<?php

namespace App\Http\Views;

use App\Game\Services\BudgetProjectionService;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;

class ShowOnboarding
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // If onboarding is complete, redirect to main game
        if (!$game->needsOnboarding()) {
            return redirect()->route('show-game', $gameId);
        }

        // Ensure we have financial projections
        $finances = $game->currentFinances;
        if (!$finances) {
            $finances = $this->projectionService->generateProjections($game);
        }

        $investment = $game->currentInvestment;
        $availableSurplus = $finances->available_surplus ?? 0;

        // Get current tiers (0-4 for each area), default to Tier 1
        $tiers = $investment ? [
            'youth_academy' => $investment->youth_academy_tier,
            'medical' => $investment->medical_tier,
            'scouting' => $investment->scouting_tier,
            'facilities' => $investment->facilities_tier,
        ] : [
            'youth_academy' => 1,
            'medical' => 1,
            'scouting' => 1,
            'facilities' => 1,
        ];

        // Get squad
        $squad = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get();

        // Key players - top 3 by overall ability
        $keyPlayers = $squad->sortByDesc(function ($player) {
            return ($player->game_technical_ability + $player->game_physical_ability) / 2;
        })->take(3)->values();

        // Squad stats
        $squadValue = $squad->sum('market_value_cents');
        $wageBill = $squad->sum('annual_wage');
        $averageAge = $squad->count() > 0 ? round($squad->avg('age'), 1) : 0;

        // Get next match info
        $nextMatch = $game->next_match;
        if ($nextMatch) {
            $nextMatch->load(['homeTeam', 'awayTeam', 'competition']);
        }

        // Determine if home or away for first match
        $isHomeMatch = $nextMatch && $nextMatch->home_team_id === $game->team_id;
        $opponent = $nextMatch ? ($isHomeMatch ? $nextMatch->awayTeam : $nextMatch->homeTeam) : null;

        return view('onboarding', [
            'game' => $game,
            'finances' => $finances,
            'investment' => $investment,
            'availableSurplus' => $availableSurplus,
            'tiers' => $tiers,
            'tierThresholds' => GameInvestment::TIER_THRESHOLDS,
            'keyPlayers' => $keyPlayers,
            'squadSize' => $squad->count(),
            'squadValue' => $squadValue,
            'wageBill' => $wageBill,
            'averageAge' => $averageAge,
            'nextMatch' => $nextMatch,
            'isHomeMatch' => $isHomeMatch,
            'opponent' => $opponent,
        ]);
    }
}
