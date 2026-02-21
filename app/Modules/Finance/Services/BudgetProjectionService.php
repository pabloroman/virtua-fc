<?php

namespace App\Modules\Finance\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;

class BudgetProjectionService
{
    /**
     * UEFA and RFEF solidarity funds (€250K)
     */
    private const SOLIDARITY_FUNDS = 100_000_000; // €1M in cents

    /**
     * Minimum transfer budget guaranteed after mandatory infrastructure.
     */
    private const MINIMUM_TRANSFER_BUDGET = 100_000_000; // €1M in cents

    /**
     * Maximum stadium seats used for commercial revenue calculation.
     * Prevents oversized stadiums from generating disproportionate commercial income.
     */
    private const MAX_COMMERCIAL_SEATS = 80_000;

    /**
     * Generate season projections for a game.
     * Called at the start of each season during pre-season.
     */
    public function generateProjections(Game $game): GameFinances
    {
        // Get user's team and league
        $team = $game->team;
        $league = $game->competition;

        // Calculate squad strengths for all teams in the league
        $teamStrengths = $this->calculateLeagueStrengths($game, $league);

        // Get user's projected position
        $projectedPosition = $this->getProjectedPosition($team->id, $teamStrengths);

        // Calculate projected revenues
        $projectedTvRevenue = $this->calculateTvRevenue($projectedPosition, $league);
        $projectedMatchdayRevenue = $this->calculateMatchdayRevenue($team, $game);
        $projectedSolidarityFundsRevenue = ($game->competition->tier > 1) ? self::SOLIDARITY_FUNDS : 0;
        $projectedCommercialRevenue = $this->getBaseCommercialRevenue($game, $team, $league);

        $projectedTotalRevenue = $projectedTvRevenue
            + $projectedMatchdayRevenue
            + $projectedSolidarityFundsRevenue
            + $projectedCommercialRevenue;

        // Calculate projected wages
        $projectedWages = $this->calculateProjectedWages($game);

        // Calculate operating expenses based on club reputation
        $reputation = $team->clubProfile->reputation_level ?? ClubProfile::REPUTATION_MODEST;
        $baseOperatingExpenses = config('finances.operating_expenses.' . $reputation, 700_000_000);
        $tierMultiplier = config('finances.operating_expense_tier_multiplier.' . $league->tier, 1.0);
        $projectedOperatingExpenses = (int) ($baseOperatingExpenses * $tierMultiplier);

        // Calculate projected surplus
        $projectedSurplus = $projectedTotalRevenue - $projectedWages - $projectedOperatingExpenses;

        // Get carried debt from previous season
        $carriedDebt = $this->getCarriedDebt($game);

        // Calculate public subsidy if needed to guarantee minimum viable budget
        $projectedSubsidyRevenue = $this->calculateSubsidy($projectedSurplus, $carriedDebt);
        if ($projectedSubsidyRevenue > 0) {
            $projectedTotalRevenue += $projectedSubsidyRevenue;
            $projectedSurplus += $projectedSubsidyRevenue;
        }

        // Create or update finances record
        $finances = GameFinances::updateOrCreate(
            [
                'game_id' => $game->id,
                'season' => $game->season,
            ],
            [
                'projected_position' => $projectedPosition,
                'projected_tv_revenue' => $projectedTvRevenue,
                'projected_solidarity_funds_revenue' => $projectedSolidarityFundsRevenue,
                'projected_matchday_revenue' => $projectedMatchdayRevenue,
                'projected_commercial_revenue' => $projectedCommercialRevenue,
                'projected_subsidy_revenue' => $projectedSubsidyRevenue,
                'projected_total_revenue' => $projectedTotalRevenue,
                'projected_wages' => $projectedWages,
                'projected_operating_expenses' => $projectedOperatingExpenses,
                'projected_surplus' => $projectedSurplus,
                'carried_debt' => $carriedDebt,
            ]
        );

        return $finances;
    }

    /**
     * Calculate squad strengths for all teams in a league.
     * Returns array of [team_id => strength] sorted by strength descending.
     *
     * Loads all players across all teams in one query to avoid N+1.
     */
    public function calculateLeagueStrengths(Game $game, Competition $league): array
    {
        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $league->id)
            ->pluck('team_id')
            ->toArray();

        $teams = Team::whereIn('id', $teamIds)->get();

        // Load ALL players across all teams in one query, grouped by team
        $playersByTeam = GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->with('player')
            ->get()
            ->groupBy('team_id');

        $strengths = [];
        foreach ($teams as $team) {
            $teamPlayers = $playersByTeam->get($team->id, collect());
            $strengths[$team->id] = $this->calculateStrengthFromPlayers($teamPlayers);
        }

        // Sort by strength descending
        arsort($strengths);

        return $strengths;
    }

    /**
     * Calculate squad strength for a team.
     * Uses average OVR of best 18 players.
     */
    public function calculateSquadStrength(Game $game, Team $team): float
    {
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->with('player')
            ->get();

        return $this->calculateStrengthFromPlayers($players);
    }

    /**
     * Calculate strength from a pre-loaded collection of players.
     * Uses average OVR of best 18 players.
     */
    private function calculateStrengthFromPlayers($players): float
    {
        $scores = $players->map(function ($player) {
                $technical = $player->game_technical_ability ?? $player->player->technical_ability;
                $physical = $player->game_physical_ability ?? $player->player->physical_ability;
                $fitness = $player->fitness ?? 70;
                $morale = $player->morale ?? 70;

                return ($technical + $physical + $fitness + $morale) / 4;
            })
            ->sortDesc()
            ->take(18);

        if ($scores->isEmpty()) {
            return 0;
        }

        return round($scores->avg(), 1);
    }

    /**
     * Get projected position for a team based on strength rankings.
     */
    public function getProjectedPosition(string $teamId, array $teamStrengths): int
    {
        $position = 1;
        foreach ($teamStrengths as $id => $strength) {
            if ($id === $teamId) {
                return $position;
            }
            $position++;
        }

        return $position; // Fallback to last position
    }

    /**
     * Calculate TV revenue based on position and league.
     */
    public function calculateTvRevenue(int $position, Competition $league): int
    {
        $config = $league->getConfig();

        return $config->getTvRevenue($position);
    }

    /**
     * Calculate matchday revenue.
     * Formula: Base (stadium_seats × revenue_per_seat) × Facilities Multiplier
     */
    public function calculateMatchdayRevenue(Team $team, Game $game): int
    {
        $reputation = $team->clubProfile->reputation_level ?? ClubProfile::REPUTATION_MODEST;

        // Base matchday revenue from stadium size and competition config rates
        $base = $team->stadium_seats * config("finances.revenue_per_seat.{$reputation}", 15_000);

        // Get facilities multiplier from current investment (default to Tier 1 = 1.0)
        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        return (int) ($base * $facilitiesMultiplier);
    }

    /**
     * Calculate projected wages for the season.
     */
    public function calculateProjectedWages(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');
    }

    /**
     * Calculate total squad market value.
     */
    public function calculateSquadValue(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('market_value_cents');
    }

    /**
     * Get carried debt from previous season.
     */
    public function getCarriedDebt(Game $game): int
    {
        $previousSeason = (int) $game->season - 1;

        $previousFinances = GameFinances::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        if (!$previousFinances) {
            return 0;
        }

        // Negative variance from previous season becomes carried debt
        if ($previousFinances->variance < 0) {
            return abs($previousFinances->variance);
        }

        return 0;
    }

    /**
     * Get base commercial revenue for budget projections.
     * Season 2+: uses previous season's actual commercial revenue.
     * Season 1: calculates from stadium_seats × config rate.
     */
    private function getBaseCommercialRevenue(Game $game, Team $team, Competition $league): int|float
    {
        // Check for prior season actual commercial revenue
        $previousSeason = (int) $game->season - 1;
        $previousFinances = GameFinances::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        if ($previousFinances && $previousFinances->actual_commercial_revenue > 0) {
            return $previousFinances->actual_commercial_revenue;
        }

        // First season: calculate from stadium seats × config rate (capped)
        $reputation = $team->clubProfile->reputation_level ?? ClubProfile::REPUTATION_MODEST;
        $seats = min($team->stadium_seats, self::MAX_COMMERCIAL_SEATS);

        $base = $seats * config("finances.commercial_per_seat.{$reputation}", 80_000);

        // Reduce commercial revenue for lower tiers
        if ($league->tier > 1) {
            return $base * 0.75;
        }

        return $base;
    }

    /**
     * Calculate public subsidy (Subvenciones Públicas) to guarantee a minimum viable budget.
     * Ensures every team can cover mandatory infrastructure + a minimum transfer budget.
     */
    private function calculateSubsidy(int $projectedSurplus, int $carriedDebt): int
    {
        $minimumAvailable = GameInvestment::MINIMUM_TOTAL_INVESTMENT + self::MINIMUM_TRANSFER_BUDGET;
        $rawAvailable = $projectedSurplus - $carriedDebt;

        if ($rawAvailable >= $minimumAvailable) {
            return 0;
        }

        return $minimumAvailable - $rawAvailable;
    }
}
