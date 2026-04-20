<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMatchStates extends Command
{
    protected $signature = 'app:backfill-match-states';

    protected $description = 'Backfill game_player_match_state rows for every game_player that does not yet have one, chunked by game.';

    /**
     * Backfill the satellite table one game at a time.
     *
     * Historically gpms was sparse — only active-scope teams carried rows.
     * SetupNewGame now seeds a row per game_player at creation time, and this
     * command brings existing games up to that invariant.
     *
     * Chunking by game_id:
     *   - gives a natural per-game progress milestone (operator can watch it)
     *   - keeps each transaction bounded to one game's player set (tens to
     *     low hundreds of rows) so WAL, locks, and commit fsync stay small
     *   - is idempotent: NOT EXISTS filters out players that already have a
     *     satellite row, so re-running or resuming after a crash is safe.
     */
    public function handle(): int
    {
        $total = Game::count();

        if ($total === 0) {
            $this->info('No games to process.');

            return self::SUCCESS;
        }

        $this->info("Backfilling match states across {$total} games...");

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% / %estimated:-6s%  inserted: %inserted%');
        $bar->setMessage('0', 'inserted');

        $insertedTotal = 0;

        Game::select('id')
            ->orderBy('id')
            ->chunk(50, function ($games) use ($bar, &$insertedTotal) {
                foreach ($games as $game) {
                    $insertedTotal += $this->backfillGame($game->id);
                    $bar->setMessage((string) $insertedTotal, 'inserted');
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Inserted {$insertedTotal} satellite rows.");

        return self::SUCCESS;
    }

    private function backfillGame(string $gameId): int
    {
        return DB::affectingStatement(<<<'SQL'
            INSERT INTO game_player_match_state (
                game_player_id, game_id, fitness, morale, injury_until, injury_type,
                appearances, season_appearances, goals, own_goals, assists,
                yellow_cards, red_cards, goals_conceded, clean_sheets
            )
            SELECT gp.id, gp.game_id, 80, 80, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0
            FROM game_players gp
            WHERE gp.game_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM game_player_match_state gpms
                  WHERE gpms.game_player_id = gp.id
              )
            ORDER BY gp.id
        SQL, [$gameId]);
    }
}
