<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameTacticalPreset;
use App\Modules\Lineup\Services\LineupService;
use Illuminate\Http\Request;

class LoadTacticalPreset
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $presetId)
    {
        $game = Game::with('tactics')->findOrFail($gameId);
        $match = $game->next_match;

        abort_unless($match, 404);

        $preset = GameTacticalPreset::where('id', $presetId)
            ->where('game_id', $gameId)
            ->firstOrFail();

        // Apply preset to defaults
        $game->tactics->update([
            'default_formation' => $preset->formation,
            'default_lineup' => $preset->lineup,
            'default_slot_assignments' => $preset->slot_assignments,
            'default_pitch_positions' => $preset->pitch_positions,
            'default_mentality' => $preset->mentality,
            'default_playing_style' => $preset->playing_style,
            'default_pressing' => $preset->pressing,
            'default_defensive_line' => $preset->defensive_line,
        ]);

        // Also apply to the next match
        $prefix = $match->isHomeTeam($game->team_id) ? 'home' : 'away';
        $match->update([
            "{$prefix}_lineup" => $preset->lineup,
            "{$prefix}_formation" => $preset->formation,
            "{$prefix}_mentality" => $preset->mentality,
            "{$prefix}_playing_style" => $preset->playing_style,
            "{$prefix}_pressing" => $preset->pressing,
            "{$prefix}_defensive_line" => $preset->defensive_line,
        ]);

        return redirect()->route('game.lineup', $gameId)
            ->with('success', __('messages.preset_loaded', ['name' => $preset->name]));
    }
}
