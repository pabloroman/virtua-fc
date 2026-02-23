<?php

namespace App\Modules\Lineup\Services;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use App\Modules\Match\Services\MatchResimulationService;
use App\Modules\Lineup\Services\SubstitutionService;

class TacticalChangeService
{
    public function __construct(
        private readonly MatchResimulationService $resimulationService,
        private readonly SubstitutionService $substitutionService,
        private readonly ExtraTimeAndPenaltyService $extraTimeService,
    ) {}

    /**
     * Process a tactical change (formation and/or mentality) mid-match.
     * Updates match record, game defaults, then re-simulates the remainder.
     */
    public function processTacticalChange(
        GameMatch $match,
        Game $game,
        int $minute,
        array $previousSubstitutions,
        ?string $formation = null,
        ?string $mentality = null,
        bool $isExtraTime = false,
    ): array {
        $isUserHome = $match->isHomeTeam($game->team_id);

        // Update match formation/mentality fields (match-only, not game defaults)
        $matchUpdates = [];

        if ($formation !== null) {
            $formationField = $isUserHome ? 'home_formation' : 'away_formation';
            $matchUpdates[$formationField] = $formation;
        }

        if ($mentality !== null) {
            $mentalityField = $isUserHome ? 'home_mentality' : 'away_mentality';
            $matchUpdates[$mentalityField] = $mentality;
        }

        if (! empty($matchUpdates)) {
            $match->update($matchUpdates);
        }

        // Build active lineup (applying previous subs)
        $userLineup = $this->substitutionService->buildActiveLineup($match, $game->team_id, $previousSubstitutions);
        $opponentLineupIds = $isUserHome ? ($match->away_lineup ?? []) : ($match->home_lineup ?? []);
        $opponentPlayers = GamePlayer::with('player')
            ->whereIn('id', $opponentLineupIds)
            ->get();

        $homePlayers = $isUserHome ? $userLineup : $opponentPlayers;
        $awayPlayers = $isUserHome ? $opponentPlayers : $userLineup;

        // Capture effective values before re-simulation (which updates match scores in-place)
        $effectiveFormation = $isUserHome ? $match->home_formation : $match->away_formation;
        $effectiveMentality = $isUserHome ? $match->home_mentality : $match->away_mentality;

        // Re-simulate with updated tactics (pass previous subs for energy calculation)
        if ($isExtraTime) {
            $result = $this->resimulationService->resimulateExtraTime($match, $game, $minute, $homePlayers, $awayPlayers, $previousSubstitutions);
        } else {
            $result = $this->resimulationService->resimulate($match, $game, $minute, $homePlayers, $awayPlayers, $previousSubstitutions);
        }

        $response = [
            'newScore' => [
                'home' => $result->newHomeScore,
                'away' => $result->newAwayScore,
            ],
            'newEvents' => $this->resimulationService->buildEventsResponse($match, $minute),
            'formation' => $effectiveFormation,
            'mentality' => $effectiveMentality,
        ];

        if ($isExtraTime) {
            $response['isExtraTime'] = true;
            $response['needsPenalties'] = $this->extraTimeService->checkNeedsPenalties(
                $match->fresh(), $result->newHomeScore, $result->newAwayScore
            );
        }

        return $response;
    }
}
