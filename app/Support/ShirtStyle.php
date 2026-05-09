<?php

namespace App\Support;

/**
 * Server-side equivalent of resources/js/modules/pitch-renderer.js
 * (`getShirtStyle` / `getNumberStyle`). Used when a pitch is rendered
 * statically (e.g. opponent scouting) without Alpine wiring.
 *
 * Keep this in sync with the JS version — the visual output must match.
 */
class ShirtStyle
{
    private const GK_BACKGROUND = 'background: linear-gradient(to bottom right, #FBBF24, #D97706)';
    private const GK_NUMBER = 'color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)';
    private const FALLBACK_BACKGROUND = 'background: linear-gradient(to bottom right, #3B82F6, #1D4ED8)';
    private const FALLBACK_NUMBER = 'color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)';

    /**
     * @param array{pattern?: string, primary?: string, secondary?: string, number?: string}|null $colors
     */
    public static function background(string $role, ?array $colors): string
    {
        if ($role === 'Goalkeeper') {
            return self::GK_BACKGROUND;
        }

        if (!$colors) {
            return self::FALLBACK_BACKGROUND;
        }

        $p = $colors['primary'] ?? '#3B82F6';
        $s = $colors['secondary'] ?? '#FFFFFF';

        return match ($colors['pattern'] ?? 'solid') {
            'stripes' => "background: linear-gradient(90deg, {$s} 3px, {$p} 3px, {$p} 9px, {$s} 9px); background-size: 12px 100%; background-position: center",
            'hoops' => "background: linear-gradient(0deg, {$s} 3px, {$p} 3px, {$p} 9px, {$s} 9px); background-size: 100% 12px; background-position: center",
            'sash' => "background: linear-gradient(135deg, {$p} 0%, {$p} 35%, {$s} 35%, {$s} 65%, {$p} 65%, {$p} 100%)",
            'bar' => "background: linear-gradient(90deg, {$p} 0%, {$p} 35%, {$s} 35%, {$s} 65%, {$p} 65%, {$p} 100%)",
            'halves' => "background: linear-gradient(90deg, {$p} 50%, {$s} 50%)",
            'quarters' => "background: conic-gradient(from 90deg, {$p} 0deg 90deg, {$s} 90deg 180deg, {$p} 180deg 270deg, {$s} 270deg 360deg)",
            'chevron' => self::chevronBackground($p, $s),
            default => "background: {$p}",
        };
    }

    /**
     * @param array{pattern?: string, primary?: string, secondary?: string, number?: string}|null $colors
     */
    public static function number(string $role, ?array $colors): string
    {
        if ($role === 'Goalkeeper') {
            return self::GK_NUMBER;
        }

        if (!$colors) {
            return self::FALLBACK_NUMBER;
        }

        $color = $colors['number'] ?? '#FFFFFF';
        $pattern = $colors['pattern'] ?? 'solid';

        if ($pattern !== 'solid') {
            $backdrop = self::backdropColor($colors);
            // CC = ~80% alpha, matches the JS implementation.
            return "color: {$color}; background: {$backdrop}CC; text-shadow: 0 1px 2px rgba(0,0,0,0.15)";
        }

        return "color: {$color}; text-shadow: 0 1px 2px rgba(0,0,0,0.2)";
    }

    private static function chevronBackground(string $primary, string $secondary): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' preserveAspectRatio='none'><path d='M0 14 L50 74 L100 14 L100 34 L50 94 L0 34 Z' fill='{$secondary}'/></svg>";
        $encoded = rawurlencode($svg);

        return "background-color: {$primary}; background-image: url(\"data:image/svg+xml,{$encoded}\"); background-size: 100% 100%; background-repeat: no-repeat";
    }

    /**
     * Pick the shirt color (primary or secondary) that contrasts least with the
     * number color — used as a translucent backdrop behind the number on
     * patterned shirts so it stays legible.
     *
     * @param array{primary?: string, secondary?: string, number?: string} $colors
     */
    private static function backdropColor(array $colors): string
    {
        $numLum = self::hexLuminance($colors['number'] ?? '#FFFFFF');
        $priLum = self::hexLuminance($colors['primary'] ?? '#3B82F6');
        $secLum = self::hexLuminance($colors['secondary'] ?? '#FFFFFF');

        return abs($numLum - $priLum) >= abs($numLum - $secLum)
            ? ($colors['primary'] ?? '#3B82F6')
            : ($colors['secondary'] ?? '#FFFFFF');
    }

    private static function hexLuminance(string $hex): float
    {
        if (strlen($hex) < 7) {
            return 0.5;
        }

        $r = hexdec(substr($hex, 1, 2)) / 255;
        $g = hexdec(substr($hex, 3, 2)) / 255;
        $b = hexdec(substr($hex, 5, 2)) / 255;

        return 0.299 * $r + 0.587 * $g + 0.114 * $b;
    }
}
