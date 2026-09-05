<?php

namespace App\Support;

/**
 * Canonical club names.
 *
 * The Transfermarkt scrape occasionally re-spells a club between season
 * refreshes ("Athletic Club" → "Athletic Bilbao", "RC Celta" → "Celta de
 * Vigo"). Teams are matched by transfermarkt_id, so a re-spelling never
 * creates a duplicate row — but every name-keyed lookup in the codebase
 * (club profiles, kit colours, regional player origins, academy nationality
 * bias) silently misses, and the club quietly loses its curated data.
 *
 * Everything that turns scraped club data into a team name goes through
 * canonical() so that only one spelling per club ever reaches the database.
 */
class ClubNames
{
    /**
     * Scrape spelling → the name the game uses. Both directions of a rename
     * appear here when the upstream data has flip-flopped, so an existing row
     * seeded under the old spelling and a fresh scrape converge on the same
     * canonical name.
     */
    private const CANONICAL = [
        'Athletic Bilbao' => 'Athletic Club',
        'Celta de Vigo' => 'RC Celta',
        'Deportivo de A Coruña' => 'Deportivo A Coruña',
        'Panathinaikos' => 'Panathinaikos FC',
        'CS Universitatea Craiova' => 'Universitatea Craiova',
    ];

    public static function canonical(string $name): string
    {
        return self::CANONICAL[$name] ?? $name;
    }

    /**
     * Scrape spellings that resolve to a canonical name. Exposed so
     * app:validate-season doesn't report a renamed club as unprofiled.
     *
     * @return array<int, string>
     */
    public static function aliases(): array
    {
        return array_keys(self::CANONICAL);
    }
}
