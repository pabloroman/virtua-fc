<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $game_id
 * @property int $user_id
 * @property string $team_id
 * @property string $competition_id
 * @property string $share_token
 * @property string $result_label
 * @property array $stats
 * @property array $squad_player_ids
 * @property array $squad_highlights
 */
class TournamentChallenge extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'user_id',
        'team_id',
        'competition_id',
        'share_token',
        'result_label',
        'stats',
        'squad_player_ids',
        'squad_highlights',
    ];

    protected $casts = [
        'stats' => 'array',
        'squad_player_ids' => 'array',
        'squad_highlights' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public static function generateShareToken(): string
    {
        do {
            $token = Str::random(10);
        } while (self::where('share_token', $token)->exists());

        return $token;
    }

    public function getShareUrl(): string
    {
        return route('challenge.show', $this->share_token);
    }
}
