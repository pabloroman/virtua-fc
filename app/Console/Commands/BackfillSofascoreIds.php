<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Restores the player→photo link that the 2026 data refresh silently dropped.
 *
 * Photos resolve as transfermarkt_id → sofascore_id → {CDN}/players/{id}.webp
 * (GamePlayer::getImageUrlAttribute). The middle hop used to come from a
 * per-season data/{season}/sofascore_ids.json, and the 2026 folder shipped
 * without one — a missing file is a non-fatal early return, so all 7,424
 * templates were written with a null sofascore_id and every profile fell back
 * to the default avatar. The images themselves were never touched; only the
 * reference was lost.
 *
 * The map is now season-independent (data/sofascore_ids.json). This fills the
 * gap it left behind, in two hops:
 *
 *   1. templates  — from the map, keyed on transfermarkt_id
 *   2. game_players — from the templates, for the saves already in progress
 *      that copied the column at setup time and so baked in the null
 *
 * Both hops only ever fill NULLs, so this is safe to re-run — which matters
 * twice over: refreshing people.csv and rebuilding the map covers more players,
 * and a chunk that loses a lock race against live gameplay is simply left for
 * the next run.
 *
 * Deliberately a command and not a migration: it competes for row locks with
 * live gameplay, and as a migration a lost race failed `migrate --force` and so
 * blocked the whole deploy. Nothing about the backfill needs to happen before
 * the new code boots — until it runs, photos just keep falling back to the
 * avatar they have been falling back to all along.
 *
 * Deliberately NOT done via app:refresh-player-templates: both foreign keys
 * onto game_player_templates are ON DELETE CASCADE, so a clear-and-regenerate
 * would take game_player_template_audits — the admin editor's trail, and the
 * manual template corrections behind it — with it.
 */
class BackfillSofascoreIds extends Command
{
    protected $signature = 'app:backfill-sofascore-ids';

    protected $description = 'Fill missing sofascore_id on player templates and in-progress saves (safe to re-run)';

    /** Rows per UPDATE statement. */
    private const CHUNK = 1000;

    /** Attempts per chunk before it is left for the next run. */
    private const ATTEMPTS = 5;

    /** First reference-data season whose templates shipped without the ids. */
    private const BROKEN_FROM_SEASON = '2026';

    private int $skipped = 0;

    public function handle(): int
    {
        // Fail a blocked chunk fast rather than queue behind a long-running
        // gameplay transaction while holding locks of our own.
        DB::statement("SET lock_timeout = '5s'");

        try {
            $this->info('Templates: '.$this->backfillTemplates().' filled');
            $this->info('Saves: '.$this->backfillGamePlayers().' players filled');
        } finally {
            DB::statement('RESET lock_timeout');
        }

        if ($this->skipped > 0) {
            $this->warn("{$this->skipped} chunk(s) lost a lock race and were left behind — re-run to pick them up.");
        }

        return self::SUCCESS;
    }

    /**
     * Fill game_player_templates.sofascore_id from the crosswalk.
     *
     * Only the templates actually missing an id are looked up, so the VALUES
     * list carries a few thousand pairs rather than the map's ~84k.
     */
    private function backfillTemplates(): int
    {
        $path = base_path('data/sofascore_ids.json');
        if (! file_exists($path)) {
            $this->warn("No crosswalk at {$path}; skipping templates.");

            return 0;
        }

        $map = json_decode((string) file_get_contents($path), true);
        if (! is_array($map) || $map === []) {
            $this->warn('Crosswalk is empty; skipping templates.');

            return 0;
        }

        $pending = DB::table('game_player_templates')
            ->whereNull('sofascore_id')
            ->whereNotNull('transfermarkt_id')
            ->distinct()
            ->pluck('transfermarkt_id');

        $pairs = [];
        foreach ($pending as $transfermarktId) {
            if (isset($map[$transfermarktId])) {
                $pairs[] = [(string) $transfermarktId, (string) $map[$transfermarktId]];
            }
        }

        $filled = 0;

        foreach (array_chunk($pairs, self::CHUNK) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?,?)'));
            $bindings = array_merge(...$chunk);

            $filled += $this->retrying(fn () => DB::update(
                "UPDATE game_player_templates t
                 SET sofascore_id = v.sofascore_id
                 FROM (VALUES {$placeholders}) AS v(transfermarkt_id, sofascore_id)
                 WHERE t.transfermarkt_id = v.transfermarkt_id
                   AND t.sofascore_id IS NULL",
                $bindings,
            ));
        }

        return $filled;
    }

    /**
     * Propagate into saves already in progress.
     *
     * sofascore_id is a pure function of transfermarkt_id, so the several
     * template rows a player can have across seasons all carry the same value
     * and which one the LIMIT 1 picks does not matter.
     */
    private function backfillGamePlayers(): int
    {
        $filled = 0;

        foreach ($this->affectedGameIds() as $gameId) {
            // One save's rows reach through the game_id index and fit in memory
            // (a squad database is a few thousand players), which keeps this off
            // a full-table scan of what is by far the largest table here.
            $ids = DB::table('game_players')
                ->where('game_id', $gameId)
                ->whereNull('sofascore_id')
                ->whereNotNull('transfermarkt_id')
                ->pluck('id');

            foreach ($ids->chunk(self::CHUNK) as $chunk) {
                $filled += $this->retrying(fn () => DB::table('game_players')
                    ->whereIn('id', $chunk)
                    ->whereNull('sofascore_id')
                    ->update([
                        'sofascore_id' => DB::raw(
                            '(SELECT t.sofascore_id
                                FROM game_player_templates t
                               WHERE t.transfermarkt_id = game_players.transfermarkt_id
                                 AND t.sofascore_id IS NOT NULL
                               LIMIT 1)'
                        ),
                    ]));
            }
        }

        return $filled;
    }

    /**
     * The saves that could have copied a null.
     *
     * base_season is pinned at creation and names the data/{season}/ vintage a
     * save's squads were copied from, so anything older than the refresh got a
     * populated id at setup and has nothing to fix. Tournament saves come along
     * regardless of it: they pin base_season to 2025 but
     * SaveSquadSelection::createTournamentGamePlayers reads whatever templates
     * are current, so one started after the refresh copied the null too.
     *
     * @return list<string>
     */
    private function affectedGameIds(): array
    {
        return DB::table('games')
            ->where(fn ($q) => $q
                ->where('base_season', '>=', self::BROKEN_FROM_SEASON)
                ->orWhere('game_mode', 'tournament'))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * Run a chunk, retrying while it is losing a lock race with live gameplay.
     *
     * 40P01 deadlock, 40001 serialization failure, 55P03 lock_timeout — all
     * transient, and the backfill only ever fills NULLs, so both a replay and a
     * whole re-run are no-ops for what earlier attempts committed. A chunk that
     * keeps losing is therefore left behind rather than aborting the run.
     *
     * @param  callable():int  $chunk
     */
    private function retrying(callable $chunk): int
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $chunk();
            } catch (QueryException $e) {
                if (! in_array($e->getCode(), ['40P01', '40001', '55P03'], true)) {
                    throw $e;
                }

                if ($attempt >= self::ATTEMPTS) {
                    $this->skipped++;

                    return 0;
                }

                usleep(250_000 * $attempt);
            }
        }
    }
}
