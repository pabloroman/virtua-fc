<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flatten the dual-axis (technical_ability + physical_ability) ability model
 * into a single `overall_score` column on `players`, `game_players`,
 * `game_player_templates`, and `academy_players`.
 *
 * The codebase was already half-flattened: valuation, formation selection
 * and goal-scorer weighting all collapsed the two values to an average.
 * This migration makes the underlying schema match.
 *
 * Idempotent so the migration is safe whether or not earlier migrations in
 * this commit have been edited to define `overall_score` directly. Fresh DBs
 * already created via the edited create / add migrations will see the
 * column-existence guards short-circuit each step.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->flattenPlayers();
        $this->flattenGamePlayers();
        $this->flattenGamePlayerTemplates();
        $this->flattenAcademyPlayers();
    }

    public function down(): void
    {
        // Re-create the dual columns and copy overall_score back into both halves.
        // This is a lossy round-trip — the asymmetry between technique and
        // physique is permanently gone.
        if (! Schema::hasColumn('players', 'technical_ability')) {
            Schema::table('players', function (Blueprint $table) {
                $table->unsignedTinyInteger('technical_ability')->default(50);
                $table->unsignedTinyInteger('physical_ability')->default(50);
            });
            DB::statement('UPDATE players SET technical_ability = overall_score, physical_ability = overall_score');
        }

        if (! Schema::hasColumn('game_players', 'game_technical_ability')) {
            Schema::table('game_players', function (Blueprint $table) {
                $table->unsignedTinyInteger('game_technical_ability')->nullable();
                $table->unsignedTinyInteger('game_physical_ability')->nullable();
            });
            DB::statement('UPDATE game_players SET game_technical_ability = overall_score, game_physical_ability = overall_score WHERE overall_score IS NOT NULL');
        }

        if (! Schema::hasColumn('game_player_templates', 'game_technical_ability')) {
            Schema::table('game_player_templates', function (Blueprint $table) {
                $table->unsignedTinyInteger('game_technical_ability')->nullable();
                $table->unsignedTinyInteger('game_physical_ability')->nullable();
            });
            DB::statement('UPDATE game_player_templates SET game_technical_ability = overall_score, game_physical_ability = overall_score WHERE overall_score IS NOT NULL');
        }

        if (! Schema::hasColumn('academy_players', 'technical_ability')) {
            Schema::table('academy_players', function (Blueprint $table) {
                $table->unsignedTinyInteger('technical_ability')->default(50);
                $table->unsignedTinyInteger('physical_ability')->default(50);
                $table->unsignedTinyInteger('initial_technical')->nullable();
                $table->unsignedTinyInteger('initial_physical')->nullable();
            });
            DB::statement('UPDATE academy_players SET technical_ability = overall_score, physical_ability = overall_score, initial_technical = initial_overall, initial_physical = initial_overall');
        }
    }

    private function flattenPlayers(): void
    {
        if (! Schema::hasColumn('players', 'overall_score')) {
            Schema::table('players', function (Blueprint $table) {
                $table->unsignedTinyInteger('overall_score')->nullable();
            });
        }

        if (Schema::hasColumn('players', 'technical_ability')) {
            DB::statement('UPDATE players SET overall_score = ROUND((technical_ability + physical_ability) / 2.0) WHERE overall_score IS NULL');

            Schema::table('players', function (Blueprint $table) {
                $table->dropColumn(['technical_ability', 'physical_ability']);
            });
        }

        // After backfill the column is populated for every row, so make it
        // NOT NULL with the same default as the original create migration.
        DB::statement('ALTER TABLE players ALTER COLUMN overall_score SET DEFAULT 50');
        DB::statement('UPDATE players SET overall_score = 50 WHERE overall_score IS NULL');
        DB::statement('ALTER TABLE players ALTER COLUMN overall_score SET NOT NULL');
    }

    private function flattenGamePlayers(): void
    {
        if (! Schema::hasColumn('game_players', 'overall_score')) {
            Schema::table('game_players', function (Blueprint $table) {
                $table->unsignedTinyInteger('overall_score')->nullable();
            });
        }

        if (Schema::hasColumn('game_players', 'game_technical_ability')) {
            DB::statement('UPDATE game_players SET overall_score = ROUND((game_technical_ability + game_physical_ability) / 2.0) WHERE overall_score IS NULL AND game_technical_ability IS NOT NULL AND game_physical_ability IS NOT NULL');

            Schema::table('game_players', function (Blueprint $table) {
                $table->dropColumn(['game_technical_ability', 'game_physical_ability']);
            });
        }
    }

    private function flattenGamePlayerTemplates(): void
    {
        if (! Schema::hasColumn('game_player_templates', 'overall_score')) {
            Schema::table('game_player_templates', function (Blueprint $table) {
                $table->unsignedTinyInteger('overall_score')->nullable();
            });
        }

        if (Schema::hasColumn('game_player_templates', 'game_technical_ability')) {
            DB::statement('UPDATE game_player_templates SET overall_score = ROUND((game_technical_ability + game_physical_ability) / 2.0) WHERE overall_score IS NULL AND game_technical_ability IS NOT NULL AND game_physical_ability IS NOT NULL');

            Schema::table('game_player_templates', function (Blueprint $table) {
                $table->dropColumn(['game_technical_ability', 'game_physical_ability']);
            });
        }
    }

    private function flattenAcademyPlayers(): void
    {
        if (! Schema::hasColumn('academy_players', 'overall_score')) {
            Schema::table('academy_players', function (Blueprint $table) {
                $table->unsignedTinyInteger('overall_score')->nullable();
            });
        }

        if (! Schema::hasColumn('academy_players', 'initial_overall')) {
            Schema::table('academy_players', function (Blueprint $table) {
                $table->unsignedTinyInteger('initial_overall')->nullable();
            });
        }

        if (Schema::hasColumn('academy_players', 'technical_ability')) {
            DB::statement('UPDATE academy_players SET overall_score = ROUND((technical_ability + physical_ability) / 2.0) WHERE overall_score IS NULL');
            DB::statement('UPDATE academy_players SET initial_overall = ROUND((initial_technical + initial_physical) / 2.0) WHERE initial_overall IS NULL AND initial_technical IS NOT NULL AND initial_physical IS NOT NULL');

            Schema::table('academy_players', function (Blueprint $table) {
                $table->dropColumn(['technical_ability', 'physical_ability', 'initial_technical', 'initial_physical']);
            });
        }

        DB::statement('UPDATE academy_players SET overall_score = 50 WHERE overall_score IS NULL');
        DB::statement('ALTER TABLE academy_players ALTER COLUMN overall_score SET NOT NULL');
    }
};
