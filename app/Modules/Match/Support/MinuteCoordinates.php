<?php

namespace App\Modules\Match\Support;

use App\Modules\Match\Enums\MatchPhase;

/**
 * Convert between "raw absolute minute" (the simulator's internal integer
 * clock) and the persisted phase tuple (phase, base minute in phase, stoppage
 * minute).
 *
 * Raw absolute time runs as a single increasing integer across the whole
 * match. Phase boundaries depend on the match's sampled stoppage durations:
 *
 *   [1 .. 45]                            FIRST_HALF
 *   [46 .. 45+fhs]                       FIRST_HALF_STOPPAGE
 *   [45+fhs+1 .. 90+fhs]                 SECOND_HALF
 *   [90+fhs+1 .. 90+fhs+shs]             SECOND_HALF_STOPPAGE
 *   [90+fhs+shs+1 .. 105+fhs+shs]        ET_FIRST_HALF
 *   [... +etfhs]                         ET_FIRST_HALF_STOPPAGE
 *   [... +15]                            ET_SECOND_HALF
 *   [... +etshs]                         ET_SECOND_HALF_STOPPAGE
 *
 * Display base minutes always count in standard football time (1–45, 46–90,
 * 91–105, 106–120) regardless of how much stoppage was actually played.
 */
final class MinuteCoordinates
{
    /**
     * Decompose a raw absolute minute into (phase, base_minute, stoppage_minute).
     *
     * @return array{phase: MatchPhase, minute: int, stoppage_minute: ?int}
     */
    public static function decompose(
        int $rawMinute,
        int $firstHalfStoppage,
        int $secondHalfStoppage,
        ?int $etFirstHalfStoppage = null,
        ?int $etSecondHalfStoppage = null,
    ): array {
        $fhs = max(0, $firstHalfStoppage);
        $shs = max(0, $secondHalfStoppage);

        // Regulation
        if ($rawMinute <= 45) {
            return ['phase' => MatchPhase::FIRST_HALF, 'minute' => $rawMinute, 'stoppage_minute' => null];
        }
        if ($rawMinute <= 45 + $fhs) {
            return ['phase' => MatchPhase::FIRST_HALF_STOPPAGE, 'minute' => 45, 'stoppage_minute' => $rawMinute - 45];
        }
        if ($rawMinute <= 90 + $fhs) {
            return ['phase' => MatchPhase::SECOND_HALF, 'minute' => $rawMinute - $fhs, 'stoppage_minute' => null];
        }
        if ($rawMinute <= 90 + $fhs + $shs) {
            return ['phase' => MatchPhase::SECOND_HALF_STOPPAGE, 'minute' => 90, 'stoppage_minute' => $rawMinute - 90 - $fhs];
        }

        // Extra time
        $etfhs = max(0, $etFirstHalfStoppage ?? 0);
        $etshs = max(0, $etSecondHalfStoppage ?? 0);
        $regOffset = $fhs + $shs;

        if ($rawMinute <= 105 + $regOffset) {
            return ['phase' => MatchPhase::ET_FIRST_HALF, 'minute' => $rawMinute - $regOffset, 'stoppage_minute' => null];
        }
        if ($rawMinute <= 105 + $regOffset + $etfhs) {
            return ['phase' => MatchPhase::ET_FIRST_HALF_STOPPAGE, 'minute' => 105, 'stoppage_minute' => $rawMinute - 105 - $regOffset];
        }
        if ($rawMinute <= 120 + $regOffset + $etfhs) {
            return ['phase' => MatchPhase::ET_SECOND_HALF, 'minute' => $rawMinute - $regOffset - $etfhs, 'stoppage_minute' => null];
        }
        if ($rawMinute <= 120 + $regOffset + $etfhs + $etshs) {
            return ['phase' => MatchPhase::ET_SECOND_HALF_STOPPAGE, 'minute' => 120, 'stoppage_minute' => $rawMinute - 120 - $regOffset - $etfhs];
        }

        // Past the configured stoppage. Clamp to the last stoppage minute of
        // whichever half we ran out at. This shouldn't happen if simulators
        // respect the configured toMinute, but the clamp keeps the data sane.
        return ['phase' => MatchPhase::ET_SECOND_HALF_STOPPAGE, 'minute' => 120, 'stoppage_minute' => max(1, $etshs)];
    }

    /**
     * Recompose (phase, base minute, stoppage minute) into a raw absolute minute.
     *
     * For PENALTIES, returns a sentinel above any in-play minute.
     */
    public static function toAbsolute(
        MatchPhase $phase,
        int $minute,
        ?int $stoppageMinute,
        int $firstHalfStoppage,
        int $secondHalfStoppage,
        ?int $etFirstHalfStoppage = null,
        ?int $etSecondHalfStoppage = null,
    ): int {
        $fhs = max(0, $firstHalfStoppage);
        $shs = max(0, $secondHalfStoppage);
        $etfhs = max(0, $etFirstHalfStoppage ?? 0);
        $etshs = max(0, $etSecondHalfStoppage ?? 0);
        $stop = $stoppageMinute ?? 0;

        return match ($phase) {
            MatchPhase::FIRST_HALF                => $minute,
            MatchPhase::FIRST_HALF_STOPPAGE       => 45 + $stop,
            MatchPhase::SECOND_HALF               => $minute + $fhs,
            MatchPhase::SECOND_HALF_STOPPAGE      => 90 + $fhs + $stop,
            MatchPhase::ET_FIRST_HALF             => $minute + $fhs + $shs,
            MatchPhase::ET_FIRST_HALF_STOPPAGE    => 105 + $fhs + $shs + $stop,
            MatchPhase::ET_SECOND_HALF            => $minute + $fhs + $shs + $etfhs,
            MatchPhase::ET_SECOND_HALF_STOPPAGE   => 120 + $fhs + $shs + $etfhs + $stop,
            // Sentinel beyond any in-play minute so penalty events sort last.
            MatchPhase::PENALTIES                 => 999,
        };
    }

    /**
     * Last raw absolute minute of regulation given the match's regulation stoppage.
     */
    public static function regulationEnd(int $firstHalfStoppage, int $secondHalfStoppage): int
    {
        return 90 + max(0, $firstHalfStoppage) + max(0, $secondHalfStoppage);
    }

    /**
     * Last raw absolute minute of extra time given the match's stoppage values.
     */
    public static function extraTimeEnd(
        int $firstHalfStoppage,
        int $secondHalfStoppage,
        int $etFirstHalfStoppage,
        int $etSecondHalfStoppage,
    ): int {
        return 120 + max(0, $firstHalfStoppage) + max(0, $secondHalfStoppage)
            + max(0, $etFirstHalfStoppage) + max(0, $etSecondHalfStoppage);
    }
}
