<?php

namespace App\Http\Views;

use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Season\Services\SeasonGoalService;
use App\Modules\Season\Jobs\SetupNewGame;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameInvestment;

class ShowOnboarding
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
        private readonly SeasonGoalService $seasonGoalService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Wait for background setup to finish
        if (!$game->isSetupComplete()) {
            // If stuck for > 2 minutes, re-dispatch the setup job
            if ($game->created_at->lt(now()->subMinutes(2))) {
                SetupNewGame::dispatch(
                    gameId: $game->id,
                    teamId: $game->team_id,
                    competitionId: $game->competition_id,
                    season: $game->season,
                    gameMode: $game->game_mode ?? Game::MODE_CAREER,
                );
            }
            return view('game-setup-loading', ['game' => $game]);
        }

        // If onboarding is complete, redirect to main game
        if (!$game->needsOnboarding()) {
            return redirect()->route('show-game', $gameId);
        }

        // Tournament mode uses squad selection instead of budget allocation
        if ($game->isTournamentMode()) {
            return redirect()->route('game.squad-selection', $gameId);
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

        // Get season goal data
        $competition = Competition::find($game->competition_id);
        $seasonGoal = $game->season_goal;
        $seasonGoalLabel = ($seasonGoal && $competition) ? $this->seasonGoalService->getGoalLabel($seasonGoal, $competition) : null;
        $seasonGoalTarget = ($seasonGoal && $competition) ? $this->seasonGoalService->getTargetPosition($seasonGoal, $competition) : null;

        // Get all competitions the team plays this season
        $competitionIds = CompetitionEntry::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->pluck('competition_id');

        $competitions = Competition::whereIn('id', $competitionIds)
            ->get()
            ->sortBy(fn ($c) => match (true) {
                $c->scope === Competition::SCOPE_DOMESTIC && $c->type === 'league' => 0,
                $c->scope === Competition::SCOPE_DOMESTIC && $c->type === 'cup' => 1,
                $c->scope === Competition::SCOPE_CONTINENTAL => 2,
                default => 3,
            })
            ->values();

        return view('onboarding', [
            'game' => $game,
            'finances' => $finances,
            'investment' => $investment,
            'availableSurplus' => $availableSurplus,
            'tiers' => $tiers,
            'tierThresholds' => GameInvestment::TIER_THRESHOLDS,
            'seasonGoal' => $seasonGoal,
            'seasonGoalLabel' => $seasonGoalLabel,
            'seasonGoalTarget' => $seasonGoalTarget,
            'competitions' => $competitions,
        ]);
    }
}
