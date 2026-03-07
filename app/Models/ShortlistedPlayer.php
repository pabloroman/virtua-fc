<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortlistedPlayer extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const INTEL_SURFACE = 0;
    public const INTEL_REPORT = 1;
    public const INTEL_DEEP = 2;

    protected $fillable = [
        'game_id',
        'game_player_id',
        'added_at',
        'intel_level',
        'is_tracking',
        'matchdays_tracked',
    ];

    protected $casts = [
        'added_at' => 'date',
        'intel_level' => 'integer',
        'is_tracking' => 'boolean',
        'matchdays_tracked' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function hasReportLevel(): bool
    {
        return $this->intel_level >= self::INTEL_REPORT;
    }

    public function hasDeepIntel(): bool
    {
        return $this->intel_level >= self::INTEL_DEEP;
    }

    /**
     * Remove a player from the shortlist (frees tracking slot).
     */
    public static function removeForPlayer(string $gameId, string $playerId): void
    {
        static::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->delete();
    }
}
