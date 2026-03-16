<?php

namespace App\Support;

use Locale;

/**
 * Curated list of countries for user profile selection.
 * Returns country names translated to the current app locale via PHP intl.
 *
 * @return array<string, string> ISO 3166-1 alpha-2 code (uppercase) => localized country name
 */
class ProfileCountries
{
    /** @return array<string, string> */
    public static function all(): array
    {
        $locale = app()->getLocale();
        $countries = [];

        foreach (CountryCodeMapper::getMap() as $name => $code) {
            // Skip football-specific sub-country codes (e.g. gb-eng, gb-sct)
            if (str_contains($code, '-')) {
                continue;
            }

            $upper = strtoupper($code);

            // Keep only one entry per code
            if (! isset($countries[$upper])) {
                $localized = Locale::getDisplayRegion('und_'.$upper, $locale);

                // Fall back to English name if intl returns the code itself
                $countries[$upper] = ($localized !== $upper) ? $localized : $name;
            }
        }

        asort($countries);

        return $countries;
    }
}
