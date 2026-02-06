<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount in cents to a display string.
     *
     * @param int $cents Amount in cents
     * @return string e.g., "€ 2.5M", "€ 450K", "€ 200"
     */
    public static function format(int $cents): string
    {
        $isNegative = $cents < 0;
        $euros = abs($cents) / 100;
        $prefix = $isNegative ? '-' : '';

        if ($euros >= 1_000_000) {
            $formatted = round($euros / 1_000_000, 1);
            // Remove .0 for whole numbers
            $formatted = ($formatted == (int) $formatted) ? (int) $formatted : $formatted;
            return "{$prefix}€ {$formatted}M";
        }

        if ($euros >= 1_000) {
            $formatted = round($euros / 1_000);
            return "{$prefix}€ {$formatted}K";
        }

        return "{$prefix}€ " . number_format($euros, 0);
    }

    /**
     * Format with explicit sign prefix for positive values.
     *
     * @param int $cents Amount in cents
     * @return string e.g., "+€ 2.5M", "-€ 450K"
     */
    public static function formatSigned(int $cents): string
    {
        if ($cents >= 0) {
            return '+' . self::format($cents);
        }
        return self::format($cents);
    }
}
