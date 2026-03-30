<?php

namespace App\Models;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property int|null $transfermarkt_id
 * @property string|null $fifa_code
 * @property bool $is_placeholder
 * @property string $name
 * @property string|null $slug
 * @property string $country
 * @property string|null $image
 * @property string|null $stadium_name
 * @property int $stadium_seats
 * @property array|null $colors
 * @property-read \App\Models\ClubProfile|null $clubProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Competition> $competitions
 * @property-read int|null $competitions_count
 * @property-read int $goal_difference
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $players
 * @property-read int|null $players_count
 * @method static \Database\Factories\TeamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereStadiumName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereStadiumSeats($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereTransfermarktId($value)
 * @mixin \Eloquent
 */
class Team extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'transfermarkt_id',
        'type',
        'fifa_code',
        'is_placeholder',
        'name',
        'slug',
        'country',
        'parent_team_id',
        'image',
        'stadium_name',
        'stadium_seats',
        'colors',
    ];

    protected $casts = [
        'stadium_seats' => 'integer',
        'is_placeholder' => 'boolean',
        'colors' => 'array',
    ];

    public function scopeWorldCupEligible(Builder $query): Builder
    {
        return $query->where('type', 'national')->whereNotNull('fifa_code');
    }

    /**
     * Exclude national teams and cup-only teams (e.g. Primera RFEF filler)
     * from transfer market queries.
     */
    public function scopeTransferMarketEligible(Builder $query): Builder
    {
        return $query
            ->where('type', '!=', 'national')
            ->whereHas('competitions', fn (Builder $q) => $q->where('role', '!=', Competition::ROLE_DOMESTIC_CUP));
    }

    public function competitions(): BelongsToMany
    {
        return $this->belongsToMany(Competition::class, 'competition_teams')
            ->withPivot('season');
    }

    public function players(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function clubProfile(): HasOne
    {
        return $this->hasOne(ClubProfile::class);
    }

    public function parentTeam(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    public function reserveTeam(): HasOne
    {
        return $this->hasOne(Team::class, 'parent_team_id');
    }

    public function isReserveTeam(): bool
    {
        return $this->parent_team_id !== null;
    }

    public function getNameAttribute(): string
    {
        $name = $this->attributes['name'] ?? '';

        if (($this->attributes['type'] ?? 'club') === 'national') {
            return __("countries.{$name}") ?? $name;
        }

        return $name;
    }

    public function getImageAttribute(): ?string
    {
        // National teams use flag SVGs
        if (($this->attributes['type'] ?? 'club') === 'national') {
            return Storage::disk('assets')->url('flags/' . strtolower($this->country) . '.svg');
        }

        $originalUrl = $this->attributes['image'] ?? null;

        if ($this->transfermarkt_id) {
            $localPath = "crests/{$this->transfermarkt_id}.png";
            if (file_exists(public_path($localPath))) {
                return Storage::disk('assets')->url($localPath);
            }
        }

        return $originalUrl;
    }

    public function getGoalDifferenceAttribute(): int
    {
        return 0; // Placeholder for team-level stats
    }

    /**
     * Get the Spanish grammatical article for this team name.
     *
     * Most teams use "el" (masculine): "del Real Madrid", "al Barcelona"
     * Some use "la" (feminine): "de la Real Sociedad", "a la UD Almería"
     * Some use no article: "de Osasuna", "a Osasuna"
     */
    public function getArticleAttribute(): ?string
    {
        $name = $this->attributes['name'] ?? '';

        if ($name === 'CA Osasuna') {
            return null;
        }

        if (str_starts_with($name, 'UD ') || str_starts_with($name, 'Real Sociedad') || $name === 'Cultural Leonesa') {
            return 'la';
        }

        return 'el';
    }

    /**
     * Team name with "de" preposition: "del Real Madrid", "de la Real Sociedad", "de Osasuna"
     */
    public function nameWithDe(): string
    {
        return match ($this->article) {
            'la' => 'de la ' . $this->name,
            null => 'de ' . $this->name,
            default => 'del ' . $this->name,
        };
    }

    /**
     * Team name with "a" preposition: "al Real Madrid", "a la Real Sociedad", "a Osasuna"
     */
    public function nameWithA(): string
    {
        return match ($this->article) {
            'la' => 'a la ' . $this->name,
            null => 'a ' . $this->name,
            default => 'al ' . $this->name,
        };
    }

    /**
     * Team name with "en" preposition: "en el Real Madrid", "en la Real Sociedad", "en Osasuna"
     */
    public function nameWithEn(): string
    {
        return match ($this->article) {
            'la' => 'en la ' . $this->name,
            null => 'en ' . $this->name,
            default => 'en el ' . $this->name,
        };
    }
}
