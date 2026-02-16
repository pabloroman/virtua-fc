<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $season
 * @property array<array-key, mixed> $final_standings
 * @property array<array-key, mixed> $player_season_stats
 * @property array<array-key, mixed> $season_awards
 * @property array<array-key, mixed> $match_results
 * @property string|null $match_events_archive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read array|null $best_goalkeeper
 * @property-read array|null $champion
 * @property-read array $match_events
 * @property-read array|null $most_assists
 * @property-read array|null $top_scorer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereFinalStandings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereMatchEventsArchive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereMatchResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive wherePlayerSeasonStats($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereSeasonAwards($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonArchive whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SeasonArchive extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'season',
        'final_standings',
        'player_season_stats',
        'season_awards',
        'match_results',
        'match_events_archive',
    ];

    protected $casts = [
        'final_standings' => 'array',
        'player_season_stats' => 'array',
        'season_awards' => 'array',
        'match_results' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get decompressed match events from archive.
     */
    public function getMatchEventsAttribute(): array
    {
        if (empty($this->match_events_archive)) {
            return [];
        }

        $decoded = @base64_decode($this->match_events_archive, true);

        if ($decoded === false) {
            return [];
        }

        $decompressed = @gzuncompress($decoded);

        if ($decompressed === false) {
            return [];
        }

        return json_decode($decompressed, true) ?? [];
    }

    /**
     * Get the champion team from awards.
     */
    public function getChampionAttribute(): ?array
    {
        return $this->season_awards['champion'] ?? null;
    }

    /**
     * Get the top scorer from awards.
     */
    public function getTopScorerAttribute(): ?array
    {
        return $this->season_awards['top_scorer'] ?? null;
    }

    /**
     * Get the most assists from awards.
     */
    public function getMostAssistsAttribute(): ?array
    {
        return $this->season_awards['most_assists'] ?? null;
    }

    /**
     * Get the best goalkeeper from awards.
     */
    public function getBestGoalkeeperAttribute(): ?array
    {
        return $this->season_awards['best_goalkeeper'] ?? null;
    }
}
