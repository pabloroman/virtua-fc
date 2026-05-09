<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Modules\Competition\Services\CompetitionViewService;
use App\Modules\Match\Services\SyntheticLeagueResolver;

class ShowCompetition
{
    public function __construct(
        private readonly CompetitionViewService $competitionViewService,
        private readonly SyntheticLeagueResolver $syntheticLeagueResolver,
    ) {}

    public function __invoke(string $gameId, string $competitionId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $competition = Competition::findOrFail($competitionId);

        $participates = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('team_id', $game->team_id)
            ->exists();

        $isFlatLeague = in_array($competition->handler_type, ['league', 'league_with_playoff'], true);

        // Flat-league competitions the player isn't entered in (e.g. browsing
        // foreign leagues) are simulated lazily on first view. Other handler
        // types (cups, swiss, group-stage) still require participation.
        if (!$participates && !$isFlatLeague) {
            abort(404, 'Your team does not participate in this competition.');
        }

        if ($isFlatLeague && !$participates) {
            $this->syntheticLeagueResolver->catchUp($game, $competition);
        }

        $otherLeagues = $this->otherLeagues($game, $competition);

        if ($competition->handler_type === 'swiss_format') {
            return $this->showSwissFormat($game, $competition, $otherLeagues);
        }

        if ($competition->handler_type === 'group_stage_cup') {
            return $this->showGroupStageCup($game, $competition, $otherLeagues);
        }

        if ($competition->isLeague()) {
            return $this->showLeague($game, $competition, $otherLeagues);
        }

        return $this->showCup($game, $competition, $otherLeagues);
    }

    /**
     * Flat-league competitions in this game the user is NOT entered in.
     * Surfaced as a small dropdown next to the page title for quick navigation
     * between leagues; standings/results are simulated lazily on first view.
     *
     * Spanish leagues come first (the player's home country in v1), then the
     * remaining countries alphabetically; within each country, by tier.
     */
    private function otherLeagues(Game $game, Competition $current): \Illuminate\Support\Collection
    {
        if (!$game->isCareerMode()) {
            return collect();
        }

        $userCompetitionIds = CompetitionEntry::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->pluck('competition_id');

        $allLeagueIdsInGame = CompetitionEntry::where('game_id', $game->id)
            ->pluck('competition_id')
            ->unique();

        return Competition::whereIn('id', $allLeagueIdsInGame)
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->whereNotIn('id', $userCompetitionIds)
            ->orderByRaw("CASE WHEN country = 'ES' THEN 0 ELSE 1 END")
            ->orderBy('country')
            ->orderBy('tier')
            ->orderBy('id')
            ->get();
    }

    private function showLeague(Game $game, Competition $competition, \Illuminate\Support\Collection $otherLeagues)
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
            'otherLeagues' => $otherLeagues,
        ]);
    }

    private function showSwissFormat(Game $game, Competition $competition, \Illuminate\Support\Collection $otherLeagues)
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
            'otherLeagues' => $otherLeagues,
        ]);
    }

    private function showCup(Game $game, Competition $competition, \Illuminate\Support\Collection $otherLeagues)
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
            'otherLeagues' => $otherLeagues,
        ]);
    }

    private function showGroupStageCup(Game $game, Competition $competition, \Illuminate\Support\Collection $otherLeagues)
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
            'otherLeagues' => $otherLeagues,
        ]);
    }
}
