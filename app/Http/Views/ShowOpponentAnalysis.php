<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameStanding;
use App\Modules\Competition\Services\CalendarService;
use App\Modules\Lineup\Services\LineupService;
use App\Modules\Lineup\Services\OpponentAnalysisBuilder;
use App\Support\TeamColors;

class ShowOpponentAnalysis
{
    public function __construct(
        private readonly LineupService $lineupService,
        private readonly CalendarService $calendarService,
        private readonly OpponentAnalysisBuilder $analysisBuilder,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $match = $game->next_match;

        abort_unless($match, 404);

        $match->load(['homeTeam', 'awayTeam', 'competition']);

        $isHome = $match->home_team_id === $game->team_id;
        $opponent = $isHome ? $match->awayTeam : $match->homeTeam;

        $matchDate = $match->scheduled_date;
        $competitionId = $match->competition_id;
        $requireEnrollment = $game->requiresSquadEnrollment();

        $userBestXI = $this->lineupService->getBestXIWithAverage(
            $gameId,
            $game->team_id,
            $matchDate,
            $competitionId,
            requireEnrollment: $requireEnrollment,
        );

        $opponentData = $this->lineupService->predictOpponentTactics(
            $gameId,
            $opponent->id,
            $matchDate,
            $competitionId,
            $match->hasHomeAdvantage($opponent->id),
            $userBestXI['average'],
        );

        $derived = $this->analysisBuilder->build($opponentData);

        $opponentColors = TeamColors::toHex(
            $opponent->colors ?? TeamColors::get($opponent->getRawOriginal('name'))
        );

        return view('opponent-analysis', [
            'game' => $game,
            'match' => $match,
            'isHome' => $isHome,
            'opponent' => $opponent,
            'opponentColors' => $opponentColors,
            'opponentData' => $opponentData,
            'userTeamAverage' => $userBestXI['average'],
            'playerForm' => $this->calendarService->getTeamForm($gameId, $game->team_id),
            'pitchSlots' => $derived['pitchSlots'],
            'topThreats' => $derived['topThreats'],
            'opponentStanding' => GameStanding::forTeamInCompetition($game, $opponent->id, $competitionId),
            'userStanding' => GameStanding::forTeamInCompetition($game, $game->team_id, $competitionId),
            'tacticsSummaries' => $derived['tacticsSummaries'],
            'userRadar' => $this->analysisBuilder->radarFor($userBestXI['players']),
            'opponentRadar' => $this->analysisBuilder->radarFor($opponentData['bestXIPlayers']),
            'coachTips' => $this->analysisBuilder->coachTips(
                $opponentData,
                $userBestXI['players'],
                $userBestXI['average'],
                $match->hasHomeAdvantage($game->team_id),
            ),
        ]);
    }
}
