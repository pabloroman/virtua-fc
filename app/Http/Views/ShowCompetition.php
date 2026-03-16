<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\LeagueFixtureGenerator;
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

        if ($competition->handler_type === 'group_stage_cup') {
            return $this->showGroupStageCup($game, $competition);
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

        $teamForms = $standings->mapWithKeys(fn ($s) => [
            $s->team_id => $s->form ? str_split($s->form) : []
        ])->all();

        // Top scorers in this competition (from match events)
        $topScorers = $this->getCompetitionTopScorers($game->id, $competition->id);

        // Get standings zones from competition config
        $standingsZones = $competition->getConfig()->getStandingsZones();

        // Check if standings are grouped (e.g. World Cup group stage)
        $hasGroups = $standings->whereNotNull('group_label')->isNotEmpty();
        $groupedStandings = $hasGroups ? $standings->groupBy('group_label') : null;

        // Playoff bracket data for league_with_playoff competitions (e.g. Segunda División)
        $knockoutRounds = collect();
        $knockoutTies = collect();
        $leaguePhaseComplete = false;

        if ($competition->handler_type === 'league_with_playoff') {
            $knockoutRounds = collect(LeagueFixtureGenerator::loadKnockoutRounds($competition->id, $competition->season, $game->season));

            $knockoutTies = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch', 'secondLegMatch', 'competition'])
                ->where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->get()
                ->groupBy('round_number');

            $leaguePhaseComplete = !GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->whereNull('cup_tie_id')
                ->where('played', false)
                ->exists() && $standings->first()?->played > 0;
        }

        return view('standings', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'groupedStandings' => $groupedStandings,
            'topScorers' => $topScorers,
            'teamForms' => $teamForms,
            'standingsZones' => $standingsZones,
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'leaguePhaseComplete' => $leaguePhaseComplete,
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

        $teamForms = $standings->mapWithKeys(fn ($s) => [
            $s->team_id => $s->form ? str_split($s->form) : []
        ])->all();

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

    private function showGroupStageCup(Game $game, Competition $competition)
    {
        // Group standings
        $standings = GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('group_label')
            ->orderBy('position')
            ->get();

        $teamForms = $standings->mapWithKeys(fn ($s) => [
            $s->team_id => $s->form ? str_split($s->form) : []
        ])->all();
        $topScorers = $this->getCompetitionTopScorers($game->id, $competition->id);

        $groupedStandings = $standings->whereNotNull('group_label')->isNotEmpty()
            ? $standings->groupBy('group_label')
            : null;

        // Check if group stage is complete
        $groupStageComplete = !GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists() && $standings->first()?->played > 0;

        // Knockout bracket
        $knockoutRounds = collect(LeagueFixtureGenerator::loadKnockoutRounds($competition->id, $competition->season, $game->season));

        $knockoutTies = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch', 'competition'])
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->get()
            ->groupBy('round_number');

        // Find player's knockout tie (latest round)
        $playerTie = null;
        foreach ($knockoutRounds->reverse() as $round) {
            $ties = $knockoutTies->get($round->round, collect());
            $playerTie = $ties->first(fn ($tie) => $tie->involvesTeam($game->team_id));
            if ($playerTie) {
                break;
            }
        }

        // Player's knockout status
        $maxRound = $knockoutRounds->max('round');
        $knockoutStatus = 'group_stage';

        if ($playerTie) {
            if (!$playerTie->completed) {
                $knockoutStatus = 'active';
            } elseif ($playerTie->winner_id === $game->team_id) {
                $knockoutStatus = ($playerTie->round_number === $maxRound) ? 'champion' : 'advanced';
            } else {
                $knockoutStatus = 'eliminated';
            }
        } elseif ($groupStageComplete) {
            // Group stage done but no knockout tie = eliminated in groups
            $playerStanding = $standings->firstWhere('team_id', $game->team_id);
            $knockoutStatus = ($playerStanding && $playerStanding->position <= 2) ? 'qualified' : 'eliminated';
        }

        return view('group-stage-cup', [
            'game' => $game,
            'competition' => $competition,
            'groupedStandings' => $groupedStandings,
            'teamForms' => $teamForms,
            'topScorers' => $topScorers,
            'groupStageComplete' => $groupStageComplete,
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'playerTie' => $playerTie,
            'knockoutStatus' => $knockoutStatus,
        ]);
    }

    /**
     * Get top scorers for a specific competition using match events.
     */
    private function getCompetitionTopScorers(string $gameId, string $competitionId): Collection
    {
        // Get goal counts and the team_id from match events (the team when the goal was scored)
        $scorerRows = MatchEvent::where('match_events.game_id', $gameId)
            ->where('match_events.event_type', MatchEvent::TYPE_GOAL)
            ->join('game_matches', 'game_matches.id', '=', 'match_events.game_match_id')
            ->where('game_matches.competition_id', $competitionId)
            ->selectRaw('match_events.game_player_id, match_events.team_id, COUNT(*) as goals')
            ->groupBy('match_events.game_player_id', 'match_events.team_id')
            ->orderByDesc('goals')
            ->limit(10)
            ->get();

        if ($scorerRows->isEmpty()) {
            return collect();
        }

        $playerIds = $scorerRows->pluck('game_player_id')->unique();
        $teamIds = $scorerRows->pluck('team_id')->unique();

        $players = GamePlayer::with('player')
            ->whereIn('id', $playerIds)
            ->get()
            ->keyBy('id');

        $teams = \App\Models\Team::whereIn('id', $teamIds)->get()->keyBy('id');

        return $scorerRows->map(function ($row) use ($players, $teams) {
            $player = $players[$row->game_player_id] ?? null;
            if (!$player) {
                return null;
            }
            $player = clone $player;
            $player->goals = $row->goals;
            $player->scorer_team = $teams[$row->team_id] ?? null;

            return $player;
        })->filter()->sortByDesc('goals')->values();
    }
}
