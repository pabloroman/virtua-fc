<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameCompetitionTeam extends Model
{
    protected $table = 'game_competition_teams';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'game_id',
        'competition_id',
        'team_id',
        'entry_round',
    ];

    protected $casts = [
        'entry_round' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
