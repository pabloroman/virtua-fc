<?php

namespace App\Models;

use App\Modules\Competition\Configs\DefaultLeagueConfig;
use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Services\CountryConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $name
 * @property string $country
 * @property int $tier
 * @property string $type
 * @property string $season
 * @property string $handler_type
 * @property string $role
 * @property string $scope
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @method static \Database\Factories\CompetitionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereHandlerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereType($value)
 * @mixin \Eloquent
 */
class Competition extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public const ROLE_LEAGUE = 'league';
    public const ROLE_DOMESTIC_CUP = 'domestic_cup';
    public const ROLE_EUROPEAN = 'european';
    public const ROLE_TEAM_POOL = 'team_pool';

    /** @deprecated Use ROLE_LEAGUE instead — kept only for migration compatibility */
    public const ROLE_PRIMARY = 'league';
    /** @deprecated Use ROLE_LEAGUE instead — kept only for migration compatibility */
    public const ROLE_FOREIGN = 'league';

    public const SCOPE_DOMESTIC = 'domestic';
    public const SCOPE_CONTINENTAL = 'continental';

    protected $fillable = [
        'id',
        'name',
        'country',
        'tier',
        'type',
        'role',
        'scope',
        'season',
        'handler_type',
    ];

    protected $casts = [
        'tier' => 'integer',
    ];

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'competition_teams')
            ->withPivot('season')
            ->orderBy('name');
    }

    public function isLeague(): bool
    {
        return in_array($this->handler_type, ['league', 'league_with_playoff', 'swiss_format', 'group_stage_cup']);
    }

    public function isCup(): bool
    {
        return !$this->isLeague();
    }

    /**
     * Whether this is a domestic tier league (tier >= 1).
     * Replaces the old ROLE_PRIMARY / ROLE_FOREIGN distinction.
     */
    public function isTierLeague(): bool
    {
        return $this->role === self::ROLE_LEAGUE && $this->tier >= 1;
    }

    /**
     * Get the configuration for this competition.
     * Checks country config for a specific config class, falls back to defaults.
     */
    public function getConfig(): CompetitionConfig
    {
        // Check country config for a specific config class
        $configClass = app(CountryConfig::class)->configClassForCompetition($this->id);
        if ($configClass) {
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
