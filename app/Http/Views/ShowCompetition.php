<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\CupRoundTemplate;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;

class ShowCompetition
{
    public function __invoke(string $gameId, string $competitionId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $competition = Competition::findOrFail($competitionId);

        // Verify the team participates in this competition
        $participates = CompetitionTeam::where('competition_id', $competitionId)
            ->where('team_id', $game->team_id)
            ->where('season', $game->season)
            ->exists();

        if (!$participates) {
            abort(404, 'Your team does not participate in this competition.');
        }

        if ($competition->isLeague()) {
            return $this->showLeague($game, $competition);
        }

        return $this->showCup($game, $competition);
    }

    private function showLeague(Game $game, Competition $competition)
    {
        $standings = GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('position')
            ->get();

        // Get form (last 5 results) for each team
        $teamForms = $this->getTeamForms($game->id, $competition->id, $standings->pluck('team_id'));

        // Get team IDs in this competition
        $competitionTeamIds = CompetitionTeam::where('competition_id', $competition->id)
            ->where('season', $game->season)
            ->pluck('team_id');

        // Top scorers in this competition
        $topScorers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
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

    private function showCup(Game $game, Competition $competition)
    {
        // Get all round templates
        $rounds = CupRoundTemplate::where('competition_id', $competition->id)
            ->where('season', $game->season)
            ->orderBy('round_number')
            ->get();

        // Get all ties for this game, grouped by round
        $tiesByRound = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch', 'secondLegMatch'])
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->get()
            ->groupBy('round_number');

        // Find player's tie in current/latest round
        $playerTie = null;
        foreach ($rounds->reverse() as $round) {
            $ties = $tiesByRound->get($round->round_number, collect());
            $playerTie = $ties->first(fn ($tie) => $tie->involvesTeam($game->team_id));
            if ($playerTie) {
                break;
            }
        }

        return view('cup', [
            'game' => $game,
            'competition' => $competition,
            'rounds' => $rounds,
            'tiesByRound' => $tiesByRound,
            'playerTie' => $playerTie,
        ]);
    }

    /**
     * Get the last 5 match results for each team.
     *
     * @return array<string, array<string>> Team ID => array of results ('W', 'D', 'L')
     */
    private function getTeamForms(string $gameId, string $competitionId, $teamIds): array
    {
        // Fetch all played matches in a single query instead of one per team
        $allMatches = GameMatch::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('played', true)
            ->orderByDesc('scheduled_date')
            ->get();

        $forms = [];

        foreach ($teamIds as $teamId) {
            $teamMatches = $allMatches->filter(function ($match) use ($teamId) {
                return $match->home_team_id === $teamId || $match->away_team_id === $teamId;
            })->take(5);

            $form = [];
            foreach ($teamMatches as $match) {
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
