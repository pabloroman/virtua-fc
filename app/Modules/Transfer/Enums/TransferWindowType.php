<?php

namespace App\Modules\Transfer\Enums;

use Carbon\Carbon;

enum TransferWindowType: string
{
    case Summer = 'summer';
    case Winter = 'winter';

    /**
     * @return int[]
     */
    public function months(): array
    {
        return match ($this) {
            self::Summer => [7, 8],
            self::Winter => [1],
        };
    }

    /**
     * First month after the window ends (September for summer, February for winter).
     */
    public function closeMonth(): int
    {
        return match ($this) {
            self::Summer => 9,
            self::Winter => 2,
        };
    }

    public function containsMonth(int $month): bool
    {
        return in_array($month, $this->months());
    }

    public function label(): string
    {
        return match ($this) {
            self::Summer => __('app.summer_window'),
            self::Winter => __('app.winter_window'),
        };
    }

    public static function fromMonth(int $month): ?self
    {
        return match (true) {
            in_array($month, self::Summer->months()) => self::Summer,
            in_array($month, self::Winter->months()) => self::Winter,
            default => null,
        };
    }

    public static function fromDate(Carbon $date): ?self
    {
        return self::fromMonth($date->month);
    }
}
