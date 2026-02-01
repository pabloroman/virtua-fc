<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonArchive extends Model
{
    use HasUuids;

    protected $guarded = [];

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

        $decompressed = @gzuncompress($this->match_events_archive);

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
