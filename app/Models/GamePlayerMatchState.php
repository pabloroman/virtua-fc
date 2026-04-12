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
}
