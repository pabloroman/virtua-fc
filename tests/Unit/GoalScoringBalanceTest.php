<?php

namespace Tests\Unit;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Match\Services\MatchSimulator;
use App\Modules\Match\Support\MatchOutcomeModel;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the goal-scoring calibration (issue #1313 / [L15]): a clearly dominant
 * squad must convert its quality edge into goals — averaging well over two per
 * game and producing regular blowouts — while evenly-matched games stay tight
 * and no scoreline explodes (the #1283/#1304 guardrail). Also verifies the
 * defensive-fatigue mechanic: a sustained shell cracks late.
 *
 * These exercise the production outcome math directly (the private xG pipeline
 * and the shared {@see MatchOutcomeModel}) with fixed strengths, so they are
 * fast and deterministic in xG without seeding a database or running full
 * minute-by-minute matches.
 */
class GoalScoringBalanceTest extends TestCase
{
    /** A dominant squad's effective strength (~90-rated best XI). */
    private const STRONG = 0.89;

    /** A clearly weaker squad's effective strength (~70-rated best XI). */
    private const WEAK = 0.70;

    private MatchSimulator $simulator;

    private ReflectionMethod $calculateBaseExpectedGoals;

    private ReflectionMethod $applyTacticalModifiers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new MatchSimulator;
        $this->calculateBaseExpectedGoals = new ReflectionMethod(MatchSimulator::class, 'calculateBaseExpectedGoals');
        $this->applyTacticalModifiers = new ReflectionMethod(MatchSimulator::class, 'applyTacticalModifiers');
    }

    public function test_dominant_squad_scores_freely_and_produces_blowouts(): void
    {
        // A dominant side against a weaker opponent playing straightforwardly.
        [$homeXG, $awayXG] = $this->fullMatchXg(
            Mentality::BALANCED, Mentality::BALANCED,
            PlayingStyle::BALANCED, PlayingStyle::BALANCED,
            PressingIntensity::STANDARD, PressingIntensity::STANDARD,
            DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
        );

        $stats = $this->sampleMany($homeXG, $awayXG);

        // Core acceptance criterion: a dominant squad averages > 2.3 GF/game.
        $this->assertGreaterThan(2.3, $stats['avgHome'],
            "Dominant squad should average > 2.3 GF/game, got {$stats['avgHome']}");

        // Result variety: clear blowouts (3+ margin) happen regularly.
        $this->assertGreaterThan(0.10, $stats['blowoutRate'],
            "Blowouts (3+ margin) should occur regularly, got rate {$stats['blowoutRate']}");

        // Guardrail against the #1283/#1304 exploding-scoreline class.
        $this->assertLessThan(6.0, $stats['avgTotal'],
            "Average total goals should stay realistic, got {$stats['avgTotal']}");
        $this->assertLessThanOrEqual(14, $stats['maxTotal'],
            "No single scoreline should explode, got max total {$stats['maxTotal']}");
    }

    public function test_dominant_squad_still_breaks_down_a_parked_bus(): void
    {
        // The exact complaint: a much stronger side vs a full defensive shell.
        // Quality damping + defensive fatigue must keep it scoring rather than
        // being held to a 0-0.
        [$homeXG, $awayXG] = $this->fullMatchXg(
            Mentality::BALANCED, Mentality::DEFENSIVE,
            PlayingStyle::BALANCED, PlayingStyle::COUNTER_ATTACK,
            PressingIntensity::STANDARD, PressingIntensity::LOW_BLOCK,
            DefensiveLineHeight::NORMAL, DefensiveLineHeight::DEEP,
        );

        $stats = $this->sampleMany($homeXG, $awayXG);

        $this->assertGreaterThan(1.5, $stats['avgHome'],
            "A dominant squad should break a parked bus for > 1.5 GF/game, got {$stats['avgHome']}");
        $this->assertGreaterThan(0.05, $stats['blowoutRate'],
            "Blowouts vs a parked bus should still happen, got rate {$stats['blowoutRate']}");
    }

    public function test_defensive_shell_concedes_more_late_than_early(): void
    {
        // Identical inputs; only the match minute differs. A tiring shell should
        // concede more in the closing stages.
        $early = $this->attackerXgAgainstShell(20.0);
        $late = $this->attackerXgAgainstShell(85.0);

        $this->assertGreaterThan($early, $late,
            "A tiring defensive shell should concede more late ({$late}) than early ({$early})");
    }

    public function test_defensive_fatigue_does_not_fire_in_evenly_matched_games(): void
    {
        // Equal strengths: the quality-damping branch (and therefore fatigue)
        // must not engage, so an evenly-matched defensive game stays tight
        // regardless of the minute.
        $early = $this->attackerXgAgainstShell(20.0, self::WEAK, self::WEAK);
        $late = $this->attackerXgAgainstShell(85.0, self::WEAK, self::WEAK);

        $this->assertEqualsWithDelta($early, $late, 0.0001,
            'Fatigue should not inflate scoring in an evenly-matched game');
    }

    /**
     * Full-match xG for the strong (home) side vs the weak (away) side, summed
     * over an early and a late period exactly as the engine scales xG by match
     * fraction — so the late period picks up the defensive-fatigue ramp.
     *
     * @return array{0: float, 1: float} [homeXG, awayXG]
     */
    private function fullMatchXg(
        Mentality $homeMentality,
        Mentality $awayMentality,
        PlayingStyle $homeStyle,
        PlayingStyle $awayStyle,
        PressingIntensity $homePress,
        PressingIntensity $awayPress,
        DefensiveLineHeight $homeLine,
        DefensiveLineHeight $awayLine,
    ): array {
        $baseGoals = (float) config('match_simulation.base_goals', 1.5);
        $strengthRatio = self::STRONG / self::WEAK;

        // Two 45' periods; each contributes its fraction of the full match.
        $fraction = 45.0 / 93.0;
        $homeXG = 0.0;
        $awayXG = 0.0;

        foreach ([22.5, 67.5] as $effectiveMinute) {
            [$h, $a] = $this->calculateBaseExpectedGoals->invoke(
                $this->simulator,
                self::STRONG, self::WEAK,
                Formation::F_4_3_3, Formation::F_4_3_3,
                $homeMentality, $awayMentality,
                $baseGoals, $fraction,
                false,
            );

            [$h, $a] = $this->applyTacticalModifiers->invoke(
                $this->simulator,
                $h, $a,
                $homeStyle, $awayStyle,
                $homePress, $awayPress,
                $homeLine, $awayLine,
                $homeMentality, $awayMentality,
                $effectiveMinute,
                $strengthRatio,
            );

            $homeXG += $h;
            $awayXG += $a;
        }

        return [$homeXG, $awayXG];
    }

    /**
     * Sample many scorelines from the shared outcome model and aggregate the
     * stats the acceptance criteria care about.
     *
     * @return array{avgHome: float, avgTotal: float, blowoutRate: float, maxTotal: int}
     */
    private function sampleMany(float $homeXG, float $awayXG, int $runs = 5000): array
    {
        $homeGoals = 0;
        $totalGoals = 0;
        $blowouts = 0;
        $maxTotal = 0;

        for ($i = 0; $i < $runs; $i++) {
            [$h, $a] = MatchOutcomeModel::sampleScoreline($homeXG, $awayXG);
            $homeGoals += $h;
            $totalGoals += $h + $a;
            $maxTotal = max($maxTotal, $h + $a);
            if ($h - $a >= 3) {
                $blowouts++;
            }
        }

        return [
            'avgHome' => $homeGoals / $runs,
            'avgTotal' => $totalGoals / $runs,
            'blowoutRate' => $blowouts / $runs,
            'maxTotal' => $maxTotal,
        ];
    }

    /**
     * Home (attacker) xG for a single late/early period against a full defensive
     * shell, at a given minute. Used to isolate the fatigue ramp.
     */
    private function attackerXgAgainstShell(
        float $effectiveMinute,
        float $homeStrength = self::STRONG,
        float $awayStrength = self::WEAK,
    ): float {
        $strengthRatio = $awayStrength > 0 ? $homeStrength / $awayStrength : 1.0;

        [$h, $a] = $this->calculateBaseExpectedGoals->invoke(
            $this->simulator,
            $homeStrength, $awayStrength,
            Formation::F_4_3_3, Formation::F_4_3_3,
            Mentality::BALANCED, Mentality::DEFENSIVE,
            (float) config('match_simulation.base_goals', 1.5), 45.0 / 93.0,
            false,
        );

        [$homeXG] = $this->applyTacticalModifiers->invoke(
            $this->simulator,
            $h, $a,
            PlayingStyle::BALANCED, PlayingStyle::COUNTER_ATTACK,
            PressingIntensity::STANDARD, PressingIntensity::LOW_BLOCK,
            DefensiveLineHeight::NORMAL, DefensiveLineHeight::DEEP,
            Mentality::BALANCED, Mentality::DEFENSIVE,
            $effectiveMinute,
            $strengthRatio,
        );

        return $homeXG;
    }
}
