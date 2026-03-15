<?php

namespace App\Http\Views;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGameStats
{
    public function __invoke(Request $request)
    {
        $teamPopularity = Game::select('team_id', DB::raw('COUNT(*) as picks'))
            ->groupBy('team_id')
            ->orderByDesc('picks')
            ->with('team:id,name,image')
            ->limit(15)
            ->get();

        $formations = DB::table('game_matches')
            ->join('games', 'game_matches.game_id', '=', 'games.id')
            ->where('game_matches.played', true)
            ->selectRaw("
                CASE
                    WHEN games.team_id = game_matches.home_team_id THEN game_matches.home_formation
                    WHEN games.team_id = game_matches.away_team_id THEN game_matches.away_formation
                END as formation,
                COUNT(*) as usage_count
            ")
            ->groupBy('formation')
            ->having('formation', '!=', null)
            ->orderByDesc('usage_count')
            ->get();

        $mentalities = DB::table('game_matches')
            ->join('games', 'game_matches.game_id', '=', 'games.id')
            ->where('game_matches.played', true)
            ->selectRaw("
                CASE
                    WHEN games.team_id = game_matches.home_team_id THEN game_matches.home_mentality
                    WHEN games.team_id = game_matches.away_team_id THEN game_matches.away_mentality
                END as mentality,
                COUNT(*) as usage_count
            ")
            ->groupBy('mentality')
            ->having('mentality', '!=', null)
            ->orderByDesc('usage_count')
            ->get();

        $seasonProgress = Game::selectRaw('season, COUNT(*) as count')
            ->whereNotNull('setup_completed_at')
            ->groupBy('season')
            ->orderBy('season')
            ->get();

        return view('admin.game-stats', [
            'teamPopularity' => $teamPopularity,
            'formations' => $formations,
            'mentalities' => $mentalities,
            'seasonProgress' => $seasonProgress,
        ]);
    }
}
