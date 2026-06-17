<?php

namespace App\Http\Views;

use App\Modules\Finance\Services\BudgetAllocationService;
use App\Modules\Season\Services\SeasonGoalService;
use App\Modules\Stadium\Services\GameStadiumResolver;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\SeasonArchive;
use App\Support\PositionMapper;

class ShowNewSeason
{
    public function __construct(
        private readonly BudgetAllocationService $budgetService,
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

        $budgetData = $this->budgetService->prepareBudgetData($game);
        $reputationLevel = $budgetData['reputationLevel'];

        // Read-only "board's starting plan" summary: the default allocation
        // applied during season setup. Editing now lives on the Club investment
        // page, so this screen only shows the starting position.
        $startingPlan = [];
        $infraTotal = 0;
        foreach (['youth_academy', 'medical', 'scouting', 'facilities'] as $area) {
            $tier = $budgetData['tiers'][$area];
            $amount = $budgetData['tierThresholds'][$area][$tier] ?? 0;
            $infraTotal += $amount;
            $startingPlan[] = ['key' => $area, 'tier' => $tier, 'amount' => $amount];
        }
        $startingTransferBudget = max(0, $budgetData['availableSurplus'] - $infraTotal);

        // Get season goal data
        $competition = Competition::find($game->competition_id);
        $seasonGoal = $game->season_goal;
        $seasonGoalLabel = ($seasonGoal && $competition) ? $this->seasonGoalService->getGoalLabel($seasonGoal, $competition) : null;
        $seasonGoalTarget = ($seasonGoal && $competition) ? $this->seasonGoalService->getTargetPosition($seasonGoal, $competition) : null;

        // Load squad data for snapshot
        $squad = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            
            ->get();

        $squadSnapshot = $this->buildSquadSnapshot($squad, $game->current_date);

        // Off-season recap (season 2+ only)
        $offseasonRecap = null;
        if ($game->season > 1) {
            $offseasonRecap = $this->buildOffseasonRecap($game, $squad, $reputationLevel);
        }

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
            ...$budgetData,
            'game' => $game,
            'stadiumName' => $stadiumName,
            'seasonGoal' => $seasonGoal,
            'seasonGoalLabel' => $seasonGoalLabel,
            'seasonGoalTarget' => $seasonGoalTarget,
            'squadSnapshot' => $squadSnapshot,
            'offseasonRecap' => $offseasonRecap,
            'stadiumCapacity' => $stadiumCapacity,
            'startingPlan' => $startingPlan,
            'startingTransferBudget' => $startingTransferBudget,
        ]);
    }

    private function buildSquadSnapshot($squad, $currentDate): array
    {
        $positionGroups = $squad->groupBy(fn ($p) => PositionMapper::getPositionGroup($p->position));

        $positionCoverage = [];
        foreach (PositionMapper::getAllGroups() as $group) {
            $players = $positionGroups->get($group, collect());
            $count = $players->count();
            $avgAbility = $count > 0 ? (int) round($players->avg(fn ($p) => $p->effective_rating)) : 0;

            // Determine coverage status
            $status = match ($group) {
                'Goalkeeper' => $count >= 2 ? 'adequate' : ($count >= 1 ? 'thin' : 'critical'),
                'Defender' => $count >= 4 ? 'adequate' : ($count >= 3 ? 'thin' : 'critical'),
                'Midfielder' => $count >= 4 ? 'adequate' : ($count >= 3 ? 'thin' : 'critical'),
                'Forward' => $count >= 2 ? 'adequate' : ($count >= 1 ? 'thin' : 'critical'),
                default => 'adequate',
            };

            $positionCoverage[$group] = [
                'count' => $count,
                'avg_ability' => $avgAbility,
                'status' => $status,
            ];
        }

        $totalPlayers = $squad->count();
        $avgOverall = $totalPlayers > 0 ? (int) round($squad->avg(fn ($p) => $p->effective_rating)) : 0;
        $avgAge = $totalPlayers > 0 ? round($squad->avg(fn ($p) => $p->age($currentDate)), 1) : 0;
        $totalWages = $squad->sum('annual_wage');

        // Detect concerns
        $concerns = [];
        $gkCount = $positionCoverage['Goalkeeper']['count'];
        if ($gkCount < 2) {
            $concerns[] = trans_choice('game.concern_low_goalkeepers', $gkCount, ['count' => $gkCount]);
        }
        if ($positionCoverage['Defender']['count'] < 4) {
            $count = $positionCoverage['Defender']['count'];
            $concerns[] = trans_choice('game.concern_low_defenders', $count, ['count' => $count]);
        }
        if ($positionCoverage['Forward']['count'] < 2) {
            $count = $positionCoverage['Forward']['count'];
            $concerns[] = trans_choice('game.concern_low_forwards', $count, ['count' => $count]);
        }
        if ($avgAge >= 30) {
            $concerns[] = __('game.concern_aging_squad', ['age' => number_format($avgAge, 1)]);
        }
        if ($totalPlayers < 20) {
            $concerns[] = __('game.concern_small_squad', ['count' => $totalPlayers]);
        }

        return [
            'total_players' => $totalPlayers,
            'avg_overall' => $avgOverall,
            'avg_age' => $avgAge,
            'total_wages' => $totalWages,
            'position_coverage' => $positionCoverage,
            'concerns' => $concerns,
        ];
    }

    private function buildOffseasonRecap(Game $game, $currentSquad, string $currentReputationLevel): ?array
    {
        $previousSeason = $game->season - 1;

        $archive = SeasonArchive::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        if (!$archive) {
            return null;
        }

        // Pro-manager team switch: the archive only stores stats for the team
        // the user was managing last season. After a switch, $game->team_id is
        // the new club, so a departures/arrivals diff against the archive is
        // meaningless (every current player looks like an arrival). Skip.
        $archivedTeamIds = collect($archive->player_season_stats ?? [])
            ->pluck('team_id')
            ->unique();

        if ($archivedTeamIds->isNotEmpty() && !$archivedTeamIds->contains($game->team_id)) {
            return null;
        }

        // Get player IDs from previous season's archived stats for the user's team
        $previousPlayerIds = collect($archive->player_season_stats ?? [])
            ->where('team_id', $game->team_id)
            ->pluck('reference_player_id')
            ->filter()
            ->toArray();

        $currentPlayerIds = $currentSquad->pluck('player_id')->toArray();

        // Departures: were in previous season but not in current squad
        $departedPlayerIds = array_diff($previousPlayerIds, $currentPlayerIds);
        $departures = collect($archive->player_season_stats ?? [])
            ->where('team_id', $game->team_id)
            ->filter(fn ($stat) => in_array($stat['reference_player_id'] ?? null, $departedPlayerIds))
            ->map(fn ($stat) => [
                'name' => $stat['name'],
                'position' => $stat['position'],
            ])
            ->values()
            ->toArray();

        // Arrivals: in current squad but not in previous season
        $arrivedPlayerIds = array_diff($currentPlayerIds, $previousPlayerIds);
        $arrivals = $currentSquad
            ->filter(fn ($p) => in_array($p->player_id, $arrivedPlayerIds))
            ->map(fn ($p) => [
                'name' => $p->name,
                'position' => $p->position,
            ])
            ->values()
            ->toArray();

        // Previous reputation level (from archive's transfer_activity metadata or TeamReputation history)
        // We can derive from the archive final_standings — check if there's a stored reputation
        // For simplicity, we'll just compare with current level
        $previousReputation = $archive->transfer_activity['previous_reputation'] ?? null;

        return [
            'departures' => $departures,
            'arrivals' => $arrivals,
            'previous_reputation' => $previousReputation,
            'current_reputation' => $currentReputationLevel,
            'reputation_changed' => $previousReputation !== null && $previousReputation !== $currentReputationLevel,
        ];
    }
}
