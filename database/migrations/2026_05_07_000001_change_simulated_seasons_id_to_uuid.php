<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The original table used a bigint autoincrement id. That shape collided on
 * the import side of the beta→prod migration: two users on different beta
 * databases can both have a `simulated_seasons.id = 63917`, so the second
 * import fails with `simulated_seasons_pkey` duplicate-key. Switching to UUID
 * removes the global PK contention. Nothing FKs into `simulated_seasons.id`
 * and no app code reads it externally, so the conversion is local to this
 * table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE simulated_seasons DROP CONSTRAINT simulated_seasons_pkey');
        DB::statement('ALTER TABLE simulated_seasons ALTER COLUMN id DROP DEFAULT');
        DB::statement('DROP SEQUENCE IF EXISTS simulated_seasons_id_seq');
        // USING gen_random_uuid() regenerates a fresh UUID for every existing
        // row during the type change.
        DB::statement('ALTER TABLE simulated_seasons ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE simulated_seasons ADD PRIMARY KEY (id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE simulated_seasons DROP CONSTRAINT simulated_seasons_pkey');
        DB::statement('CREATE SEQUENCE simulated_seasons_id_seq');
        DB::statement('ALTER TABLE simulated_seasons ALTER COLUMN id TYPE bigint USING (nextval(\'simulated_seasons_id_seq\'))');
        DB::statement('ALTER TABLE simulated_seasons ALTER COLUMN id SET DEFAULT nextval(\'simulated_seasons_id_seq\')');
        DB::statement('ALTER SEQUENCE simulated_seasons_id_seq OWNED BY simulated_seasons.id');
        DB::statement('SELECT setval(\'simulated_seasons_id_seq\', COALESCE((SELECT MAX(id) FROM simulated_seasons), 1))');
        DB::statement('ALTER TABLE simulated_seasons ADD PRIMARY KEY (id)');
    }
};
