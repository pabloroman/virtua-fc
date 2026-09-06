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

    /**
     * Paper strength in the 0..1 rating band for a selected XI.
     *
     * Fitness weight is intentionally absent — its effect enters via lineup
     * selection (low-fitness players are penalized when the XI is picked), not
     * via the paper-strength average.
     *
     * A squad-less cup entrant has no XI to average, so it falls back to
     * {@see GhostStrength}, which reads its standing from the club profile
     * every team carries. Pass `$reputationLevel` when the caller knows whose
     * lineup this is; without it a ghost gets the flat amateur rating, which
     * is the same answer for the overwhelming majority of them.
     *
     * @param  iterable<object>  $lineupPlayers  players exposing `overall_score` and `morale`
     */
    public static function estimate(iterable $lineupPlayers, ?string $reputationLevel = null): float
    {
        $players = is_array($lineupPlayers) ? $lineupPlayers : iterator_to_array($lineupPlayers);

        if ($players === []) {
            return GhostStrength::forReputation($reputationLevel);
        }

        if (count($players) < self::MIN_LINEUP_SIZE) {
            return GhostStrength::thinLineup();
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
