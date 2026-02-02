<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameMatch;
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

        // Get form (last 5 results) for each team
        $teamForms = $this->getTeamForms($gameId, $game->competition_id, $standings->pluck('team_id'));

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
            'teamForms' => $teamForms,
        ]);
    }

    /**
     * Get the last 5 match results for each team.
     *
     * @return array<string, array<string>> Team ID => array of results ('W', 'D', 'L')
     */
    private function getTeamForms(string $gameId, string $competitionId, $teamIds): array
    {
        $forms = [];

        foreach ($teamIds as $teamId) {
            $matches = GameMatch::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->where('played', true)
                ->where(function ($query) use ($teamId) {
                    $query->where('home_team_id', $teamId)
                        ->orWhere('away_team_id', $teamId);
                })
                ->orderByDesc('scheduled_date')
                ->limit(5)
                ->get();

            $form = [];
            foreach ($matches as $match) {
                $isHome = $match->home_team_id === $teamId;
                $teamScore = $isHome ? $match->home_score : $match->away_score;
                $oppScore = $isHome ? $match->away_score : $match->home_score;

                if ($teamScore > $oppScore) {
                    $form[] = 'W';
                } elseif ($teamScore < $oppScore) {
                    $form[] = 'L';
                } else {
                    $form[] = 'D';
                }
            }

            // Reverse so oldest is first (left to right = oldest to newest)
            $forms[$teamId] = array_reverse($form);
        }

        return $forms;
    }
}
