<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create game_tactics table
        Schema::create('game_tactics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id')->unique();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();

            // Migrated from games table
            $table->string('default_formation', 10)->default('4-4-2');
            $table->json('default_lineup')->nullable();
            $table->json('default_slot_assignments')->nullable();
            $table->string('default_mentality')->default('balanced');

            // New tactical instructions
            $table->string('default_playing_style')->default('balanced');
            $table->string('default_pressing')->default('standard');
            $table->string('default_defensive_line')->default('normal');
        });

        // 2. Migrate existing data from games to game_tactics
        $games = DB::table('games')->select(
            'id',
            'default_formation',
            'default_lineup',
            'default_slot_assignments',
            'default_mentality'
        )->get();

        foreach ($games as $game) {
            DB::table('game_tactics')->insert([
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'default_formation' => $game->default_formation ?? '4-4-2',
                'default_lineup' => $game->default_lineup,
                'default_slot_assignments' => $game->default_slot_assignments,
                'default_mentality' => $game->default_mentality ?? 'balanced',
                'default_playing_style' => 'balanced',
                'default_pressing' => 'standard',
                'default_defensive_line' => 'normal',
            ]);
        }

        // 3. Add instruction columns to game_matches
        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('home_playing_style')->nullable()->after('away_mentality');
            $table->string('away_playing_style')->nullable()->after('home_playing_style');
            $table->string('home_pressing')->nullable()->after('away_playing_style');
            $table->string('away_pressing')->nullable()->after('home_pressing');
            $table->string('home_defensive_line')->nullable()->after('away_pressing');
            $table->string('away_defensive_line')->nullable()->after('home_defensive_line');
        });

        // 4. Drop old tactical columns from games
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'default_formation',
                'default_lineup',
                'default_slot_assignments',
                'default_mentality',
            ]);
        });
    }

    public function down(): void
    {
        // Re-add columns to games
        Schema::table('games', function (Blueprint $table) {
            $table->string('default_formation', 10)->default('4-4-2');
            $table->json('default_lineup')->nullable();
            $table->json('default_slot_assignments')->nullable();
            $table->string('default_mentality')->default('balanced');
        });

        // Migrate data back
        $tactics = DB::table('game_tactics')->get();
        foreach ($tactics as $row) {
            DB::table('games')->where('id', $row->game_id)->update([
                'default_formation' => $row->default_formation,
                'default_lineup' => $row->default_lineup,
                'default_slot_assignments' => $row->default_slot_assignments,
                'default_mentality' => $row->default_mentality,
            ]);
        }

        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn([
                'home_playing_style',
                'away_playing_style',
                'home_pressing',
                'away_pressing',
                'home_defensive_line',
                'away_defensive_line',
            ]);
        });

        Schema::dropIfExists('game_tactics');
    }
};
