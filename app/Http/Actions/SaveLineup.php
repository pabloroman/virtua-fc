<?php

namespace App\Http\Actions;

use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Game\Services\LineupService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class SaveLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId)
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::where('game_id', $gameId)->findOrFail($matchId);

        $validated = $request->validate([
            'players' => 'required|array|min:1',
            'players.*' => 'required|string|uuid',
            'formation' => ['required', 'string', new Enum(Formation::class)],
            'mentality' => ['required', 'string', new Enum(Mentality::class)],
            'slot_assignments' => 'nullable|array',
            'slot_assignments.*' => 'nullable|string|uuid',
        ]);

        $playerIds = array_values(array_filter($validated['players']));
        $formation = Formation::from($validated['formation']);
        $mentality = Mentality::from($validated['mentality']);
        $slotAssignments = $validated['slot_assignments'] ?? null;

        // Get match details for validation
        $matchDate = $match->scheduled_date;
        $competitionId = $match->competition_id;

        // Validate the lineup against the formation
        $errors = $this->lineupService->validateLineup(
            $playerIds,
            $gameId,
            $game->team_id,
            $matchDate,
            $competitionId,
            $formation,
            $slotAssignments
        );

        if (!empty($errors)) {
            return redirect()
                ->route('game.lineup', [$gameId, $matchId])
                ->withErrors($errors)
                ->withInput(['players' => $playerIds, 'formation' => $formation->value, 'mentality' => $mentality->value]);
        }

        // Save the lineup, slot assignments, formation, and mentality for this match
        $this->lineupService->saveLineup($match, $game->team_id, $playerIds);
        $this->lineupService->saveFormation($match, $game->team_id, $formation->value);
        $this->lineupService->saveMentality($match, $game->team_id, $mentality->value);

        // Always save lineup, formation, mentality, and slot assignments as defaults
        $game->update([
            'default_lineup' => $playerIds,
            'default_slot_assignments' => $slotAssignments,
            'default_formation' => $formation->value,
            'default_mentality' => $mentality->value,
        ]);

        // Redirect to game page - user clicks Continue to advance
        return redirect()->route('show-game', $gameId)
            ->with('message', 'Lineup confirmed! Click Continue to play the match.');
    }
}
