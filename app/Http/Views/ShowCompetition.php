<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Modules\Competition\Services\CompetitionViewService;
use App\Modules\Competition\Services\WorldCupKnockoutGenerator;
use App\Modules\Match\Services\SyntheticLeagueResolver;

class ShowCompetition
{
    public function __construct(
        private readonly CompetitionViewService $competitionViewService,
        private readonly SyntheticLeagueResolver $syntheticLeagueResolver,
        private readonly WorldCupKnockoutGenerator $worldCupKnockoutGenerator,
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

        [$userLeagues, $otherLeagues] = $this->leagueMenuOptions($game);

        if ($competition->handler_type === 'swiss_format') {
            return $this->showSwissFormat($game, $competition, $userLeagues, $otherLeagues);
        }

        if ($competition->handler_type === 'group_stage_cup') {
            return $this->showGroupStageCup($game, $competition, $userLeagues, $otherLeagues);
        }

        if ($competition->isLeague()) {
            return $this->showLeague($game, $competition, $userLeagues, $otherLeagues);
        }

        return $this->showCup($game, $competition, $userLeagues, $otherLeagues);
    }

    /**
     * Flat-league competitions surfaced in the dropdown next to the page title:
     * the user's own league(s) on top so they can always navigate home, then
     * other leagues in the game (lazily simulated on first view).
     *
     * Within each group: Spanish leagues come first (player's home country in
     * v1), then the rest alphabetically by country and ascending by tier.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function leagueMenuOptions(Game $game): array
    {
        if (!$game->isCareerMode()) {
            return [collect(), collect()];
        }

        $userCompetitionIds = CompetitionEntry::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->pluck('competition_id');

        $allLeagueIdsInGame = CompetitionEntry::where('game_id', $game->id)
            ->pluck('competition_id')
            ->unique();

        $leagues = Competition::whereIn('id', $allLeagueIdsInGame)
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->orderByRaw("CASE WHEN country = 'ES' THEN 0 ELSE 1 END")
            ->orderBy('country')
            ->orderBy('tier')
            ->orderBy('id')
            ->get();

        return [
            $leagues->whereIn('id', $userCompetitionIds)->values(),
            $leagues->whereNotIn('id', $userCompetitionIds)->values(),
        ];
    }

    private function showLeague(Game $game, Competition $competition, \Illuminate\Support\Collection $userLeagues, \Illuminate\Support\Collection $otherLeagues)
    {
        $standings = $this->competitionViewService->getStandings($game, $competition);
        $hasGroups = $standings->whereNotNull('group_label')->isNotEmpty();

        $knockoutRounds = collect();
        $knockoutTies = collect();
        $leaguePhaseComplete = false;

        if ($competition->handler_type === 'league_with_playoff') {
            $knockoutRounds = $this->competitionViewService->getKnockoutRounds($competition, $game);
            $knockoutTies = $this->competitionViewService->getKnockoutTies($game, $competition);
            $leaguePhaseComplete = $this->competitionViewService->isLeaguePhaseComplete($game, $competition, $standings);
        }

        return view('standings', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'groupedStandings' => $hasGroups ? $standings->groupBy('group_label') : null,
            'topScorers' => $this->competitionViewService->getTopScorers($game->id, $competition->id),
            'bestGoalkeepers' => $this->competitionViewService->getBestGoalkeepers($game->id, $competition->id),
            'teamForms' => $this->competitionViewService->getTeamForms($standings),
            'standingsZones' => $competition->getConfig()->getStandingsZones(),
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'leaguePhaseComplete' => $leaguePhaseComplete,
            'userLeagues' => $userLeagues,
            'otherLeagues' => $otherLeagues,
        ]);
    }

    private function showSwissFormat(Game $game, Competition $competition, \Illuminate\Support\Collection $userLeagues, \Illuminate\Support\Collection $otherLeagues)
    {
        $standings = $this->competitionViewService->getStandings($game, $competition);
        $knockoutRounds = $this->competitionViewService->getKnockoutRounds($competition, $game);
        $knockoutTies = $this->competitionViewService->getKnockoutTies($game, $competition);

        return view('swiss-standings', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'topScorers' => $this->competitionViewService->getTopScorers($game->id, $competition->id),
            'bestGoalkeepers' => $this->competitionViewService->getBestGoalkeepers($game->id, $competition->id),
            'teamForms' => $this->competitionViewService->getTeamForms($standings),
            'standingsZones' => $competition->getConfig()->getStandingsZones(),
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'leaguePhaseComplete' => $this->competitionViewService->isLeaguePhaseComplete($game, $competition, $standings),
            'userLeagues' => $userLeagues,
            'otherLeagues' => $otherLeagues,
        ]);
    }

    private function showCup(Game $game, Competition $competition, \Illuminate\Support\Collection $userLeagues, \Illuminate\Support\Collection $otherLeagues)
    {
        $rounds = $this->competitionViewService->getKnockoutRounds($competition, $game);
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
            'userLeagues' => $userLeagues,
            'otherLeagues' => $otherLeagues,
        ]);
    }

    private function showGroupStageCup(Game $game, Competition $competition, \Illuminate\Support\Collection $userLeagues, \Illuminate\Support\Collection $otherLeagues)
    {
        $standings = $this->competitionViewService->getStandings($game, $competition);
        $groupStageComplete = $this->competitionViewService->isLeaguePhaseComplete($game, $competition, $standings);
        $knockoutRounds = $this->competitionViewService->getKnockoutRounds($competition, $game);
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
            'bestGoalkeepers' => $this->competitionViewService->getBestGoalkeepers($game->id, $competition->id),
            'groupStageComplete' => $groupStageComplete,
            'knockoutRounds' => $knockoutRounds,
            'knockoutTies' => $knockoutTies,
            'knockoutSlotsByRound' => $this->worldCupKnockoutGenerator->getSlotsPerRound(),
            'knockoutDisplayOrderByRound' => $this->worldCupKnockoutGenerator->getDisplayOrderPerRound(),
            'playerTie' => $playerTie,
            'knockoutStatus' => $knockoutStatus,
            'userLeagues' => $userLeagues,
            'otherLeagues' => $otherLeagues,
        ]);
    }
}
