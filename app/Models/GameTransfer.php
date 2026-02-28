<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTransfer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'game_player_id',
        'from_team_id',
        'to_team_id',
        'transfer_fee',
        'type',
        'season',
        'window',
    ];

    protected $casts = [
        'transfer_fee' => 'integer',
    ];

    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_FREE_AGENT = 'free_agent';
    public const TYPE_LOAN = 'loan';

    /**
     * Record a completed transfer in the ledger.
     */
    public static function record(
        string $gameId,
        string $gamePlayerId,
        ?string $fromTeamId,
        string $toTeamId,
        int $transferFee,
        string $type,
        string $season,
        string $window,
    ): static {
        return static::create([
            'game_id' => $gameId,
            'game_player_id' => $gamePlayerId,
            'from_team_id' => $fromTeamId,
            'to_team_id' => $toTeamId,
            'transfer_fee' => $transferFee,
            'type' => $type,
            'season' => $season,
            'window' => $window,
        ]);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }
}
