<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property int|null $transfermarkt_id
 * @property string $name
 * @property string $country
 * @property string|null $image
 * @property string|null $stadium_name
 * @property int $stadium_seats
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
        'name',
        'official_name',
        'country',
        'image',
        'stadium_name',
        'stadium_seats',
        'colors',
        'current_market_value',
        'founded_on',
    ];

    protected $casts = [
        'stadium_seats' => 'integer',
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

    public function clubProfile(): HasOne
    {
        return $this->hasOne(ClubProfile::class);
    }

    public function getImageAttribute(): ?string
    {
        $originalUrl = $this->attributes['image'] ?? null;

        if ($this->transfermarkt_id) {
            $localPath = "crests/{$this->transfermarkt_id}.png";
            if (file_exists(public_path($localPath))) {
                return "/{$localPath}";
            }
        }

        return $originalUrl;
    }

    public function getGoalDifferenceAttribute(): int
    {
        return 0; // Placeholder for team-level stats
    }
}
