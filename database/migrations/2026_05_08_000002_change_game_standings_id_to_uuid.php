<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same fix as simulated_seasons / manager_trophies / activation_events: a
 * bigint autoincrement PK collides on the import side of the beta→prod
 * migration when two users on different beta databases happen to share an id.
 * Nothing FKs into game_standings.id and no app code reads it externally.
 *
 * Keeps a DB-side DEFAULT gen_random_uuid() because StandingsCalculator
 * initializes rows via GameStanding::insert() (bulk insert), which bypasses
 * the HasUuids creating listener — without the default, those inserts would
 * fail the NOT NULL PK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE game_standings DROP CONSTRAINT game_standings_pkey');
        DB::statement('ALTER TABLE game_standings ALTER COLUMN id DROP DEFAULT');
        DB::statement('DROP SEQUENCE IF EXISTS game_standings_id_seq');
        DB::statement('ALTER TABLE game_standings ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE game_standings ALTER COLUMN id SET DEFAULT gen_random_uuid()');
        DB::statement('ALTER TABLE game_standings ADD PRIMARY KEY (id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE game_standings DROP CONSTRAINT game_standings_pkey');
        DB::statement('ALTER TABLE game_standings ALTER COLUMN id DROP DEFAULT');
        DB::statement('CREATE SEQUENCE game_standings_id_seq');
        DB::statement('ALTER TABLE game_standings ALTER COLUMN id TYPE bigint USING (nextval(\'game_standings_id_seq\'))');
        DB::statement('ALTER TABLE game_standings ALTER COLUMN id SET DEFAULT nextval(\'game_standings_id_seq\')');
        DB::statement('ALTER SEQUENCE game_standings_id_seq OWNED BY game_standings.id');
        DB::statement('SELECT setval(\'game_standings_id_seq\', COALESCE((SELECT MAX(id) FROM game_standings), 1))');
        DB::statement('ALTER TABLE game_standings ADD PRIMARY KEY (id)');
    }
};
