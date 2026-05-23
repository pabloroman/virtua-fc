<?php

namespace App\Modules\Match\Support;

use App\Models\GameMatch;

/**
 * Per-match sampled stoppage durations for each half (regulation + ET).
 *
 * Carried as a value object instead of four scalar parameters because every
 * simulator entry point and persistence call needs all four together.
 */
final readonly class StoppageDurations
{
    public function __construct(
        public int $firstHalf,
        public int $secondHalf,
        public ?int $etFirstHalf = null,
        public ?int $etSecondHalf = null,
    ) {}

    public static function fromMatch(GameMatch $match): self
    {
        return new self(
            firstHalf: (int) ($match->first_half_stoppage ?? 0),
            secondHalf: (int) ($match->second_half_stoppage ?? 0),
            etFirstHalf: $match->et_first_half_stoppage,
            etSecondHalf: $match->et_second_half_stoppage,
        );
    }

    public function withExtraTime(int $etFirstHalf, int $etSecondHalf): self
    {
        return new self($this->firstHalf, $this->secondHalf, $etFirstHalf, $etSecondHalf);
    }

    /**
     * Last raw absolute minute of regulation.
     */
    public function regulationEnd(): int
    {
        return MinuteCoordinates::regulationEnd($this->firstHalf, $this->secondHalf);
    }

    /**
     * Last raw absolute minute of ET first half (including its stoppage).
     * The moment the live clock reaches ET half-time.
     */
    public function etFirstHalfEnd(): int
    {
        return 105 + $this->firstHalf + $this->secondHalf + ($this->etFirstHalf ?? 0);
    }

    /**
     * Last raw absolute minute of extra time (regulation end + 30 + ET stoppage).
     */
    public function extraTimeEnd(): int
    {
        return MinuteCoordinates::extraTimeEnd(
            $this->firstHalf,
            $this->secondHalf,
            $this->etFirstHalf ?? 0,
            $this->etSecondHalf ?? 0,
        );
    }
}
