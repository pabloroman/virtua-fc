<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Modules\Competition\Services\CompetitionViewService;

class ShowCompetition
{
    public function __construct(
        private readonly CompetitionViewService $competitionViewService,
    ) {}

    public function __invoke(string $gameId, string $competitionId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $competition = Competition::findOrFail($competitionId);

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
        $standings = $this->competitionViewService->getStandings($game, $competition);
        $hasGroups = $standings->whereNotNull('group_label')->isNotEmpty();

        $knockoutRounds = collect();
        $knockoutTies = collect();
        $leaguePhaseComplete = false;

        if ($competition->handler_type === 'league_with_playoff') {
            $knockoutRounds = $this->competitionViewService->getKnockoutRounds($competition, $game->season);
            $knockoutTies = $this->competitionViewService->getKnockoutTies($game, $competition);
            $leaguePhaseComplete = $this->competitionViewService->isLeaguePhaseComplete($game, $competition, $standings);
        }

        return view('standings', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'groupedStandings' => $hasGroups ? $standings->groupBy('group_label') : null,
            'topScorers' => $this->competitionViewService->getTopScorers($game->id, $competition->id),
            'teamForms' => $this->competitionViewService->getTeamForms($standings),
            'standingsZones' => $competition->getConfig()->getStandingsZones(),
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'leaguePhaseComplete' => $leaguePhaseComplete,
        ]);
    }

    private function showSwissFormat(Game $game, Competition $competition)
    {
        $standings = $this->competitionViewService->getStandings($game, $competition);
        $knockoutRounds = $this->competitionViewService->getKnockoutRounds($competition, $game->season);
        $knockoutTies = $this->competitionViewService->getKnockoutTies($game, $competition);

        return view('swiss-standings', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'topScorers' => $this->competitionViewService->getTopScorers($game->id, $competition->id),
            'teamForms' => $this->competitionViewService->getTeamForms($standings),
            'standingsZones' => $competition->getConfig()->getStandingsZones(),
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'leaguePhaseComplete' => $this->competitionViewService->isLeaguePhaseComplete($game, $competition, $standings),
        ]);
    }

    private function showCup(Game $game, Competition $competition)
    {
        $rounds = $this->competitionViewService->getKnockoutRounds($competition, $game->season);
        $tiesByRound = $this->competitionViewService->getKnockoutTies($game, $competition);
        $playerTie = $this->competitionViewService->findPlayerTie($rounds, $tiesByRound, $game->team_id);
        $maxRound = $rounds->max('round');

        return view('cup', [
            'game' => $game,
            'competition' => $competition,
            'rounds' => $rounds,
            'tiesByRound' => $tiesByRound,
            'playerTie' => $playerTie,
            'cupStatus' => $this->competitionViewService->resolveCupStatus($playerTie, $game->team_id, $maxRound),
            'playerRoundName' => $playerTie?->getRoundConfig()?->name,
        ]);
    }

    private function showGroupStageCup(Game $game, Competition $competition)
    {
        $standings = $this->competitionViewService->getStandings($game, $competition);
        $groupStageComplete = $this->competitionViewService->isLeaguePhaseComplete($game, $competition, $standings);
        $knockoutRounds = $this->competitionViewService->getKnockoutRounds($competition, $game->season);
        $knockoutTies = $this->competitionViewService->getKnockoutTies($game, $competition);
        $playerTie = $this->competitionViewService->findPlayerTie($knockoutRounds, $knockoutTies, $game->team_id);

        $knockoutStatus = 'group_stage';
        if ($playerTie) {
            $maxRound = $knockoutRounds->max('round');
            $knockoutStatus = $this->competitionViewService->resolveCupStatus($playerTie, $game->team_id, $maxRound);
        } elseif ($groupStageComplete) {
            $playerStanding = $standings->firstWhere('team_id', $game->team_id);
            $knockoutStatus = ($playerStanding && $playerStanding->position <= 2) ? 'qualified' : 'eliminated';
        }

        $groupedStandings = $standings->whereNotNull('group_label')->isNotEmpty()
            ? $standings->groupBy('group_label')
            : null;

        return view('group-stage-cup', [
            'game' => $game,
            'competition' => $competition,
            'groupedStandings' => $groupedStandings,
            'teamForms' => $this->competitionViewService->getTeamForms($standings),
            'topScorers' => $this->competitionViewService->getTopScorers($game->id, $competition->id),
            'groupStageComplete' => $groupStageComplete,
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'playerTie' => $playerTie,
            'knockoutStatus' => $knockoutStatus,
        ]);
    }
}
