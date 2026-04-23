<?php

namespace App\Modules\Manager\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SeasonArchive;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the "performance history" strip shown on the Reputation page:
 * one final league position per completed season plus the current season
 * "so far", with the league tier captured so promotion/relegation
 * transitions can be rendered correctly.
 *
 * Caching: the archived-seasons portion reads every SeasonArchive row for
 * the game, which holds large JSON blobs. That portion only changes when
 * SeasonArchiveProcessor writes a new row at season close, so it is
 * cached per-game with a long TTL and invalidated explicitly from the
 * processor (see self::forget). The current in-progress season is
 * resolved fresh on every call from GameStanding, which is cheap.
 */
class PerformanceHistoryService
{
    /** Long TTL acts as a safety net — primary invalidation is explicit. */
    private const CACHE_TTL = 604800; // 7 days

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
        $archivedSeasons = $this->getArchivedSeasons($game);
        $currentSeason = $this->buildCurrentSeason($game);

        $seasons = $currentSeason !== null
            ? [...$archivedSeasons, $currentSeason]
            : $archivedSeasons;

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

    public static function cacheKey(string $gameId): string
    {
        return "performance_history:archived:{$gameId}";
    }

    /**
     * Drop the cached archived-seasons shape for a game. Call this after
     * any write to season_archives for the game (season close, admin
     * corrections). New archives trigger this from SeasonArchiveProcessor.
     */
    public static function forget(string $gameId): void
    {
        Cache::forget(self::cacheKey($gameId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getArchivedSeasons(Game $game): array
    {
        return Cache::remember(
            self::cacheKey($game->id),
            self::CACHE_TTL,
            fn () => $this->buildArchivedSeasons($game),
        );
    }

    /**
     * Heavy path: reads every SeasonArchive row for the game, shapes one
     * entry per archived season. Only runs on cache miss.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildArchivedSeasons(Game $game): array
    {
        // Skip the heavy JSON columns we don't need here
        // (player_season_stats, season_awards, transfer_activity,
        // transition_log) so cache misses stay memory-light.
        $archives = SeasonArchive::where('game_id', $game->id)
            ->select(['id', 'season', 'final_standings', 'match_results'])
            ->orderBy('season')
            ->get();

        if ($archives->isEmpty()) {
            return [];
        }

        // Collect every competition id referenced by any archive (final_standings
        // carries it on backfilled archives; match_results is the fallback for
        // legacy archives) so we can resolve tiers in a single query.
        $competitionIds = [];
        foreach ($archives as $archive) {
            foreach ($archive->final_standings ?? [] as $row) {
                if (!empty($row['competition_id'])) {
                    $competitionIds[$row['competition_id']] = true;
                }
            }
            foreach ($archive->match_results ?? [] as $match) {
                if (!empty($match['competition_id'])) {
                    $competitionIds[$match['competition_id']] = true;
                }
            }
        }

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

            $leagueCompetition = $this->resolveLeagueCompetition($archive, $teamRow, $competitions);
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

        return $seasons;
    }

    /**
     * Trailing in-progress season. Cheap (two GameStanding queries + one
     * Competition lookup), recomputed every call so the point updates as
     * the user plays matches. Returns null before the first match of the
     * season is played — otherwise the point would read as "1st" on day 1.
     */
    private function buildCurrentSeason(Game $game): ?array
    {
        $currentStanding = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        if (!$currentStanding || $currentStanding->played <= 0) {
            return null;
        }

        $currentCompetition = Competition::find($game->competition_id);
        if (!$currentCompetition) {
            return null;
        }

        $currentTeamCount = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->count();

        return [
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

    /**
     * Resolve the league Competition for a season archive.
     *
     * Newer archives denormalize `competition_id` onto every final_standings
     * row (see SeasonArchiveProcessor::captureStandings), so we use the user
     * team's row directly when available. For legacy archives written before
     * that change, we fall back to scanning match_results for the first
     * competition_id that resolves to a league — GameStanding only exists
     * for the league tier the user's team played, so any league id present
     * in their match history is the correct one.
     *
     * @param  array<string, mixed>  $teamRow
     * @param  \Illuminate\Support\Collection<string, Competition>  $competitions
     */
    private function resolveLeagueCompetition(SeasonArchive $archive, array $teamRow, $competitions): ?Competition
    {
        $directId = $teamRow['competition_id'] ?? null;
        if ($directId) {
            $competition = $competitions->get($directId);
            if ($competition && $competition->role === Competition::ROLE_LEAGUE) {
                return $competition;
            }
        }

        foreach ($archive->match_results ?? [] as $match) {
            $competition = $competitions->get($match['competition_id'] ?? null);
            if ($competition && $competition->role === Competition::ROLE_LEAGUE) {
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
