<?php

namespace App\Modules\Competition\Services;

use App\Models\Team;

/**
 * Picks the neutral-venue stadium for a cup final.
 *
 * Copa del Rey is always at La Cartuja by real-world designation. UEFA
 * finals rotate across top-tier European grounds (>50k), so we sample
 * a random club stadium from the Team table, excluding the two finalists
 * to guarantee the venue is genuinely neutral.
 */
class FinalVenueResolver
{
    private const ESPCUP_VENUE = [
        'name' => 'La Cartuja',
        'capacity' => 70000,
    ];

    private const EUROPEAN_COMPETITIONS = ['UCL', 'UEL', 'UECL', 'UEFASUP'];
    private const MIN_CAPACITY = 50000;

    /**
     * @return array{name: string, capacity: int}|null
     */
    public function resolve(string $competitionId, string $homeTeamId, string $awayTeamId): ?array
    {
        if ($competitionId === 'ESPCUP') {
            return self::ESPCUP_VENUE;
        }

        if (in_array($competitionId, self::EUROPEAN_COMPETITIONS, true)) {
            return $this->randomEuropeanVenue($homeTeamId, $awayTeamId);
        }

        return null;
    }

    /**
     * @return array{name: string, capacity: int}|null
     */
    private function randomEuropeanVenue(string $homeTeamId, string $awayTeamId): ?array
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
            return null;
        }

        return [
            'name' => $team->stadium_name,
            'capacity' => (int) $team->stadium_seats,
        ];
    }
}
