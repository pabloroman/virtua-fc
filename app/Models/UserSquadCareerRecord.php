<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Career history for a player currently owned by the user's club.
 *
 * Lifecycle:
 *  - inserted when a GamePlayer becomes user-owned (academy/filial generation,
 *    transfer in, internal promotion to/within the user's organisation),
 *  - updated each season-close to append that season's stats to season_stats,
 *  - deleted when the player ceases to be user-owned (sold, retired, etc.).
 *
 * One row per user-owned GamePlayer. AI-vs-AI players have no row.
 *
 * @property string $id
 * @property string $game_player_id
 * @property string $game_id
 * @property string $team_id
 * @property int $joined_season
 * @property string|null $joined_from   'Academy' sentinel or previous team name snapshot
 * @property bool $homegrown            developed by the club (academy or filial promotion)
 * @property array $season_stats        keyed by season number
 */
class UserSquadCareerRecord extends Model
{
    use HasFactory, HasUuids;

    public const ORIGIN_ACADEMY = 'Academy';

    public const ORIGIN_FREE_AGENT = 'FreeAgent';

    public $timestamps = false;

    protected $fillable = [
        'game_player_id',
        'game_id',
        'team_id',
        'joined_season',
        'joined_from',
        'homegrown',
        'season_stats',
    ];

    protected $casts = [
        'joined_season' => 'integer',
        'homegrown' => 'boolean',
        'season_stats' => 'array',
    ];

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
