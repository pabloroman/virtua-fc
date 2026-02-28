<?php

namespace App\Http\Actions;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use App\Models\Game;
use App\Support\PitchGrid;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class SaveLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('tactics')->findOrFail($gameId);
        $match = $game->next_match;

        abort_unless($match, 404);

        $validated = $request->validate([
            'players' => 'required|array|min:1',
            'players.*' => 'required|string|uuid',
            'formation' => ['required', 'string', new Enum(Formation::class)],
            'mentality' => ['required', 'string', new Enum(Mentality::class)],
            'playing_style' => ['required', 'string', new Enum(PlayingStyle::class)],
            'pressing' => ['required', 'string', new Enum(PressingIntensity::class)],
            'defensive_line' => ['required', 'string', new Enum(DefensiveLineHeight::class)],
            'slot_assignments' => 'nullable|array',
            'slot_assignments.*' => 'nullable|string|uuid',
            'pitch_positions' => 'nullable|array',
            'pitch_positions.*' => 'nullable|string',
        ]);

        $playerIds = array_values(array_filter($validated['players']));
        $formation = Formation::from($validated['formation']);
        $mentality = Mentality::from($validated['mentality']);
        $playingStyle = PlayingStyle::from($validated['playing_style']);
        $pressing = PressingIntensity::from($validated['pressing']);
        $defensiveLine = DefensiveLineHeight::from($validated['defensive_line']);
        $slotAssignments = $validated['slot_assignments'] ?? null;
        $pitchPositions = $this->parsePitchPositions($validated['pitch_positions'] ?? null, $formation);

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
                ->route('game.lineup', $gameId)
                ->withErrors($errors)
                ->withInput(['players' => $playerIds, 'formation' => $formation->value, 'mentality' => $mentality->value]);
        }

        // Save the lineup, slot assignments, formation, mentality, and instructions for this match
        $this->lineupService->saveLineup($match, $game->team_id, $playerIds);
        $this->lineupService->saveFormation($match, $game->team_id, $formation->value);
        $this->lineupService->saveMentality($match, $game->team_id, $mentality->value);

        // Save instructions on the match record
        $prefix = $match->isHomeTeam($game->team_id) ? 'home' : 'away';
        $match->update([
            "{$prefix}_playing_style" => $playingStyle->value,
            "{$prefix}_pressing" => $pressing->value,
            "{$prefix}_defensive_line" => $defensiveLine->value,
        ]);

        // Always save lineup, formation, mentality, and instructions as defaults
        $game->tactics->update([
            'default_lineup' => $playerIds,
            'default_slot_assignments' => $slotAssignments,
            'default_pitch_positions' => $pitchPositions,
            'default_formation' => $formation->value,
            'default_mentality' => $mentality->value,
            'default_playing_style' => $playingStyle->value,
            'default_pressing' => $pressing->value,
            'default_defensive_line' => $defensiveLine->value,
        ]);

        // Redirect to game page - user clicks Continue to advance
        return redirect()->route('show-game', $gameId)
            ->with('message', 'Lineup confirmed! Click Continue to play the match.');
    }

    /**
     * Parse and validate pitch position data from the form.
     * Input format: {"slotId": "col,row", ...}
     * Output format: {"slotId": [col, row], ...} (only valid, non-default positions kept)
     */
    private function parsePitchPositions(?array $raw, Formation $formation): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $slots = $formation->pitchSlots();
        $slotLabels = [];
        foreach ($slots as $slot) {
            $slotLabels[$slot['id']] = $slot['label'];
        }

        $defaultCells = PitchGrid::getDefaultCells($formation);
        $positions = [];

        foreach ($raw as $slotId => $value) {
            if (empty($value) || ! isset($slotLabels[$slotId])) {
                continue;
            }

            $parts = explode(',', $value);
            if (count($parts) !== 2) {
                continue;
            }

            $col = (int) $parts[0];
            $row = (int) $parts[1];

            // Validate within grid bounds and slot zone
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
