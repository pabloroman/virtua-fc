<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixtureTemplate extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'competition_id',
        'season',
        'round_number',
        'match_number',
        'home_team_id',
        'away_team_id',
        'scheduled_date',
        'location',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'match_number' => 'integer',
        'scheduled_date' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
