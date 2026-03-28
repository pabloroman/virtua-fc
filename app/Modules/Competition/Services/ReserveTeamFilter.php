<?php

namespace App\Modules\Competition\Services;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Determines which teams are reserve/B teams that cannot be promoted
 * to the same division as their parent club.
 *
 * In Spanish football, reserve teams (e.g. Real Sociedad B) cannot play
 * in the same division as their parent team. If a reserve team qualifies
 * for promotion or playoffs, they are skipped and the next eligible team
 * takes their place.
 */
class ReserveTeamFilter
{
    /**
     * Get team IDs currently in the top division for a given bottom division.
     *
     * @return Collection<int, string> Team UUIDs in the top division
     */
    public function getTopDivisionTeamIds(Game $game, string $bottomDivision): Collection
    {
        $countryConfig = app(CountryConfig::class);
        $topDivision = $this->findTopDivision($countryConfig, $bottomDivision);

        if (!$topDivision) {
            return collect();
        }

        return CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $topDivision)
            ->pluck('team_id');
    }

    /**
     * Batch-load parent_team_id for a set of teams.
     *
     * @param  array  $teamIds  Team UUIDs to load
     * @return Collection<string, string|null> Keyed by team_id => parent_team_id
     */
    public function loadParentTeamIds(array $teamIds): Collection
    {
        if (empty($teamIds)) {
            return collect();
        }

        return Team::whereIn('id', $teamIds)
            ->whereNotNull('parent_team_id')
            ->pluck('parent_team_id', 'id');
    }

    /**
     * Check if a team is a reserve team whose parent is in the top division.
     *
     * @param  string  $teamId  The team UUID to check
     * @param  Collection<int, string>  $topDivisionTeamIds  Team UUIDs in the top division
     * @param  Collection<string, string|null>|null  $parentMap  Pre-loaded parent map from loadParentTeamIds()
     */
    public function isBlockedReserveTeam(string $teamId, Collection $topDivisionTeamIds, ?Collection $parentMap = null): bool
    {
        if ($parentMap !== null) {
            $parentTeamId = $parentMap->get($teamId);

            return $parentTeamId !== null && $topDivisionTeamIds->contains($parentTeamId);
        }

        // Fallback for callers that don't pre-load (e.g. ESP2PlayoffGenerator)
        $team = Team::find($teamId);

        if (!$team || !$team->parent_team_id) {
            return false;
        }

        return $topDivisionTeamIds->contains($team->parent_team_id);
    }

    private function findTopDivision(CountryConfig $countryConfig, string $bottomDivision): ?string
    {
        foreach ($countryConfig->allCountryCodes() as $countryCode) {
            foreach ($countryConfig->promotions($countryCode) as $promotion) {
                if ($promotion['bottom_division'] === $bottomDivision) {
                    return $promotion['top_division'];
                }
            }
        }

        return null;
    }
}
