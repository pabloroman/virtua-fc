<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same fix as simulated_seasons: a bigint autoincrement PK collides on the
 * import side of the beta→prod migration when two users on different beta
 * databases happen to share an id. Nothing FKs into manager_trophies.id and
 * no app code reads it externally, so the conversion is local to this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE manager_trophies DROP CONSTRAINT manager_trophies_pkey');
        DB::statement('ALTER TABLE manager_trophies ALTER COLUMN id DROP DEFAULT');
        DB::statement('DROP SEQUENCE IF EXISTS manager_trophies_id_seq');
        DB::statement('ALTER TABLE manager_trophies ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE manager_trophies ADD PRIMARY KEY (id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE manager_trophies DROP CONSTRAINT manager_trophies_pkey');
        DB::statement('CREATE SEQUENCE manager_trophies_id_seq');
        DB::statement('ALTER TABLE manager_trophies ALTER COLUMN id TYPE bigint USING (nextval(\'manager_trophies_id_seq\'))');
        DB::statement('ALTER TABLE manager_trophies ALTER COLUMN id SET DEFAULT nextval(\'manager_trophies_id_seq\')');
        DB::statement('ALTER SEQUENCE manager_trophies_id_seq OWNED BY manager_trophies.id');
        DB::statement('SELECT setval(\'manager_trophies_id_seq\', COALESCE((SELECT MAX(id) FROM manager_trophies), 1))');
        DB::statement('ALTER TABLE manager_trophies ADD PRIMARY KEY (id)');
    }
};
