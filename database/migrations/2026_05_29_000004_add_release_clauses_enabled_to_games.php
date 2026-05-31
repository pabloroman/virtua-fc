<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // New-games-only feature gate. default(false) so existing saves see
            // nothing; GameCreationService sets it true for every new game.
            $table->boolean('release_clauses_enabled')->default(false)->after('squad_registration_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('release_clauses_enabled');
        });
    }
};
