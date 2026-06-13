<?php

namespace App\Modules\Finance\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Squad\Services\SquadService;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Support\Facades\DB;

/**
 * Assigns each club's seeded squad a wage bill it can actually afford, by the
 * shared wage model `wage = playerWeight × clubWageLevel` (see WageModelService).
 *
 * This is NOT a correction of the per-player wage formula. The seeded wage only
 * ever encodes a player's RELATIVE standing in his squad (the ability/age curve);
 * its absolute euros are meaningless until a club is in view, because the bill a
 * club can carry is `revenue × wage_revenue_ratio[reputation]`, and revenue is an
 * emergent, league-wide quantity. This runs once the whole league is seeded —
 * the first point at which every club's revenue (and thus its wage level) is
 * known — and is where squad wages are actually set: each player's relative
 * weight × his club's wage level, floored at the league minimum.
 *
 * Because the target sits below `wage_cap_ratio`, no club starts over its salary
 * cap. Idempotent — re-running on a crash-recovery pass converges (the bill is
 * already ≈ target, so the level ≈ 1.0).
 */
class SquadWageBudgetService
{
    public function __construct(
        private readonly WageModelService $wageModel,
        private readonly SquadService $squadService,
        private readonly ContractService $contractService,
    ) {}

    /**
     * Assign every league club's squad wage bill. League strengths (which set the
     * TV-revenue position) are computed once per competition, not per club.
     */
    public function assignWageBudget(Game $game): void
    {
        $byLeague = CompetitionEntry::where('game_id', $game->id)
            ->whereHas('competition', fn ($q) => $q->where('role', Competition::ROLE_LEAGUE))
            ->with('competition')
            ->get()
            ->groupBy('competition_id');

        foreach ($byLeague as $entries) {
            $league = $entries->first()->competition;
            if (!$league) {
                continue;
            }

            $strengths = $this->squadService->calculateLeagueStrengths($game, $league);

            foreach ($entries as $entry) {
                $team = Team::find($entry->team_id);
                if (!$team) {
                    continue;
                }
                $position = $this->squadService->getProjectedPosition($team->id, $strengths);
                $this->assignTeamWageBudget($game, $team, $league, $position);
            }
        }
    }

    /**
     * Set one club's squad wages to `weight × clubWageLevel`, floored at the
     * league minimum. No-op when the club has no players or no revenue base.
     */
    public function assignTeamWageBudget(Game $game, Team $team, Competition $league, ?int $projectedPosition = null): void
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);

        $rows = GamePlayer::where('game_id', $game->id)->where('team_id', $team->id);

        // The seeded first-pass wage IS the player's relative weight: the
        // ability/age curve already shaped it, and only each player's proportion
        // within the squad matters here — the absolute scale comes from the club
        // wage level below.
        $totalWeight = (int) (clone $rows)->sum('annual_wage');

        $level = $this->wageModel->clubWageLevel($game, $team, $league, $reputation, $totalWeight, $projectedPosition);
        if ($level <= 0.0) {
            return;
        }

        $minWage = $this->contractService->getMinimumWageForClub((int) $league->tier, $reputation);

        // One bulk UPDATE per club rather than a save() per player — this runs
        // over every league club at game setup. Each weight is scaled by the club
        // level, rounded to the nearest €10k (like the wage formula), then
        // re-floored at the league minimum so scaling down never drops below it.
        $rows->update([
            'annual_wage' => DB::raw(sprintf(
                'GREATEST(ROUND(annual_wage * %.10f / 1000000) * 1000000, %d)',
                $level,
                $minWage,
            )),
        ]);
    }
}
