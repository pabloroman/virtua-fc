<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Historical no-op.
     *
     * This slot used to run a full-table backfill of game_player_match_state
     * for every existing game_player. On large databases that INSERT ran long
     * enough to exceed the deploy timeout, so the work has moved to an
     * out-of-band artisan command:
     *
     *     php artisan app:backfill-match-states
     *
     * The command iterates games one at a time with progress output and is
     * idempotent (NOT EXISTS filter). It is safe to run at any time; until it
     * does, {@see \App\Models\GamePlayerMatchState::ensureExistForGamePlayers}
     * fills any remaining gaps at matchday time.
     *
     * The file remains so environments that already recorded this migration
     * don't see it as "pending" — removing it would break the migrations
     * table invariant in those databases.
     */
    public function up(): void
    {
        // intentionally empty — see class docblock
    }

    public function down(): void
    {
        // intentionally empty — see class docblock
    }
};
