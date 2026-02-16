<?php

namespace App\Game\Services;

use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;

class TacticalChangeService
{
    public function __construct(
        private readonly MatchResimulationService $resimulationService,
        private readonly SubstitutionService $substitutionService,
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
    ): array {
        $isUserHome = $match->isHomeTeam($game->team_id);

        // Update match formation/mentality fields
        $matchUpdates = [];
        $gameUpdates = [];

        if ($formation !== null) {
            $formationField = $isUserHome ? 'home_formation' : 'away_formation';
            $matchUpdates[$formationField] = $formation;
            $gameUpdates['default_formation'] = $formation;
        }

        if ($mentality !== null) {
            $mentalityField = $isUserHome ? 'home_mentality' : 'away_mentality';
            $matchUpdates[$mentalityField] = $mentality;
            $gameUpdates['default_mentality'] = $mentality;
        }

        if (! empty($matchUpdates)) {
            $match->update($matchUpdates);
        }

        if (! empty($gameUpdates)) {
            $game->update($gameUpdates);
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

        // Re-simulate with updated tactics
        $result = $this->resimulationService->resimulate($match, $game, $minute, $homePlayers, $awayPlayers);

        return [
            'newScore' => [
                'home' => $result->newHomeScore,
                'away' => $result->newAwayScore,
            ],
            'newEvents' => $this->resimulationService->buildEventsResponse($match, $minute),
            'formation' => $effectiveFormation,
            'mentality' => $effectiveMentality,
        ];
    }
}
