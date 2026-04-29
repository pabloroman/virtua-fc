<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Competition\Services\SwissKnockoutGenerator;
use App\Modules\Manager\Services\PerformanceHistoryService;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\GameTransfer;
use App\Models\SeasonArchive;
use Illuminate\Support\Facades\DB;

/**
 * Archives season data before stats are reset.
 * Priority: 5 (runs first, before development and stats reset)
 */
class SeasonArchiveProcessor implements SeasonProcessor
{
    // Minimum appearances for goalkeeper award (50% of league matches)
    private const MIN_GOALKEEPER_APPEARANCES = 19;

    public function priority(): int
    {
        return 25;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $season = $game->season;

        // Idempotency: if archive already exists (re-dispatch after partial failure),
        // re-populate metadata needed by later processors and skip
        $existingArchive = SeasonArchive::where('game_id', $game->id)
            ->where('season', $season)
            ->first();

        if ($existingArchive) {
            $data->setMetadata('seasonAwards', $existingArchive->season_awards);
            $this->captureEuropeanWinners($game, $data);
            return $data;
        }

        // Capture final standings
        $standings = $this->captureStandings($game);

        // Capture player season stats (scoped to user's team for storage)
        $playerStats = $this->capturePlayerStats($game);

        // Calculate season awards (each award is a single-row SQL query)
        $awards = $this->calculateAwards($game, $standings);

        // Capture match results (lightweight)
        $matchResults = $this->captureMatchResults($game);

        // Capture transfer activity
        $transferActivity = $this->captureTransferActivity($game);

        // Create archive record
        SeasonArchive::create([
            'game_id' => $game->id,
            'season' => $season,
            'final_standings' => $standings,
            'player_season_stats' => $playerStats,
            'season_awards' => $awards,
            'match_results' => $matchResults,
            'transfer_activity' => $transferActivity,
        ]);

        // Bust the cached archived-seasons shape so the Reputation page
        // reflects the new season on the next page load.
        PerformanceHistoryService::forget($game->id);

        // Delete archived data to free up space
        $this->deleteArchivedData($game);

        // Store awards in transition data for display on season-end screen
        $data->setMetadata('seasonAwards', $awards);

        // Capture European competition winners before cup tie data is deleted
        $this->captureEuropeanWinners($game, $data);

        return $data;
    }

    /**
     * Capture UCL and UEL winners before cup tie data is deleted by later
     * processors. These feed next season's UEFA Super Cup (and UCL
     * qualification cascade for the UEL winner).
     *
     * If the player participated, take the final's winner. Otherwise pick
     * a random team from that competition's entries as a stand-in.
     */
    private function captureEuropeanWinners(Game $game, SeasonTransitionData $data): void
    {
        $data->setMetadata(
            SeasonTransitionData::META_UCL_WINNER,
            $this->resolveCompetitionWinner($game, 'UCL'),
        );

        $data->setMetadata(
            SeasonTransitionData::META_UEL_WINNER,
            $this->resolveCompetitionWinner($game, 'UEL'),
        );
    }

    private function resolveCompetitionWinner(Game $game, string $competitionId): ?string
    {
        $final = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', SwissKnockoutGenerator::ROUND_FINAL)
            ->where('completed', true)
            ->first();

        if ($final && $final->winner_id) {
            return $final->winner_id;
        }

        // Competition wasn't played by the user — pick a random entry as stand-in
        $entry = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->inRandomOrder()
            ->first();

        return $entry?->team_id;
    }

    /**
     * Capture final league standings.
     */
    private function captureStandings(Game $game): array
    {
        return GameStanding::where('game_id', $game->id)
            ->with('team')
            ->orderBy('position')
            ->get()
            ->map(function ($standing) {
                return [
                    'team_id' => $standing->team_id,
                    'team_name' => $standing->team->name ?? 'Unknown',
                    'competition_id' => $standing->competition_id,
                    'position' => $standing->position,
                    'played' => $standing->played,
                    'won' => $standing->won,
                    'drawn' => $standing->drawn,
                    'lost' => $standing->lost,
                    'goals_for' => $standing->goals_for,
                    'goals_against' => $standing->goals_against,
                    'goal_difference' => $standing->goal_difference,
                    'points' => $standing->points,
                ];
            })
            ->toArray();
    }

    /**
     * Capture player season stats for the user's team only.
     *
     * Awards are computed via dedicated single-row queries (see
     * calculateAwards), so there is no need to load the entire game's
     * roster here just to filter it back down to the user's team.
     */
    private function capturePlayerStats(Game $game): array
    {
        return GamePlayer::with('matchState')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get()
            ->map(fn ($player) => [
                'player_id' => $player->id,
                'reference_player_id' => $player->player_id,
                'name' => $player->name,
                'team_id' => $player->team_id,
                'position' => $player->position,
                'appearances' => $player->appearances,
                'goals' => $player->goals,
                'assists' => $player->assists,
                'own_goals' => $player->own_goals,
                'yellow_cards' => $player->yellow_cards,
                'red_cards' => $player->red_cards,
                'goals_conceded' => $player->goals_conceded,
                'clean_sheets' => $player->clean_sheets,
            ])
            ->all();
    }

    /**
     * Calculate season awards via single-row SQL queries.
     *
     * Each award becomes one ORDER BY ... LIMIT 1 against the satellite
     * stats table, instead of hydrating every rostered player to sort
     * them in PHP.
     */
    private function calculateAwards(Game $game, array $standings): array
    {
        $champion = collect($standings)->firstWhere('position', 1);

        $topScorer = GamePlayer::with('matchState')
            ->joinMatchState()
            ->where('game_players.game_id', $game->id)
            ->whereNotNull('game_players.team_id')
            ->whereMatchStat('goals', '>', 0)
            ->orderByMatchStat('goals', 'desc')
            ->first();

        $mostAssists = GamePlayer::with('matchState')
            ->joinMatchState()
            ->where('game_players.game_id', $game->id)
            ->whereNotNull('game_players.team_id')
            ->whereMatchStat('assists', '>', 0)
            ->orderByMatchStat('assists', 'desc')
            ->first();

        // Best goalkeeper: minimum appearances required (50% of league matches).
        // Ranking by raw goals_conceded ASC is equivalent to per-game ratio
        // since every candidate clears the same appearance threshold; clean
        // sheets is the tiebreaker.
        $bestGoalkeeper = GamePlayer::with('matchState')
            ->joinMatchState()
            ->where('game_players.game_id', $game->id)
            ->where('game_players.position', 'Goalkeeper')
            ->whereMatchStat('appearances', '>=', self::MIN_GOALKEEPER_APPEARANCES)
            ->orderByMatchStat('goals_conceded', 'asc')
            ->orderByMatchStat('clean_sheets', 'desc')
            ->first();

        return [
            'champion' => $champion ? [
                'team_id' => $champion['team_id'],
                'team_name' => $champion['team_name'],
                'points' => $champion['points'],
            ] : null,

            'top_scorer' => $topScorer ? [
                'player_id' => $topScorer->id,
                'name' => $topScorer->name,
                'team_id' => $topScorer->team_id,
                'goals' => $topScorer->goals,
            ] : null,

            'most_assists' => $mostAssists ? [
                'player_id' => $mostAssists->id,
                'name' => $mostAssists->name,
                'team_id' => $mostAssists->team_id,
                'assists' => $mostAssists->assists,
            ] : null,

            'best_goalkeeper' => $bestGoalkeeper ? [
                'player_id' => $bestGoalkeeper->id,
                'name' => $bestGoalkeeper->name,
                'team_id' => $bestGoalkeeper->team_id,
                'goals_conceded' => $bestGoalkeeper->goals_conceded,
                'clean_sheets' => $bestGoalkeeper->clean_sheets,
                'appearances' => $bestGoalkeeper->appearances,
                'goals_conceded_per_game' => $bestGoalkeeper->appearances > 0
                    ? round($bestGoalkeeper->goals_conceded / $bestGoalkeeper->appearances, 2)
                    : 0.0,
            ] : null,
        ];
    }

    /**
     * Capture lightweight match results.
     */
    private function captureMatchResults(Game $game): array
    {
        $results = [];

        GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)->orWhere('away_team_id', $game->team_id))
            ->chunk(200, function ($chunk) use (&$results) {
                foreach ($chunk as $match) {
                    $results[] = [
                        'home_team_id' => $match->home_team_id,
                        'away_team_id' => $match->away_team_id,
                        'home_score' => $match->home_score,
                        'away_score' => $match->away_score,
                        'is_extra_time' => $match->is_extra_time,
                        'home_score_et' => $match->home_score_et,
                        'away_score_et' => $match->away_score_et,
                        'home_score_penalties' => $match->home_score_penalties,
                        'away_score_penalties' => $match->away_score_penalties,
                        'competition_id' => $match->competition_id,
                        'round_number' => $match->round_number,
                        'date' => $match->scheduled_date->toDateString(),
                    ];
                }
            });

        return $results;
    }

    /**
     * Capture all transfer activity for the season.
     */
    private function captureTransferActivity(Game $game): array
    {
        $activity = [];

        GameTransfer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where(fn ($q) => $q->where('from_team_id', $game->team_id)->orWhere('to_team_id', $game->team_id))
            ->chunk(200, function ($chunk) use (&$activity) {
                foreach ($chunk as $transfer) {
                    $activity[] = [
                        'game_player_id' => $transfer->game_player_id,
                        'from_team_id' => $transfer->from_team_id,
                        'to_team_id' => $transfer->to_team_id,
                        'transfer_fee' => $transfer->transfer_fee,
                        'type' => $transfer->type,
                        'window' => $transfer->window,
                    ];
                }
            });

        return $activity;
    }

    /**
     * Delete archived data from active tables.
     */
    private function deleteArchivedData(Game $game): void
    {
        // Single indexed DELETE — match_events(game_id) is indexed and one
        // game's events are at most low thousands of rows.
        DB::table('match_events')->where('game_id', $game->id)->delete();

        // GameMatch rows are intentionally left in place here. SeasonSettlement
        // (priority 60) iterates this season's home matches to compute matchday
        // revenue from MatchAttendance (whose FK cascades on game_match_id), so
        // deleting them here would wipe the per-fixture attendance data before
        // settlement sees it. LeagueFixtureProcessor (priority 30 in the setup
        // pipeline) purges all GameMatches for this game_id immediately after
        // the closing pipeline finishes, which does the cleanup.

        // Delete transfer records for this season
        GameTransfer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->delete();
    }
}
