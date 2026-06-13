<?php

namespace App\Modules\Finance\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Transfer\Services\ContractService;

/**
 * The shared wage model: a player's wage is `playerWeight × clubWageLevel`.
 *
 *  - playerWeight is the player's RELATIVE wage importance (ability/age/position).
 *    Its absolute scale is irrelevant — only a player's proportion of the squad
 *    matters.
 *  - clubWageLevel (this service) is the euro multiplier that turns that weight
 *    into an actual wage for a given club. It is the club's affordable wage bill
 *    — wageBaseRevenue × wage_revenue_ratio[reputation] — spread across the total
 *    weight of its squad.
 *
 * A player's wage cannot be priced without his club: the affordable bill is set
 * by the club's revenue, an emergent, league-wide quantity (TV by projected
 * position + commercial by stadium/reputation) that does not exist when a single
 * player is generated. clubWageLevel is the first point at which that revenue —
 * and thus the wage a club can carry — is known, which is why squad wages are
 * assigned (not "corrected") here. Revenue keys off squad strength, not wages, so
 * there is no circular dependency.
 */
class WageModelService
{
    public function __construct(
        private readonly BudgetProjectionService $budgetProjection,
        private readonly ContractService $contractService,
    ) {}

    /**
     * Euro multiplier converting a relative wage weight into an annual wage for
     * this club: `wage = weight × clubWageLevel`. Equals the club's affordable
     * wage bill (wage-budget revenue × ratio) divided by the squad's total
     * weight.
     *
     * The revenue base is `wageBudgetRevenueForTeam` — gate-inclusive (TV +
     * commercial + solidarity + season tickets) and aligned with the salary-cap
     * base. The narrower wage-base revenue would understate low-tier clubs
     * (whose income is mostly gate) and collapse their wages onto the league
     * minimum.
     *
     * Returns 0.0 when there is no squad weight or no revenue base — the caller
     * leaves wages untouched (a club with no players or no revenue has no bill to
     * distribute).
     *
     * @param  string  $reputation  Club reputation level (drives the revenue ratio)
     * @param  int  $totalSquadWeight  Sum of the squad's relative wage weights
     * @param  int|null  $projectedPosition  Projected finishing position (drives TV revenue); resolved by the projection service when null
     */
    public function clubWageLevel(
        Game $game,
        Team $team,
        Competition $league,
        string $reputation,
        int $totalSquadWeight,
        ?int $projectedPosition = null,
    ): float {
        if ($totalSquadWeight <= 0) {
            return 0.0;
        }

        $ratio = (float) config('finances.wage_revenue_ratio.' . $reputation, 0.55);
        $baseRevenue = $this->budgetProjection->wageBudgetRevenueForTeam($game, $team, $league, $projectedPosition);
        $targetBill = (int) round($baseRevenue * $ratio);

        if ($targetBill <= 0) {
            return 0.0;
        }

        return $targetBill / $totalSquadWeight;
    }

    /**
     * The wage (in cents) a player should earn at a club under the wage model:
     * playerWeight × clubWageLevel, floored at $minWage. Mid-game pricing path —
     * a youth promotion, a regen, or an AI signing is paid its share of the
     * club's affordable bill, exactly like the club's setup-leveled squad,
     * instead of the standalone formula (which, being un-leveled, underpays
     * newcomers against that squad).
     *
     * Falls back to the standalone wage formula when the player has no club to
     * be priced against (free agent with no destination, a club with no league,
     * no squad, or no revenue). The single entry point for every mid-game
     * signing site, so the "level-or-standalone, floored at the minimum"
     * decision lives in one place.
     */
    public function wageForSigning(Game $game, ?string $teamId, int $overallScore, int $marketValueCents, ?int $age, ?string $position, int $minWage): int
    {
        $level = $teamId !== null ? $this->clubWageLevelForTeam($game, $teamId) : null;

        return $this->wageFromLevel($level, $overallScore, $marketValueCents, $age, $position, $minWage);
    }

    /**
     * Same pricing as {@see wageForSigning}, but for a club whose wage level was
     * already resolved (e.g. a bulk generation loop that caches one level per
     * team via {@see clubWageLevelForTeam}). A null $level — a club that can't be
     * priced — falls back to the standalone formula. Floored at $minWage.
     */
    public function wageFromLevel(?float $level, int $overallScore, int $marketValueCents, ?int $age, ?string $position, int $minWage): int
    {
        $wage = $level !== null
            ? (int) round($this->contractService->playerWeight($overallScore, $marketValueCents, $age, $position) * $level)
            : $this->contractService->calculateAnnualWageForPlayer($overallScore, $marketValueCents, $minWage, $age, $position);

        return max($wage, $minWage);
    }

    /**
     * A club's current wage level — affordable bill ÷ the total weight of its
     * current squad — or null when it can't be priced (unknown team, no league,
     * empty squad, or no revenue base). Callers pricing many players for the same
     * club should resolve this once and multiply each player's playerWeight by it.
     */
    public function clubWageLevelForTeam(Game $game, string $teamId): ?float
    {
        $team = Team::find($teamId);
        if (!$team) {
            return null;
        }

        $league = Competition::whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
            ->where('role', Competition::ROLE_LEAGUE)
            ->first();
        if (!$league) {
            return null;
        }

        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $totalWeight = $this->squadWeight($game, $team);

        $level = $this->clubWageLevel($game, $team, $league, $reputation, $totalWeight);

        return $level > 0.0 ? $level : null;
    }

    /**
     * Total relative wage weight of a club's current squad — the denominator that
     * spreads the affordable bill across players. Uses the same playerWeight curve
     * that prices each individual, so the level it feeds is self-consistent.
     */
    private function squadWeight(Game $game, Team $team): int
    {
        // Full models so the overall_score accessor and age() have their source
        // columns; a club squad is small (~25 rows).
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->get();

        $total = 0;
        foreach ($players as $player) {
            $total += $this->contractService->playerWeight(
                (int) $player->overall_score,
                (int) $player->market_value_cents,
                $player->age($game->current_date),
                $player->position,
            );
        }

        return $total;
    }
}
