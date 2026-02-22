<?php

namespace App\Http\Actions;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProcessExtraTime
{
    public function __construct(
        private readonly ExtraTimeAndPenaltyService $service,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('game_id', $gameId)
            ->findOrFail($matchId);

        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => 'Match not in progress'], 403);
        }

        if (! $match->cup_tie_id) {
            return response()->json(['needed' => false]);
        }

        $cupTie = CupTie::with(['firstLegMatch', 'secondLegMatch'])->find($match->cup_tie_id);

        if (! $cupTie || ! $cupTie->needsExtraTime($match)) {
            return response()->json(['needed' => false]);
        }

        $result = $this->service->processExtraTime($match, $game);

        return response()->json([
            'needed' => true,
            'extraTimeEvents' => $this->buildFrontendEvents($result->storedEvents),
            'homeScoreET' => $result->homeScoreET,
            'awayScoreET' => $result->awayScoreET,
            'needsPenalties' => $result->needsPenalties,
        ]);
    }

    private function buildFrontendEvents(Collection $events): array
    {
        $formattedEvents = $events
            ->filter(fn ($e) => $e->event_type !== 'assist')
            ->map(fn ($e) => [
                'minute' => $e->minute,
                'type' => $e->event_type,
                'playerName' => $e->gamePlayer->player->name ?? '',
                'teamId' => $e->team_id,
                'gamePlayerId' => $e->game_player_id,
                'metadata' => $e->metadata,
            ])
            ->values()
            ->all();

        $assists = $events
            ->filter(fn ($e) => $e->event_type === 'assist')
            ->keyBy('minute');

        return array_map(function ($event) use ($assists) {
            if (in_array($event['type'], ['goal', 'own_goal']) && isset($assists[$event['minute']])) {
                $event['assistPlayerName'] = $assists[$event['minute']]->gamePlayer->player->name ?? null;
            }

            return $event;
        }, $formattedEvents);
    }
}
