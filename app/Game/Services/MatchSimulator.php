<?php

namespace App\Game\Services;

use App\Game\DTO\MatchEventData;
use App\Game\DTO\MatchResult;
use App\Game\Enums\Formation;
use App\Models\GamePlayer;
use App\Models\Team;
use Illuminate\Support\Collection;

class MatchSimulator
{
    /**
     * Match performance cache - stores per-player performance modifiers for the current match.
     * Each player gets a random "form on the day" that affects their contribution.
     * Range: 0.7 to 1.3 (30% variance from their base ability)
     *
     * @var array<string, float>
     */
    private array $matchPerformance = [];

    // Position weights for goal scoring (higher = more likely to score)
    private const SCORING_WEIGHTS = [
        'Centre-Forward' => 30,
        'Second Striker' => 25,
        'Left Winger' => 15,
        'Right Winger' => 15,
        'Attacking Midfield' => 12,
        'Central Midfield' => 6,
        'Left Midfield' => 5,
        'Right Midfield' => 5,
        'Defensive Midfield' => 3,
        'Left-Back' => 2,
        'Right-Back' => 2,
        'Centre-Back' => 2,
        'Goalkeeper' => 0,
    ];

    // Position weights for assists (higher = more likely to assist)
    private const ASSIST_WEIGHTS = [
        'Attacking Midfield' => 25,
        'Left Winger' => 20,
        'Right Winger' => 20,
        'Central Midfield' => 15,
        'Left Midfield' => 12,
        'Right Midfield' => 12,
        'Second Striker' => 10,
        'Centre-Forward' => 8,
        'Left-Back' => 8,
        'Right-Back' => 8,
        'Defensive Midfield' => 6,
        'Centre-Back' => 2,
        'Goalkeeper' => 1,
    ];

    // Position weights for fouls/cards (higher = more likely to get carded)
    private const CARD_WEIGHTS = [
        'Centre-Back' => 20,
        'Defensive Midfield' => 18,
        'Left-Back' => 12,
        'Right-Back' => 12,
        'Central Midfield' => 10,
        'Left Midfield' => 8,
        'Right Midfield' => 8,
        'Attacking Midfield' => 6,
        'Centre-Forward' => 8,
        'Second Striker' => 6,
        'Left Winger' => 5,
        'Right Winger' => 5,
        'Goalkeeper' => 4,
    ];

    // Injury types with weeks out
    private const INJURY_TYPES = [
        'Muscle strain' => [1, 2],
        'Hamstring injury' => [2, 4],
        'Knee injury' => [3, 6],
        'Ankle injury' => [2, 4],
        'Calf injury' => [2, 3],
        'Groin injury' => [2, 4],
    ];

    /**
     * Simulate a match result between two teams.
     *
     * @param Team $homeTeam
     * @param Team $awayTeam
     * @param Collection<GamePlayer> $homePlayers Players for home team (lineup)
     * @param Collection<GamePlayer> $awayPlayers Players for away team (lineup)
     * @param Formation|null $homeFormation Formation for home team
     * @param Formation|null $awayFormation Formation for away team
     */
    public function simulate(
        Team $homeTeam,
        Team $awayTeam,
        Collection $homePlayers,
        Collection $awayPlayers,
        ?Formation $homeFormation = null,
        ?Formation $awayFormation = null,
    ): MatchResult {
        // Reset per-match performance modifiers for fresh randomness
        $this->resetMatchPerformance();

        $homeFormation = $homeFormation ?? Formation::F_4_4_2;
        $awayFormation = $awayFormation ?? Formation::F_4_4_2;

        $events = collect();

        // Calculate expected goals based on lineup strength
        $homeStrength = $this->calculateTeamStrength($homePlayers);
        $awayStrength = $this->calculateTeamStrength($awayPlayers);
        $totalStrength = $homeStrength + $awayStrength;

        // Apply formation modifiers to expected goals
        $homeExpectedGoals = (1.4 + ($homeStrength / $totalStrength))
            * $homeFormation->attackModifier()
            * $awayFormation->defenseModifier();

        $awayExpectedGoals = (0.9 + ($awayStrength / $totalStrength) * 0.8)
            * $awayFormation->attackModifier()
            * $homeFormation->defenseModifier();

        // Generate scores using Poisson distribution
        // These represent "balls in the opponent's net"
        $homeScore = $this->poissonRandom($homeExpectedGoals);
        $awayScore = $this->poissonRandom($awayExpectedGoals);

        // Generate detailed events only if we have player data
        // Events track WHO scored, but don't affect the final score
        if ($homePlayers->isNotEmpty() && $awayPlayers->isNotEmpty()) {
            $homeGoalEvents = $this->generateGoalEvents(
                $homeScore,
                $homeTeam->id,
                $awayTeam->id,
                $homePlayers,
                $awayPlayers
            );

            $awayGoalEvents = $this->generateGoalEvents(
                $awayScore,
                $awayTeam->id,
                $homeTeam->id,
                $awayPlayers,
                $homePlayers
            );

            $events = $events->merge($homeGoalEvents)->merge($awayGoalEvents);

            // Generate card events
            $homeCardEvents = $this->generateCardEvents($homeTeam->id, $homePlayers);
            $awayCardEvents = $this->generateCardEvents($awayTeam->id, $awayPlayers);
            $events = $events->merge($homeCardEvents)->merge($awayCardEvents);

            // Generate injury events (rare)
            $homeInjuryEvents = $this->generateInjuryEvents($homeTeam->id, $homePlayers);
            $awayInjuryEvents = $this->generateInjuryEvents($awayTeam->id, $awayPlayers);
            $events = $events->merge($homeInjuryEvents)->merge($awayInjuryEvents);

            // Sort events by minute
            $events = $events->sortBy('minute')->values();
        }

        return new MatchResult($homeScore, $awayScore, $events);
    }

    /**
     * Generate goal events for a team's attack.
     *
     * @return Collection<MatchEventData>
     */
    private function generateGoalEvents(
        int $goalCount,
        string $scoringTeamId,
        string $concedingTeamId,
        Collection $scoringTeamPlayers,
        Collection $concedingTeamPlayers,
    ): Collection {
        $events = collect();
        $usedMinutes = [];

        for ($i = 0; $i < $goalCount; $i++) {
            $minute = $this->generateUniqueMinute($usedMinutes);
            $usedMinutes[] = $minute;

            // ~2% chance of own goal
            if ($this->percentChance(2) && $concedingTeamPlayers->isNotEmpty()) {
                // Own goal by a defender from the conceding team
                $ownGoalScorer = $this->pickPlayerByPosition($concedingTeamPlayers, [
                    'Centre-Back' => 40,
                    'Left-Back' => 20,
                    'Right-Back' => 20,
                    'Defensive Midfield' => 15,
                    'Goalkeeper' => 5,
                ]);

                if ($ownGoalScorer) {
                    $events->push(MatchEventData::ownGoal(
                        $concedingTeamId,
                        $ownGoalScorer->id,
                        $minute
                    ));
                    continue;
                }
            }

            // Regular goal
            $scorer = $this->pickPlayerByPosition($scoringTeamPlayers, self::SCORING_WEIGHTS);
            if (!$scorer) {
                continue;
            }

            $events->push(MatchEventData::goal($scoringTeamId, $scorer->id, $minute));

            // 60% chance of assist
            if ($this->percentChance(60)) {
                $assister = $this->pickPlayerByPosition(
                    $scoringTeamPlayers->reject(fn ($p) => $p->id === $scorer->id),
                    self::ASSIST_WEIGHTS
                );

                if ($assister) {
                    $events->push(MatchEventData::assist($scoringTeamId, $assister->id, $minute));
                }
            }
        }

        return $events;
    }

    /**
     * Generate card events for a team.
     *
     * @return Collection<MatchEventData>
     */
    private function generateCardEvents(string $teamId, Collection $players): Collection
    {
        $events = collect();

        // Average ~1.7 yellow cards per team per match (Poisson)
        $yellowCount = $this->poissonRandom(1.7);
        $usedMinutes = [];
        $playersWithYellow = collect();

        for ($i = 0; $i < $yellowCount; $i++) {
            $player = $this->pickPlayerByPosition($players, self::CARD_WEIGHTS);
            if (!$player) {
                continue;
            }

            $minute = $this->generateUniqueMinute($usedMinutes);
            $usedMinutes[] = $minute;

            // Check if this player already has a yellow in this match
            if ($playersWithYellow->contains($player->id)) {
                // Second yellow = red card
                $events->push(MatchEventData::redCard($teamId, $player->id, $minute, true));
                // Player "sent off" - remove from pool for further cards
                $players = $players->reject(fn ($p) => $p->id === $player->id);
            } else {
                $events->push(MatchEventData::yellowCard($teamId, $player->id, $minute));
                $playersWithYellow->push($player->id);
            }
        }

        // ~3% chance of direct red card per match (split between teams)
        if ($this->percentChance(1.5)) {
            $player = $this->pickPlayerByPosition($players, self::CARD_WEIGHTS);
            if ($player && !$playersWithYellow->contains($player->id)) {
                $minute = $this->generateUniqueMinute($usedMinutes);
                $events->push(MatchEventData::redCard($teamId, $player->id, $minute, false));
            }
        }

        return $events;
    }

    /**
     * Generate injury events (rare).
     *
     * @return Collection<MatchEventData>
     */
    private function generateInjuryEvents(string $teamId, Collection $players): Collection
    {
        $events = collect();

        // ~5% chance of injury per team per match
        if (!$this->percentChance(5)) {
            return $events;
        }

        // Pick a random player (weighted slightly toward physical players)
        $player = $this->pickPlayerByPosition($players, [
            'Centre-Forward' => 10,
            'Left Winger' => 10,
            'Right Winger' => 10,
            'Central Midfield' => 10,
            'Defensive Midfield' => 10,
            'Left-Back' => 8,
            'Right-Back' => 8,
            'Centre-Back' => 8,
            'Attacking Midfield' => 8,
            'Second Striker' => 8,
            'Left Midfield' => 8,
            'Right Midfield' => 8,
            'Goalkeeper' => 2,
        ]);

        if (!$player) {
            return $events;
        }

        // Pick random injury type
        $injuryTypes = array_keys(self::INJURY_TYPES);
        $injuryType = $injuryTypes[array_rand($injuryTypes)];
        $weeksRange = self::INJURY_TYPES[$injuryType];
        $weeksOut = rand($weeksRange[0], $weeksRange[1]);

        $minute = rand(1, 85);

        $events->push(MatchEventData::injury($teamId, $player->id, $minute, $injuryType, $weeksOut));

        return $events;
    }

    /**
     * Pick a player based on position weights and player quality.
     * Uses effective score (base ability × match performance) for weighting.
     */
    private function pickPlayerByPosition(Collection $players, array $weights): ?GamePlayer
    {
        if ($players->isEmpty()) {
            return null;
        }

        // Build weighted array with quality multiplier
        $weighted = [];
        foreach ($players as $player) {
            $positionWeight = $weights[$player->position] ?? 5;

            // Use effective score which includes match-day performance
            $effectiveScore = $this->getEffectiveScore($player);

            // Quality multiplier: players above 70 get bonus, below get penalty
            // Now includes the hidden performance modifier for randomness
            $qualityMultiplier = $effectiveScore / 70;
            $weight = (int) max(1, round($positionWeight * $qualityMultiplier));

            for ($i = 0; $i < $weight; $i++) {
                $weighted[] = $player;
            }
        }

        if (empty($weighted)) {
            return $players->random();
        }

        return $weighted[array_rand($weighted)];
    }

    /**
     * Generate a unique minute for an event.
     */
    private function generateUniqueMinute(array $usedMinutes): int
    {
        $attempts = 0;
        do {
            // Most events happen between 1-90, with some stoppage time
            $minute = rand(1, 93);
            $attempts++;
        } while (in_array($minute, $usedMinutes) && $attempts < 20);

        return $minute;
    }

    /**
     * Calculate team strength based on lineup player attributes.
     * Incorporates match-day performance modifiers for realistic variance.
     *
     * @param Collection<GamePlayer> $lineup
     */
    private function calculateTeamStrength(Collection $lineup): float
    {
        if ($lineup->count() < 11) {
            // Fallback for incomplete lineup - assume average team
            return 0.5;
        }

        // Calculate effective attributes with match performance modifier
        $totalStrength = 0;
        foreach ($lineup as $player) {
            $performance = $this->getMatchPerformance($player);

            // Apply performance modifier to each attribute
            // Technical ability is most affected by "form on the day"
            $effectiveTechnical = $player->technical_ability * $performance;
            // Physical attributes are more consistent
            $effectivePhysical = $player->physical_ability * (0.5 + $performance * 0.5);
            // Fitness and morale are not modified - they influence performance
            $fitness = $player->fitness;
            $morale = $player->morale;

            // Weighted contribution
            $playerStrength = ($effectiveTechnical * 0.40) +
                              ($effectivePhysical * 0.25) +
                              ($fitness * 0.20) +
                              ($morale * 0.15);

            $totalStrength += $playerStrength;
        }

        // Average across all players, normalized to 0-1 range
        return ($totalStrength / $lineup->count()) / 100;
    }

    /**
     * Generate a Poisson-distributed random number.
     */
    private function poissonRandom(float $lambda): int
    {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L);

        return max(0, $k - 1);
    }

    /**
     * Return true with given percentage chance.
     */
    private function percentChance(float $percent): bool
    {
        return (mt_rand() / mt_getrandmax() * 100) < $percent;
    }

    /**
     * Get or generate match performance modifier for a player.
     *
     * This creates a "hidden" form rating that introduces per-match randomness.
     * A player with high morale and fitness has a better chance of a good performance.
     *
     * Performance distribution (bell curve centered around 1.0):
     * - 0.70-0.85: Poor day (rare for high morale/fitness players)
     * - 0.85-0.95: Below average
     * - 0.95-1.05: Average
     * - 1.05-1.15: Above average
     * - 1.15-1.30: Outstanding day (rare)
     *
     * @return float Performance modifier (0.7 to 1.3)
     */
    private function getMatchPerformance(GamePlayer $player): float
    {
        // Return cached performance if already calculated this match
        if (isset($this->matchPerformance[$player->id])) {
            return $this->matchPerformance[$player->id];
        }

        // Base randomness using normal distribution (bell curve)
        // Box-Muller transform for normal distribution
        $u1 = max(0.0001, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();
        $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        // Standard deviation of 0.12 means:
        // ~68% of performances fall within ±0.12 of baseline
        // ~95% fall within ±0.24
        $stdDev = 0.12;
        $basePerformance = 1.0 + ($z * $stdDev);

        // Morale influences performance more than fitness
        // High morale (80+) slightly increases chance of good performance
        // Low morale (<50) increases chance of poor performance
        $moraleModifier = ($player->morale - 65) / 200; // Range: -0.075 to +0.175

        // Fitness affects consistency - low fitness increases variance
        $fitnessModifier = 0;
        if ($player->fitness < 70) {
            // Low fitness = more likely to have a poor game
            $fitnessModifier = ($player->fitness - 70) / 300; // Negative modifier
        }

        $performance = $basePerformance + $moraleModifier + $fitnessModifier;

        // Clamp to reasonable range
        $performance = max(0.70, min(1.30, $performance));

        // Cache for this match
        $this->matchPerformance[$player->id] = $performance;

        return $performance;
    }

    /**
     * Reset match performance cache (call before each new match simulation).
     */
    private function resetMatchPerformance(): void
    {
        $this->matchPerformance = [];
    }

    /**
     * Get the effective overall score for a player in this match.
     * Combines base ability with match-day performance.
     */
    private function getEffectiveScore(GamePlayer $player): float
    {
        $performance = $this->getMatchPerformance($player);

        return $player->overall_score * $performance;
    }

    /**
     * Get all match performance modifiers after simulation.
     * Useful for post-match player ratings display.
     *
     * @return array<string, float> Map of player ID to performance modifier (0.7-1.3)
     */
    public function getMatchPerformances(): array
    {
        return $this->matchPerformance;
    }

    /**
     * Convert match performance to a display rating (1-10 scale).
     * This can be used for post-match player ratings.
     *
     * @param float $performance The raw performance modifier (0.7-1.3)
     * @return float Rating on 1-10 scale
     */
    public static function performanceToRating(float $performance): float
    {
        // Map 0.7-1.3 to 4.0-9.5 scale (typical football rating range)
        // 0.7 -> 4.0 (very poor)
        // 1.0 -> 6.5 (average)
        // 1.3 -> 9.0 (outstanding)
        $rating = 4.0 + (($performance - 0.7) / 0.6) * 5.0;

        return round(max(1.0, min(10.0, $rating)), 1);
    }

    /**
     * Simulate extra time (30 minutes of play).
     * Lower expected goals than normal time.
     */
    public function simulateExtraTime(
        Team $homeTeam,
        Team $awayTeam,
        Collection $homePlayers,
        Collection $awayPlayers,
    ): MatchResult {
        $events = collect();

        // Lower expected goals for 30 min extra time (roughly 1/3 of normal match)
        $homeStrength = $this->calculateTeamStrength($homePlayers);
        $awayStrength = $this->calculateTeamStrength($awayPlayers);
        $totalStrength = $homeStrength + $awayStrength;

        // Much lower expected goals - extra time is usually cagey
        $homeExpectedGoals = 0.3 + ($homeStrength / $totalStrength) * 0.3;
        $awayExpectedGoals = 0.2 + ($awayStrength / $totalStrength) * 0.25;

        $homeScore = $this->poissonRandom($homeExpectedGoals);
        $awayScore = $this->poissonRandom($awayExpectedGoals);

        // Generate goal events only if we have player data (ET is 91'-120')
        if ($homePlayers->isNotEmpty() && $awayPlayers->isNotEmpty()) {
            for ($i = 0; $i < $homeScore; $i++) {
                $minute = rand(91, 120);
                $scorer = $this->pickPlayerByPosition($homePlayers, self::SCORING_WEIGHTS);
                if ($scorer) {
                    $events->push(MatchEventData::goal($homeTeam->id, $scorer->id, $minute));
                }
            }

            for ($i = 0; $i < $awayScore; $i++) {
                $minute = rand(91, 120);
                $scorer = $this->pickPlayerByPosition($awayPlayers, self::SCORING_WEIGHTS);
                if ($scorer) {
                    $events->push(MatchEventData::goal($awayTeam->id, $scorer->id, $minute));
                }
            }

            $events = $events->sortBy('minute')->values();
        }

        return new MatchResult($homeScore, $awayScore, $events);
    }

    /**
     * Simulate a penalty shootout.
     * Standard 5 penalties each, then sudden death if tied.
     *
     * @return array{0: int, 1: int} [home_score, away_score]
     */
    public function simulatePenalties(Collection $homePlayers, Collection $awayPlayers): array
    {
        $homeScore = 0;
        $awayScore = 0;
        $round = 1;
        $maxRounds = 5;

        // First 5 penalties each
        for ($i = 0; $i < $maxRounds; $i++) {
            // Home takes
            if ($this->penaltyScored()) {
                $homeScore++;
            }

            // Away takes
            if ($this->penaltyScored()) {
                $awayScore++;
            }

            // Check if one team has mathematically won
            $remainingRounds = $maxRounds - $round;
            if ($homeScore > $awayScore + $remainingRounds) {
                break; // Home has won
            }
            if ($awayScore > $homeScore + $remainingRounds) {
                break; // Away has won
            }

            $round++;
        }

        // Sudden death if still tied after 5
        while ($homeScore === $awayScore) {
            $homeScored = $this->penaltyScored();
            $awayScored = $this->penaltyScored();

            if ($homeScored) $homeScore++;
            if ($awayScored) $awayScore++;

            // In sudden death, if both miss or both score, continue
            // If only one scores, they win
            if ($homeScored !== $awayScored) {
                break;
            }
        }

        return [$homeScore, $awayScore];
    }

    /**
     * Determine if a penalty is scored.
     * Roughly 75-80% success rate.
     */
    private function penaltyScored(): bool
    {
        return $this->percentChance(77);
    }
}
