<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulatedSeason extends Model
{
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
