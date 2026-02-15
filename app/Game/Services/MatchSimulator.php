<?php

namespace App\Game\Services;

use App\Game\DTO\MatchEventData;
use App\Game\DTO\MatchResult;
use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use Illuminate\Support\Collection;

class MatchSimulator
{
    public function __construct(
        private readonly InjuryService $injuryService = new InjuryService,
    ) {}

    /**
     * Match performance cache - stores per-player performance modifiers for the current match.
     * Each player gets a random "form on the day" that affects their contribution.
     * Range: 0.7 to 1.3 (30% variance from their base ability)
     *
     * @var array<string, float>
     */
    private array $matchPerformance = [];

    // Position weights for goal scoring (used by pickGoalScorer with dampened quality)
    private const GOAL_SCORING_WEIGHTS = [
        'Centre-Forward' => 25,
        'Second Striker' => 22,
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

    /**
     * Simulate a match result between two teams.
     *
     * @param  Collection<GamePlayer>  $homePlayers  Players for home team (lineup)
     * @param  Collection<GamePlayer>  $awayPlayers  Players for away team (lineup)
     * @param  Formation|null  $homeFormation  Formation for home team
     * @param  Formation|null  $awayFormation  Formation for away team
     * @param  Mentality|null  $homeMentality  Mentality for home team
     * @param  Mentality|null  $awayMentality  Mentality for away team
     * @param  Game|null  $game  Optional game for medical tier effects on injuries
     */
    public function simulate(
        Team $homeTeam,
        Team $awayTeam,
        Collection $homePlayers,
        Collection $awayPlayers,
        ?Formation $homeFormation = null,
        ?Formation $awayFormation = null,
        ?Mentality $homeMentality = null,
        ?Mentality $awayMentality = null,
        ?Game $game = null,
    ): MatchResult {
        return $this->simulateRemainder(
            $homeTeam, $awayTeam,
            $homePlayers, $awayPlayers,
            $homeFormation, $awayFormation,
            $homeMentality, $awayMentality,
            fromMinute: 0,
            game: $game,
        );
    }

    /**
     * Reassign goal/assist events from players who were removed from the match
     * (via injury or red card) to available teammates.
     *
     * Since scores are determined before events are generated, this only changes
     * WHO scored, not HOW MANY goals were scored.
     *
     * @return Collection<MatchEventData>
     */
    private function reassignEventsFromUnavailablePlayers(
        Collection $events,
        Collection $homePlayers,
        Collection $awayPlayers,
    ): Collection {
        // Build map of player_id => minute they were removed
        $removedAt = [];
        foreach ($events as $event) {
            if (in_array($event->type, ['injury', 'red_card']) && ! isset($removedAt[$event->gamePlayerId])) {
                $removedAt[$event->gamePlayerId] = $event->minute;
            }
        }

        if (empty($removedAt)) {
            return $events;
        }

        return $events->map(function (MatchEventData $event) use ($removedAt, $homePlayers, $awayPlayers) {
            if (! in_array($event->type, ['goal', 'assist'])) {
                return $event;
            }

            if (! isset($removedAt[$event->gamePlayerId])) {
                return $event;
            }

            if ($event->minute < $removedAt[$event->gamePlayerId]) {
                return $event;
            }

            // Find the team's players and exclude anyone removed at or before this minute
            $teamPlayers = $homePlayers->contains('id', $event->gamePlayerId)
                ? $homePlayers
                : $awayPlayers;

            $availablePlayers = $teamPlayers->reject(function ($p) use ($removedAt, $event) {
                return isset($removedAt[$p->id]) && $removedAt[$p->id] <= $event->minute;
            });

            $replacement = $event->type === 'goal'
                ? $this->pickGoalScorer($availablePlayers)
                : $this->pickPlayerByPosition($availablePlayers, self::ASSIST_WEIGHTS);

            if (! $replacement) {
                return $event;
            }

            return $event->type === 'goal'
                ? MatchEventData::goal($event->teamId, $replacement->id, $event->minute)
                : MatchEventData::assist($event->teamId, $replacement->id, $event->minute);
        });
    }

    /**
     * Pick a player based on position weights and player quality.
     * Uses effective score (base ability × match performance) for weighting.
     *
     * Players with position weight of 0 are excluded entirely (e.g., goalkeepers can't score).
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

            // Skip players with zero position weight (e.g., goalkeepers for scoring)
            if ($positionWeight === 0) {
                continue;
            }

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
     * Pick a goal scorer with dampened quality weighting and diminishing returns.
     *
     * Differs from pickPlayerByPosition() in two ways:
     * 1. Uses sqrt-dampened quality multiplier (pow(score/70, 0.5)) instead of linear,
     *    reducing the advantage of high-rated players from 29% to 13%.
     * 2. Halves weight for each prior goal in the same match, making hat-tricks rare.
     *
     * @param  array<string, int>  $goalCounts  Map of player ID to goals scored so far this match
     */
    private function pickGoalScorer(Collection $players, array $goalCounts = []): ?GamePlayer
    {
        if ($players->isEmpty()) {
            return null;
        }

        $weighted = [];
        foreach ($players as $player) {
            $positionWeight = self::GOAL_SCORING_WEIGHTS[$player->position] ?? 5;

            if ($positionWeight === 0) {
                continue;
            }

            $effectiveScore = $this->getEffectiveScore($player);

            // Dampened quality multiplier: sqrt reduces the gap between high and low rated players
            $qualityMultiplier = pow($effectiveScore / 70, 0.5);

            $weight = $positionWeight * $qualityMultiplier;

            // Diminishing returns: halve weight for each prior goal in this match
            $priorGoals = $goalCounts[$player->id] ?? 0;
            if ($priorGoals > 0) {
                $weight /= pow(2, $priorGoals);
            }

            $weight = (int) max(1, round($weight));

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
     * Calculate team strength based on lineup player attributes.
     * Incorporates match-day performance modifiers for realistic variance.
     *
     * @param  Collection<GamePlayer>  $lineup
     */
    private function calculateTeamStrength(Collection $lineup): float
    {
        if ($lineup->count() < 11) {
            // Fallback for incomplete lineup - reflects amateur/semi-pro level
            return 0.30;
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
     * Calculate striker quality bonus for expected goals.
     *
     * Elite forwards (90+) provide a significant boost to their team's
     * expected goals, reflecting their ability to create chances from nothing.
     *
     * @param  Collection<GamePlayer>  $lineup
     * @return float Bonus expected goals (0.0 to ~0.5)
     */
    private function calculateStrikerBonus(Collection $lineup): float
    {
        $forwardPositions = ['Centre-Forward', 'Second Striker', 'Left Winger', 'Right Winger'];

        // Get the best forward in the lineup (using effective score for match-day variance)
        $bestForwardScore = 0;
        foreach ($lineup as $player) {
            if (in_array($player->position, $forwardPositions)) {
                $effectiveScore = $this->getEffectiveScore($player);
                $bestForwardScore = max($bestForwardScore, $effectiveScore);
            }
        }

        // No bonus if no forwards or if best forward is below 85
        if ($bestForwardScore < 85) {
            return 0.0;
        }

        // Bonus scales from 0 at 85 to ~0.25 at 100
        // Formula: (rating - 85) / 60 gives 0.0 to 0.25 range
        // Only truly elite forwards provide a noticeable boost
        // A 94-rated Mbappé gets +0.15 expected goals
        // A 88-rated striker gets +0.05 expected goals
        return ($bestForwardScore - 85) / 60;
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

        // Standard deviation controls randomness (configurable)
        // ~68% of performances fall within ±stdDev of baseline
        // ~95% fall within ±2*stdDev
        $stdDev = config('match_simulation.performance_std_dev', 0.12);
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

        // Clamp to configurable range
        $minPerf = config('match_simulation.performance_min', 0.70);
        $maxPerf = config('match_simulation.performance_max', 1.30);
        $performance = max($minPerf, min($maxPerf, $performance));

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
     * @param  float  $performance  The raw performance modifier (0.7-1.3)
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
     * Simulate the remainder of a match from a given minute.
     * Used when a substitution changes the lineup mid-match.
     * Only generates events for the period [fromMinute+1, 93].
     */
    public function simulateRemainder(
        Team $homeTeam,
        Team $awayTeam,
        Collection $homePlayers,
        Collection $awayPlayers,
        ?Formation $homeFormation = null,
        ?Formation $awayFormation = null,
        ?Mentality $homeMentality = null,
        ?Mentality $awayMentality = null,
        int $fromMinute = 45,
        ?Game $game = null,
        array $existingInjuryTeamIds = [],
        array $existingYellowPlayerIds = [],
    ): MatchResult {
        $this->resetMatchPerformance();

        $homeFormation = $homeFormation ?? Formation::F_4_4_2;
        $awayFormation = $awayFormation ?? Formation::F_4_4_2;
        $homeMentality = $homeMentality ?? Mentality::BALANCED;
        $awayMentality = $awayMentality ?? Mentality::BALANCED;

        // Scale everything by the fraction of match remaining
        $matchFraction = max(0, (93 - $fromMinute)) / 93;

        $events = collect();

        $homeStrength = $this->calculateTeamStrength($homePlayers);
        $awayStrength = $this->calculateTeamStrength($awayPlayers);

        $strengthExponent = config('match_simulation.strength_exponent', 1.0);
        $homeStrength = pow($homeStrength, $strengthExponent);
        $awayStrength = pow($awayStrength, $strengthExponent);

        $totalStrength = $homeStrength + $awayStrength;

        $baseHomeGoals = config('match_simulation.base_home_goals', 1.4);
        $baseAwayGoals = config('match_simulation.base_away_goals', 0.9);
        $strengthMultiplier = config('match_simulation.strength_multiplier', 1.0);
        $homeAdvantageGoals = config('match_simulation.home_advantage_goals', 0.0);
        $awayDisadvantage = config('match_simulation.away_disadvantage_multiplier', 0.8);

        $homeShare = $homeStrength / $totalStrength;
        $awayShare = $awayStrength / $totalStrength;
        $scaledBaseHome = $baseHomeGoals * ($homeShare * 2);
        $scaledBaseAway = $baseAwayGoals * ($awayShare * 2);

        $homeExpectedGoals = ($scaledBaseHome + $homeAdvantageGoals + $homeShare * $strengthMultiplier)
            * $homeFormation->attackModifier()
            * $awayFormation->defenseModifier()
            * $homeMentality->ownGoalsModifier()
            * $awayMentality->opponentGoalsModifier()
            * $matchFraction;

        $awayExpectedGoals = ($scaledBaseAway + $awayShare * $strengthMultiplier * $awayDisadvantage)
            * $awayFormation->attackModifier()
            * $homeFormation->defenseModifier()
            * $awayMentality->ownGoalsModifier()
            * $homeMentality->opponentGoalsModifier()
            * $matchFraction;

        $homeStrikerBonus = $this->calculateStrikerBonus($homePlayers) * $matchFraction;
        $awayStrikerBonus = $this->calculateStrikerBonus($awayPlayers) * $matchFraction;
        $homeExpectedGoals += $homeStrikerBonus;
        $awayExpectedGoals += $awayStrikerBonus;

        $homeScore = $this->poissonRandom($homeExpectedGoals);
        $awayScore = $this->poissonRandom($awayExpectedGoals);

        $maxGoalsCap = config('match_simulation.max_goals_cap', 0);
        if ($maxGoalsCap > 0) {
            $homeScore = min($homeScore, $maxGoalsCap);
            $awayScore = min($awayScore, $maxGoalsCap);
        }

        if ($homePlayers->isNotEmpty() && $awayPlayers->isNotEmpty()) {
            $homeGoalEvents = $this->generateGoalEventsInRange(
                $homeScore, $homeTeam->id, $awayTeam->id,
                $homePlayers, $awayPlayers, $fromMinute + 1, 93
            );

            $awayGoalEvents = $this->generateGoalEventsInRange(
                $awayScore, $awayTeam->id, $homeTeam->id,
                $awayPlayers, $homePlayers, $fromMinute + 1, 93
            );

            $events = $events->merge($homeGoalEvents)->merge($awayGoalEvents);

            $goalDifference = $homeScore - $awayScore;
            $homeCardEvents = $this->generateCardEventsInRange($homeTeam->id, $homePlayers, -$goalDifference, $fromMinute + 1, 93, $matchFraction, $existingYellowPlayerIds);
            $awayCardEvents = $this->generateCardEventsInRange($awayTeam->id, $awayPlayers, $goalDifference, $fromMinute + 1, 93, $matchFraction, $existingYellowPlayerIds);
            $events = $events->merge($homeCardEvents)->merge($awayCardEvents);

            if (! in_array($homeTeam->id, $existingInjuryTeamIds)) {
                $homeInjuryEvents = $this->generateInjuryEventsInRange($homeTeam->id, $homePlayers, $fromMinute + 1, 93, $game);
                $events = $events->merge($homeInjuryEvents);
            }
            if (! in_array($awayTeam->id, $existingInjuryTeamIds)) {
                $awayInjuryEvents = $this->generateInjuryEventsInRange($awayTeam->id, $awayPlayers, $fromMinute + 1, 93, $game);
                $events = $events->merge($awayInjuryEvents);
            }

            $events = $events->sortBy('minute')->values();

            $events = $this->reassignEventsFromUnavailablePlayers(
                $events, $homePlayers, $awayPlayers
            );
        }

        return new MatchResult($homeScore, $awayScore, $events);
    }

    /**
     * Generate goal events with minutes constrained to a range.
     */
    private function generateGoalEventsInRange(
        int $goalCount,
        string $scoringTeamId,
        string $concedingTeamId,
        Collection $scoringTeamPlayers,
        Collection $concedingTeamPlayers,
        int $minMinute,
        int $maxMinute,
    ): Collection {
        $events = collect();
        $usedMinutes = [];
        $goalCounts = [];

        for ($i = 0; $i < $goalCount; $i++) {
            $minute = $this->generateUniqueMinuteInRange($usedMinutes, $minMinute, $maxMinute);
            $usedMinutes[] = $minute;

            $ownGoalChance = config('match_simulation.own_goal_chance', 2.0);
            if ($this->percentChance($ownGoalChance) && $concedingTeamPlayers->isNotEmpty()) {
                $ownGoalScorer = $this->pickPlayerByPosition($concedingTeamPlayers, [
                    'Centre-Back' => 40,
                    'Left-Back' => 20,
                    'Right-Back' => 20,
                    'Defensive Midfield' => 15,
                    'Goalkeeper' => 5,
                ]);

                if ($ownGoalScorer) {
                    $events->push(MatchEventData::ownGoal($concedingTeamId, $ownGoalScorer->id, $minute));
                    continue;
                }
            }

            $scorer = $this->pickGoalScorer($scoringTeamPlayers, $goalCounts);
            if (! $scorer) {
                continue;
            }

            $events->push(MatchEventData::goal($scoringTeamId, $scorer->id, $minute));
            $goalCounts[$scorer->id] = ($goalCounts[$scorer->id] ?? 0) + 1;

            $assistChance = config('match_simulation.assist_chance', 60.0);
            if ($this->percentChance($assistChance)) {
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
     * Generate card events with minutes constrained to a range.
     */
    private function generateCardEventsInRange(
        string $teamId,
        Collection $players,
        int $goalDifference,
        int $minMinute,
        int $maxMinute,
        float $matchFraction,
        array $existingYellowPlayerIds = [],
    ): Collection {
        $events = collect();

        $baseYellowCards = config('match_simulation.yellow_cards_per_team', 1.5);

        // Scale by match fraction
        $yellowCardsPerTeam = max(0.1, $baseYellowCards * $matchFraction);
        $yellowCount = $this->poissonRandom($yellowCardsPerTeam);

        $usedMinutes = [];
        // Seed with players who already have a yellow earlier in this match
        $playersWithYellow = collect();
        foreach ($existingYellowPlayerIds as $playerId) {
            $playersWithYellow->put($playerId, $minMinute - 1);
        }

        for ($i = 0; $i < $yellowCount; $i++) {
            $player = $this->pickPlayerByPosition($players, self::CARD_WEIGHTS);
            if (! $player) {
                continue;
            }

            if ($playersWithYellow->has($player->id)) {
                $firstYellowMinute = (int) $playersWithYellow->get($player->id);
                $minute = $this->generateUniqueMinuteInRange($usedMinutes, max($minMinute, $firstYellowMinute + 1), $maxMinute);
                $usedMinutes[] = $minute;
                $events->push(MatchEventData::redCard($teamId, $player->id, $minute, true));
                $players = $players->reject(fn ($p) => $p->id === $player->id);
            } else {
                $minute = $this->generateUniqueMinuteInRange($usedMinutes, $minMinute, $maxMinute);
                $usedMinutes[] = $minute;
                $events->push(MatchEventData::yellowCard($teamId, $player->id, $minute));
                $playersWithYellow->put($player->id, $minute);
            }
        }

        $baseRedChance = config('match_simulation.direct_red_chance', 1.5);
        $redChanceModifier = $goalDifference < 0 ? abs($goalDifference) * 0.5 : 0;
        $directRedChance = ($baseRedChance + $redChanceModifier) * $matchFraction;

        if ($this->percentChance($directRedChance)) {
            $player = $this->pickPlayerByPosition($players, self::CARD_WEIGHTS);
            if ($player && ! $playersWithYellow->has($player->id)) {
                $minute = $this->generateUniqueMinuteInRange($usedMinutes, $minMinute, $maxMinute);
                $events->push(MatchEventData::redCard($teamId, $player->id, $minute, false));
                $players = $players->reject(fn ($p) => $p->id === $player->id);
            }
        }

        return $events;
    }

    /**
     * Generate injury events with minutes constrained to a range.
     */
    private function generateInjuryEventsInRange(
        string $teamId,
        Collection $players,
        int $minMinute,
        int $maxMinute,
        ?Game $game = null,
    ): Collection {
        $events = collect();

        foreach ($players as $player) {
            if ($this->injuryService->rollForInjury($player, null, null, $game)) {
                $injury = $this->injuryService->generateInjury($player, $game);

                $minute = rand($minMinute, $maxMinute);
                $events->push(MatchEventData::injury(
                    $teamId,
                    $player->id,
                    $minute,
                    $injury['type'],
                    $injury['weeks'],
                ));

                break;
            }
        }

        return $events;
    }

    /**
     * Generate a unique minute within a specific range.
     */
    private function generateUniqueMinuteInRange(array $usedMinutes, int $minMinute, int $maxMinute): int
    {
        $minMinute = max(1, min($minMinute, $maxMinute));
        $maxMinute = max($minMinute, $maxMinute);

        $attempts = 0;
        do {
            $minute = rand($minMinute, $maxMinute);
            $attempts++;
        } while (in_array($minute, $usedMinutes) && $attempts < 20);

        return $minute;
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
                $scorer = $this->pickPlayerByPosition($homePlayers, self::GOAL_SCORING_WEIGHTS);
                if ($scorer) {
                    $events->push(MatchEventData::goal($homeTeam->id, $scorer->id, $minute));
                }
            }

            for ($i = 0; $i < $awayScore; $i++) {
                $minute = rand(91, 120);
                $scorer = $this->pickPlayerByPosition($awayPlayers, self::GOAL_SCORING_WEIGHTS);
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

            if ($homeScored) {
                $homeScore++;
            }
            if ($awayScored) {
                $awayScore++;
            }

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
