<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameStanding;

class ShowStandings
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $competition = Competition::find($game->competition_id);

        $standings = GameStanding::with('team')
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->orderBy('position')
            ->get();

        // Get team IDs in this competition
        $competitionTeamIds = CompetitionTeam::where('competition_id', $game->competition_id)
            ->where('season', $game->season)
            ->pluck('team_id');

        // Top scorers in this competition
        $topScorers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->whereIn('team_id', $competitionTeamIds)
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->limit(10)
            ->get();

        return view('standings', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'topScorers' => $topScorers,
        ]);
    }
}
