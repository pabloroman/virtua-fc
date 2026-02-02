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
        'minimum_annual_wage' => 'integer',
    ];

    /**
     * Get the minimum annual wage for this competition.
     * Returns null for cups (they don't have their own minimum).
     */
    public function getMinimumAnnualWageEurosAttribute(): ?int
    {
        if ($this->minimum_annual_wage === null) {
            return null;
        }

        return (int) ($this->minimum_annual_wage / 100);
    }

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
