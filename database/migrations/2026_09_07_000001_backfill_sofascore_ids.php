<?php

use Illuminate\Database\Migrations\Migration;
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
 *   2. game_players — from the templates, for careers already in progress
 *      (SetupNewGame copies the column at setup time, so saves created after
 *      the refresh baked in the null)
 *
 * Both hops only ever fill NULLs, so this is safe to re-run — which matters,
 * because refreshing people.csv and rebuilding the map covers more players and
 * the same backfill then wants running again.
 *
 * Deliberately NOT done via app:refresh-player-templates: both foreign keys
 * onto game_player_templates are ON DELETE CASCADE, so a clear-and-regenerate
 * would take game_player_template_audits — the admin editor's trail, and the
 * manual template corrections behind it — with it.
 */
return new class extends Migration
{
    /** Pairs per UPDATE ... FROM (VALUES ...) statement. */
    private const CHUNK = 1000;

    public function up(): void
    {
        $this->backfillTemplates();
        $this->backfillGamePlayers();
    }

    /**
     * Fill game_player_templates.sofascore_id from the crosswalk.
     *
     * Only the templates actually missing an id are looked up, so the VALUES
     * list carries a few thousand pairs rather than the map's ~84k.
     */
    private function backfillTemplates(): void
    {
        $path = base_path('data/sofascore_ids.json');
        if (! file_exists($path)) {
            return;
        }

        $map = json_decode((string) file_get_contents($path), true);
        if (! is_array($map) || $map === []) {
            return;
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

        foreach (array_chunk($pairs, self::CHUNK) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?,?)'));
            $bindings = array_merge(...$chunk);

            DB::statement(
                "UPDATE game_player_templates t
                 SET sofascore_id = v.sofascore_id
                 FROM (VALUES {$placeholders}) AS v(transfermarkt_id, sofascore_id)
                 WHERE t.transfermarkt_id = v.transfermarkt_id
                   AND t.sofascore_id IS NULL",
                $bindings,
            );
        }
    }

    /**
     * Propagate into saves already in progress.
     *
     * sofascore_id is a pure function of transfermarkt_id, so the several
     * template rows a player can have across seasons all carry the same value
     * and the row UPDATE ... FROM happens to pick does not matter.
     */
    private function backfillGamePlayers(): void
    {
        DB::statement(
            'UPDATE game_players gp
             SET sofascore_id = t.sofascore_id
             FROM game_player_templates t
             WHERE t.transfermarkt_id = gp.transfermarkt_id
               AND t.sofascore_id IS NOT NULL
               AND gp.sofascore_id IS NULL',
        );
    }

    public function down(): void
    {
        // Intentionally left empty — nulling these back out would not restore a
        // prior state, it would re-break the photos for every player this fixed
        // and for the 2025-vintage saves that never lost them.
    }
};
