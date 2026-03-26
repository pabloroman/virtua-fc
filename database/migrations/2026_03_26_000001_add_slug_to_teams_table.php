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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill slugs for existing club teams
        $teams = DB::table('teams')->whereNull('slug')->get();
        $usedSlugs = [];

        foreach ($teams as $team) {
            $slug = Str::slug($team->name);

            // Handle duplicates by appending a suffix
            if (isset($usedSlugs[$slug])) {
                $usedSlugs[$slug]++;
                $slug = $slug.'-'.$usedSlugs[$slug];
            } else {
                $usedSlugs[$slug] = 1;
            }

            DB::table('teams')->where('id', $team->id)->update(['slug' => $slug]);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
