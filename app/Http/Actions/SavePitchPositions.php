<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Lineup\Enums\Formation;
use App\Support\PitchGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavePitchPositions
{
    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::where('game_id', $gameId)->findOrFail($matchId);

        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => __('game.match_not_in_progress')], 403);
        }

        if (! $match->involvesTeam($game->team_id)) {
            return response()->json(['error' => __('game.sub_error_not_your_match')], 422);
        }

        $validated = $request->validate([
            'pitch_positions' => 'required|array',
            'pitch_positions.*' => 'required|array|size:2',
            'pitch_positions.*.0' => 'required|integer|min:0',
            'pitch_positions.*.1' => 'required|integer|min:0',
        ]);

        $isHome = $match->home_team_id === $game->team_id;
        $formationField = $isHome ? 'home_formation' : 'away_formation';
        $formation = Formation::tryFrom($match->$formationField) ?? Formation::F_4_4_2;

        $positions = $this->validatePositions($validated['pitch_positions'], $formation);

        $positionField = $isHome ? 'home_pitch_positions' : 'away_pitch_positions';
        $match->update([$positionField => $positions]);

        return response()->json(['saved' => true, 'positions' => $positions]);
    }

    private function validatePositions(array $raw, Formation $formation): ?array
    {
        $slots = $formation->pitchSlots();
        $slotLabels = [];
        foreach ($slots as $slot) {
            $slotLabels[$slot['id']] = $slot['label'];
        }

        $defaultCells = PitchGrid::getDefaultCells($formation);
        $positions = [];

        foreach ($raw as $slotId => $coords) {
            if (! isset($slotLabels[$slotId])) {
                continue;
            }

            $col = (int) $coords[0];
            $row = (int) $coords[1];

            if (! PitchGrid::isValidCell($slotLabels[$slotId], $col, $row)) {
                continue;
            }

            // Only store if different from default
            $default = $defaultCells[$slotId] ?? null;
            if ($default && $default['col'] === $col && $default['row'] === $row) {
                continue;
            }

            $positions[(string) $slotId] = [$col, $row];
        }

        return empty($positions) ? null : $positions;
    }
}
