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
 * @property string|null $flag
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

    /**
     * Short display names for competitions, keyed by competition ID.
     * Falls back to the full name if not mapped here.
     */
    private const SHORT_NAMES = [
        'ESP1'    => 'Liga',
        'ESP2'    => 'Liga 2',
        'ESP3A'   => 'Primera Fed. - I',
        'ESP3B'   => 'Primera Fed. - II',
        'ESP3PO'  => 'Playoff ascenso',
        'ESPCUP'  => 'Copa del Rey',
        'ESPSUP'  => 'Supercopa',
        'ENG1'    => 'Premier League',
        'DEU1'    => 'Bundesliga',
        'FRA1'    => 'Ligue 1',
        'ITA1'    => 'Serie A',
        'UCL'     => 'Champions League',
        'UEL'     => 'Europa League',
        'UECL'    => 'Conference League',
        'UEFASUP' => 'Super Cup',
        'WC2026'  => 'Mundial',
        'PRESEASON' => 'Amistoso',
    ];

    // Ultra-compact tags for tight layouts (narrow dashboard column). Falls back
    // to shortName() for anything not listed here.
    private const ABBREVIATIONS = [
        'ESP1'    => 'Liga',
        'ESP2'    => 'Liga 2',
        'ESP3A'   => '1ªFed I',
        'ESP3B'   => '1ªFed II',
        'ESP3PO'  => 'Playoff',
        'ESPCUP'  => 'Copa',
        'ESPSUP'  => 'Supercopa',
        'ENG1'    => 'Premier',
        'UCL'     => 'UCL',
        'UEL'     => 'UEL',
        'UECL'    => 'UECL',
        'UEFASUP' => 'Supercup',
    ];

    protected $fillable = [
        'id',
        'name',
        'country',
        'flag',
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

    public function shortName(): string
    {
        return self::SHORT_NAMES[$this->id] ?? $this->name;
    }

    public function abbreviation(): string
    {
        return self::ABBREVIATIONS[$this->id] ?? $this->shortName();
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
     * Whether this is a domestic round-robin league (ESP1, ENG1, …) as opposed
     * to a cup or a continental competition. Unlike isLeague(), this EXCLUDES
     * the continental Swiss/group formats. Used to decide whether a match's
     * strength normalization should use that league's own rating band (domestic
     * league) or the global cross-band scale (cups, Europe, World Cup).
     */
    public function isDomesticLeague(): bool
    {
        return in_array($this->handler_type, ['league', 'league_with_playoff'], true)
            && $this->role === self::ROLE_LEAGUE
            && $this->scope === self::SCOPE_DOMESTIC;
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
