<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Player\PlayerAge;
use App\Modules\Squad\Enums\SquadRole;
use Illuminate\Support\Collection;

/**
 * Generates the squad-level "what should I do?" bullets that show up in the
 * planner's Transfer Recommendations sidebar.
 *
 * Reads the projection produced by NextSeasonProjectionService and the role
 * labels written by PlayerSquadRoleClassifier — does not requery the DB.
 */
class SquadAdvisorService
{
    /**
     * Cap on names listed in a single advisory (keeps bullets readable).
     */
    private const MAX_NAMES_PER_BULLET = 5;

    /**
     * A player counts as "barely playing" when their season appearances are
     * below this threshold — the dev-opportunity advisory targets these.
     */
    private const LOW_APPEARANCES = 12;

    /**
     * @return array<int, Advisory>
     */
    public function build(array $projection, Formation $formation, Game $game): array
    {
        $available = collect()
            ->merge($projection['staying']['goalkeepers'])
            ->merge($projection['staying']['defenders'])
            ->merge($projection['staying']['midfielders'])
            ->merge($projection['staying']['forwards'])
            ->reject(fn (GamePlayer $p) => $p->next_season_reason === NextSeasonProjectionService::REASON_STILL_ON_LOAN)
            ->merge($projection['incoming']);

        $advisories = [];

        foreach ($this->depthGapAdvisories($available, $formation) as $a) {
            $advisories[] = $a;
        }

        foreach ($this->ageGapAdvisories($available) as $a) {
            $advisories[] = $a;
        }

        foreach ($this->wageCliffAdvisories($available, $game) as $a) {
            $advisories[] = $a;
        }

        $devAdvisory = $this->developmentAdvisory($available);
        if ($devAdvisory !== null) {
            $advisories[] = $devAdvisory;
        }

        foreach ($this->departureAdvisories($projection['outgoing']) as $a) {
            $advisories[] = $a;
        }

        // Stable sort by severity so critical bubbles to the top.
        usort($advisories, function (Advisory $a, Advisory $b) {
            $order = [
                Advisory::SEVERITY_CRITICAL => 0,
                Advisory::SEVERITY_WARN => 1,
                Advisory::SEVERITY_INFO => 2,
            ];
            return ($order[$a->severity] ?? 99) <=> ($order[$b->severity] ?? 99);
        });

        return $advisories;
    }

    /**
     * One bullet per position group that's short of the formation's
     * requirement. Severity scales with how many players are missing.
     *
     * @return array<int, Advisory>
     */
    private function depthGapAdvisories(Collection $available, Formation $formation): array
    {
        $advisories = [];
        $needs = $formation->requirements();
        $haves = $available->countBy(fn (GamePlayer $p) => $p->position_group);

        foreach ($needs as $group => $need) {
            $have = $haves[$group] ?? 0;
            if ($have >= $need) {
                continue;
            }

            $missing = $need - $have;
            $severity = $missing >= 2 ? Advisory::SEVERITY_CRITICAL : Advisory::SEVERITY_WARN;

            $advisories[] = new Advisory(
                severity: $severity,
                category: Advisory::CATEGORY_DEPTH,
                message: __('planner.advisory_depth_gap', [
                    'count' => $missing,
                    'position' => $this->groupLabel($group),
                ]),
            );
        }

        return $advisories;
    }

    /**
     * Bullet when a position group has no players in the growing-phase pipeline
     * — signals a future cliff even if today's depth looks fine.
     *
     * @return array<int, Advisory>
     */
    private function ageGapAdvisories(Collection $available): array
    {
        $advisories = [];

        foreach (['Goalkeeper', 'Defender', 'Midfielder', 'Forward'] as $group) {
            $inGroup = $available->where('position_group', $group);

            if ($inGroup->isEmpty()) {
                continue;
            }

            $hasYouth = $inGroup->contains(function (GamePlayer $p) {
                return PlayerAge::isYoung($p->next_season_age ?? PlayerAge::PRIME_END + 1);
            });

            if (! $hasYouth) {
                $advisories[] = new Advisory(
                    severity: Advisory::SEVERITY_WARN,
                    category: Advisory::CATEGORY_AGE,
                    message: __('planner.advisory_age_gap', [
                        'position' => $this->groupLabel($group),
                        'age' => PlayerAge::YOUNG_END,
                    ]),
                );
            }
        }

        return $advisories;
    }

    /**
     * One bullet per key player whose contract runs out within roughly a year
     * and who has no renewal or pre-contract on the books — flags renewal work
     * before the pre-contract window opens to rival clubs.
     *
     * @return array<int, Advisory>
     */
    private function wageCliffAdvisories(Collection $available, Game $game): array
    {
        $cutoff = $game->current_date->copy()->addMonths(14);

        $atRisk = $available
            ->filter(function (GamePlayer $p) use ($cutoff) {
                $role = $p->squad_role ?? null;
                $isKey = $role === SquadRole::KEY_PLAYER || $role === SquadRole::FIRST_TEAM;
                if (! $isKey) {
                    return false;
                }
                if (! $p->contract_until || $p->contract_until->gt($cutoff)) {
                    return false;
                }
                return ! $p->hasRenewalAgreed() && ! $p->hasPreContractAgreement();
            })
            ->sortBy('contract_until')
            ->values();

        $advisories = [];
        foreach ($atRisk as $player) {
            $advisories[] = new Advisory(
                severity: Advisory::SEVERITY_WARN,
                category: Advisory::CATEGORY_WAGE,
                message: __('planner.advisory_wage_cliff', [
                    'name' => $player->name,
                    'year' => $player->contract_until->year,
                ]),
            );
        }

        return $advisories;
    }

    /**
     * Single bullet listing prospects/wonderkids who are not getting minutes —
     * the "give minutes to X, Y, Z" nudge from the mockup.
     */
    private function developmentAdvisory(Collection $available): ?Advisory
    {
        $candidates = $available
            ->filter(function (GamePlayer $p) {
                $role = $p->squad_role ?? null;
                if ($role !== SquadRole::WONDERKID && $role !== SquadRole::PROSPECT) {
                    return false;
                }
                return $p->season_appearances < self::LOW_APPEARANCES;
            })
            ->sortByDesc('potential')
            ->take(self::MAX_NAMES_PER_BULLET);

        if ($candidates->isEmpty()) {
            return null;
        }

        $names = $candidates->pluck('name')->join(', ');

        return new Advisory(
            severity: Advisory::SEVERITY_INFO,
            category: Advisory::CATEGORY_DEVELOPMENT,
            message: __('planner.advisory_development', ['names' => $names]),
        );
    }

    /**
     * One bullet per outgoing player who would leave a real gap — key players,
     * starters, and high-overall regulars. Squad fillers leaving don't need a
     * replacement nudge.
     *
     * @return array<int, Advisory>
     */
    private function departureAdvisories(Collection $outgoing): array
    {
        $impactful = $outgoing->filter(function (GamePlayer $p) {
            $role = $p->squad_role ?? null;
            // squad_role is DEPARTING for everyone in the outgoing bucket — fall
            // back to ability+tier to pick out the meaningful losses.
            return $p->overall_score >= 75 || $p->tier >= 4;
        });

        $advisories = [];
        foreach ($impactful as $player) {
            $advisories[] = new Advisory(
                severity: Advisory::SEVERITY_CRITICAL,
                category: Advisory::CATEGORY_DEPARTURE,
                message: __('planner.advisory_key_departure', [
                    'name' => $player->name,
                    'position' => $player->position_name,
                ]),
            );
        }

        return $advisories;
    }

    private function groupLabel(string $group): string
    {
        return match ($group) {
            'Goalkeeper' => __('planner.group_goalkeeper'),
            'Defender' => __('planner.group_defender'),
            'Midfielder' => __('planner.group_midfielder'),
            'Forward' => __('planner.group_forward'),
            default => $group,
        };
    }
}
