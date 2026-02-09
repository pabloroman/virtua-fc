<?php

namespace App\Game\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;

class BudgetProjectionService
{
    /**
     * Conservative prize money estimate (Round of 16 cup exit).
     * Includes: Round of 64 (€100K) + Round of 32 (€250K) + Round of 16 (€500K)
     */
    private const PROJECTED_PRIZE_MONEY = 85_000_000; // €850K in cents

    /**
     * Generate season projections for a game.
     * Called at the start of each season during pre-season.
     */
    public function generateProjections(Game $game): GameFinances
    {
        // Get user's team and league
        $team = $game->team;
        $league = $team->competitions()->where('type', 'league')->first();

        if (!$league) {
            throw new \RuntimeException('Team has no league competition');
        }

        // Calculate squad strengths for all teams in the league
        $teamStrengths = $this->calculateLeagueStrengths($game, $league);

        // Get user's projected position
        $projectedPosition = $this->getProjectedPosition($team->id, $teamStrengths);

        // Calculate projected revenues
        $projectedTvRevenue = $this->calculateTvRevenue($projectedPosition, $league);
        $projectedMatchdayRevenue = $this->calculateMatchdayRevenue($team, $projectedPosition, $game);
        $projectedPrizeRevenue = self::PROJECTED_PRIZE_MONEY;
        $projectedCommercialRevenue = $this->getBaseCommercialRevenue($game, $team, $league);

        $projectedTotalRevenue = $projectedTvRevenue
            + $projectedMatchdayRevenue
            + $projectedPrizeRevenue
            + $projectedCommercialRevenue;

        // Calculate projected wages
        $projectedWages = $this->calculateProjectedWages($game);

        // Calculate operating expenses based on club reputation
        $reputation = $team->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_MODEST;
        $projectedOperatingExpenses = config('finances.operating_expenses.' . $reputation, 700_000_000);

        // Calculate projected surplus
        $projectedSurplus = $projectedTotalRevenue - $projectedWages - $projectedOperatingExpenses;

        // Get carried debt from previous season
        $carriedDebt = $this->getCarriedDebt($game);

        // Create or update finances record
        $finances = GameFinances::updateOrCreate(
            [
                'game_id' => $game->id,
                'season' => $game->season,
            ],
            [
                'projected_position' => $projectedPosition,
                'projected_tv_revenue' => $projectedTvRevenue,
                'projected_prize_revenue' => $projectedPrizeRevenue,
                'projected_matchday_revenue' => $projectedMatchdayRevenue,
                'projected_commercial_revenue' => $projectedCommercialRevenue,
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
     */
    public function calculateLeagueStrengths(Game $game, Competition $league): array
    {
        $teams = $league->teams;
        $strengths = [];

        foreach ($teams as $team) {
            $strengths[$team->id] = $this->calculateSquadStrength($game, $team);
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
            ->get()
            ->map(function ($player) {
                // Calculate overall as average of technical + physical + fitness + morale
                $technical = $player->game_technical_ability ?? $player->player->technical_ability;
                $physical = $player->game_physical_ability ?? $player->player->physical_ability;
                $fitness = $player->fitness ?? 70;
                $morale = $player->morale ?? 70;

                return ($technical + $physical + $fitness + $morale) / 4;
            })
            ->sortDesc()
            ->take(18);

        if ($players->isEmpty()) {
            return 0;
        }

        return round($players->avg(), 1);
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
     * Formula: Base (stadium_seats × revenue_per_seat) × Facilities Multiplier × Position Factor
     */
    public function calculateMatchdayRevenue(Team $team, int $position, Game $game): int
    {
        $reputation = $team->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_MODEST;

        // Base matchday revenue from stadium size and competition config rates
        $league = $team->competitions()->where('type', 'league')->first();
        if (!$league) {
            return 0;
        }

        $base = $team->stadium_seats * config("finances.revenue_per_seat.{$reputation}", 15_000);

        // Get facilities multiplier from current investment (default to Tier 1 = 1.0)
        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        // Position factor from competition config
        $config = $league->getConfig();
        $positionFactor = $config->getPositionFactor($position) ?? 1.0;

        return (int) ($base * $facilitiesMultiplier * $positionFactor);
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
    private function getBaseCommercialRevenue(Game $game, Team $team, Competition $league): int
    {
        // Check for prior season actual commercial revenue
        $previousSeason = (int) $game->season - 1;
        $previousFinances = GameFinances::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        if ($previousFinances && $previousFinances->actual_commercial_revenue > 0) {
            return $previousFinances->actual_commercial_revenue;
        }

        // First season: calculate from stadium seats × config rate
        $reputation = $team->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_MODEST;

        return $team->stadium_seats * config("finances.commercial_per_seat.{$reputation}", 80_000);
    }
}
