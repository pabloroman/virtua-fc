<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameTacticalPreset;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class SaveTacticalPreset
{
    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $validated = $request->validate([
            'name' => 'required|string|max:30',
            'formation' => ['required', 'string', new Enum(Formation::class)],
            'lineup' => 'required|array|min:11|max:11',
            'lineup.*' => 'required|string|uuid',
            'slot_assignments' => 'nullable|json',
            'pitch_positions' => 'nullable|json',
            'mentality' => ['required', 'string', new Enum(Mentality::class)],
            'playing_style' => ['required', 'string', new Enum(PlayingStyle::class)],
            'pressing' => ['required', 'string', new Enum(PressingIntensity::class)],
            'defensive_line' => ['required', 'string', new Enum(DefensiveLineHeight::class)],
            'preset_id' => 'nullable|string|uuid',
        ]);

        // If updating an existing preset
        if (!empty($validated['preset_id'])) {
            $preset = GameTacticalPreset::where('id', $validated['preset_id'])
                ->where('game_id', $gameId)
                ->firstOrFail();

            $preset->update([
                'name' => $validated['name'],
                'formation' => $validated['formation'],
                'lineup' => $validated['lineup'],
                'slot_assignments' => json_decode($validated['slot_assignments'] ?? 'null', true),
                'pitch_positions' => json_decode($validated['pitch_positions'] ?? 'null', true),
                'mentality' => $validated['mentality'],
                'playing_style' => $validated['playing_style'],
                'pressing' => $validated['pressing'],
                'defensive_line' => $validated['defensive_line'],
            ]);

            return redirect()->route('game.lineup', $gameId)
                ->with('success', __('messages.preset_updated'));
        }

        // Enforce max 3 presets
        $count = GameTacticalPreset::where('game_id', $gameId)->count();
        if ($count >= 3) {
            return redirect()->route('game.lineup', $gameId)
                ->withErrors([__('messages.preset_limit_reached')]);
        }

        // Assign next sort order
        $maxSort = GameTacticalPreset::where('game_id', $gameId)->max('sort_order') ?? 0;

        GameTacticalPreset::create([
            'game_id' => $gameId,
            'name' => $validated['name'],
            'sort_order' => $maxSort + 1,
            'formation' => $validated['formation'],
            'lineup' => $validated['lineup'],
            'slot_assignments' => json_decode($validated['slot_assignments'] ?? 'null', true),
            'pitch_positions' => json_decode($validated['pitch_positions'] ?? 'null', true),
            'mentality' => $validated['mentality'],
            'playing_style' => $validated['playing_style'],
            'pressing' => $validated['pressing'],
            'defensive_line' => $validated['defensive_line'],
        ]);

        return redirect()->route('game.lineup', $gameId)
            ->with('success', __('messages.preset_saved'));
    }
}
