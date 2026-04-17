<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\MatchAttendance;
use App\Models\Team;
use App\Models\TeamReputation;

/**
 * Resolves the per-fixture MatchAttendance record. Idempotent — calling
 * twice for the same match returns the existing row, which makes it safe
 * to invoke from the pre-match orchestrator hook, the live-match view
 * fallback, and the MatchFinalized safety-net listener.
 */
class MatchAttendanceService
{
    public function __construct(
        private readonly DemandCurveService $demandCurve,
    ) {}

    /**
     * Return the MatchAttendance for this fixture, computing and persisting
     * it on first call. Returns null only when the match is at a neutral
     * venue (cup/European finals) — those don't generate matchday revenue
     * for the nominal home club, so we don't fabricate a row.
     */
    public function resolveForMatch(GameMatch $match, Game $game): ?MatchAttendance
    {
        $existing = MatchAttendance::where('game_match_id', $match->id)->first();
        if ($existing) {
            return $existing;
        }

        $competition = $match->competition ?? Competition::find($match->competition_id);
        if ($competition && $this->isNeutralVenue($match, $competition)) {
            return null;
        }

        $home = Team::find($match->home_team_id);
        if (!$home) {
            return null;
        }

        $homeRep = $this->loadReputation($game->id, $home->id);
        $awayRep = $this->loadReputation($game->id, $match->away_team_id);

        $homePosition = null;
        if ($competition && $competition->role === Competition::ROLE_LEAGUE) {
            $homePosition = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->where('team_id', $home->id)
                ->value('position');
            $homePosition = $homePosition !== null ? (int) $homePosition : null;
        }

        $attendance = $this->demandCurve->project(
            $home,
            $homeRep,
            $awayRep,
            $competition,
            $homePosition,
        );

        return MatchAttendance::create([
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'attendance' => $attendance,
            'capacity_at_match' => (int) ($home->stadium_seats ?? 0),
        ]);
    }

    /**
     * Cup and European finals are played at neutral venues — recording
     * attendance against the nominal home club would be misleading and
     * those matches don't feed the home club's matchday revenue anyway.
     */
    private function isNeutralVenue(GameMatch $match, Competition $competition): bool
    {
        if ($competition->role !== Competition::ROLE_DOMESTIC_CUP
            && $competition->role !== Competition::ROLE_EUROPEAN) {
            return false;
        }

        return $match->round_name === 'final';
    }

    /**
     * Load the game-scoped TeamReputation row, falling back to a synthetic
     * instance seeded from ClubProfile when no row exists (e.g. teams from
     * outside the primary game that occasionally appear in cups).
     */
    private function loadReputation(string $gameId, string $teamId): TeamReputation
    {
        $rep = TeamReputation::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->first();

        if ($rep) {
            return $rep;
        }

        $profile = ClubProfile::where('team_id', $teamId)->first();
        $level = $profile->reputation_level ?? ClubProfile::REPUTATION_LOCAL;
        $anchor = (int) ($profile->fan_loyalty ?? ClubProfile::FAN_LOYALTY_DEFAULT);
        $loyalty = $anchor * 10;

        $synthetic = new TeamReputation();
        $synthetic->game_id = $gameId;
        $synthetic->team_id = $teamId;
        $synthetic->reputation_level = $level;
        $synthetic->base_reputation_level = $level;
        $synthetic->reputation_points = TeamReputation::pointsForTier($level);
        $synthetic->base_loyalty = $loyalty;
        $synthetic->loyalty_points = $loyalty;

        return $synthetic;
    }
}
