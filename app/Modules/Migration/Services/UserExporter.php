<?php

namespace App\Modules\Migration\Services;

use App\Modules\Migration\TableManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Produces the JSON envelopes that the export side ships to the import side.
 *
 * The endpoint runs in two modes — manifest (control plane + game ids) and
 * per-game — so the import side can stream one game at a time and keep peak
 * memory bounded. A single power-user export of the full envelope easily ran
 * to hundreds of thousands of rows; building it as one PHP array and JSON-
 * encoding it could OOM the export worker.
 *
 * The shape is intentionally dumb: raw column-value rows, no Eloquent
 * accessors, no transformation. The import side uses DB::insert with the same
 * shape so a Postgres → Postgres round-trip is byte-equivalent for plain
 * columns and string-equivalent for json/jsonb columns (Postgres re-parses
 * them on insert).
 */
class UserExporter
{
    public const FORMAT_VERSION = 2;

    /** @var array<string, true>|null Cached set of tables on the tenant connection. */
    private ?array $tenantTables = null;

    /**
     * Manifest envelope: control-plane rows plus the list of game ids the
     * import side should fetch one at a time.
     *
     * @return array<string, mixed>
     */
    public function exportManifest(int $userId): array
    {
        $user = $this->row('pgsql_control', 'users', 'id', $userId);
        if ($user === null) {
            throw new \RuntimeException("User {$userId} not found on the export side.");
        }

        $controlPlane = ['users' => [$user]];
        foreach (TableManifest::CONTROL_PLANE_TABLES as $table => $meta) {
            if ($table === 'users') {
                continue;
            }
            $controlPlane[$table] = DB::connection('pgsql_control')
                ->table($table)
                ->where($meta['user_key'], $userId)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        $gameIds = DB::table('games')->where('user_id', $userId)->pluck('id')->all();

        return [
            'format_version' => self::FORMAT_VERSION,
            'exported_at' => now()->toAtomString(),
            'user_id' => $userId,
            'control_plane' => $controlPlane,
            'game_ids' => $gameIds,
        ];
    }

    /**
     * Per-game envelope. Validates the game belongs to the verified user
     * before returning anything; the verified user_id comes from the bearer
     * token, so the only way the caller could ask for someone else's game is
     * by guessing a UUID — but we still defend in depth.
     *
     * @return array<string, mixed>
     */
    public function exportGame(int $userId, string $gameId): array
    {
        $owns = DB::table('games')
            ->where('id', $gameId)
            ->where('user_id', $userId)
            ->exists();
        if (! $owns) {
            throw new \RuntimeException("Game {$gameId} does not belong to user {$userId}.");
        }

        $tables = [];
        foreach (TableManifest::TENANT_TABLES_IN_INSERT_ORDER as $table) {
            $tables[$table] = $this->rowsForGame($table, $gameId);
        }

        return [
            'format_version' => self::FORMAT_VERSION,
            'user_id' => $userId,
            'game_id' => $gameId,
            'tables' => $tables,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function rowsForGame(string $table, string $gameId): array
    {
        if (! $this->tenantTableExists($table)) {
            return [];
        }

        // Every tenant table in the manifest carries a direct game_id column,
        // except the `games` root itself which is keyed by `id`. The
        // child-of-child tables (match_events, game_player_match_state,
        // financial_transactions) had `game_id` added in dedicated migrations
        // precisely so a single-column filter can dump them.
        $column = $table === 'games' ? 'id' : 'game_id';

        return DB::table($table)
            ->where($column, $gameId)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function tenantTableExists(string $table): bool
    {
        if ($this->tenantTables === null) {
            // schemaQualified=false — getTableListing() defaults to true,
            // which returns names like "public.games". The manifest stores
            // bare names ("games"), so without this every lookup misses and
            // we silently ship empty tables.
            $this->tenantTables = array_fill_keys(Schema::getTableListing(schemaQualified: false), true);
        }

        return isset($this->tenantTables[$table]);
    }

    /** @return array<string, mixed>|null */
    private function row(string $connection, string $table, string $key, mixed $value): ?array
    {
        $row = DB::connection($connection)->table($table)->where($key, $value)->first();

        return $row ? (array) $row : null;
    }
}
