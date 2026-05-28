<?php

namespace App\Modules\Competition\Services;

use App\Models\Team;

/**
 * Picks the neutral-venue stadium for matches that are not played at a
 * finalist's home ground.
 *
 * - Copa del Rey (ESPCUP) final is always at La Cartuja by real-world
 *   designation.
 * - The Spanish Supercup (ESPSUP) is a Final Four hosted abroad: *every*
 *   game (both semi-finals and the final) is played at the King Abdullah
 *   Sports City Stadium.
 * - UEFA finals (UCL/UEL/UECL) and the UEFA Super Cup (UEFASUP) rotate
 *   across top-tier European grounds (>=50k), so we sample a random club
 *   stadium from the Team table, excluding the two finalists to guarantee
 *   the venue is genuinely neutral. If no eligible stadium is found we fall
 *   back to a guaranteed neutral venue rather than silently yielding none
 *   (which would leak the home club's capacity into the final).
 */
class NeutralVenueResolver
{
    private const ESPCUP_VENUE = [
        'name' => 'La Cartuja',
        'capacity' => 70000,
    ];

    private const ESPSUP_VENUE = [
        'name' => 'King Abdullah Sports City Stadium',
        'capacity' => 62345,
    ];

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

    /**
     * @return array{name: string, capacity: int}|null
     */
    public function resolve(string $competitionId, string $roundName, string $homeTeamId, string $awayTeamId): ?array
    {
        // Spanish Supercup is a Final Four hosted abroad — semis and final
        // alike are played at the same neutral venue.
        if ($competitionId === 'ESPSUP') {
            return self::ESPSUP_VENUE;
        }

        // The remaining competitions only move to a neutral venue for the
        // single-legged final.
        if ($roundName !== self::FINAL_ROUND) {
            return null;
        }

        if ($competitionId === 'ESPCUP') {
            return self::ESPCUP_VENUE;
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
