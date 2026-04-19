<?php

namespace App\Modules\Stadium\Services;

use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameMatch;
use App\Models\MatchAttendance;
use App\Models\TeamReputation;

/**
 * Shapes the data shown on the Club > Stadium page: capacity, fan-loyalty
 * stat, most-recent home-match attendance, and the current season's
 * projected vs. actual matchday revenue. Pure read-side service — no
 * writes, no side effects.
 */
class StadiumSummaryService
{
    /**
     * @return array{
     *   stadium_name: ?string,
     *   capacity: int,
     *   loyalty_points: int,
     *   base_loyalty: int,
     *   loyalty_direction: 'rising'|'stable'|'declining',
     *   last_home_match: ?array{
     *     match: GameMatch,
     *     attendance: int,
     *     capacity_at_match: int,
     *     fill_rate: int,
     *   },
     *   finances: ?GameFinances,
     * }
     */
    public function build(Game $game): array
    {
        $team = $game->team;

        $reputation = TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        $loyaltyPoints = $reputation?->loyalty_points ?? 0;
        $baseLoyalty = $reputation?->base_loyalty ?? 0;

        // Match the 5-point band used by the reputation-direction hint for
        // consistency across the Club hub. Below that band loyalty is
        // considered "stable" even with small cosmetic drifts.
        $loyaltyDelta = $loyaltyPoints - $baseLoyalty;
        $direction = $loyaltyDelta > 5 ? 'rising' : ($loyaltyDelta < -5 ? 'declining' : 'stable');

        $lastHomeMatch = $this->resolveLastHomeMatch($game);

        return [
            'stadium_name' => $team->stadium_name,
            'capacity' => (int) ($team->stadium_seats ?? 0),
            'loyalty_points' => $loyaltyPoints,
            'base_loyalty' => $baseLoyalty,
            'loyalty_direction' => $direction,
            'last_home_match' => $lastHomeMatch,
            'finances' => $game->currentFinances,
        ];
    }

    /**
     * Most recent played home match that also has a persisted attendance
     * row. Returns null until the team has played its first home fixture of
     * the save (pre-season / new-game state).
     */
    private function resolveLastHomeMatch(Game $game): ?array
    {
        $match = GameMatch::query()
            ->where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', true)
            ->whereIn('id', MatchAttendance::query()->select('game_match_id')->where('game_id', $game->id))
            ->with(['awayTeam', 'competition'])
            ->orderByDesc('scheduled_date')
            ->first();

        if (!$match) {
            return null;
        }

        $attendance = MatchAttendance::where('game_match_id', $match->id)->first();

        if (!$attendance) {
            return null;
        }

        return [
            'match' => $match,
            'attendance' => (int) $attendance->attendance,
            'capacity_at_match' => (int) $attendance->capacity_at_match,
            'fill_rate' => $attendance->fillRatePercent(),
        ];
    }
}
