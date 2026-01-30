<?php

namespace App\Http\Actions;

use App\Game\Enums\Formation;
use App\Game\Services\LineupService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\Request;

class SaveLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId)
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::where('game_id', $gameId)->findOrFail($matchId);

        // Get selected player IDs from request
        $playerIds = $request->input('players', []);

        // Get formation from request
        $formationValue = $request->input('formation', '4-4-2');
        $formation = Formation::tryFrom($formationValue) ?? Formation::F_4_4_2;

        // Ensure we have an array of strings
        $playerIds = array_values(array_filter((array) $playerIds));

        // Determine matchday for validation
        $matchday = $match->round_number ?? $game->current_matchday + 1;
        $matchDate = $match->scheduled_date;

        // Validate the lineup against the formation
        $errors = $this->lineupService->validateLineup(
            $playerIds,
            $gameId,
            $game->team_id,
            $matchDate,
            $matchday,
            $formation
        );

        if (!empty($errors)) {
            return redirect()
                ->route('game.lineup', [$gameId, $matchId])
                ->withErrors($errors)
                ->withInput(['players' => $playerIds, 'formation' => $formation->value]);
        }

        // Save the lineup and formation
        $this->lineupService->saveLineup($match, $game->team_id, $playerIds);
        $this->lineupService->saveFormation($match, $game->team_id, $formation->value);

        // Save as default formation if requested
        if ($request->boolean('save_default_formation')) {
            $game->update(['default_formation' => $formation->value]);
        }

        // Redirect to game page - user clicks Continue to advance
        return redirect()->route('show-game', $gameId)
            ->with('message', 'Lineup confirmed! Click Continue to play the match.');
    }
}
