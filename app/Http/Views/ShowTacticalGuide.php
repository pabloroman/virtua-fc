<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;

class ShowTacticalGuide
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $nextMatch = $game->nextMatch();

        $formations = collect(config('match_simulation.formations'))->map(fn ($mods, $name) => [
            'name' => $name,
            'attack' => $mods['attack'],
            'defense' => $mods['defense'],
        ])->values();

        $mentalities = collect(config('match_simulation.mentalities'))->map(fn ($mods, $name) => [
            'name' => $name,
            'own_goals' => $mods['own_goals'],
            'opponent_goals' => $mods['opponent_goals'],
        ])->values();

        $playingStyles = collect(PlayingStyle::cases())->map(fn (PlayingStyle $s) => [
            'label' => $s->label(),
            'own_xg' => $s->ownXGModifier(),
            'opp_xg' => $s->opponentXGModifier(),
            'energy' => $s->energyDrainMultiplier(),
        ]);

        $pressingOptions = collect(PressingIntensity::cases())->map(fn (PressingIntensity $p) => [
            'label' => $p->label(),
            'own_xg' => $p->ownXGModifier(),
            'opp_xg' => config("match_simulation.pressing.{$p->value}.opp_xg"),
            'energy' => $p->energyDrainMultiplier(),
            'fades' => config("match_simulation.pressing.{$p->value}.fade_after") !== null,
            'fade_to' => config("match_simulation.pressing.{$p->value}.fade_opp_xg"),
        ]);

        $defensiveLines = collect(DefensiveLineHeight::cases())->map(fn (DefensiveLineHeight $d) => [
            'label' => $d->label(),
            'own_xg' => $d->ownXGModifier(),
            'opp_xg' => $d->opponentXGModifier(),
            'threshold' => $d->physicalThreshold(),
        ]);

        $interactions = config('match_simulation.tactical_interactions');

        return view('tactical-guide', [
            'game' => $game,
            'nextMatch' => $nextMatch,
            'formations' => $formations,
            'mentalities' => $mentalities,
            'playingStyles' => $playingStyles,
            'pressingOptions' => $pressingOptions,
            'defensiveLines' => $defensiveLines,
            'interactions' => $interactions,
        ]);
    }
}
