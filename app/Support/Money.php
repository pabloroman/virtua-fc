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
        $euros = $cents / 100;

        if ($euros >= 1_000_000) {
            $formatted = round($euros / 1_000_000, 1);
            // Remove .0 for whole numbers
            $formatted = ($formatted == (int) $formatted) ? (int) $formatted : $formatted;
            return "€ {$formatted}M";
        }

        if ($euros >= 1_000) {
            $formatted = round($euros / 1_000);
            return "€ {$formatted}K";
        }

        return "€ " . number_format($euros, 0);
    }

    /**
     * Format with explicit sign prefix for positive values.
     *
     * @param int $cents Amount in cents
     * @return string e.g., "+€ 2.5M", "-€ 450K"
     */
    public static function formatSigned(int $cents): string
    {
        $prefix = $cents >= 0 ? '+' : '';
        return $prefix . self::format($cents);
    }
}
