<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'colors' => 'array',
        'stadium_seats' => 'integer',
        'founded_on' => 'date',
    ];

    public function competitions(): BelongsToMany
    {
        return $this->belongsToMany(Competition::class, 'competition_teams')
            ->withPivot('season');
    }

    public function players(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function getGoalDifferenceAttribute(): int
    {
        return 0; // Placeholder for team-level stats
    }
}
