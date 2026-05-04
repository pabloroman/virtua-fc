<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 of the player-data planes refactor: copy biographical columns
 * (transfermarkt_id, name, date_of_birth, nationality, height, foot) from
 * the control-plane `players` table into the matching `game_player_templates`
 * rows added in Phase 1.
 *
 * Plane-safe: reads and writes both go through `pgsql_control` — Player and
 * GamePlayerTemplate now both live on the control plane. No cross-plane JOIN.
 *
 * Idempotent: every write is filtered by `name IS NULL`, so re-running the
 * command after a partial run only touches rows still pending.
 */
class BackfillTemplatePlayerBiography extends Command
{
    protected $signature = 'app:backfill-template-player-biography
                            {--chunk=500 : Distinct player_ids to process per batch}
                            {--limit= : Max distinct player_ids to process this run}
                            {--dry-run : Report what would be updated without writing}';

    protected $description = 'Copy biographical fields from players (control) into game_player_templates (tenant). Phase 2 of the player-data planes refactor.';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = DB::connection('pgsql_control')->table('game_player_templates')
            ->whereNull('name')
            ->whereNotNull('player_id')
            ->select('player_id')
            ->distinct()
            ->orderBy('player_id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $playerIds = $query->pluck('player_id');

        $this->info("Distinct players referenced by NULL templates: {$playerIds->count()}");

        $templatesUpdated = 0;
        $playersBackfilled = 0;
        $orphanedPlayerIds = 0;

        foreach ($playerIds->chunk($chunkSize) as $chunk) {
            $players = Player::query()
                ->whereIn('id', $chunk)
                ->get(['id', 'transfermarkt_id', 'name', 'date_of_birth', 'nationality', 'height', 'foot'])
                ->keyBy('id');

            foreach ($chunk as $playerId) {
                $player = $players->get($playerId);

                if (!$player) {
                    $orphanedPlayerIds++;
                    continue;
                }

                if ($dryRun) {
                    $playersBackfilled++;
                    continue;
                }

                $affected = DB::connection('pgsql_control')->table('game_player_templates')
                    ->where('player_id', $playerId)
                    ->whereNull('name')
                    ->update([
                        'transfermarkt_id' => $player->transfermarkt_id,
                        'name' => $player->name,
                        'date_of_birth' => $player->date_of_birth?->toDateString(),
                        'nationality' => $player->nationality !== null
                            ? json_encode($player->nationality)
                            : null,
                        'height' => $player->height,
                        'foot' => $player->foot,
                    ]);

                $templatesUpdated += $affected;
                $playersBackfilled++;
            }
        }

        if ($dryRun) {
            $this->info("DRY RUN — would backfill biography for {$playersBackfilled} player(s).");
        } else {
            $this->info("Backfilled biography for {$playersBackfilled} player(s), updating {$templatesUpdated} template row(s).");
        }

        if ($orphanedPlayerIds > 0) {
            $this->warn("{$orphanedPlayerIds} template player_id(s) had no matching Player row — left untouched.");
        }

        $remainingNull = DB::connection('pgsql_control')->table('game_player_templates')->whereNull('name')->count();
        if ($remainingNull > 0) {
            $this->warn("{$remainingNull} template row(s) still have NULL name.");
        } else {
            $this->info('All template rows now have populated biography.');
        }

        return self::SUCCESS;
    }
}
