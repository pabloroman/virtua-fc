<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $game_id
 * @property string $season
 * @property string|null $storage_path
 * @property array<array-key, mixed>|null $final_standings
 * @property array<array-key, mixed>|null $player_season_stats
 * @property array<array-key, mixed>|null $season_awards
 * @property array<array-key, mixed>|null $match_results
 * @property array<array-key, mixed>|null $transfer_activity
 * @property string|null $match_events_archive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read array|null $best_goalkeeper
 * @property-read array|null $champion
 * @property-read array $match_events
 * @property-read array|null $most_assists
 * @property-read array|null $top_scorer
 */
class SeasonArchive extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'season',
        'storage_path',
        'final_standings',
        'player_season_stats',
        'season_awards',
        'match_results',
        'transfer_activity',
        'match_events_archive',
    ];

    protected $casts = [
        'final_standings' => 'array',
        'player_season_stats' => 'array',
        'season_awards' => 'array',
        'match_results' => 'array',
        'transfer_activity' => 'array',
    ];

    /**
     * Cached archive data loaded from object storage.
     */
    private ?array $storageData = null;

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Upload archive data to object storage and set the storage_path.
     */
    public static function createInStorage(array $attributes, array $archiveData): static
    {
        $path = "{$attributes['game_id']}/{$attributes['season']}.json.gz";
        $compressed = gzcompress(json_encode($archiveData), 9);

        Storage::disk('season-archives')->put($path, $compressed);

        return static::create([
            'game_id' => $attributes['game_id'],
            'season' => $attributes['season'],
            'storage_path' => $path,
        ]);
    }

    /**
     * Load archive data from object storage (cached for request lifecycle).
     */
    private function loadFromStorage(): array
    {
        if ($this->storageData === null) {
            $compressed = Storage::disk('season-archives')->get($this->storage_path);
            $this->storageData = json_decode(gzuncompress($compressed), true) ?? [];
        }

        return $this->storageData;
    }

    /**
     * Check if this archive uses object storage or legacy DB columns.
     */
    private function usesStorage(): bool
    {
        return $this->storage_path !== null;
    }

    /**
     * Get final standings, from storage or legacy DB column.
     */
    public function getFinalStandingsAttribute(): array
    {
        if ($this->usesStorage()) {
            return $this->loadFromStorage()['final_standings'] ?? [];
        }

        $value = $this->attributes['final_standings'] ?? null;

        return $value ? (is_string($value) ? json_decode($value, true) : $value) : [];
    }

    /**
     * Get player season stats, from storage or legacy DB column.
     */
    public function getPlayerSeasonStatsAttribute(): array
    {
        if ($this->usesStorage()) {
            return $this->loadFromStorage()['player_season_stats'] ?? [];
        }

        $value = $this->attributes['player_season_stats'] ?? null;

        return $value ? (is_string($value) ? json_decode($value, true) : $value) : [];
    }

    /**
     * Get season awards, from storage or legacy DB column.
     */
    public function getSeasonAwardsAttribute(): array
    {
        if ($this->usesStorage()) {
            return $this->loadFromStorage()['season_awards'] ?? [];
        }

        $value = $this->attributes['season_awards'] ?? null;

        return $value ? (is_string($value) ? json_decode($value, true) : $value) : [];
    }

    /**
     * Get match results, from storage or legacy DB column.
     */
    public function getMatchResultsAttribute(): array
    {
        if ($this->usesStorage()) {
            return $this->loadFromStorage()['match_results'] ?? [];
        }

        $value = $this->attributes['match_results'] ?? null;

        return $value ? (is_string($value) ? json_decode($value, true) : $value) : [];
    }

    /**
     * Get transfer activity, from storage or legacy DB column.
     */
    public function getTransferActivityAttribute(): array
    {
        if ($this->usesStorage()) {
            return $this->loadFromStorage()['transfer_activity'] ?? [];
        }

        $value = $this->attributes['transfer_activity'] ?? null;

        return $value ? (is_string($value) ? json_decode($value, true) : $value) : [];
    }

    /**
     * Get decompressed match events from storage or legacy archive column.
     */
    public function getMatchEventsAttribute(): array
    {
        if ($this->usesStorage()) {
            return $this->loadFromStorage()['match_events'] ?? [];
        }

        if (empty($this->attributes['match_events_archive'] ?? null)) {
            return [];
        }

        $decoded = @base64_decode($this->attributes['match_events_archive'], true);

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
