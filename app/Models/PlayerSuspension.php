<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSuspension extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'matches_remaining' => 'integer',
    ];

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id', 'id');
    }

    /**
     * Decrement matches remaining and delete if served.
     *
     * @return bool True if suspension is now cleared
     */
    public function serveMatch(): bool
    {
        $this->matches_remaining--;

        if ($this->matches_remaining <= 0) {
            $this->delete();
            return true;
        }

        $this->save();
        return false;
    }

    /**
     * Create or update a suspension for a player in a competition.
     */
    public static function applySuspension(string $gamePlayerId, string $competitionId, int $matches): self
    {
        return self::updateOrCreate(
            [
                'game_player_id' => $gamePlayerId,
                'competition_id' => $competitionId,
            ],
            [
                'matches_remaining' => $matches,
            ]
        );
    }

    /**
     * Get suspension for a player in a specific competition.
     */
    public static function forPlayerInCompetition(string $gamePlayerId, string $competitionId): ?self
    {
        return self::where('game_player_id', $gamePlayerId)
            ->where('competition_id', $competitionId)
            ->first();
    }

    /**
     * Check if a player is suspended in a specific competition.
     */
    public static function isSuspended(string $gamePlayerId, string $competitionId): bool
    {
        return self::where('game_player_id', $gamePlayerId)
            ->where('competition_id', $competitionId)
            ->where('matches_remaining', '>', 0)
            ->exists();
    }

    /**
     * Get remaining matches for a player in a competition.
     */
    public static function getMatchesRemaining(string $gamePlayerId, string $competitionId): int
    {
        $suspension = self::forPlayerInCompetition($gamePlayerId, $competitionId);
        return $suspension?->matches_remaining ?? 0;
    }
}
