<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_player_id
 * @property string $competition_id
 * @property int $matches_remaining
 * @property int $yellow_cards
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Competition|null $competition
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereMatchesRemaining($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayerSuspension whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PlayerSuspension extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_player_id',
        'competition_id',
        'matches_remaining',
        'yellow_cards',
    ];

    protected $casts = [
        'matches_remaining' => 'integer',
        'yellow_cards' => 'integer',
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
     * Decrement matches remaining and clear when fully served.
     *
     * @return bool True if suspension is now cleared
     */
    public function serveMatch(): bool
    {
        $this->matches_remaining--;

        if ($this->matches_remaining <= 0) {
            $this->matches_remaining = 0;
            $this->save();

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
            ['game_player_id' => $gamePlayerId, 'competition_id' => $competitionId],
            ['matches_remaining' => $matches],
        );
    }

    /**
     * Record a yellow card for a player in a competition.
     *
     * @return int The updated yellow card count for this competition
     */
    public static function recordYellowCard(string $gamePlayerId, string $competitionId): int
    {
        $record = self::firstOrCreate(
            ['game_player_id' => $gamePlayerId, 'competition_id' => $competitionId],
            ['matches_remaining' => 0, 'yellow_cards' => 0],
        );

        $record->increment('yellow_cards');
        $record->refresh();

        return $record->yellow_cards;
    }

    /**
     * Revert a yellow card for a player in a competition (used during match resimulation).
     */
    public static function revertYellowCard(string $gamePlayerId, string $competitionId): void
    {
        $record = self::forPlayerInCompetition($gamePlayerId, $competitionId);

        if ($record && $record->yellow_cards > 0) {
            $record->decrement('yellow_cards');
        }
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

        return $suspension->matches_remaining ?? 0;
    }

    /**
     * Get yellow card count for a player in a specific competition.
     */
    public static function getYellowCards(string $gamePlayerId, string $competitionId): int
    {
        return self::where('game_player_id', $gamePlayerId)
            ->where('competition_id', $competitionId)
            ->value('yellow_cards') ?? 0;
    }
}
