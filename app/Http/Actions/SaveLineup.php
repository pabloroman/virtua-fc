<?php

namespace App\Http\Actions;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use App\Models\Game;
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
        ]);

        $playerIds = array_values(array_filter($validated['players']));
        $formation = Formation::from($validated['formation']);
        $mentality = Mentality::from($validated['mentality']);
        $playingStyle = PlayingStyle::from($validated['playing_style']);
        $pressing = PressingIntensity::from($validated['pressing']);
        $defensiveLine = DefensiveLineHeight::from($validated['defensive_line']);
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
}
