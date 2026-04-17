<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\TeamReputation;

/**
 * Reads and writes the fan-loyalty stats that live on TeamReputation.
 *
 * Loyalty follows the same two-layer pattern as reputation:
 *   - base_loyalty   — the seeded anchor (copied from ClubProfile.fan_loyalty
 *                      at game start). Captures cultural identity; never
 *                      moves during the game.
 *   - loyalty_points — the current, drifting value. Nudged by season-end
 *                      outcomes (Phase 1) and, later, by ticket pricing and
 *                      homegrown-star dynamics. Clamped so it can't fall
 *                      more than MAX_LOYALTY_DROP_BELOW_BASE below base —
 *                      the "Newcastle doesn't lose its fans" floor.
 *
 * Two entry points:
 *   - seedInitialValue() — the value SetupNewGame writes when it creates
 *     the TeamReputation row for a new game.
 *   - applySeasonEndUpdate() — the season-close nudge applied by
 *     FanLoyaltyUpdateProcessor.
 */
class FanLoyaltyService
{
    /**
     * Seed value for a fresh TeamReputation row. Takes the curated 0-10
     * anchor from ClubProfile.fan_loyalty and scales it to the 0-100
     * internal range used by the demand curve and season-end drift.
     * A null curated value (shouldn't happen — column has a default)
     * falls back to the scale midpoint.
     */
    public function seedInitialValue(?int $curatedLoyalty): int
    {
        $anchor = $curatedLoyalty ?? ClubProfile::FAN_LOYALTY_DEFAULT;
        $anchor = max(ClubProfile::FAN_LOYALTY_MIN, min(ClubProfile::FAN_LOYALTY_MAX, $anchor));

        return $this->clamp($anchor * 10);
    }

    /**
     * Recompute and persist loyalty_points for every TeamReputation row in
     * the game using the just-finished season's outcomes.
     *
     * AI clubs are treated identically to the user's club — the demand
     * curve applies to everyone, so loyalty must evolve everywhere.
     * Standings come from GameStanding for the user's league and
     * SimulatedSeason for AI leagues (matches ReputationUpdateProcessor).
     */
    public function applySeasonEndUpdate(Game $game): void
    {
        $deltas = config('finances.loyalty_deltas', []);
        if (empty($deltas)) {
            return;
        }

        $finalPositions = $this->collectFinalPositions($game);
        $cupWins = $this->collectCupWins($game);
        $leagueWinners = $this->collectLeagueWinners($game);

        $reputations = TeamReputation::where('game_id', $game->id)->get();

        foreach ($reputations as $rep) {
            $delta = (int) ($deltas['gravity'] ?? 0);

            if (in_array($rep->team_id, $leagueWinners, true)) {
                $delta += (int) ($deltas['league_title'] ?? 0);
            }

            $cupCount = $cupWins[$rep->team_id] ?? 0;
            if ($cupCount > 0) {
                $delta += $cupCount * (int) ($deltas['cup'] ?? 0);
            }

            $position = $finalPositions[$rep->team_id] ?? null;
            if ($position !== null) {
                if ($position <= 4) {
                    $delta += (int) ($deltas['top_four_finish'] ?? 0);
                } elseif ($position >= 18) {
                    // Loose relegation-zone proxy that works for both 20- and
                    // 22-team formats. Specific promotion/relegation events
                    // become first-class signals in a later phase.
                    $delta += (int) ($deltas['bottom_three_finish'] ?? 0);
                }
            }

            $rep->loyalty_points = $this->clampWithBaseFloor(
                ((int) $rep->loyalty_points) + $delta,
                (int) $rep->base_loyalty,
            );
            $rep->save();
        }
    }

    /**
     * @return array<string, int> team_id => final league position
     */
    private function collectFinalPositions(Game $game): array
    {
        $positions = [];

        $leagueIds = Competition::where('role', Competition::ROLE_LEAGUE)
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->pluck('id')
            ->all();

        if (empty($leagueIds)) {
            return $positions;
        }

        $standings = GameStanding::where('game_id', $game->id)
            ->whereIn('competition_id', $leagueIds)
            ->where('played', '>', 0)
            ->get();

        foreach ($standings as $standing) {
            $positions[$standing->team_id] = (int) $standing->position;
        }

        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->whereIn('competition_id', $leagueIds)
            ->get();

        foreach ($simulated as $sim) {
            if (empty($sim->results)) {
                continue;
            }
            foreach ($sim->results as $index => $teamId) {
                if (!isset($positions[$teamId])) {
                    $positions[$teamId] = $index + 1;
                }
            }
        }

        return $positions;
    }

    /**
     * @return array<string, int> team_id => number of cup ties won this season
     */
    private function collectCupWins(Game $game): array
    {
        $wins = [];

        $cupTies = CupTie::where('game_id', $game->id)
            ->where('completed', true)
            ->whereNotNull('winner_id')
            ->get(['winner_id']);

        foreach ($cupTies as $tie) {
            $teamId = $tie->winner_id;
            $wins[$teamId] = ($wins[$teamId] ?? 0) + 1;
        }

        return $wins;
    }

    /**
     * @return array<int, string> team_ids that won a top-tier league title
     */
    private function collectLeagueWinners(Game $game): array
    {
        $tierOneLeagueIds = Competition::where('role', Competition::ROLE_LEAGUE)
            ->where('tier', 1)
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->pluck('id')
            ->all();

        if (empty($tierOneLeagueIds)) {
            return [];
        }

        $winners = GameStanding::where('game_id', $game->id)
            ->whereIn('competition_id', $tierOneLeagueIds)
            ->where('position', 1)
            ->where('played', '>', 0)
            ->pluck('team_id')
            ->all();

        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->whereIn('competition_id', $tierOneLeagueIds)
            ->get();

        foreach ($simulated as $sim) {
            if (!empty($sim->results)) {
                $winners[] = $sim->results[0];
            }
        }

        return array_values(array_unique($winners));
    }

    private function clamp(int $value): int
    {
        return max(TeamReputation::LOYALTY_MIN, min(TeamReputation::LOYALTY_MAX, $value));
    }

    /**
     * Loyalty can't fall more than MAX_LOYALTY_DROP_BELOW_BASE below the
     * seeded anchor, so a run of poor seasons at a high-loyalty club drains
     * loyalty but stops short of emptying the stadium. High-base clubs keep
     * their floor; low-base clubs can fall further in absolute terms.
     */
    private function clampWithBaseFloor(int $value, int $base): int
    {
        $floor = max(TeamReputation::LOYALTY_MIN, $base - TeamReputation::MAX_LOYALTY_DROP_BELOW_BASE);

        return max($floor, min(TeamReputation::LOYALTY_MAX, $value));
    }
}
