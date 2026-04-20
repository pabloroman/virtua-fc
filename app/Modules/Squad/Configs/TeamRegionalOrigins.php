<?php

namespace App\Modules\Squad\Configs;

/**
 * Maps clubs to a regional naming origin that overrides the default Spanish
 * (es_ES) locale used for generated players. Basque and Catalan clubs draw
 * from custom Faker providers ({@see \App\Support\Faker\Provider\eu_ES\Person}
 * and {@see \App\Support\Faker\Provider\ca_ES\Person}), so their canteranos
 * and AI replenishment players get culturally appropriate names rather than
 * generic Spanish ones.
 *
 * Nationality stays "Spain" for these clubs — only the name source changes.
 * Clubs not listed here fall through to the normal nationality→locale mapping
 * in PlayerNameGenerator.
 */
class TeamRegionalOrigins
{
    public const REGION_BASQUE = 'basque';
    public const REGION_CATALAN = 'catalan';

    /**
     * Team name → region. Team names match the `name` column on the `teams`
     * table, which in turn comes from data/<year>/<league>/teams.json.
     */
    private const TEAMS = [
        // Basque clubs (Euskadi / País Vasco + Navarre via Osasuna).
        'Athletic Club' => self::REGION_BASQUE,
        'Real Sociedad' => self::REGION_BASQUE,
        'Real Sociedad B' => self::REGION_BASQUE,
        'Deportivo Alavés' => self::REGION_BASQUE,
        'CA Osasuna' => self::REGION_BASQUE,
        'SD Eibar' => self::REGION_BASQUE,

        // Catalan clubs (Catalunya).
        'FC Barcelona' => self::REGION_CATALAN,
        'RCD Espanyol Barcelona' => self::REGION_CATALAN,
        'Girona FC' => self::REGION_CATALAN,
    ];

    /**
     * Return the region for a given team name, or null if the team has no
     * regional naming override.
     */
    public static function regionFor(?string $teamName): ?string
    {
        if ($teamName === null) {
            return null;
        }

        return self::TEAMS[$teamName] ?? null;
    }
}
