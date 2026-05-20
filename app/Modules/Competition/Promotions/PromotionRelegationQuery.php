<?php

namespace App\Modules\Competition\Promotions;

use App\Models\Game;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Services\CountryConfig;

/**
 * Read-only query facade for callers that just need to know "what would
 * happen if promotion/relegation ran right now?" — without actually applying it.
 *
 * Used by:
 *   - SeasonSummaryService: rendering the season-end promotion/relegation panel
 *   - JobOfferService / SnapshotManagerSeasonRecordProcessor: deciding whether
 *     the player's team got promoted this season (drives end-of-season offer
 *     generation and the manager-season record's grade signal).
 *
 * The query runs the planner on a fresh snapshot every call. The planner is
 * pure and cheap (no DB writes, one snapshot read), so callers don't need to
 * cache — but they can keep the result around within a request if they make
 * the same call repeatedly.
 *
 * Exceptions propagate from the planner; PlayoffInProgress and LogicException
 * are caller-visible. wasTeamPromoted() swallows these (treating "can't
 * determine" as "not promoted") because its callers can't usefully act on a
 * mid-flight playoff signal.
 */
class PromotionRelegationQuery
{
    public function __construct(
        private readonly CountrySeasonSnapshotBuilder $snapshotBuilder,
        private readonly CountryPromotionRelegationPlanner $planner,
        private readonly CountryConfig $countryConfig,
    ) {}

    /**
     * Did $teamId (defaulting to the user's managed team) get promoted from
     * its current competition this season? Returns false if the country has
     * no promotion rules, if planning fails, or if the team isn't in any
     * promotion move.
     */
    public function wasTeamPromoted(Game $game, ?string $teamId = null): bool
    {
        if ($game->country === null) {
            return false;
        }

        $target = $teamId ?? $game->team_id;

        try {
            $plan = $this->planFor($game);
        } catch (\Throwable) {
            return false;
        }

        foreach ($plan->moves as $move) {
            if ($move->teamId !== $target) {
                continue;
            }
            if ($move->isPromotion()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the promoted/relegated team summary the season-end view renders.
     * Returns null when there are no moves to show or when planning isn't
     * possible (e.g. playoff in progress).
     *
     * Each entry carries `position` for the view's "1º" / "Playoff" label —
     * the team's finishing position in the relevant source competition
     * (top division for relegations, source group for promotions). Derived
     * from the snapshot's ordered standings.
     *
     * @return array{
     *     promoted: list<array{teamId: string, position: int|string}>,
     *     relegated: list<array{teamId: string, position: int|string}>,
     *     promotedCompetitionId: string,
     *     relegatedCompetitionId: string,
     * }|null
     */
    public function summaryForCompetition(Game $game, string $competitionId): ?array
    {
        if ($game->country === null) {
            return null;
        }

        try {
            $config = $this->countryConfig->get($game->country) ?? [];
            $snapshot = $this->snapshotBuilder->build($game);
            $plan = $this->planner->planFromSnapshot($snapshot, $config);
        } catch (PlayoffInProgressException) {
            return null;
        } catch (\RuntimeException) {
            return null;
        } catch (\LogicException) {
            return null;
        }

        $country = $game->country;
        $rule = $this->ruleInvolving($country, $competitionId);
        if ($rule === null) {
            return null;
        }

        $top = $rule['top_division'];
        $bottom = $rule['bottom_division'];
        $sources = $rule['playoff_source_divisions'] ?? [$bottom];

        $promoted = [];
        $relegated = [];
        foreach ($plan->moves as $move) {
            if ($move->isPromotion() && $move->toCompetitionId === $top
                && in_array($move->fromCompetitionId, $sources, true)
            ) {
                $promoted[] = [
                    'teamId' => $move->teamId,
                    'position' => $this->positionFor($move, $snapshot),
                ];
            }
            if ($move->isRelegation() && $move->fromCompetitionId === $top
                && in_array($move->toCompetitionId, $sources, true)
            ) {
                $relegated[] = [
                    'teamId' => $move->teamId,
                    'position' => $this->positionFor($move, $snapshot),
                ];
            }
        }

        if (empty($promoted) && empty($relegated)) {
            return null;
        }

        return [
            'promoted' => $promoted,
            'relegated' => $relegated,
            'promotedCompetitionId' => $top,
            'relegatedCompetitionId' => $sources[0],
        ];
    }

    private function positionFor(PromotionMove $move, CountrySeasonSnapshot $snapshot): int|string
    {
        $standings = $snapshot->standingsByCompetition[$move->fromCompetitionId] ?? [];
        $idx = array_search($move->teamId, $standings, true);
        if ($idx !== false) {
            return $idx + 1;
        }
        // Defensive: the team isn't in the source comp's standings (shouldn't
        // happen since the planner sources moves from the same snapshot). Fall
        // back to the legacy 'Playoff' label so the view still renders.
        return 'Playoff';
    }

    private function planFor(Game $game): PromotionRelegationPlan
    {
        $config = $this->countryConfig->get($game->country) ?? [];
        $snapshot = $this->snapshotBuilder->build($game);
        return $this->planner->planFromSnapshot($snapshot, $config);
    }

    private function ruleInvolving(string $country, string $competitionId): ?array
    {
        foreach ($this->countryConfig->promotions($country) as $rule) {
            $top = $rule['top_division'];
            $bottom = $rule['bottom_division'];
            $sources = $rule['playoff_source_divisions'] ?? [$bottom];
            if ($top === $competitionId || in_array($competitionId, $sources, true)) {
                return $rule;
            }
        }
        return null;
    }
}
