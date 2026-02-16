<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'transfermarkt_id',
        'type',
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
            return '/flags/' . strtolower($this->country) . '.svg';
        }

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
