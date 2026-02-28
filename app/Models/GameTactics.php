<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTactics extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'default_formation',
        'default_lineup',
        'default_slot_assignments',
        'default_pitch_positions',
        'default_mentality',
        'default_playing_style',
        'default_pressing',
        'default_defensive_line',
    ];

    protected $casts = [
        'default_lineup' => 'array',
        'default_slot_assignments' => 'array',
        'default_pitch_positions' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
