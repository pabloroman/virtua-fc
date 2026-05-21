<?php

namespace App\Http\Views;

use App\Models\GameStanding;
use App\Modules\Competition\Services\CalendarService;
use App\Modules\Lineup\Services\AITacticsService;
use App\Modules\Lineup\Services\LineupService;
use App\Modules\Lineup\Services\OpponentAnalysisBuilder;
use App\Support\PreMatchContext;
use App\Support\TeamColors;
use Illuminate\Http\Request;

class ShowOpponentAnalysis
{
    public function __construct(
        private readonly LineupService $lineupService,
        private readonly CalendarService $calendarService,
        private readonly OpponentAnalysisBuilder $analysisBuilder,
        private readonly AITacticsService $aiTactics,
    ) {}

    public function __invoke(string $gameId, Request $request)
    {
        $context = PreMatchContext::resolve($gameId);
        $game = $context->game;
        $match = $context->match;
        $isHome = $context->isHome;
        $opponent = $context->opponent;
        $matchDate = $context->matchDate;
        $competitionId = $context->competitionId;
        $requireEnrollment = $game->requiresSquadEnrollment();

        $userBestXI = $this->lineupService->getBestXIWithAverage(
            $gameId,
            $game->team_id,
            $matchDate,
            $competitionId,
            requireEnrollment: $requireEnrollment,
        );

        $opponentAvailable = $this->lineupService->getAvailablePlayers($gameId, $opponent->id, $matchDate, $competitionId);
        $opponentData = $this->aiTactics->predictOpponentTactics(
            $opponentAvailable,
            $gameId,
            $opponent->id,
            $match->hasHomeAdvantage($opponent->id),
            $userBestXI['average'],
        );

        $derived = $this->analysisBuilder->build($opponentData);

        $opponentSquad = $this->lineupService->getAllPlayers($gameId, $opponent->id);
        $absentees = $this->analysisBuilder->absentees($opponentSquad, $matchDate, $competitionId);

        $opponentColors = TeamColors::toHex(
            $opponent->colors ?? TeamColors::get($opponent->getRawOriginal('name'))
        );

        $viewData = [
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
            'absentees' => $absentees,
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
        ];

        // The lineup page embeds this analysis in a modal that lazy-loads the
        // partial via fetch — skip the chrome and return only the inner markup
        // so it can be injected directly into the modal body.
        if ($request->ajax()) {
            return view('partials.opponent-analysis-content', $viewData + ['inModal' => true]);
        }

        return view('opponent-analysis', $viewData);
    }
}
