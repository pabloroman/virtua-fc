<?php

namespace App\Modules\Manager\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SeasonArchive;

/**
 * Builds the "performance history" strip shown on the Reputation page:
 * one final league position per completed season plus the current season
 * "so far", with the league tier captured so promotion/relegation
 * transitions can be rendered correctly.
 */
class PerformanceHistoryService
{
    /**
     * @return array{
     *   seasons: array<int, array{
     *     season:string,
     *     position:int,
     *     tier:int,
     *     team_count:int,
     *     league_short_name:string,
     *     promoted:bool,
     *     relegated:bool,
     *     is_current:bool,
     *   }>,
     *   tiers_present: array<int, int>,
     * }
     */
    public function build(Game $game): array
    {
        $archives = SeasonArchive::where('game_id', $game->id)
            ->orderBy('season')
            ->get();

        // Collect every competition id referenced by any archive's match_results,
        // plus the game's current competition, so we can resolve tiers in a single query.
        $competitionIds = [];
        foreach ($archives as $archive) {
            foreach ($archive->match_results ?? [] as $match) {
                if (!empty($match['competition_id'])) {
                    $competitionIds[$match['competition_id']] = true;
                }
            }
        }
        $competitionIds[$game->competition_id] = true;

        $competitions = Competition::whereIn('id', array_keys($competitionIds))
            ->get()
            ->keyBy('id');

        $seasons = [];

        foreach ($archives as $archive) {
            $teamRow = collect($archive->final_standings ?? [])
                ->firstWhere('team_id', $game->team_id);

            if (!$teamRow) {
                continue;
            }

            $leagueCompetition = $this->resolveLeagueCompetition($archive, $competitions);
            if (!$leagueCompetition) {
                continue;
            }

            $seasons[] = [
                'season' => $archive->season,
                'position' => (int) $teamRow['position'],
                'tier' => (int) $leagueCompetition->tier,
                'team_count' => count($archive->final_standings ?? []),
                'league_short_name' => $leagueCompetition->shortName(),
                'promoted' => false,
                'relegated' => false,
                'is_current' => false,
            ];
        }

        // Trailing in-progress season: only include if the current league has
        // seen a standing row for the user's team (i.e. at least one matchday
        // has been played — otherwise the point would read as "1st" on day 1).
        $currentStanding = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        $currentCompetition = $competitions->get($game->competition_id);

        if ($currentStanding && $currentStanding->played > 0 && $currentCompetition) {
            $currentTeamCount = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $game->competition_id)
                ->count();

            $seasons[] = [
                'season' => $game->season,
                'position' => (int) $currentStanding->position,
                'tier' => (int) $currentCompetition->tier,
                'team_count' => $currentTeamCount,
                'league_short_name' => $currentCompetition->shortName(),
                'promoted' => false,
                'relegated' => false,
                'is_current' => true,
            ];
        }

        $this->markTierTransitions($seasons);

        $tiersPresent = array_values(array_unique(array_map(
            fn (array $row) => $row['tier'],
            $seasons,
        )));
        sort($tiersPresent);

        return [
            'seasons' => $seasons,
            'tiers_present' => $tiersPresent,
        ];
    }

    /**
     * Find the league competition for a given archive by scanning its
     * match_results for the first competition_id that resolves to a league.
     * GameStanding (and therefore final_standings) only exists for the
     * league tier the user's team played in that season, so any league id
     * appearing in match_results is the right one.
     *
     * @param  \Illuminate\Support\Collection<string, Competition>  $competitions
     */
    private function resolveLeagueCompetition(SeasonArchive $archive, $competitions): ?Competition
    {
        foreach ($archive->match_results ?? [] as $match) {
            $competition = $competitions->get($match['competition_id'] ?? null);
            if ($competition && $competition->isLeague() && $competition->role === Competition::ROLE_LEAGUE) {
                return $competition;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $seasons  (by reference)
     */
    private function markTierTransitions(array &$seasons): void
    {
        for ($i = 1; $i < count($seasons); $i++) {
            $prevTier = $seasons[$i - 1]['tier'];
            $currTier = $seasons[$i]['tier'];

            if ($currTier < $prevTier) {
                $seasons[$i]['promoted'] = true;
            } elseif ($currTier > $prevTier) {
                $seasons[$i]['relegated'] = true;
            }
        }
    }
}
