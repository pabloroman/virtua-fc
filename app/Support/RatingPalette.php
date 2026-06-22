<?php

namespace App\Support;

/**
 * Single source of truth for the OVR/rating colour band, so the threshold
 * boundaries stay identical wherever a rating is coloured — the <x-rating-badge>
 * component and any payload that pre-computes the class for an Alpine-rendered
 * row (e.g. the scouting shortlist).
 */
class RatingPalette
{
    /**
     * The `rating-*` CSS class for a rating value (see the rating-* utilities
     * defined in resources/css/app.css).
     */
    public static function classFor(int $value): string
    {
        return match (true) {
            $value >= 80 => 'rating-elite',
            $value >= 70 => 'rating-good',
            $value >= 60 => 'rating-average',
            $value >= 50 => 'rating-below',
            default => 'rating-poor',
        };
    }
}
