<?php

namespace App\Modules\Match\Services;

use App\Models\MatchEvent;
use App\Modules\Match\DTOs\MatchEventData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MatchEventRepository
{
    /**
     * Map MatchEventData objects to database rows and bulk insert.
     *
     * @param  Collection<MatchEventData>  $events
     * @return array<string>  Inserted row IDs
     */
    public function bulkInsert(Collection $events, string $gameId, string $matchId, int $chunkSize = 50): array
    {
        $now = now();

        $rows = $events->map(fn (MatchEventData $e) => [
            'id' => Str::uuid()->toString(),
            'game_id' => $gameId,
            'game_match_id' => $matchId,
            'game_player_id' => $e->gamePlayerId,
            'team_id' => $e->teamId,
            'minute' => $e->minute,
            'event_type' => $e->type,
            'metadata' => $e->metadata ? json_encode($e->metadata) : null,
            'created_at' => $now,
        ])->all();

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            MatchEvent::insert($chunk);
        }

        return array_column($rows, 'id');
    }
}
