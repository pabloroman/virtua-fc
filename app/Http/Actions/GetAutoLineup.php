<?php

namespace App\Http\Actions;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\LineupService;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAutoLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(Request $request, string $gameId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = $game->next_match;

        abort_unless($match, 404);

        // Get formation from request, default to 4-3-3
        $formationValue = $request->input('formation', '4-3-3');
        $formation = Formation::tryFrom($formationValue) ?? Formation::F_4_3_3;

        // Get match details for availability checks
        $matchDate = $match->scheduled_date;
        $competitionId = $match->competition_id;

        // Get auto-selected lineup for the formation. Pass the user's
        // configured rotation policy so tired starters are penalised the
        // same way fast-mode prep does — otherwise this button would rank
        // purely by raw overall_score.
        $requireEnrollment = $game->requiresSquadEnrollment();
        $autoLineup = $this->lineupService->autoSelectLineup(
            $gameId,
            $game->team_id,
            $matchDate,
            $competitionId,
            $formation,
            $requireEnrollment,
            $game->tactics?->default_rotation_policy,
        );

        return response()->json([
            'autoLineup' => $autoLineup,
            'formation' => $formation->value,
        ]);
    }
}
