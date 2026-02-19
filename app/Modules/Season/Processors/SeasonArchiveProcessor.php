<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use App\Models\SeasonArchive;
use App\Models\Team;

/**
 * Archives season data before stats are reset.
 * Priority: 5 (runs first, before development and stats reset)
 */
class SeasonArchiveProcessor implements SeasonEndProcessor
{
    // Minimum appearances for goalkeeper award (50% of league matches)
    private const MIN_GOALKEEPER_APPEARANCES = 19;

    public function priority(): int
    {
        return 5;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $season = $game->season;

        // Capture final standings
        $standings = $this->captureStandings($game);

        // Capture player season stats
        $playerStats = $this->capturePlayerStats($game);

        // Calculate season awards
        $awards = $this->calculateAwards($game, $standings, $playerStats);

        // Capture match results (lightweight)
        $matchResults = $this->captureMatchResults($game);

        // Compress detailed match events
        $eventsArchive = $this->compressMatchEvents($game);

        // Create archive record
        SeasonArchive::create([
            'game_id' => $game->id,
            'season' => $season,
            'final_standings' => $standings,
            'player_season_stats' => $playerStats,
            'season_awards' => $awards,
            'match_results' => $matchResults,
            'match_events_archive' => $eventsArchive,
        ]);

        // Delete archived data to free up space
        $this->deleteArchivedData($game);

        // Store awards in transition data for display on season-end screen
        $data->setMetadata('seasonAwards', $awards);

        return $data;
    }

    /**
     * Capture final league standings.
     */
    private function captureStandings(Game $game): array
    {
        return GameStanding::where('game_id', $game->id)
            ->orderBy('position')
            ->get()
            ->map(function ($standing) {
                $team = Team::find($standing->team_id);

                return [
                    'team_id' => $standing->team_id,
                    'team_name' => $team->name ?? 'Unknown',
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
     * Capture player season stats for all players with appearances.
     */
    private function capturePlayerStats(Game $game): array
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('appearances', '>', 0)
            ->get()
            ->map(function ($player) {
                return [
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
                ];
            })
            ->toArray();
    }

    /**
     * Calculate season awards.
     */
    private function calculateAwards(Game $game, array $standings, array $playerStats): array
    {
        // Champion
        $champion = collect($standings)->firstWhere('position', 1);

        // Top scorer
        $topScorer = collect($playerStats)
            ->sortByDesc('goals')
            ->first();

        // Most assists
        $mostAssists = collect($playerStats)
            ->sortByDesc('assists')
            ->first();

        // Best goalkeeper (minimum appearances required)
        $bestGoalkeeper = $this->calculateBestGoalkeeper($game);

        return [
            'champion' => $champion ? [
                'team_id' => $champion['team_id'],
                'team_name' => $champion['team_name'],
                'points' => $champion['points'],
            ] : null,

            'top_scorer' => $topScorer ? [
                'player_id' => $topScorer['player_id'],
                'name' => $topScorer['name'],
                'team_id' => $topScorer['team_id'],
                'goals' => $topScorer['goals'],
            ] : null,

            'most_assists' => $mostAssists && $mostAssists['assists'] > 0 ? [
                'player_id' => $mostAssists['player_id'],
                'name' => $mostAssists['name'],
                'team_id' => $mostAssists['team_id'],
                'assists' => $mostAssists['assists'],
            ] : null,

            'best_goalkeeper' => $bestGoalkeeper,
        ];
    }

    /**
     * Calculate best goalkeeper based on goals conceded per game.
     * Requires minimum appearances (50% of league matches).
     */
    private function calculateBestGoalkeeper(Game $game): ?array
    {
        $goalkeepers = GamePlayer::where('game_id', $game->id)
            ->where('position', 'Goalkeeper')
            ->where('appearances', '>=', self::MIN_GOALKEEPER_APPEARANCES)
            ->get();

        if ($goalkeepers->isEmpty()) {
            return null;
        }

        // Calculate goals conceded per game and find the best
        $ranked = $goalkeepers->map(function ($gk) {
            $perGame = $gk->appearances > 0
                ? round($gk->goals_conceded / $gk->appearances, 2)
                : 999;

            return [
                'player' => $gk,
                'per_game' => $perGame,
            ];
        })
        ->sortBy('per_game')
        ->sortByDesc(fn($item) => $item['player']->clean_sheets); // Tiebreaker: more clean sheets

        $best = $ranked->first();

        if (!$best) {
            return null;
        }

        $gk = $best['player'];

        return [
            'player_id' => $gk->id,
            'name' => $gk->name,
            'team_id' => $gk->team_id,
            'goals_conceded' => $gk->goals_conceded,
            'clean_sheets' => $gk->clean_sheets,
            'appearances' => $gk->appearances,
            'goals_conceded_per_game' => $best['per_game'],
        ];
    }

    /**
     * Capture lightweight match results.
     */
    private function captureMatchResults(Game $game): array
    {
        return GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->get()
            ->map(function ($match) {
                return [
                    'home_team_id' => $match->home_team_id,
                    'away_team_id' => $match->away_team_id,
                    'home_score' => $match->home_score,
                    'away_score' => $match->away_score,
                    'competition_id' => $match->competition_id,
                    'round_number' => $match->round_number,
                    'date' => $match->scheduled_date->toDateString(),
                ];
            })
            ->toArray();
    }

    /**
     * Compress all match events into a gzipped blob.
     */
    private function compressMatchEvents(Game $game): ?string
    {
        $events = MatchEvent::where('game_id', $game->id)
            ->get()
            ->map(function ($event) {
                return [
                    'match_id' => $event->game_match_id,
                    'player_id' => $event->game_player_id,
                    'team_id' => $event->team_id,
                    'minute' => $event->minute,
                    'event_type' => $event->event_type,
                    'metadata' => $event->metadata,
                ];
            })
            ->toArray();

        if (empty($events)) {
            return null;
        }

        return base64_encode(gzcompress(json_encode($events), 9));
    }

    /**
     * Delete archived data from active tables.
     */
    private function deleteArchivedData(Game $game): void
    {
        // Delete match events
        MatchEvent::where('game_id', $game->id)->delete();

        // Delete played matches (keep unplayed fixtures for potential reference)
        GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->delete();
    }
}
