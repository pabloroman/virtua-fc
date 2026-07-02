<?php

namespace App\Modules\Match\Support;

/**
 * Ability-dominant "paper" strength of a starting XI — who was favoured on paper,
 * before a ball is kicked.
 *
 * This is the strength that feeds {@see MatchOutcomeModel::expectedGoals}: the
 * average of each player's `overall_score` (weighted 0.95) and `morale` (0.05),
 * divided by 11 and rescaled to the 0..1 rating band. It deliberately omits the
 * match-time noise that {@see \App\Modules\Match\Services\MatchSimulator::calculateTeamStrength}
 * layers on (per-minute energy drain, form-on-the-day, out-of-position penalties)
 * — those describe how a match *unfolds*, not who was favoured going in.
 *
 * {@see \App\Modules\Match\Services\AIMatchResolver} resolves AI-vs-AI matches
 * through this, and {@see \App\Modules\Player\Services\PlayerConditionService}
 * recomputes it post-match to read the expected-points a squad was supposed to
 * take — the basis of the underperformance morale term — so the paper-strength
 * formula lives in exactly one place.
 */
class PaperStrength
{
    /** Fallback when a lineup is too thin to be a real XI (partial/empty lineups). */
    private const MIN_LINEUP_SIZE = 7;

    private const THIN_LINEUP_STRENGTH = 0.30;

    /**
     * Paper strength in the 0..1 rating band for a selected XI.
     *
     * Fitness weight is intentionally absent — its effect enters via lineup
     * selection (low-fitness players are penalized when the XI is picked), not
     * via the paper-strength average.
     *
     * @param  iterable<object>  $lineupPlayers  players exposing `overall_score` and `morale`
     */
    public static function estimate(iterable $lineupPlayers): float
    {
        $players = is_array($lineupPlayers) ? $lineupPlayers : iterator_to_array($lineupPlayers);

        if (count($players) < self::MIN_LINEUP_SIZE) {
            return self::THIN_LINEUP_STRENGTH;
        }

        $wOverall = config('match_simulation.strength_weight_overall', 0.95);
        $wMorale = config('match_simulation.strength_weight_morale', 0.05);

        $totalStrength = 0;
        foreach ($players as $player) {
            $totalStrength += ($player->overall_score * $wOverall) +
                              ($player->morale * $wMorale);
        }

        return ($totalStrength / 11) / 100;
    }
}
