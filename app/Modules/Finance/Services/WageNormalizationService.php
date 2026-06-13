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
 * Scales each club's seeded wage bill to a realistic share of its OWN revenue
 * (config `finances.wage_revenue_ratio`), so the projected surplus — and the
 * transfer budget derived from it — comes out realistic.
 *
 * The base wage formula systematically under-pays squads (bills ran ~15–20% of
 * revenue, which let the surplus balloon). Anchoring the BILL to revenue is what
 * fixes it across divisions: a flat per-tier or per-reputation figure can't,
 * because a second-division 'established' club and a La Liga one earn wildly
 * different revenue. The formula's intra-squad distribution is preserved — every
 * player is scaled by the same club factor, so stars still earn the most.
 *
 * Because the target is capped below `wage_cap_ratio`, no club is left over its
 * salary cap at kickoff.
 */
class WageNormalizationService
{
    public function __construct(
        private readonly BudgetProjectionService $budgetProjection,
        private readonly SquadService $squadService,
        private readonly ContractService $contractService,
    ) {}

    /**
     * Normalize every league club in the game. League strengths (which set the
     * TV-revenue position) are computed once per competition, not per club.
     */
    public function normalizeGame(Game $game): void
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
                $this->normalizeTeam($game, $team, $league, $position);
            }
        }
    }

    /**
     * Scale one club's seeded wages so the bill ≈ ratio × wage-base revenue,
     * keeping every wage at or above the league minimum. No-op when the club has
     * no players, no current bill, or no revenue base.
     */
    public function normalizeTeam(Game $game, Team $team, Competition $league, ?int $projectedPosition = null): void
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $ratio = (float) config('finances.wage_revenue_ratio.' . $reputation, 0.55);
        $baseRevenue = $this->budgetProjection->wageBaseRevenueForTeam($game, $team, $league, $projectedPosition);
        $targetBill = (int) round($baseRevenue * $ratio);

        $rows = GamePlayer::where('game_id', $game->id)->where('team_id', $team->id);
        $currentBill = (int) (clone $rows)->sum('annual_wage');
        if ($currentBill <= 0 || $targetBill <= 0) {
            return;
        }

        $factor = $targetBill / $currentBill;
        $minWage = $this->contractService->getMinimumWageForClub((int) $league->tier, $reputation);

        // One bulk UPDATE per club rather than a save() per player — this runs
        // over every league club at game setup. Each wage is scaled by the club
        // factor, rounded to the nearest €10k (like the wage formula), then
        // re-floored at the league minimum so scaling down never drops below it.
        $rows->update([
            'annual_wage' => DB::raw(sprintf(
                'GREATEST(ROUND(annual_wage * %.10f / 1000000) * 1000000, %d)',
                $factor,
                $minWage,
            )),
        ]);
    }
}
