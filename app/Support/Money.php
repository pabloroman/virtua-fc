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

    /**
     * Round a price in cents to the nearest "nice" increment.
     *
     * Uses tiered granularity so small values don't round down to zero:
     *   >= €1M   → round to nearest €100K
     *   >= €100K → round to nearest €50K
     *   otherwise → round to nearest €10K
     *
     * @param int $cents Amount in cents
     * @return int Rounded amount in cents
     */
    public static function roundPrice(int $cents): int
    {
        if ($cents >= 100_000_000) {      // >= €1M
            return (int) (round($cents / 10_000_000) * 10_000_000);
        }
        if ($cents >= 10_000_000) {       // >= €100K
            return (int) (round($cents / 5_000_000) * 5_000_000);
        }

        return (int) (round($cents / 1_000_000) * 1_000_000); // round to €10K
    }

    /**
     * Parse a market value string like "€10M" or "500k" into cents.
     *
     * @param string|null $value e.g., "€10M", "€500K", "250000"
     * @return int Amount in cents
     */
    public static function parseMarketValue(?string $value): int
    {
        if (!$value) {
            return 0;
        }

        $value = preg_replace('/[€$£\s]/', '', $value);

        if (preg_match('/^([\d.]+)(m|k)?$/i', $value, $matches)) {
            $number = (float) $matches[1];
            $multiplier = strtolower($matches[2] ?? '');

            $amount = match ($multiplier) {
                'm' => $number * 1_000_000,
                'k' => $number * 1_000,
                default => $number,
            };

            return (int) ($amount * 100);
        }

        return 0;
    }
}
