<?php

namespace App\Models;

use App\Game\Competitions\DefaultLeagueConfig;
use App\Game\Competitions\LaLiga2Config;
use App\Game\Competitions\LaLigaConfig;
use App\Game\Contracts\CompetitionConfig;
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
     * Competition ID to config class mapping.
     */
    private const CONFIG_MAP = [
        'ESP1' => LaLigaConfig::class,
        'ESP2' => LaLiga2Config::class,
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
            ->withPivot('season')
            ->orderBy('name');
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

    /**
     * Get the configuration for this competition.
     * Returns a competition-specific config or a default based on tier.
     */
    public function getConfig(): CompetitionConfig
    {
        // Check for specific competition config
        if (isset(self::CONFIG_MAP[$this->id])) {
            $configClass = self::CONFIG_MAP[$this->id];
            return new $configClass();
        }

        // Default config based on number of teams in the competition
        $numTeams = $this->teams()->count();
        if ($numTeams === 0) {
            $numTeams = 20; // Fallback
        }

        // Scale base TV revenue by tier
        $baseTvRevenue = match ($this->tier) {
            1 => 5_000_000_000,  // €50M base for tier 1
            2 => 1_000_000_000,  // €10M base for tier 2
            default => 500_000_000, // €5M base for lower tiers
        };

        return new DefaultLeagueConfig($numTeams, $baseTvRevenue);
    }
}
