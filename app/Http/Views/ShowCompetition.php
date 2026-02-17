<?php

namespace App\Http\Views;

use App\Game\Services\LeagueFixtureGenerator;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use Illuminate\Support\Collection;

class ShowCompetition
{
    public function __invoke(string $gameId, string $competitionId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $competition = Competition::findOrFail($competitionId);

        // Verify the team participates in this competition
        $participates = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('team_id', $game->team_id)
            ->exists();

        if (!$participates) {
            abort(404, 'Your team does not participate in this competition.');
        }

        if ($competition->handler_type === 'swiss_format') {
            return $this->showSwissFormat($game, $competition);
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
            ->orderBy('group_label')
            ->orderBy('position')
            ->get();

        // Get form (last 5 results) for each team
        $teamForms = $this->getTeamForms($game->id, $competition->id, $standings->pluck('team_id'));

        // Top scorers in this competition (from match events)
        $topScorers = $this->getCompetitionTopScorers($game->id, $competition->id);

        // Get standings zones from competition config
        $standingsZones = $competition->getConfig()->getStandingsZones();

        // Check if standings are grouped (e.g. World Cup group stage)
        $hasGroups = $standings->whereNotNull('group_label')->isNotEmpty();
        $groupedStandings = $hasGroups ? $standings->groupBy('group_label') : null;

        return view('standings', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'groupedStandings' => $groupedStandings,
            'topScorers' => $topScorers,
            'teamForms' => $teamForms,
            'standingsZones' => $standingsZones,
        ]);
    }

    private function showSwissFormat(Game $game, Competition $competition)
    {
        // League phase standings
        $standings = GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('position')
            ->get();

        $teamForms = $this->getTeamForms($game->id, $competition->id, $standings->pluck('team_id'));

        $topScorers = $this->getCompetitionTopScorers($game->id, $competition->id);

        $standingsZones = $competition->getConfig()->getStandingsZones();

        // Knockout bracket data from schedule.json (year-adjusted for current game season)
        $knockoutRounds = collect(LeagueFixtureGenerator::loadKnockoutRounds($competition->id, $competition->season, $game->season));

        $knockoutTies = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch', 'secondLegMatch', 'competition'])
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->get()
            ->groupBy('round_number');

        // Check if league phase is complete
        $leaguePhaseComplete = !GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists() && $standings->first()?->played > 0;

        return view('swiss-standings', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'topScorers' => $topScorers,
            'teamForms' => $teamForms,
            'standingsZones' => $standingsZones,
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'leaguePhaseComplete' => $leaguePhaseComplete,
        ]);
    }

    private function showCup(Game $game, Competition $competition)
    {
        // Get all round configs from schedule.json (year-adjusted for current game season)
        $rounds = collect(LeagueFixtureGenerator::loadKnockoutRounds($competition->id, $competition->season, $game->season));

        // Get all ties for this game, grouped by round
        $tiesByRound = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch', 'secondLegMatch', 'competition'])
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->get()
            ->groupBy('round_number');

        // Find player's tie in current/latest round
        $playerTie = null;
        foreach ($rounds->reverse() as $round) {
            $ties = $tiesByRound->get($round->round, collect());
            $playerTie = $ties->first(fn ($tie) => $tie->involvesTeam($game->team_id));
            if ($playerTie) {
                break;
            }
        }

        // Determine player's cup status from CupTie data
        $maxRound = $rounds->max('round');
        $cupStatus = 'not_entered';

        if ($playerTie) {
            if (!$playerTie->completed) {
                $cupStatus = 'active';
            } elseif ($playerTie->winner_id === $game->team_id) {
                $cupStatus = ($playerTie->round_number === $maxRound) ? 'champion' : 'advanced';
            } else {
                $cupStatus = 'eliminated';
            }
        }

        $playerRoundName = $playerTie?->getRoundConfig()?->name;

        return view('cup', [
            'game' => $game,
            'competition' => $competition,
            'rounds' => $rounds,
            'tiesByRound' => $tiesByRound,
            'playerTie' => $playerTie,
            'cupStatus' => $cupStatus,
            'playerRoundName' => $playerRoundName,
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

    /**
     * Get top scorers for a specific competition using match events.
     */
    private function getCompetitionTopScorers(string $gameId, string $competitionId): Collection
    {
        $goalCounts = MatchEvent::where('match_events.game_id', $gameId)
            ->where('match_events.event_type', MatchEvent::TYPE_GOAL)
            ->join('game_matches', 'game_matches.id', '=', 'match_events.game_match_id')
            ->where('game_matches.competition_id', $competitionId)
            ->selectRaw('match_events.game_player_id, COUNT(*) as goals')
            ->groupBy('match_events.game_player_id')
            ->orderByDesc('goals')
            ->limit(10)
            ->pluck('goals', 'game_player_id');

        if ($goalCounts->isEmpty()) {
            return collect();
        }

        $players = GamePlayer::with(['player', 'team'])
            ->whereIn('id', $goalCounts->keys())
            ->get()
            ->each(fn ($p) => $p->goals = $goalCounts[$p->id])
            ->sortByDesc('goals')
            ->values();

        return $players;
    }
}
