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
     * La Liga TV revenue by position (in cents).
     */
    private const TV_REVENUE_LALIGA = [
        1 => 10_000_000_000,   // €100M
        2 => 9_000_000_000,    // €90M
        3 => 8_500_000_000,    // €85M
        4 => 8_000_000_000,    // €80M
        5 => 7_500_000_000,    // €75M
        6 => 7_000_000_000,    // €70M
        7 => 6_500_000_000,    // €65M
        8 => 6_000_000_000,    // €60M
        9 => 5_800_000_000,    // €58M
        10 => 5_600_000_000,   // €56M
        11 => 5_400_000_000,   // €54M
        12 => 5_200_000_000,   // €52M
        13 => 5_000_000_000,   // €50M
        14 => 5_000_000_000,   // €50M
        15 => 4_800_000_000,   // €48M
        16 => 4_600_000_000,   // €46M
        17 => 4_400_000_000,   // €44M
        18 => 4_200_000_000,   // €42M
        19 => 4_000_000_000,   // €40M
        20 => 4_000_000_000,   // €40M
    ];

    /**
     * La Liga 2 TV revenue by position (in cents).
     */
    private const TV_REVENUE_LALIGA2 = [
        1 => 2_000_000_000,    // €20M
        2 => 1_800_000_000,    // €18M
        3 => 1_500_000_000,    // €15M
        4 => 1_400_000_000,    // €14M
        5 => 1_300_000_000,    // €13M
        6 => 1_200_000_000,    // €12M
        7 => 1_100_000_000,    // €11M
        8 => 1_050_000_000,    // €10.5M
        9 => 1_000_000_000,    // €10M
        10 => 950_000_000,     // €9.5M
        11 => 900_000_000,     // €9M
        12 => 850_000_000,     // €8.5M
        13 => 800_000_000,     // €8M
        14 => 800_000_000,     // €8M
        15 => 750_000_000,     // €7.5M
        16 => 700_000_000,     // €7M
        17 => 650_000_000,     // €6.5M
        18 => 650_000_000,     // €6.5M
        19 => 600_000_000,     // €6M
        20 => 600_000_000,     // €6M
        21 => 600_000_000,     // €6M
        22 => 600_000_000,     // €6M
    ];

    /**
     * Position factors for matchday revenue.
     */
    private const POSITION_FACTORS = [
        'top' => 1.10,      // 1st-4th
        'mid_high' => 1.0,  // 5th-10th
        'mid_low' => 0.95,  // 11th-16th
        'relegation' => 0.85, // 17th-20th
    ];

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
        $projectedCommercialRevenue = $team->clubProfile?->commercial_revenue ?? 0;

        $projectedTotalRevenue = $projectedTvRevenue
            + $projectedMatchdayRevenue
            + $projectedPrizeRevenue
            + $projectedCommercialRevenue;

        // Calculate projected wages
        $projectedWages = $this->calculateProjectedWages($game);

        // Calculate projected surplus
        $projectedSurplus = $projectedTotalRevenue - $projectedWages;

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
        $isLaLiga = $league->tier === 1;
        $table = $isLaLiga ? self::TV_REVENUE_LALIGA : self::TV_REVENUE_LALIGA2;

        return $table[$position] ?? $table[array_key_last($table)];
    }

    /**
     * Calculate matchday revenue.
     * Formula: Base × Facilities Multiplier × Position Factor
     */
    public function calculateMatchdayRevenue(Team $team, int $position, Game $game): int
    {
        $clubProfile = $team->clubProfile;
        if (!$clubProfile) {
            return 0;
        }

        // Base matchday revenue from club profile
        $base = $clubProfile->calculateBaseMatchdayRevenue();

        // Get facilities multiplier from current investment (default to Tier 1 = 1.0)
        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        // Position factor
        $positionFactor = $this->getPositionFactor($position);

        return (int) ($base * $facilitiesMultiplier * $positionFactor);
    }

    /**
     * Get position factor for matchday revenue.
     */
    private function getPositionFactor(int $position): float
    {
        if ($position <= 4) {
            return self::POSITION_FACTORS['top'];
        }
        if ($position <= 10) {
            return self::POSITION_FACTORS['mid_high'];
        }
        if ($position <= 16) {
            return self::POSITION_FACTORS['mid_low'];
        }
        return self::POSITION_FACTORS['relegation'];
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
     * Calculate projected position for display (from strength comparison).
     */
    public function getLeagueProjections(Game $game): array
    {
        $team = $game->team;
        $league = $team->competitions()->where('type', 'league')->first();

        if (!$league) {
            return [];
        }

        $teamStrengths = $this->calculateLeagueStrengths($game, $league);

        $projections = [];
        $position = 1;

        foreach ($teamStrengths as $teamId => $strength) {
            $t = Team::find($teamId);
            $projections[] = [
                'team_id' => $teamId,
                'team_name' => $t->name,
                'strength' => $strength,
                'position' => $position,
                'is_user_team' => $teamId === $team->id,
            ];
            $position++;
        }

        return $projections;
    }
}
