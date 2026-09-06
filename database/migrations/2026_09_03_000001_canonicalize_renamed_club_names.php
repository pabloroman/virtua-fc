<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align rows seeded before the 2026 data refresh with the canonical names
     * in App\Support\ClubNames. Keyed by transfermarkt_id (stable identifier)
     * rather than name, so this is safe to re-run and independent of whichever
     * spelling the row currently holds.
     *
     * Athletic Club (621) and RC Celta (940) already have their own rename
     * migrations; these are the two that were still on an old spelling.
     */
    private const RENAMES = [
        897 => ['Deportivo A Coruña', 'deportivo-a-coruna'],
        40812 => ['Universitatea Craiova', 'universitatea-craiova'],
    ];

    private const PREVIOUS = [
        897 => ['Deportivo de A Coruña', 'deportivo-de-a-coruna'],
        40812 => ['CS Universitatea Craiova', 'cs-universitatea-craiova'],
    ];

    public function up(): void
    {
        $this->apply(self::RENAMES);
    }

    public function down(): void
    {
        $this->apply(self::PREVIOUS);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $names
     */
    private function apply(array $names): void
    {
        foreach ($names as $transfermarktId => [$name, $slug]) {
            DB::table('teams')
                ->where('transfermarkt_id', $transfermarktId)
                ->update(['name' => $name, 'slug' => $slug]);
        }
    }
};
