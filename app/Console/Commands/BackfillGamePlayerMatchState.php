<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Modules\Player\Support\GamePlayerScopeResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot data backfill that copies match-state columns out of game_players
 * into the new game_player_match_state satellite for every existing game.
 *
 * Run between the create-table migration and the drop-columns migration:
 *
 *   php artisan migrate                                        # creates the satellite table
 *   php artisan app:backfill-game-player-match-state           # this command
 *   php artisan migrate                                        # drops the 13 columns
 *
 * Idempotent: a player whose satellite row already exists is left alone.
 * Per-game transactions so partial failure is recoverable.
 */
class BackfillGamePlayerMatchState extends Command
{
    protected $signature = 'app:backfill-game-player-match-state {--game= : Backfill a single game by id}';

    protected $description = 'Backfill game_player_match_state from existing game_players hot-write columns';

    public function handle(GamePlayerScopeResolver $scopeResolver): int
    {
        $gameQuery = Game::query();
        if ($gameId = $this->option('game')) {
            $gameQuery->where('id', $gameId);
        }

        $totalGames = (clone $gameQuery)->count();
        if ($totalGames === 0) {
            $this->info('No games to backfill.');
            return self::SUCCESS;
        }

        $this->info("Backfilling match state for {$totalGames} game(s).");

        $totalInserted = 0;
        $bar = $this->output->createProgressBar($totalGames);

        $gameQuery->each(function (Game $game) use ($scopeResolver, &$totalInserted, $bar) {
            $totalInserted += $this->backfillGame($game, $scopeResolver);
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Inserted {$totalInserted} match-state rows.");

        return self::SUCCESS;
    }

    private function backfillGame(Game $game, GamePlayerScopeResolver $scopeResolver): int
    {
        $activeTeamIds = $scopeResolver->activeTeamIdsForGame($game);
        if (empty($activeTeamIds)) {
            return 0;
        }

        // Tournament games (e.g. World Cup) participate via national teams
        // that aren't in the country-based active scope. Treat every team in
        // the game as active for those — the satellite is small.
        if ($game->isTournamentMode()) {
            $activeTeamIds = DB::table('game_players')
                ->where('game_id', $game->id)
                ->whereNotNull('team_id')
                ->distinct()
                ->pluck('team_id')
                ->all();
        }

        return DB::transaction(function () use ($game, $activeTeamIds): int {
            $before = DB::table('game_player_match_state')
                ->whereIn('game_player_id', function ($q) use ($game) {
                    $q->select('id')->from('game_players')->where('game_id', $game->id);
                })
                ->count();

            DB::statement(<<<'SQL'
                INSERT INTO game_player_match_state (
                    game_player_id, fitness, morale, injury_until, injury_type,
                    appearances, season_appearances, goals, own_goals, assists,
                    yellow_cards, red_cards, goals_conceded, clean_sheets
                )
                SELECT
                    gp.id,
                    COALESCE(gp.fitness, 80),
                    COALESCE(gp.morale, 80),
                    gp.injury_until,
                    gp.injury_type,
                    COALESCE(gp.appearances, 0),
                    COALESCE(gp.season_appearances, 0),
                    COALESCE(gp.goals, 0),
                    COALESCE(gp.own_goals, 0),
                    COALESCE(gp.assists, 0),
                    COALESCE(gp.yellow_cards, 0),
                    COALESCE(gp.red_cards, 0),
                    COALESCE(gp.goals_conceded, 0),
                    COALESCE(gp.clean_sheets, 0)
                FROM game_players gp
                WHERE gp.game_id = ?
                  AND gp.team_id = ANY(?::uuid[])
                ON CONFLICT (game_player_id) DO NOTHING
            SQL, [$game->id, '{' . implode(',', $activeTeamIds) . '}']);

            $after = DB::table('game_player_match_state')
                ->whereIn('game_player_id', function ($q) use ($game) {
                    $q->select('id')->from('game_players')->where('game_id', $game->id);
                })
                ->count();

            return $after - $before;
        });
    }
}
