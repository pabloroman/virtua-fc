<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->boolean('is_on_loan')->default(false)->after('appeared_at');
            $table->integer('joined_season')->nullable()->after('is_on_loan');
            $table->unsignedTinyInteger('initial_technical')->nullable()->after('joined_season');
            $table->unsignedTinyInteger('initial_physical')->nullable()->after('initial_technical');
        });
    }

    public function down(): void
    {
        Schema::table('academy_players', function (Blueprint $table) {
            $table->dropColumn(['is_on_loan', 'joined_season', 'initial_technical', 'initial_physical']);
        });
    }
};
