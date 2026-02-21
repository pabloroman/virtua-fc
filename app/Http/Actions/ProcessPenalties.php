<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessPenalties
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::where('game_id', $gameId)->findOrFail($matchId);

        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => 'Match not in progress'], 403);
        }

        if (! $match->is_extra_time) {
            return response()->json(['error' => 'Extra time not played'], 400);
        }

        if ($match->home_score_penalties !== null) {
            return response()->json(['error' => 'Penalties already resolved'], 400);
        }

        $request->validate([
            'kickerOrder' => 'required|array|min:5',
            'kickerOrder.*' => 'string',
        ]);

        // Load players
        $allLineupIds = array_merge($match->home_lineup ?? [], $match->away_lineup ?? []);
        $players = GamePlayer::with('player')->whereIn('id', $allLineupIds)->get();
        $homePlayers = $players->filter(fn ($p) => $p->team_id === $match->home_team_id);
        $awayPlayers = $players->filter(fn ($p) => $p->team_id === $match->away_team_id);

        // Determine which side the user controls
        $isUserHome = $match->home_team_id === $game->team_id;
        $userOrder = $request->input('kickerOrder');

        $result = $this->matchSimulator->simulatePenaltyShootout(
            $homePlayers,
            $awayPlayers,
            $isUserHome ? $userOrder : null,
            $isUserHome ? null : $userOrder,
        );

        $match->update([
            'home_score_penalties' => $result['homeScore'],
            'away_score_penalties' => $result['awayScore'],
        ]);

        return response()->json([
            'homeScore' => $result['homeScore'],
            'awayScore' => $result['awayScore'],
            'kicks' => $result['kicks'],
        ]);
    }
}
