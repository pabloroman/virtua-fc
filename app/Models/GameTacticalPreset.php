<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTacticalPreset extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'name',
        'sort_order',
        'formation',
        'lineup',
        'slot_assignments',
        'pitch_positions',
        'mentality',
        'playing_style',
        'pressing',
        'defensive_line',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'lineup' => 'array',
        'slot_assignments' => 'array',
        'pitch_positions' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
