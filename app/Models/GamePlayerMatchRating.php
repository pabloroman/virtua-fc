<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $game_match_id
 * @property string $game_player_id
 * @property float $rating
 * @property float $performance_modifier
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GameMatch $gameMatch
 * @property-read \App\Models\GamePlayer $gamePlayer
 */
class GamePlayerMatchRating extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'game_match_id',
        'game_player_id',
        'rating',
        'performance_modifier',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'performance_modifier' => 'decimal:3',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }
}
