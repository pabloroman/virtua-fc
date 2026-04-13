<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sparse satellite of GamePlayer holding the per-matchday hot-write state:
 * fitness, morale, injuries, appearances, goals, assists, cards, GK stats.
 *
 * Only populated for "active" players (teams in the user's competition
 * footprint or European opponents for a season the user qualifies for).
 * Pure transfer-pool foreign players have no row here.
 *
 * Read paths go through the {@see GamePlayer} accessor delegates so existing
 * call sites that read `$player->fitness`, `$player->goals` etc. keep working.
 *
 * @property string $game_player_id
 * @property int $fitness
 * @property int $morale
 * @property \Illuminate\Support\Carbon|null $injury_until
 * @property string|null $injury_type
 * @property int $appearances
 * @property int $season_appearances
 * @property int $goals
 * @property int $own_goals
 * @property int $assists
 * @property int $yellow_cards
 * @property int $red_cards
 * @property int $goals_conceded
 * @property int $clean_sheets
 */
class GamePlayerMatchState extends Model
{
    use HasFactory;

    protected $table = 'game_player_match_state';

    protected $primaryKey = 'game_player_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'game_player_id',
        'fitness',
        'morale',
        'injury_until',
        'injury_type',
        'appearances',
        'season_appearances',
        'goals',
        'own_goals',
        'assists',
        'yellow_cards',
        'red_cards',
        'goals_conceded',
        'clean_sheets',
    ];

    protected $casts = [
        'fitness' => 'integer',
        'morale' => 'integer',
        'injury_until' => 'date',
        'appearances' => 'integer',
        'season_appearances' => 'integer',
        'goals' => 'integer',
        'own_goals' => 'integer',
        'assists' => 'integer',
        'yellow_cards' => 'integer',
        'red_cards' => 'integer',
        'goals_conceded' => 'integer',
        'clean_sheets' => 'integer',
    ];

    /**
     * Default values for a freshly-created satellite row. Mirrors the
     * defaults the dropped game_players columns used to carry.
     */
    public const DEFAULTS = [
        'fitness' => 80,
        'morale' => 80,
        'injury_until' => null,
        'injury_type' => null,
        'appearances' => 0,
        'season_appearances' => 0,
        'goals' => 0,
        'own_goals' => 0,
        'assists' => 0,
        'yellow_cards' => 0,
        'red_cards' => 0,
        'goals_conceded' => 0,
        'clean_sheets' => 0,
    ];

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }

    /**
     * Dual-write a raw SQL UPDATE to the legacy `game_players` columns.
     *
     * Only executes while the legacy columns still exist (before the
     * drop-columns migration). This keeps the old columns in sync so a
     * rollback of the code deploy doesn't lose data.
     *
     * @param string $sql  The UPDATE statement targeting `game_players`.
     * @param array  $bindings  Positional bind values.
     */
    public static function legacyWrite(string $sql, array $bindings = []): void
    {
        if (! static::legacyColumnsExist()) {
            return;
        }

        DB::statement($sql, $bindings);
    }

    /**
     * Dual-write an Eloquent-style update to the legacy `game_players` columns.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query  A query scoped to game_players rows.
     * @param array $values  Column => value pairs to update.
     */
    public static function legacyEloquentWrite($query, array $values): void
    {
        if (! static::legacyColumnsExist()) {
            return;
        }

        $query->update($values);
    }

    /**
     * Whether the legacy match-state columns still exist on `game_players`.
     *
     * Cached once per request. Returns true before the drop-columns migration
     * runs, false after. Used by write paths to dual-write to both tables
     * during the transition window so that a rollback doesn't lose data.
     */
    private static ?bool $legacyColumnsExist = null;

    public static function legacyColumnsExist(): bool
    {
        if (self::$legacyColumnsExist === null) {
            self::$legacyColumnsExist = Schema::hasColumn('game_players', 'fitness');
        }

        return self::$legacyColumnsExist;
    }

    /**
     * Reset the cached schema check. Only needed in tests that run
     * migrations mid-test.
     */
    public static function resetLegacyColumnsCache(): void
    {
        self::$legacyColumnsExist = null;
    }

    // ──────────────────────────────────────────────────────────────
    //  Centralized write API
    //
    //  All satellite writes go through these methods so callers never
    //  reference the table name, column list, or dual-write mechanism.
    // ──────────────────────────────────────────────────────────────

    /**
     * Bulk-insert satellite rows. Each entry must contain at least
     * 'game_player_id'; 'fitness' and 'morale' are optional overrides.
     * All other columns are filled from DEFAULTS.
     *
     * Uses insertOrIgnore so existing rows are silently skipped.
     */
    public static function createForPlayers(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $prepared = array_map(function (array $row) {
            return array_merge(self::DEFAULTS, $row);
        }, $rows);

        foreach (array_chunk($prepared, 500) as $chunk) {
            // Filter to only game_player_ids that actually exist, to avoid
            // FK violations when a parent insert was partially skipped.
            $candidateIds = array_column($chunk, 'game_player_id');
            $existingIds = GamePlayer::whereIn('id', $candidateIds)->pluck('id')->all();
            $existingSet = array_flip($existingIds);

            $validChunk = array_filter($chunk, fn ($row) => isset($existingSet[$row['game_player_id']]));

            if (! empty($validChunk)) {
                static::insertOrIgnore(array_values($validChunk));
            }
        }
    }

    /**
     * Create a single satellite row with defaults, or return the existing one.
     */
    public static function createWithDefaults(string $gamePlayerId, int $fitness = 80, int $morale = 80): self
    {
        return static::firstOrCreate(
            ['game_player_id' => $gamePlayerId],
            array_merge(self::DEFAULTS, [
                'fitness' => $fitness,
                'morale' => $morale,
            ]),
        );
    }

    /**
     * Bulk-insert default satellite rows for every player on every team
     * in the given set that doesn't already have one. Idempotent.
     *
     * Used when foreign-league teams enter the user's match scope
     * (e.g., European competition qualification).
     */
    public static function ensureExistForGamePlayers(string $gameId, array $teamIds): void
    {
        if (empty($teamIds)) {
            return;
        }

        $idList = '{' . implode(',', $teamIds) . '}';

        DB::statement(<<<'SQL'
            INSERT INTO game_player_match_state (
                game_player_id, fitness, morale, injury_until, injury_type,
                appearances, season_appearances, goals, own_goals, assists,
                yellow_cards, red_cards, goals_conceded, clean_sheets
            )
            SELECT gp.id, 80, 80, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0
            FROM game_players gp
            WHERE gp.game_id = ?
              AND gp.team_id IN (SELECT unnest(?::uuid[]))
            ON CONFLICT (game_player_id) DO NOTHING
        SQL, [$gameId, $idList]);
    }

    /**
     * Bulk-increment stat columns using a single CASE WHEN query.
     *
     * @param  array<string, array<string, int>>  $increments  [playerId => [column => +amount]]
     */
    public static function bulkIncrementStats(array $increments): void
    {
        if (empty($increments)) {
            return;
        }

        $columns = [];
        foreach ($increments as $playerIncrements) {
            foreach (array_keys($playerIncrements) as $col) {
                $columns[$col] = true;
            }
        }

        $ids = array_keys($increments);
        $idList = "'" . implode("','", $ids) . "'";

        $setClauses = [];
        foreach (array_keys($columns) as $column) {
            $cases = [];
            foreach ($increments as $playerId => $playerIncrements) {
                $amount = $playerIncrements[$column] ?? 0;
                if ($amount !== 0) {
                    $cases[] = "WHEN game_player_id = '{$playerId}' THEN {$column} + {$amount}";
                }
            }
            if (! empty($cases)) {
                $setClauses[] = "{$column} = CASE " . implode(' ', $cases) . " ELSE {$column} END";
            }
        }

        if (! empty($setClauses)) {
            DB::statement('UPDATE game_player_match_state SET ' . implode(', ', $setClauses) . " WHERE game_player_id IN ({$idList})");

            $legacyClauses = str_replace('game_player_id', 'id', implode(', ', $setClauses));
            static::legacyWrite("UPDATE game_players SET {$legacyClauses} WHERE id IN ({$idList})");
        }
    }

    /**
     * Increment appearances and season_appearances for the given player IDs.
     *
     * @param  string[]  $playerIds
     */
    public static function bulkIncrementAppearances(array $playerIds): void
    {
        if (empty($playerIds)) {
            return;
        }

        $values = [
            'appearances' => DB::raw('appearances + 1'),
            'season_appearances' => DB::raw('season_appearances + 1'),
        ];

        DB::table('game_player_match_state')
            ->whereIn('game_player_id', $playerIds)
            ->update($values);

        static::legacyEloquentWrite(
            GamePlayer::whereIn('id', $playerIds),
            $values
        );
    }

    /**
     * Bulk-set absolute values using a single CASE WHEN query.
     *
     * @param  array<string, array<string, mixed>>  $updates  [playerId => [column => value]]
     */
    public static function bulkSetValues(array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        $columns = [];
        foreach ($updates as $playerValues) {
            foreach (array_keys($playerValues) as $col) {
                $columns[$col] = true;
            }
        }

        $ids = array_keys($updates);
        $idList = "'" . implode("','", $ids) . "'";

        $setClauses = [];
        foreach (array_keys($columns) as $column) {
            $cases = [];
            foreach ($updates as $playerId => $playerValues) {
                if (array_key_exists($column, $playerValues)) {
                    $value = $playerValues[$column];
                    $cases[] = "WHEN game_player_id = '{$playerId}' THEN {$value}";
                }
            }
            if (! empty($cases)) {
                $setClauses[] = "{$column} = CASE " . implode(' ', $cases) . " ELSE {$column} END";
            }
        }

        if (! empty($setClauses)) {
            DB::statement('UPDATE game_player_match_state SET ' . implode(', ', $setClauses) . " WHERE game_player_id IN ({$idList})");

            $legacyClauses = str_replace('game_player_id', 'id', implode(', ', $setClauses));
            static::legacyWrite("UPDATE game_players SET {$legacyClauses} WHERE id IN ({$idList})");
        }
    }

    /**
     * Reset all satellite rows for a game to the given values.
     */
    public static function bulkResetForGame(string $gameId, array $values): void
    {
        DB::table('game_player_match_state')
            ->whereIn('game_player_id', function ($q) use ($gameId) {
                $q->select('id')->from('game_players')->where('game_id', $gameId);
            })
            ->update($values);

        static::legacyEloquentWrite(
            GamePlayer::where('game_id', $gameId),
            $values
        );
    }

    /**
     * Set injury fields for a single player.
     */
    public static function setInjury(string $gamePlayerId, string $injuryType, string $injuryUntil): void
    {
        $values = [
            'injury_type' => $injuryType,
            'injury_until' => $injuryUntil,
        ];

        static::where('game_player_id', $gamePlayerId)->update($values);
        static::legacyEloquentWrite(
            GamePlayer::where('id', $gamePlayerId), $values
        );
    }

    /**
     * Batch-set injuries for multiple players in a single query.
     *
     * @param  array<array{playerId: string, injuryType: string, injuryUntil: \Carbon\Carbon}>  $injuries
     */
    public static function bulkSetInjuries(array $injuries): void
    {
        if (empty($injuries)) {
            return;
        }

        $typeCases = [];
        $untilCases = [];
        $ids = [];
        foreach ($injuries as $injury) {
            $id = $injury['playerId'];
            $type = str_replace("'", "''", $injury['injuryType']);
            $until = $injury['injuryUntil']->toDateString();
            $ids[] = "'{$id}'";
            $typeCases[] = "WHEN game_player_id = '{$id}' THEN '{$type}'";
            $untilCases[] = "WHEN game_player_id = '{$id}' THEN '{$until}'::date";
        }

        $idList = implode(',', $ids);
        DB::statement(
            'UPDATE game_player_match_state SET injury_type = CASE ' . implode(' ', $typeCases) . ' END, '
            . 'injury_until = CASE ' . implode(' ', $untilCases) . ' END '
            . "WHERE game_player_id IN ({$idList})"
        );

        $legacyTypeCases = str_replace('game_player_id', 'id', implode(' ', $typeCases));
        $legacyUntilCases = str_replace('game_player_id', 'id', implode(' ', $untilCases));
        static::legacyWrite(
            "UPDATE game_players SET injury_type = CASE {$legacyTypeCases} END, injury_until = CASE {$legacyUntilCases} END WHERE id IN ({$idList})"
        );
    }

    /**
     * Clear injury fields for a single player.
     */
    public static function clearInjury(string $gamePlayerId): void
    {
        $values = ['injury_type' => null, 'injury_until' => null];

        static::where('game_player_id', $gamePlayerId)->update($values);
        static::legacyEloquentWrite(
            GamePlayer::where('id', $gamePlayerId), $values
        );
    }

    /**
     * Decrement appearances for the given player IDs (used during resimulation).
     *
     * @param  string[]  $playerIds
     */
    public static function bulkDecrementAppearances(array $playerIds): void
    {
        if (empty($playerIds)) {
            return;
        }

        $values = [
            'appearances' => DB::raw('GREATEST(appearances - 1, 0)'),
            'season_appearances' => DB::raw('GREATEST(season_appearances - 1, 0)'),
        ];

        static::whereIn('game_player_id', $playerIds)
            ->where('appearances', '>', 0)
            ->update($values);

        static::legacyEloquentWrite(
            GamePlayer::whereIn('id', $playerIds)->where('appearances', '>', 0),
            $values
        );
    }
}
