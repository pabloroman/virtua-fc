<?php

namespace App\Http\View\Composers;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use Illuminate\View\View;

/**
 * Provides the tactical-guide modal with the static reference data it renders.
 *
 * None of this content depends on a specific game/match — it's all derived
 * from app config + tactic enums — so it lives in a composer instead of being
 * rebuilt by every view action that includes the partial.
 */
class TacticalGuideComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'guideFormations' => collect(config('match_simulation.formations'))->map(fn ($mods, $name) => [
                'name' => $name,
                'attack' => $mods['attack'],
                'defense' => $mods['defense'],
            ])->values(),

            'guideMentalities' => collect(config('match_simulation.mentalities'))->map(fn ($mods, $name) => [
                'name' => $name,
                'own_goals' => $mods['own_goals'],
                'opponent_goals' => $mods['opponent_goals'],
            ])->values(),

            'guidePlayingStyles' => collect(PlayingStyle::cases())->map(fn (PlayingStyle $s) => [
                'label' => $s->label(),
                'own_xg' => $s->ownXGModifier(),
                'opp_xg' => $s->opponentXGModifier(),
                'energy' => $s->energyDrainMultiplier(),
            ]),

            'guidePressingOptions' => collect(PressingIntensity::cases())->map(fn (PressingIntensity $p) => [
                'label' => $p->label(),
                'own_xg' => $p->ownXGModifier(),
                'opp_xg' => config("match_simulation.pressing.{$p->value}.opp_xg"),
                'energy' => $p->energyDrainMultiplier(),
                'fades' => config("match_simulation.pressing.{$p->value}.fade_after") !== null,
                'fade_to' => config("match_simulation.pressing.{$p->value}.fade_opp_xg"),
            ]),

            'guideDefensiveLines' => collect(DefensiveLineHeight::cases())->map(fn (DefensiveLineHeight $d) => [
                'label' => $d->label(),
                'own_xg' => $d->ownXGModifier(),
                'opp_xg' => $d->opponentXGModifier(),
            ]),

            'tacticalInteractions' => config('match_simulation.tactical_interactions'),
        ]);
    }
}
