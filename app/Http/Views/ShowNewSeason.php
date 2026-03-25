<?php

namespace App\Http\Views;

use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Season\Services\SeasonGoalService;
use App\Modules\Season\Jobs\SetupNewGame;
use App\Modules\Season\Jobs\SetupTournamentGame;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\SeasonArchive;
use App\Models\TeamReputation;
use App\Support\PositionMapper;

class ShowNewSeason
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
                if ($game->isTournamentMode()) {
                    SetupTournamentGame::dispatch(
                        gameId: $game->id,
                        teamId: $game->team_id,
                    );
                } else {
                    SetupNewGame::dispatch(
                        gameId: $game->id,
                        teamId: $game->team_id,
                        competitionId: $game->competition_id,
                        season: $game->season,
                        gameMode: $game->game_mode ?? Game::MODE_CAREER,
                    );
                }
            }
            return view('game-loading', [
                'game' => $game,
                'title' => __('game.preparing_season'),
                'message' => __('game.setup_loading_message'),
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

        // Ensure we have financial projections
        $finances = $game->currentFinances;
        if (!$finances) {
            $finances = $this->projectionService->generateProjections($game);
        }

        $investment = $game->currentInvestment;
        $availableSurplus = $finances->available_surplus ?? 0;

        // Get current tiers (0-4 for each area), default based on club reputation
        $reputationLevel = TeamReputation::resolveLevel($game->id, $game->team_id);
        $tiers = $investment ? [
            'youth_academy' => $investment->youth_academy_tier,
            'medical' => $investment->medical_tier,
            'scouting' => $investment->scouting_tier,
            'facilities' => $investment->facilities_tier,
        ] : GameInvestment::defaultTiersForReputation(
            $reputationLevel,
            $availableSurplus,
        );

        // Get season goal data
        $competition = Competition::find($game->competition_id);
        $seasonGoal = $game->season_goal;
        $seasonGoalLabel = ($seasonGoal && $competition) ? $this->seasonGoalService->getGoalLabel($seasonGoal, $competition) : null;
        $seasonGoalTarget = ($seasonGoal && $competition) ? $this->seasonGoalService->getTargetPosition($seasonGoal, $competition) : null;

        // Load squad data for snapshot
        $squad = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->with('player')
            ->get();

        $squadSnapshot = $this->buildSquadSnapshot($squad, $game->current_date);

        // Off-season recap (season 2+ only)
        $offseasonRecap = null;
        if ($game->season > 1) {
            $offseasonRecap = $this->buildOffseasonRecap($game, $squad, $reputationLevel);
        }

        return view('new-season', [
            'game' => $game,
            'finances' => $finances,
            'investment' => $investment,
            'availableSurplus' => $availableSurplus,
            'tiers' => $tiers,
            'tierThresholds' => GameInvestment::TIER_THRESHOLDS,
            'seasonGoal' => $seasonGoal,
            'seasonGoalLabel' => $seasonGoalLabel,
            'seasonGoalTarget' => $seasonGoalTarget,
            'reputationLevel' => $reputationLevel,
            'squadSnapshot' => $squadSnapshot,
            'offseasonRecap' => $offseasonRecap,
        ]);
    }

    private function buildSquadSnapshot($squad, $currentDate): array
    {
        $positionGroups = $squad->groupBy(fn ($p) => PositionMapper::getPositionGroup($p->position));

        $positionCoverage = [];
        foreach (PositionMapper::getAllGroups() as $group) {
            $players = $positionGroups->get($group, collect());
            $count = $players->count();
            $avgAbility = $count > 0 ? (int) round($players->avg('overall_score')) : 0;

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
        $avgOverall = $totalPlayers > 0 ? (int) round($squad->avg('overall_score')) : 0;
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
