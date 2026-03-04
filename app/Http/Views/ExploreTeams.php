<?php

namespace App\Http\Views;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;

class ExploreTeams
{
    public function __invoke(Request $request, string $gameId, string $competitionId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('team_id', '!=', $game->team_id)
            ->pluck('team_id');

        $teams = Team::whereIn('id', $teamIds)
            ->with('clubProfile')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'image' => $team->image,
                'stadiumName' => $team->stadium_name,
                'stadiumSeats' => $team->stadium_seats,
                'reputationLevel' => $team->clubProfile?->reputation_level,
            ]);

        return response()->json($teams);
    }
}
