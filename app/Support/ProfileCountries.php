<?php

namespace App\Support;

/**
 * Curated list of countries for user profile selection.
 * Derived from CountryCodeMapper, using canonical names only (no aliases).
 *
 * @return array<string, string> ISO 3166-1 alpha-2 code (uppercase) => country name
 */
class ProfileCountries
{
    /** @return array<string, string> */
    public static function all(): array
    {
        $countries = [];

        foreach (CountryCodeMapper::getMap() as $name => $code) {
            // Skip football-specific sub-country codes (e.g. gb-eng, gb-sct)
            if (str_contains($code, '-')) {
                continue;
            }

            $upper = strtoupper($code);

            // Keep only the first name per code (canonical, skip aliases)
            if (! isset($countries[$upper])) {
                $countries[$upper] = $name;
            }
        }

        asort($countries);

        return $countries;
    }
}
