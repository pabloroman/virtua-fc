<?php

namespace App\Http\Views;

use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Season\Services\SeasonGoalService;
use App\Modules\Stadium\Services\GameStadiumResolver;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TeamReputation;

class ShowNewSeason
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
        private readonly SeasonGoalService $seasonGoalService,
        private readonly GameStadiumResolver $stadiumResolver,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Wait for background setup to finish
        if (!$game->isSetupComplete()) {
            // If stuck for > 2 minutes, re-dispatch the setup job
            if ($game->created_at->lt(now()->subMinutes(2))) {
                $game->redispatchSetupJob();
            }
            $isTournament = $game->isTournamentMode();
            return view('game-loading', [
                'game' => $game,
                'title' => $isTournament ? __('game.preparing_tournament') : __('game.preparing_season'),
                'message' => $isTournament ? __('game.setup_tournament_loading_message') : __('game.setup_loading_message'),
                'showCrest' => true,
            ]);
        }

        // If new-season setup is complete, redirect to main game
        if (!$game->needsNewSeasonSetup()) {
            return redirect()->route('show-game', $gameId);
        }

        // Tournament mode uses squad selection instead of budget allocation
        if ($game->isTournamentMode()) {
            return redirect()->route('game.squad-selection', $gameId);
        }

        // Headline finances + reputation — the only budget figures this screen
        // shows. Infrastructure allocation now lives entirely on the Club
        // investment page, so we no longer load investment tiers here.
        $finances = $game->currentFinances ?? $this->projectionService->generateProjections($game);
        $availableSurplus = $finances->available_surplus ?? 0;
        $reputationLevel = TeamReputation::resolveLevel($game->id, $game->team_id);

        // Season goal
        $competition = Competition::find($game->competition_id);
        $seasonGoal = $game->season_goal;
        $seasonGoalLabel = ($seasonGoal && $competition) ? $this->seasonGoalService->getGoalLabel($seasonGoal, $competition) : null;
        $seasonGoalTarget = ($seasonGoal && $competition) ? $this->seasonGoalService->getTargetPosition($seasonGoal, $competition) : null;

        // Squad headline numbers
        $squad = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();
        $squadNumbers = $this->buildSquadNumbers($squad, $game->current_date);

        // Per-game stadium capacity (overlays Team.stadium_seats once the user
        // has expanded or rebuilt the ground); falls back to the team baseline
        // when no overlay row exists.
        $stadiumCapacity = $this->stadiumResolver->effectiveCapacity(
            $game->id,
            $game->team_id,
            (int) ($game->team?->stadium_seats ?? 0),
        );

        $stadiumName = $this->stadiumResolver->effectiveName(
            $game->id,
            $game->team_id,
            $game->team?->stadium_name,
        );

        return view('new-season', [
            'game' => $game,
            'reputationLevel' => $reputationLevel,
            'availableSurplus' => $availableSurplus,
            'seasonGoalLabel' => $seasonGoalLabel,
            'seasonGoalTarget' => $seasonGoalTarget,
            'squad' => $squadNumbers,
            'stadiumName' => $stadiumName,
            'stadiumCapacity' => $stadiumCapacity,
        ]);
    }

    /**
     * Headline squad figures shown on the "team in numbers" briefing.
     *
     * @return array{total_players:int, avg_overall:int, avg_age:float}
     */
    private function buildSquadNumbers($squad, $currentDate): array
    {
        $totalPlayers = $squad->count();

        return [
            'total_players' => $totalPlayers,
            'avg_overall' => $totalPlayers > 0 ? (int) round($squad->avg(fn ($p) => $p->effective_rating)) : 0,
            'avg_age' => $totalPlayers > 0 ? round($squad->avg(fn ($p) => $p->age($currentDate)), 1) : 0,
        ];
    }
}
