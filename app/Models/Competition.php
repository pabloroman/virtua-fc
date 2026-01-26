<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'tier' => 'integer',
    ];

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'competition_teams')
            ->withPivot('season');
    }

    public function fixtureTemplates(): HasMany
    {
        return $this->hasMany(FixtureTemplate::class);
    }

    public function isLeague(): bool
    {
        return $this->type === 'league';
    }

    public function isCup(): bool
    {
        return $this->type === 'cup';
    }
}
