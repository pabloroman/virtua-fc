<?php

namespace App\Modules\Transfer\Enums;

use Carbon\Carbon;

enum TransferWindowType: string
{
    case SUMMER = 'summer';
    case WINTER = 'winter';

    /**
     * @return int[]
     */
    public function months(): array
    {
        return match ($this) {
            self::SUMMER => [7, 8],
            self::WINTER => [1],
        };
    }

    /**
     * First month after the window ends (September for summer, February for winter).
     */
    public function closeMonth(): int
    {
        return match ($this) {
            self::SUMMER => 9,
            self::WINTER => 2,
        };
    }

    public function containsMonth(int $month): bool
    {
        return in_array($month, $this->months());
    }

    public function label(): string
    {
        return match ($this) {
            self::SUMMER => __('app.summer_window'),
            self::WINTER => __('app.winter_window'),
        };
    }

    public static function fromMonth(int $month): ?self
    {
        return match (true) {
            in_array($month, self::SUMMER->months()) => self::SUMMER,
            in_array($month, self::WINTER->months()) => self::WINTER,
            default => null,
        };
    }

    public static function fromDate(Carbon $date): ?self
    {
        return self::fromMonth($date->month);
    }

    /**
     * Resolve the current window value string, defaulting to 'summer'.
     */
    public static function currentValue(Carbon $date): string
    {
        return self::fromDate($date)?->value ?? self::SUMMER->value;
    }
}
