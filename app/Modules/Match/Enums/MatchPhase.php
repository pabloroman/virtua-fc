<?php

namespace App\Modules\Match\Enums;

/**
 * Phases of a football match, in chronological order.
 *
 * Every `match_events` row carries a `phase` value plus a `minute` (the base
 * minute *within* the phase) and an optional `stoppage_minute`. This tuple is
 * the canonical position of an event — minute alone is ambiguous in stoppage
 * time (a 91' event could be regular-stoppage or ET first minute).
 */
enum MatchPhase: string
{
    case FIRST_HALF                = 'first_half';
    case FIRST_HALF_STOPPAGE       = 'first_half_stoppage';
    case SECOND_HALF               = 'second_half';
    case SECOND_HALF_STOPPAGE      = 'second_half_stoppage';
    case ET_FIRST_HALF             = 'et_first_half';
    case ET_FIRST_HALF_STOPPAGE    = 'et_first_half_stoppage';
    case ET_SECOND_HALF            = 'et_second_half';
    case ET_SECOND_HALF_STOPPAGE   = 'et_second_half_stoppage';
    case PENALTIES                 = 'penalties';

    /**
     * Total ordering of phases. Combined with (minute, stoppage_minute) this
     * gives a strict ordering for any pair of events.
     */
    public function ordinal(): int
    {
        return match ($this) {
            self::FIRST_HALF              => 1,
            self::FIRST_HALF_STOPPAGE     => 2,
            self::SECOND_HALF             => 3,
            self::SECOND_HALF_STOPPAGE    => 4,
            self::ET_FIRST_HALF           => 5,
            self::ET_FIRST_HALF_STOPPAGE  => 6,
            self::ET_SECOND_HALF          => 7,
            self::ET_SECOND_HALF_STOPPAGE => 8,
            self::PENALTIES               => 9,
        };
    }

    public function isExtraTime(): bool
    {
        return match ($this) {
            self::ET_FIRST_HALF,
            self::ET_FIRST_HALF_STOPPAGE,
            self::ET_SECOND_HALF,
            self::ET_SECOND_HALF_STOPPAGE => true,
            default => false,
        };
    }

    public function isRegulation(): bool
    {
        return match ($this) {
            self::FIRST_HALF,
            self::FIRST_HALF_STOPPAGE,
            self::SECOND_HALF,
            self::SECOND_HALF_STOPPAGE => true,
            default => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function regulation(): array
    {
        return [self::FIRST_HALF, self::FIRST_HALF_STOPPAGE, self::SECOND_HALF, self::SECOND_HALF_STOPPAGE];
    }

    /**
     * @return list<self>
     */
    public static function extraTime(): array
    {
        return [self::ET_FIRST_HALF, self::ET_FIRST_HALF_STOPPAGE, self::ET_SECOND_HALF, self::ET_SECOND_HALF_STOPPAGE];
    }
}
