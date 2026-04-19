<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('fast_mode')->default(false)->after('squad_registration_enabled');
            $table->date('fast_mode_entered_on')->nullable()->after('fast_mode');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['fast_mode', 'fast_mode_entered_on']);
        });
    }
};
