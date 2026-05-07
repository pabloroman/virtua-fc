<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $season
 * @property string $competition_id
 * @property array<array-key, mixed> $results
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Game $game
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SimulatedSeason whereSeason($value)
 * @mixin \Eloquent
 */
class SimulatedSeason extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'season',
        'competition_id',
        'results',
    ];

    protected $casts = [
        'results' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * Get the winner (1st place) team ID.
     */
    public function getWinnerTeamId(): ?string
    {
        return $this->results[0] ?? null;
    }

    /**
     * Get the runner-up (2nd place) team ID.
     */
    public function getRunnerUpTeamId(): ?string
    {
        return $this->results[1] ?? null;
    }

    /**
     * Get team IDs at specific positions (1-indexed).
     *
     * @param array<int> $positions e.g. [18, 19, 20] for bottom 3
     * @return array<string> Team IDs at those positions
     */
    public function getTeamIdsAtPositions(array $positions): array
    {
        $teamIds = [];

        foreach ($positions as $position) {
            $index = $position - 1; // Convert 1-indexed to 0-indexed
            if (isset($this->results[$index])) {
                $teamIds[] = $this->results[$index];
            }
        }

        return $teamIds;
    }
}
