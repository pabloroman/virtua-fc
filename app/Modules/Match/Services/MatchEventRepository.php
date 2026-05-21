<?php

namespace App\Modules\Match\Services;

use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Support\MinuteCoordinates;
use App\Modules\Match\Support\StoppageDurations;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MatchEventRepository
{
    /**
     * Map MatchEventData objects to database rows and bulk insert.
     *
     * The simulator hands us events with a raw absolute `minute`. At persist
     * time we decompose into (phase, base minute in phase, stoppage minute)
     * using the match's sampled stoppage durations — that decomposition is
     * the canonical form stored in `match_events`.
     *
     * @param  Collection<MatchEventData>  $events
     * @return array<string>  Inserted row IDs
     */
    public function bulkInsert(Collection $events, string $gameId, string $matchId, int $chunkSize = 50): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        $stoppage = StoppageDurations::fromMatch(GameMatch::query()->findOrFail($matchId));

        $rows = $events->map(function (MatchEventData $e) use ($gameId, $matchId, $stoppage) {
            $coords = MinuteCoordinates::decomposeWith($e->minute, $stoppage);

            return [
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'game_match_id' => $matchId,
                'game_player_id' => $e->gamePlayerId,
                'team_id' => $e->teamId,
                'minute' => $coords['minute'],
                'phase' => $coords['phase']->value,
                'stoppage_minute' => $coords['stoppage_minute'],
                'event_type' => $e->type,
                'metadata' => $e->metadata ? json_encode($e->metadata) : null,
            ];
        })->all();

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            MatchEvent::insert($chunk);
        }

        return array_column($rows, 'id');
    }
}
