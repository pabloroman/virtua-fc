<?php

namespace App\Support;

use App\Models\Competition;

class CompetitionColors
{
    private const VARIANTS = [
        'league' => [
            'banner_bg' => 'bg-amber-600',
            'banner_badge' => 'bg-surface-800/20 text-white',
            'banner_text' => 'text-amber-100',
            'border' => 'border-l-accent-gold',
            'badge_bg' => 'bg-accent-gold/20',
            'badge_text' => 'text-accent-gold',
            'dot' => 'bg-accent-gold',
        ],
        'cup' => [
            'banner_bg' => 'bg-emerald-600',
            'banner_badge' => 'bg-surface-800/20 text-white',
            'banner_text' => 'text-emerald-100',
            'border' => 'border-l-accent-green',
            'badge_bg' => 'bg-accent-green/20',
            'badge_text' => 'text-accent-green',
            'dot' => 'bg-accent-green',
        ],
        'european' => [
            'banner_bg' => 'bg-blue-600',
            'banner_badge' => 'bg-surface-800/20 text-white',
            'banner_text' => 'text-blue-100',
            'border' => 'border-l-accent-blue',
            'badge_bg' => 'bg-accent-blue/20',
            'badge_text' => 'text-accent-blue',
            'dot' => 'bg-accent-blue',
        ],
        'preseason' => [
            'banner_bg' => 'bg-accent-blue',
            'banner_badge' => 'bg-surface-800/20 text-white',
            'banner_text' => 'text-sky-100',
            'border' => 'border-l-accent-blue',
            'badge_bg' => 'bg-accent-blue/20',
            'badge_text' => 'text-accent-blue',
            'dot' => 'bg-accent-blue',
        ],
    ];

    /**
     * Determine the color category for a competition.
     *
     * @return 'league'|'cup'|'european'|'preseason'
     */
    public static function category(?Competition $competition): string
    {
        if (! $competition) {
            return 'league';
        }

        if (($competition->handler_type ?? '') === 'preseason') {
            return 'preseason';
        }

        return match ($competition->role ?? '') {
            Competition::ROLE_EUROPEAN => 'european',
            Competition::ROLE_DOMESTIC_CUP => 'cup',
            default => 'league',
        };
    }

    /**
     * Get banner classes (bg, badge pill, text color).
     *
     * @return array{bg: string, badge: string, text: string}
     */
    public static function banner(?Competition $competition): array
    {
        $v = self::VARIANTS[self::category($competition)];

        return [
            'bg' => $v['banner_bg'],
            'badge' => $v['banner_badge'],
            'text' => $v['banner_text'],
        ];
    }

    /**
     * Get the border-left class for fixture rows.
     */
    public static function border(?Competition $competition): string
    {
        return self::VARIANTS[self::category($competition)]['border'];
    }

    /**
     * Get the dot background class for fixture rows.
     */
    public static function dot(?Competition $competition): string
    {
        return self::VARIANTS[self::category($competition)]['dot'];
    }

    /**
     * Get badge classes (standalone competition badge).
     *
     * @return array{bg: string, text: string}
     */
    public static function badge(?Competition $competition): array
    {
        $v = self::VARIANTS[self::category($competition)];

        return [
            'bg' => $v['badge_bg'],
            'text' => $v['badge_text'],
        ];
    }
}
