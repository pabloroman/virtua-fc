<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A club's seeding within a competition, where the competition has one.
 *
 * Supercups need it: the Supercopa's semi-finals are not drawn, they are
 * fixed by how each club qualified (cup winner v league runner-up, cup
 * runner-up v league champion). SupercupQualificationProcessor already
 * derives that order; without somewhere to keep it the order was lost
 * between the closing pipeline and the draw, and the semi-finals came out
 * of a shuffle.
 *
 * Null for every competition that draws its ties instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('seed')->nullable()->after('entry_round');
        });
    }

    public function down(): void
    {
        Schema::table('competition_entries', function (Blueprint $table) {
            $table->dropColumn('seed');
        });
    }
};
