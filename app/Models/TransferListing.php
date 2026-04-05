<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferListing extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUS_LISTED = 'listed';
    public const STATUS_LOAN_SEARCH = 'loan_search';

    protected $fillable = [
        'game_id',
        'game_player_id',
        'team_id',
        'status',
        'listed_at',
        'asking_price',
    ];

    protected $casts = [
        'listed_at' => 'date',
        'asking_price' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isUserListing(Game $game): bool
    {
        return $this->team_id === $game->team_id;
    }
}
