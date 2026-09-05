<?php

namespace App\Modules\Competition\Services;

use App\Models\Team;

/**
 * Picks the neutral-venue stadium for matches that are not played at a
 * finalist's home ground.
 *
 * - Domestic cups declare their neutral grounds in config/countries.php
 *   (`domestic_cups.<cup>.neutral_venues`, keyed by round name, '*' for
 *   every round): the Copa del Rey final at La Cartuja, every game of the
 *   Spanish Supercup's final four in Saudi Arabia, FA Cup semi-finals and
 *   final at Wembley.
 * - UEFA finals (UCL/UEL/UECL) and the UEFA Super Cup (UEFASUP) rotate
 *   across top-tier European grounds (>=50k), so we sample a random club
 *   stadium from the Team table, excluding the two finalists to guarantee
 *   the venue is genuinely neutral. If no eligible stadium is found we fall
 *   back to a guaranteed neutral venue rather than silently yielding none
 *   (which would leak the home club's capacity into the final).
 */
class NeutralVenueResolver
{
    /**
     * Guaranteed neutral venue for UEFA finals when no eligible club
     * stadium can be sampled (e.g. minimal seed/test datasets).
     */
    private const UEFA_FALLBACK_VENUE = [
        'name' => 'Wembley Stadium',
        'capacity' => 90000,
    ];

    private const EUROPEAN_FINAL_COMPETITIONS = ['UCL', 'UEL', 'UECL', 'UEFASUP'];
    private const FINAL_ROUND = 'cup.final';
    private const MIN_CAPACITY = 50000;

    public function __construct(
        private readonly CountryConfig $countryConfig = new CountryConfig(),
    ) {}

    /**
     * @return array{name: string, capacity: int}|null
     */
    public function resolve(string $competitionId, string $roundName, string $homeTeamId, string $awayTeamId): ?array
    {
        $declared = $this->countryConfig->neutralVenue($competitionId, $roundName);
        if ($declared) {
            return ['name' => $declared['name'], 'capacity' => (int) $declared['capacity']];
        }

        // UEFA competitions only move to a neutral venue for the
        // single-legged final.
        if ($roundName !== self::FINAL_ROUND) {
            return null;
        }

        if (in_array($competitionId, self::EUROPEAN_FINAL_COMPETITIONS, true)) {
            return $this->randomEuropeanVenue($homeTeamId, $awayTeamId);
        }

        return null;
    }

    /**
     * @return array{name: string, capacity: int}
     */
    private function randomEuropeanVenue(string $homeTeamId, string $awayTeamId): array
    {
        $team = Team::query()
            ->where('type', 'club')
            ->where('is_placeholder', false)
            ->where('stadium_seats', '>=', self::MIN_CAPACITY)
            ->whereNotNull('stadium_name')
            ->whereNotIn('id', [$homeTeamId, $awayTeamId])
            ->inRandomOrder()
            ->first();

        if (!$team) {
            return self::UEFA_FALLBACK_VENUE;
        }

        return [
            'name' => $team->stadium_name,
            'capacity' => (int) $team->stadium_seats,
        ];
    }
}
