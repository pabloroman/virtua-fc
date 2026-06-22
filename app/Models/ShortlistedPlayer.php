<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortlistedPlayer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'game_player_id',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'date',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    /**
     * Remove a player from the shortlist.
     */
    public static function removeForPlayer(string $gameId, string $playerId): void
    {
        static::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->delete();
    }
}
