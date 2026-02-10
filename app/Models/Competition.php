<?php

namespace App\Models;

use App\Game\Competitions\ChampionsLeagueConfig;
use App\Game\Competitions\ConferenceLeagueConfig;
use App\Game\Competitions\DefaultLeagueConfig;
use App\Game\Competitions\EuropaLeagueConfig;
use App\Game\Competitions\LaLiga2Config;
use App\Game\Competitions\LaLigaConfig;
use App\Game\Contracts\CompetitionConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Competition extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public const ROLE_PRIMARY = 'primary';
    public const ROLE_DOMESTIC_CUP = 'domestic_cup';
    public const ROLE_CONTINENTAL = 'continental';
    public const ROLE_FOREIGN = 'foreign';

    protected $fillable = [
        'id',
        'name',
        'country',
        'tier',
        'type',
        'role',
        'season',
        'handler_type',
    ];

    protected $casts = [
        'tier' => 'integer',
    ];

    /**
     * Competition ID to config class mapping.
     */
    private const CONFIG_MAP = [
        'ESP1' => LaLigaConfig::class,
        'ESP2' => LaLiga2Config::class,
        'UCL' => ChampionsLeagueConfig::class,
        'UEL' => EuropaLeagueConfig::class,
        'UECL' => ConferenceLeagueConfig::class,
    ];

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'competition_teams')
            ->withPivot('season')
            ->orderBy('name');
    }

    public function isLeague(): bool
    {
        return in_array($this->handler_type, ['league', 'league_with_playoff', 'swiss_format']);
    }

    public function isCup(): bool
    {
        return !$this->isLeague();
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
