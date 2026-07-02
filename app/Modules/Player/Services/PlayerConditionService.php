<?php

namespace App\Modules\Player\Services;

use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Modules\Match\Services\EnergyCalculator;
use App\Modules\Match\Support\MatchOutcomeModel;
use App\Modules\Match\Support\PaperStrength;
use App\Modules\Player\PlayerAge;
use Carbon\Carbon;

class PlayerConditionService
{
    // Maximum fitness
    private const MAX_FITNESS = 100;

    // Minimum fitness (players can't drop below this)
    private const MIN_FITNESS = 40;

    // Morale changes
    private const MORALE_WIN = [4, 8];
    private const MORALE_DRAW = [0, 2];
    private const MORALE_LOSS = [-4, -1];

    // Bench frustration: morale penalty applied each match a player doesn't feature
    private const MORALE_BENCH_FRUSTRATION = [1, 2];

    // Individual event morale impacts
    private const MORALE_GOAL = [2, 4];
    private const MORALE_ASSIST = [1, 3];
    private const MORALE_OWN_GOAL = [-4, -2];

    // Morale bounds
    private const MAX_MORALE = 100;
    private const MIN_MORALE = 50;

    // Underperformance/overperformance morale, keyed on the expected-points delta
    // (actual − expected league points, expected derived from the sim's pre-match
    // win probability). Dropping points you were favoured to take erodes morale
    // beyond the flat loss penalty; stealing points you weren't favoured to take
    // lifts it beyond the flat win bonus. Both scale with severity and stack on
    // the normal result change, so a heavy favourite's collapse bites hard while
    // an underdog's expected defeat barely registers.
    private const UNDERPERFORMANCE_POINTS_THRESHOLD = 1.0; // expected pts dropped before it bites
    private const UNDERPERFORMANCE_SPAN = 1.5;              // delta beyond threshold ⇒ full penalty
    private const UNDERPERFORMANCE_MAX_PENALTY = 5;         // cap (heavy-favourite collapse)
    private const OVERPERFORMANCE_POINTS_THRESHOLD = 1.0;  // expected pts gained above expectation before it lifts
    private const OVERPERFORMANCE_SPAN = 1.5;
    private const OVERPERFORMANCE_MAX_BONUS = 4;            // cap (underdog giant-killing)

    // A lineup below this many players isn't a real XI (partial/empty lineups in
    // tests or abandoned matches) — skip the expectation term for that match.
    private const MIN_LINEUP_FOR_EXPECTATION = 7;

    /**
     * Batch-update fitness and morale for all players across all matches in a matchday.
     * Single UPDATE query for all players (~500 in a typical La Liga matchday).
     *
     * @param  \Illuminate\Support\Collection  $matches  All matches in this matchday batch
     * @param  array  $matchResults  Match result data including events
     * @param  \Illuminate\Support\Collection  $allPlayersByTeam  Pre-loaded players grouped by team_id
     * @param  array<string, int>  $recoveryDaysByTeam  team_id => calendar days since that team's last match
     */
    public function batchUpdateAfterMatchday($matches, array $matchResults, $allPlayersByTeam, array $recoveryDaysByTeam, Carbon $currentDate): void
    {
        $updates = [];
        $injuredFloor = (int) config('player.condition.injured_floor', 30);

        // Index by matchId for O(1) lookups instead of O(n) per match
        $resultsByMatchId = [];
        foreach ($matchResults as $result) {
            $resultsByMatchId[$result['matchId']] = $result;
        }

        foreach ($matches as $match) {
            $result = $resultsByMatchId[$match->id] ?? null;
            $events = $result['events'] ?? [];
            $eventsByPlayer = $this->groupEventsByPlayer($events);

            $lineupIds = array_merge($match->home_lineup ?? [], $match->away_lineup ?? []);
            $homeWon = $match->home_score > $match->away_score;
            $awayWon = $match->away_score > $match->home_score;

            $homePlayers = $allPlayersByTeam->get($match->home_team_id, collect());
            $awayPlayers = $allPlayersByTeam->get($match->away_team_id, collect());

            // Expected-points delta per side (actual − expected). Computed once per
            // match from the paper strength of each XI, before the player loop.
            [$homeDelta, $awayDelta] = $this->expectedPointsDeltas($match, $homePlayers, $awayPlayers);

            $players = collect()->merge($homePlayers)->merge($awayPlayers);

            foreach ($players as $player) {
                if (isset($updates[$player->id])) {
                    continue; // already processed via another match
                }

                $isInLineup = in_array($player->id, $lineupIds);
                $isHome = $player->team_id === $match->home_team_id;
                $teamRecoveryDays = $recoveryDaysByTeam[$player->team_id] ?? 7;

                $fitnessChange = $this->calculateFitnessChange($player, $isInLineup, $teamRecoveryDays, $currentDate);
                $moraleChange = $this->calculateMoraleChange(
                    $player,
                    $isInLineup,
                    $isHome ? $homeWon : $awayWon,
                    $isHome ? $awayWon : $homeWon,
                    $eventsByPlayer[$player->id] ?? [],
                    $isHome ? $homeDelta : $awayDelta,
                );

                // Sidelined players are allowed below MIN_FITNESS so a long
                // layoff registers visibly. Everyone else clamps at the
                // regular floor.
                $fitnessFloor = $player->isInjured($currentDate) ? $injuredFloor : self::MIN_FITNESS;

                $updates[$player->id] = [
                    'fitness' => max($fitnessFloor, min(self::MAX_FITNESS, $player->fitness + $fitnessChange)),
                    'morale' => max(self::MIN_MORALE, min(self::MAX_MORALE, $player->morale + $moraleChange)),
                ];
            }
        }

        $this->bulkUpdateConditions($updates);
    }

    /**
     * Perform bulk update of fitness and morale using a single query.
     */
    private function bulkUpdateConditions(array $updates): void
    {
        GamePlayerMatchState::bulkSetValues($updates);
    }

    /**
     * Expected-points delta (actual − expected) for each side of a match.
     *
     * Recomputes the pre-match expectation from the paper strength of each XI —
     * the same math the sim used to produce the result — so morale can react to
     * a favourite dropping points it was supposed to take (negative delta) or an
     * underdog stealing points it wasn't (positive delta).
     *
     * Returns [0.0, 0.0] when either XI is too thin to be a real lineup (partial
     * or empty lineups), leaving the flat result change as the only morale driver.
     *
     * @return array{0: float, 1: float}  [homeDelta, awayDelta]
     */
    private function expectedPointsDeltas($match, $homePlayers, $awayPlayers): array
    {
        $homeXI = $homePlayers->whereIn('id', $match->home_lineup ?? []);
        $awayXI = $awayPlayers->whereIn('id', $match->away_lineup ?? []);

        if ($homeXI->count() < self::MIN_LINEUP_FOR_EXPECTATION
            || $awayXI->count() < self::MIN_LINEUP_FOR_EXPECTATION) {
            return [0.0, 0.0];
        }

        [$homeXG, $awayXG] = MatchOutcomeModel::expectedGoals(
            PaperStrength::estimate($homeXI),
            PaperStrength::estimate($awayXI),
            $match->isNeutralVenue(),
        );
        $probs = MatchOutcomeModel::outcomeProbabilities(
            MatchOutcomeModel::scoreProbabilityMatrix($homeXG, $awayXG),
        );

        $homeExpectedPts = 3 * $probs['home'] + $probs['draw'];
        $awayExpectedPts = 3 * $probs['away'] + $probs['draw'];

        $homeActualPts = $this->actualPoints($match->home_score, $match->away_score);
        $awayActualPts = $this->actualPoints($match->away_score, $match->home_score);

        return [$homeActualPts - $homeExpectedPts, $awayActualPts - $awayExpectedPts];
    }

    /**
     * League points a side earned from its own vs the opponent's score (3/1/0).
     */
    private function actualPoints(int $forScore, int $againstScore): int
    {
        return match (true) {
            $forScore > $againstScore => 3,
            $forScore === $againstScore => 1,
            default => 0,
        };
    }

    /**
     * Calculate fitness change for a player using nonlinear recovery
     * and energy-drain-based match loss (unified energy model).
     *
     * Match loss is derived from the EnergyCalculator drain formula:
     * players lose energy proportionally to their starting fitness,
     * based on physical ability, age, and position (GK multiplier).
     *
     * Recovery is based on the estimated post-match energy level so that
     * the nonlinear formula correctly accelerates recovery from the low
     * energy state after a match. This ensures:
     * - Weekly matches: full recovery to 100
     * - Congested periods (3 days): stabilize around 75-80 starting energy
     *
     * Formula: recoveryRate = base × (1 + scaling × (100 − postMatchEnergy) / 100)
     */
    private function calculateFitnessChange(GamePlayer $player, bool $playedMatch, int $daysSinceLastMatch, Carbon $currentDate): int
    {
        $config = config('player.condition');
        $currentFitness = $player->fitness;
        $maxRecoveryDays = $config['max_recovery_days'];
        $recoveryDays = min($daysSinceLastMatch, $maxRecoveryDays);

        // Sidelined players don't play and don't recover — they lose match
        // sharpness over the layoff. Scaled by elapsed days so a weekly
        // cadence ≈ -weekly_decay, congested weeks decay proportionally less.
        if ($player->isInjured($currentDate)) {
            $weeklyDecay = (float) ($config['weekly_decay_when_injured'] ?? 8);

            return -(int) round($weeklyDecay * $recoveryDays / 7);
        }

        // Nonlinear recovery: faster when far below 100, slow near the top
        $baseRecovery = $config['base_recovery_per_day'];
        $scaling = $config['recovery_scaling'];

        if ($playedMatch) {
            // Energy-drain-based loss: use EnergyCalculator to determine
            // how much energy the player would lose during 90 minutes.
            // Tactical drain averages to ~1.0 across a season, so we use
            // the default multiplier for between-match calculations.
            $age = $player->age($currentDate);
            $isGK = $player->position === 'Goalkeeper';
            $ageModifier = $this->getAgeLossModifier($player, $config, $currentDate);

            $endingEnergy = EnergyCalculator::energyAtMinute(
                $player->overall_score,
                $age,
                $isGK,
                90,
                0,
                1.0, // default tactical drain
                (float) $currentFitness,
            );

            $loss = (int) round(($currentFitness - $endingEnergy) * $ageModifier);

            // Recovery is based on estimated post-match energy, not current fitness.
            // After playing, the player is at low energy and recovers faster from there.
            // This correctly models: play match → drop to ~60% → recover over N days.
            $estimatedPostMatch = max(self::MIN_FITNESS, $currentFitness - $loss);
            $recoveryRate = $baseRecovery * (1 + $scaling * (self::MAX_FITNESS - $estimatedPostMatch) / 100);
            $recovery = (int) round($recoveryRate * $recoveryDays);

            return $recovery - $loss;
        }

        // Non-playing players: recovery only, based on current fitness
        $recoveryRate = $baseRecovery * (1 + $scaling * (self::MAX_FITNESS - $currentFitness) / 100);
        $recovery = (int) round($recoveryRate * $recoveryDays);

        return $recovery;
    }

    /**
     * Get age-based modifier for fitness loss (veterans lose more per match).
     */
    private function getAgeLossModifier(GamePlayer $player, array $config, Carbon $currentDate): float
    {
        $age = $player->age($currentDate);
        $ageMod = $config['age_loss_modifier'];

        return match (true) {
            $age <= PlayerAge::YOUNG_END => $ageMod['young'],
            $age < PlayerAge::MIN_RETIREMENT_OUTFIELD => $ageMod['prime'],
            default => $ageMod['veteran'],
        };
    }

    /**
     * Calculate morale change for a player.
     */
    private function calculateMoraleChange(
        GamePlayer $player,
        bool $playedMatch,
        bool $teamWon,
        bool $teamLost,
        array $playerEvents,
        float $teamPointsDelta = 0.0
    ): int {
        $change = 0;

        // Match result affects all squad members, but more for those who played
        $resultMultiplier = $playedMatch ? 1.0 : 0.5;

        if ($teamWon) {
            $change += (int) (rand(self::MORALE_WIN[0], self::MORALE_WIN[1]) * $resultMultiplier);
        } elseif ($teamLost) {
            $change += (int) (rand(self::MORALE_LOSS[0], self::MORALE_LOSS[1]) * $resultMultiplier);
        } else {
            $change += (int) (rand(self::MORALE_DRAW[0], self::MORALE_DRAW[1]) * $resultMultiplier);
        }

        // Under/overperformance vs the pre-match expectation, keyed on the
        // expected-points delta. A favourite that drops points it was supposed
        // to take loses morale beyond the flat penalty; an underdog that steals
        // points gains beyond the flat bonus. Shares the same result multiplier
        // so bench players feel it at half.
        $change += self::underperformanceMoraleDelta($teamPointsDelta, $resultMultiplier);

        // Individual event impacts (only for players who participated)
        if ($playedMatch) {
            foreach ($playerEvents as $event) {
                $change += match ($event['event_type']) {
                    'goal' => rand(self::MORALE_GOAL[0], self::MORALE_GOAL[1]),
                    'assist' => rand(self::MORALE_ASSIST[0], self::MORALE_ASSIST[1]),
                    'own_goal' => rand(self::MORALE_OWN_GOAL[0], self::MORALE_OWN_GOAL[1]),
                    default => 0,
                };
            }
        }

        // Bench frustration: players who don't get game time gradually lose morale
        // regardless of team results. Offsets the win bonus for non-playing players.
        // Better players get more frustrated — star players have higher expectations.
        if (!$playedMatch) {
            $ability = $player->overall_score;
            // Multiplier ranges from ~0.3x (ability 20) to ~1.0x (ability 100)
            $frustrationMultiplier = 0.3 + ($ability / 100.0) * 0.7;
            $baseFrustration = rand(self::MORALE_BENCH_FRUSTRATION[0], self::MORALE_BENCH_FRUSTRATION[1]);
            $change -= max(1, (int) round($baseFrustration * $frustrationMultiplier));
        }

        return $change;
    }

    /**
     * Morale change from a result relative to its pre-match expectation, keyed on
     * the expected-points delta (actual − expected league points).
     *
     * Deterministic and severity-scaled: a favourite dropping points it was
     * favoured to take erodes morale beyond the flat loss penalty, and an
     * underdog stealing points lifts it beyond the flat win bonus, while a result
     * that merely matched expectation returns 0. An expected defeat (small
     * negative delta below the threshold) barely registers. `$resultMultiplier`
     * scales the same way the flat result does (1.0 played, 0.5 bench).
     *
     * Public + static so the morale-realism diagnostic reads the exact same
     * curve the sim applies, and so the shape can be unit-tested without the
     * random flat-result noise around it.
     */
    public static function underperformanceMoraleDelta(float $teamPointsDelta, float $resultMultiplier = 1.0): int
    {
        $dropped = -$teamPointsDelta; // >0 when the side finished below expectation
        $gained = $teamPointsDelta;   // >0 when the side finished above expectation

        if ($dropped > self::UNDERPERFORMANCE_POINTS_THRESHOLD) {
            $severity = min(1.0, ($dropped - self::UNDERPERFORMANCE_POINTS_THRESHOLD) / self::UNDERPERFORMANCE_SPAN);

            return -(int) round($severity * self::UNDERPERFORMANCE_MAX_PENALTY * $resultMultiplier);
        }

        if ($gained > self::OVERPERFORMANCE_POINTS_THRESHOLD) {
            $severity = min(1.0, ($gained - self::OVERPERFORMANCE_POINTS_THRESHOLD) / self::OVERPERFORMANCE_SPAN);

            return (int) round($severity * self::OVERPERFORMANCE_MAX_BONUS * $resultMultiplier);
        }

        return 0;
    }

    /**
     * Group match events by player ID for quick lookup.
     */
    private function groupEventsByPlayer(array $events): array
    {
        $grouped = [];

        foreach ($events as $event) {
            $playerId = $event['game_player_id'] ?? null;
            if ($playerId) {
                $grouped[$playerId][] = $event;
            }
        }

        return $grouped;
    }

}
