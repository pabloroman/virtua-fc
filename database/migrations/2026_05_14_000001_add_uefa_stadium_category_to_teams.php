<?php

use App\Modules\Stadium\UefaCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teams live on the control plane; this column captures each ground's
     * UEFA category (1–4) for use as the per-game baseline. Nullable so
     * national / placeholder / no-stadium teams stay uncategorised.
     */
    public function up(): void
    {
        Schema::connection('pgsql_control')->table('teams', function (Blueprint $table) {
            $table->unsignedTinyInteger('uefa_stadium_category')->nullable()->after('stadium_seats');
        });

        // Backfill from stadium_seats using the same heuristic the seeder
        // will apply going forward. Chunked to keep memory bounded.
        DB::connection('pgsql_control')->table('teams')
            ->select(['id', 'stadium_seats'])
            ->orderBy('id')
            ->chunk(500, function ($teams) {
                foreach ($teams as $team) {
                    $category = UefaCategory::deriveFromCapacity((int) ($team->stadium_seats ?? 0));
                    DB::connection('pgsql_control')->table('teams')
                        ->where('id', $team->id)
                        ->update(['uefa_stadium_category' => $category]);
                }
            });
    }

    public function down(): void
    {
        Schema::connection('pgsql_control')->table('teams', function (Blueprint $table) {
            $table->dropColumn('uefa_stadium_category');
        });
    }
};
