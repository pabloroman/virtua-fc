<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Player\PlayerAge;
use App\Modules\Squad\DTOs\Advisory;
use App\Modules\Squad\DTOs\SquadContext;
use App\Modules\Squad\Enums\AdvisoryCategory;
use App\Modules\Squad\Enums\AdvisorySeverity;
use App\Modules\Squad\Enums\PositionGroup;
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
     * below this threshold — the dev-opportunity and wasted-wage advisories
     * both target these.
     */
    private const LOW_APPEARANCES = 12;

    /**
     * A position group is a "weak spot" when its projected starters average
     * this many overall points below the rest of the squad.
     */
    private const QUALITY_GAP_THRESHOLD = 5;

    /**
     * Wage percentile above which a player is considered "expensive" for the
     * purposes of the wasted-wage advisory. 0.75 = top quartile of the squad.
     */
    private const HIGH_WAGE_PERCENTILE = 0.75;

    /**
     * Overall-points gap between the worst starter and the first backup that
     * counts as "not a decent substitute". Below this, the backup is close
     * enough to step in without a quality drop.
     */
    private const BACKUP_GAP_THRESHOLD = 10;

    /**
     * Surplus of elite (KEY_PLAYER) projections over the formation's spots
     * that triggers the overload advisory. 2 = one rotation-level competitor
     * is fine; two or more is dressing-room friction.
     */
    private const ELITE_SURPLUS_THRESHOLD = 2;

    /**
     * @return array<int, Advisory>
     */
    public function build(array $projection, Formation $formation, Game $game, SquadContext $context): array
    {
        $available = NextSeasonProjectionService::availablePool($projection);

        $advisories = [];

        // Compute depth shortages once so quality-gap can skip groups we
        // already flagged as thin — saying "your defense is short" and
        // "your defense is weak" in the same panel is just noise.
        $thinGroups = $this->findThinGroups($available, $formation);

        foreach ($this->depthGapAdvisories($thinGroups) as $a) {
            $advisories[] = $a;
        }

        foreach ($this->qualityGapAdvisories($available, $formation, array_keys($thinGroups)) as $a) {
            $advisories[] = $a;
        }

        foreach ($this->backupGapAdvisories($available, $formation, array_keys($thinGroups)) as $a) {
            $advisories[] = $a;
        }

        foreach ($this->overloadAdvisories($available, $formation) as $a) {
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

        $wastedWageAdvisory = $this->wastedWageAdvisory($available);
        if ($wastedWageAdvisory !== null) {
            $advisories[] = $wastedWageAdvisory;
        }

        foreach ($this->departureAdvisories($projection['outgoing'], $context) as $a) {
            $advisories[] = $a;
        }

        // Stable sort by severity so critical bubbles to the top.
        usort($advisories, fn (Advisory $a, Advisory $b) => $a->severity->sortWeight() <=> $b->severity->sortWeight());

        return $advisories;
    }

    /**
     * Identify position groups that are short of the formation's requirement.
     *
     * @return array<string, int> group key → missing-player count
     */
    private function findThinGroups(Collection $available, Formation $formation): array
    {
        $needs = $formation->requirements();
        $haves = $available->countBy(fn (GamePlayer $p) => $p->position_group);

        $thin = [];
        foreach ($needs as $group => $need) {
            $missing = $need - ($haves[$group] ?? 0);
            if ($missing > 0) {
                $thin[$group] = $missing;
            }
        }

        return $thin;
    }

    /**
     * One bullet per position group that's short of the formation's
     * requirement. Severity scales with how many players are missing.
     *
     * @param array<string, int> $thinGroups
     * @return array<int, Advisory>
     */
    private function depthGapAdvisories(array $thinGroups): array
    {
        $advisories = [];

        foreach ($thinGroups as $group => $missing) {
            $severity = $missing >= 2 ? AdvisorySeverity::CRITICAL : AdvisorySeverity::WARN;

            $advisories[] = new Advisory(
                severity: $severity,
                category: AdvisoryCategory::DEPTH,
                message: __('planner.advisory_depth_gap', [
                    'count' => $missing,
                    'position' => $this->advisoryLabel($group),
                ]),
            );
        }

        return $advisories;
    }

    /**
     * Flag position groups whose *quality* (top-N projected overall) sits
     * meaningfully below the rest of the squad. Catches the "we have the
     * bodies but they aren't good enough" case that depth gap misses.
     *
     * Skips groups already covered by depth gap — those need bodies before
     * quality is the right complaint.
     *
     * @param array<int, string> $skipGroups
     * @return array<int, Advisory>
     */
    private function qualityGapAdvisories(Collection $available, Formation $formation, array $skipGroups = []): array
    {
        $needs = $formation->requirements();

        // Per-group average ovr of the projected first-choice players.
        $groupAverages = [];
        foreach ($needs as $group => $need) {
            $top = $available
                ->where('position_group', $group)
                ->sortByDesc('next_season_overall')
                ->take($need);

            if ($top->isEmpty()) {
                continue;
            }

            $groupAverages[$group] = $top->avg('next_season_overall');
        }

        if (count($groupAverages) < 2) {
            return [];
        }

        $advisories = [];

        foreach ($groupAverages as $group => $avg) {
            if (in_array($group, $skipGroups, true)) {
                continue;
            }

            // Compare against the average of the *other* groups, weighted by
            // their formation needs — so a weak group doesn't dilute the
            // benchmark it's being measured against.
            $otherScore = 0;
            $otherWeight = 0;
            foreach ($groupAverages as $g => $a) {
                if ($g === $group) {
                    continue;
                }
                $w = $needs[$g];
                $otherScore += $a * $w;
                $otherWeight += $w;
            }

            if ($otherWeight === 0) {
                continue;
            }

            $reference = $otherScore / $otherWeight;
            $gap = (int) round($reference - $avg);

            if ($gap < self::QUALITY_GAP_THRESHOLD) {
                continue;
            }

            $advisories[] = new Advisory(
                severity: AdvisorySeverity::WARN,
                category: AdvisoryCategory::QUALITY,
                message: __('planner.advisory_quality_gap', [
                    'position' => $this->advisoryLabel($group),
                    'gap' => $gap,
                ]),
            );
        }

        return $advisories;
    }

    /**
     * Flag position groups where the projected starters have no credible
     * substitute on the bench: either no extra body beyond the formation's
     * needs, or the first backup is meaningfully weaker than the worst
     * starter. Catches "11 starters, no plan B for injuries/suspensions".
     *
     * Skips groups already covered by the depth-gap advisory — they need
     * starters before they need backups.
     *
     * @param array<int, string> $skipGroups
     * @return array<int, Advisory>
     */
    private function backupGapAdvisories(Collection $available, Formation $formation, array $skipGroups = []): array
    {
        $needs = $formation->requirements();
        $advisories = [];

        foreach ($needs as $group => $need) {
            if (in_array($group, $skipGroups, true)) {
                continue;
            }

            $players = $available
                ->where('position_group', $group)
                ->sortByDesc('next_season_overall')
                ->values();

            // No body at all beyond the starters → no backup, full stop.
            if ($players->count() <= $need) {
                $advisories[] = new Advisory(
                    severity: AdvisorySeverity::WARN,
                    category: AdvisoryCategory::BACKUP,
                    message: __('planner.advisory_no_backup', [
                        'position' => $this->advisoryLabel($group),
                    ]),
                );
                continue;
            }

            $starters = $players->take($need);
            $firstBackup = $players->get($need);

            $worstStarter = (int) $starters->min('next_season_overall');
            $backupOvr = (int) $firstBackup->next_season_overall;
            $gap = $worstStarter - $backupOvr;

            if ($gap >= self::BACKUP_GAP_THRESHOLD) {
                $advisories[] = new Advisory(
                    severity: AdvisorySeverity::WARN,
                    category: AdvisoryCategory::BACKUP,
                    message: __('planner.advisory_weak_backup', [
                        'position' => $this->advisoryLabel($group),
                        'gap' => $gap,
                    ]),
                );
            }
        }

        return $advisories;
    }

    /**
     * Flag position groups where too many established players (KEY_PLAYER
     * roles) are competing for too few starting spots. Cornerstone players
     * stuck on the bench breed dressing-room friction — the user should
     * either sell, loan out, or accept the risk.
     *
     * @return array<int, Advisory>
     */
    private function overloadAdvisories(Collection $available, Formation $formation): array
    {
        $needs = $formation->requirements();
        $advisories = [];

        foreach ($needs as $group => $need) {
            $elite = $available
                ->where('position_group', $group)
                ->filter(fn (GamePlayer $p) => ($p->squad_role ?? null) === SquadRole::KEY_PLAYER)
                ->sortByDesc('next_season_overall')
                ->values();

            if ($elite->count() - $need < self::ELITE_SURPLUS_THRESHOLD) {
                continue;
            }

            $names = $this->joinNames(
                $elite->take(self::MAX_NAMES_PER_BULLET)->pluck('name')
            );

            $advisories[] = new Advisory(
                severity: AdvisorySeverity::WARN,
                category: AdvisoryCategory::OVERLOAD,
                message: __('planner.advisory_overload', [
                    'position' => $this->advisoryLabel($group),
                    'count' => $elite->count(),
                    'spots' => $need,
                    'names' => $names,
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

        foreach (PositionGroup::cases() as $group) {
            $inGroup = $available->where('position_group', $group->value);

            if ($inGroup->isEmpty()) {
                continue;
            }

            $hasYouth = $inGroup->contains(function (GamePlayer $p) {
                return PlayerAge::isYoung($p->next_season_age ?? PlayerAge::PRIME_END + 1);
            });

            if (! $hasYouth) {
                $advisories[] = new Advisory(
                    severity: AdvisorySeverity::WARN,
                    category: AdvisoryCategory::AGE,
                    message: __('planner.advisory_age_gap', [
                        'position' => $group->advisoryLabel(),
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
        $atRisk = $available
            ->filter(function (GamePlayer $p) use ($game) {
                $role = $p->squad_role ?? null;
                $isKey = $role === SquadRole::KEY_PLAYER || $role === SquadRole::FIRST_TEAM;
                if (! $isKey) {
                    return false;
                }
                return $p->contractNeedsAttention($game->current_date);
            })
            ->sortBy('contract_until')
            ->values();

        $advisories = [];
        foreach ($atRisk as $player) {
            $advisories[] = new Advisory(
                severity: AdvisorySeverity::WARN,
                category: AdvisoryCategory::WAGE,
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

        $names = $this->joinNames($candidates->pluck('name'));

        return new Advisory(
            severity: AdvisorySeverity::INFO,
            category: AdvisoryCategory::DEVELOPMENT,
            message: __('planner.advisory_development', ['names' => $names]),
        );
    }

    /**
     * Single combined bullet flagging players in the projected squad whose
     * wage is in the top quartile *and* who barely featured this season.
     * They're the "overpaid, underutilized" sell candidates — moving them
     * frees wage space without hurting first-choice quality.
     */
    private function wastedWageAdvisory(Collection $available): ?Advisory
    {
        $wages = $available->pluck('annual_wage')->filter(fn ($w) => $w > 0)->sort()->values();

        if ($wages->count() < 4) {
            return null;
        }

        $index = (int) floor(($wages->count() - 1) * self::HIGH_WAGE_PERCENTILE);
        $wageThreshold = $wages[$index];

        $candidates = $available
            ->filter(function (GamePlayer $p) use ($wageThreshold) {
                $role = $p->squad_role ?? null;
                if ($role !== SquadRole::ROTATION && $role !== SquadRole::RESERVES) {
                    return false;
                }
                if ($p->season_appearances >= self::LOW_APPEARANCES) {
                    return false;
                }
                return $p->annual_wage > $wageThreshold;
            })
            ->sortByDesc('annual_wage')
            ->take(self::MAX_NAMES_PER_BULLET);

        if ($candidates->isEmpty()) {
            return null;
        }

        $names = $this->joinNames($candidates->pluck('name'));

        return new Advisory(
            severity: AdvisorySeverity::INFO,
            category: AdvisoryCategory::WAGE,
            message: __('planner.advisory_wasted_wage', ['names' => $names]),
        );
    }

    /**
     * One bullet per outgoing player who would leave a real gap — those who
     * project at starter level next season. Squad fillers leaving don't
     * need a replacement nudge; the depth/backup advisories already cover
     * structural holes.
     *
     * @return array<int, Advisory>
     */
    private function departureAdvisories(Collection $outgoing, SquadContext $context): array
    {
        $impactful = $outgoing->filter(fn (GamePlayer $p) => $context->isImpactfulLoss($p));

        $advisories = [];
        foreach ($impactful as $player) {
            $advisories[] = new Advisory(
                severity: AdvisorySeverity::CRITICAL,
                category: AdvisoryCategory::DEPARTURE,
                message: __('planner.advisory_key_departure', [
                    'name' => $player->name,
                    'position' => $player->position_name,
                ]),
            );
        }

        return $advisories;
    }

    /**
     * Natural-language join of player names: comma-separated except the last
     * item, which gets the localized conjunction ("y" / "and") prepended.
     *
     *   1 name  → "Carvajal"
     *   2 names → "Carvajal y Alaba"
     *   3+ names → "Carvajal, Rüdiger y Alaba"
     */
    private function joinNames(Collection $names): string
    {
        $count = $names->count();

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return (string) $names->first();
        }

        $last = $names->last();
        $rest = $names->slice(0, $count - 1)->join(', ');

        return $rest . ' ' . __('planner.list_conjunction') . ' ' . $last;
    }

    /**
     * Resolve the lowercase singular advisory label for either a raw
     * `position_group` string (as it arrives from Formation requirements
     * and `position_group` collections) or a typed `PositionGroup`.
     */
    private function advisoryLabel(string|PositionGroup $group): string
    {
        $resolved = $group instanceof PositionGroup
            ? $group
            : PositionGroup::tryFrom($group);

        return $resolved?->advisoryLabel() ?? (string) $group;
    }
}
