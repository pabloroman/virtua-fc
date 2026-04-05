<?php

namespace App\Modules\Match\Services;

use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Game;
use App\Modules\Match\DTOs\MatchEventData;
use Illuminate\Support\Collection;

/**
 * Lightweight statistical match resolver for AI-vs-AI matches.
 *
 * Replaces the full MatchSimulator pipeline (lineup generation, energy curves,
 * substitution windows, minute-by-minute event placement) with a fast
 * Poisson/Dixon-Coles result generator that produces the same MatchResult
 * data structure.
 *
 * Key differences from MatchSimulator:
 * - No lineup generation or formation recommendation
 * - No energy model or AI substitutions
 * - No minute-by-minute simulation periods
 * - Uses pre-computed team strength from lightweight player query
 * - Rotation handled via simple fitness-threshold swaps
 */
class AIMatchResolver
{
    private const DIXON_COLES_MAX_GOALS = 8;

    private const FACTORIALS = [1, 1, 2, 6, 24, 120, 720, 5040, 40320];

    // Position weights for goal scoring (matches MatchSimulator)
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
     * Resolve a batch of AI-vs-AI matches statistically.
     *
     * @param  Collection<GameMatch>  $matches
     * @param  Collection  $allPlayers  Players grouped by team_id
     * @param  Game  $game
     * @param  array<string, array<string>>  $suspendedByCompetition  [competitionId => [playerId, ...]]
     * @return array  Match results in the same format as MatchdayOrchestrator::simulateMatch()
     */
    public function resolveMatches(Collection $matches, $allPlayers, Game $game, array $suspendedByCompetition = []): array
    {
        $results = [];

        foreach ($matches as $match) {
            $results[] = $this->resolveMatch($match, $allPlayers, $game, $suspendedByCompetition);
        }

        return $results;
    }

    /**
     * Resolve a single AI-vs-AI match using statistical generation.
     */
    private function resolveMatch(GameMatch $match, $allPlayers, Game $game, array $suspendedByCompetition): array
    {
        $suspendedIds = $suspendedByCompetition[$match->competition_id] ?? [];

        $homeTeamPlayers = $allPlayers->get($match->home_team_id, collect())
            ->reject(fn ($p) => in_array($p->id, $suspendedIds));
        $awayTeamPlayers = $allPlayers->get($match->away_team_id, collect())
            ->reject(fn ($p) => in_array($p->id, $suspendedIds));

        // Select rotated XI per team (lightweight: sort by effective score, take best 11)
        $homeXI = $this->selectRotatedXI($homeTeamPlayers, $game);
        $awayXI = $this->selectRotatedXI($awayTeamPlayers, $game);

        // Store lineups on the match (same contract as LineupService)
        $match->home_lineup = $homeXI->pluck('id')->values()->all();
        $match->away_lineup = $awayXI->pluck('id')->values()->all();
        $match->home_formation = '4-3-3';
        $match->away_formation = '4-3-3';
        $match->home_mentality = 'balanced';
        $match->away_mentality = 'balanced';

        if ($match->isDirty()) {
            $match->save();
        }

        // Calculate team strengths from the selected XI
        $homeStrength = $this->calculateTeamStrength($homeXI);
        $awayStrength = $this->calculateTeamStrength($awayXI);

        // Generate scoreline
        $neutralVenue = $match->competition_id === 'WC2026';
        [$homeXG, $awayXG] = $this->calculateExpectedGoals($homeStrength, $awayStrength, $neutralVenue);
        [$homeScore, $awayScore] = $this->dixonColesRandom($homeXG, $awayXG);

        // Cap goals
        $maxGoals = (int) config('match_simulation.max_goals_cap', 6);
        if ($maxGoals > 0) {
            $homeScore = min($homeScore, $maxGoals);
            $awayScore = min($awayScore, $maxGoals);
        }

        // Generate match events (goals, assists, cards, injuries)
        $events = $this->generateEvents(
            $homeXI, $awayXI,
            $match->home_team_id, $match->away_team_id,
            $homeScore, $awayScore,
            $game,
        );

        // Calculate possession (simplified)
        $strengthRatio = $awayStrength > 0 ? $homeStrength / $awayStrength : 1.0;
        $homeRawPoss = 50 + min(5, max(-5, ($strengthRatio - 1.0) * 20));
        $noise = (crc32($match->id) % 7) - 3; // deterministic ±3
        $homePossession = (int) max(30, min(70, round($homeRawPoss + $noise)));
        $awayPossession = 100 - $homePossession;

        // Pick MVP (highest overall from winning team, or random from draw)
        $mvpPlayerId = $this->pickMvp($homeXI, $awayXI, $homeScore, $awayScore);

        return [
            'matchId' => $match->id,
            'homeTeamId' => $match->home_team_id,
            'awayTeamId' => $match->away_team_id,
            'homeScore' => $homeScore,
            'awayScore' => $awayScore,
            'homePossession' => $homePossession,
            'awayPossession' => $awayPossession,
            'competitionId' => $match->competition_id,
            'mvpPlayerId' => $mvpPlayerId,
            'events' => $events,
        ];
    }

    /**
     * Select a rotated starting XI from the squad.
     *
     * Picks the best 11 by effective score (overall_score with fitness penalty),
     * respecting position groups: 1 GK, defenders, midfielders, forwards.
     * Players below the fitness rotation threshold get penalized so fresher
     * alternatives rotate in naturally.
     */
    private function selectRotatedXI(Collection $teamPlayers, Game $game): Collection
    {
        $available = $teamPlayers
            ->reject(fn ($p) => $p->isInjured($game->current_date))
            ->values();

        if ($available->count() <= 11) {
            return $available;
        }

        $threshold = (int) config('player.condition.ai_rotation_threshold', 80);

        $effectiveScore = function (GamePlayer $p) use ($threshold): float {
            $score = (float) $p->overall_score;
            if ($p->fitness < $threshold) {
                $score *= 0.80 + ($p->fitness / $threshold) * 0.20;
            }

            return $score;
        };

        $grouped = $available->groupBy('position_group');
        $selected = collect();

        // 4-3-3 requirements
        $requirements = [
            'Goalkeeper' => 1,
            'Defender' => 4,
            'Midfielder' => 3,
            'Forward' => 3,
        ];

        foreach ($requirements as $group => $count) {
            $positionPlayers = ($grouped->get($group) ?? collect())
                ->sortByDesc($effectiveScore)
                ->take($count);
            $selected = $selected->merge($positionPlayers);
        }

        // Fill remaining spots if we don't have enough in some position group
        if ($selected->count() < 11) {
            $selectedIds = $selected->pluck('id')->toArray();
            $remaining = $available
                ->filter(fn ($p) => ! in_array($p->id, $selectedIds))
                ->sortByDesc($effectiveScore);

            foreach ($remaining as $player) {
                if ($selected->count() >= 11) {
                    break;
                }
                $selected->push($player);
            }
        }

        return $selected->values();
    }

    /**
     * Calculate team strength from a selected XI.
     *
     * Simplified version of MatchSimulator::calculateTeamStrength():
     * - No energy model (no minute-by-minute drain)
     * - No per-player match performance variance (averages out over a season)
     * - Same weight formula: tech*0.55 + phys*0.35 + fitness*0.05 + morale*0.05
     */
    private function calculateTeamStrength(Collection $lineup): float
    {
        if ($lineup->count() < 7) {
            return 0.30;
        }

        $totalStrength = 0;
        foreach ($lineup as $player) {
            $playerStrength = ($player->technical_ability * 0.55) +
                              ($player->physical_ability * 0.35) +
                              ($player->fitness * 0.05) +
                              ($player->morale * 0.05);
            $totalStrength += $playerStrength;
        }

        return ($totalStrength / 11) / 100;
    }

    /**
     * Calculate expected goals using the same ratio-based formula as MatchSimulator.
     */
    private function calculateExpectedGoals(float $homeStrength, float $awayStrength, bool $neutralVenue): array
    {
        $baseGoals = (float) config('match_simulation.base_goals', 1.2);
        $skillDominance = (float) config('match_simulation.skill_dominance', 2.3);
        $homeAdvantage = $neutralVenue ? 0.0 : (float) config('match_simulation.home_advantage_goals', 0.20);

        if ($awayStrength <= 0) {
            return [$baseGoals + $homeAdvantage, $baseGoals * 0.5];
        }

        $strengthRatio = $homeStrength / $awayStrength;
        $homeXG = pow($strengthRatio, $skillDominance) * $baseGoals + $homeAdvantage;
        $awayXG = pow(1.0 / $strengthRatio, $skillDominance) * $baseGoals;

        return [$homeXG, $awayXG];
    }

    /**
     * Dixon-Coles correlated Poisson sampling (identical to MatchSimulator).
     */
    private function dixonColesRandom(float $homeXG, float $awayXG): array
    {
        $rho = (float) config('match_simulation.dixon_coles_rho', -0.13);
        $concentration = (float) config('match_simulation.score_concentration', 1.0);

        $probabilities = [];

        for ($i = 0; $i <= self::DIXON_COLES_MAX_GOALS; $i++) {
            $pHome = $this->poissonPmf($i, $homeXG);
            for ($j = 0; $j <= self::DIXON_COLES_MAX_GOALS; $j++) {
                $pAway = $this->poissonPmf($j, $awayXG);
                $tau = $this->dixonColesTau($i, $j, $homeXG, $awayXG, $rho);
                $probabilities[] = [$i, $j, $pHome * $pAway * $tau];
            }
        }

        if ($concentration !== 1.0) {
            foreach ($probabilities as &$entry) {
                $entry[2] = $entry[2] ** $concentration;
            }
            unset($entry);
        }

        $cumulative = 0.0;
        foreach ($probabilities as &$entry) {
            $cumulative += $entry[2];
            $entry[2] = $cumulative;
        }
        unset($entry);

        $rand = (mt_rand() / mt_getrandmax()) * $cumulative;

        foreach ($probabilities as [$home, $away, $cum]) {
            if ($rand <= $cum) {
                return [$home, $away];
            }
        }

        return [0, 0];
    }

    private function poissonPmf(int $k, float $lambda): float
    {
        if ($lambda <= 0) {
            return $k === 0 ? 1.0 : 0.0;
        }

        return exp(-$lambda) * pow($lambda, $k) / self::FACTORIALS[$k];
    }

    private function dixonColesTau(int $homeGoals, int $awayGoals, float $homeXG, float $awayXG, float $rho): float
    {
        if ($homeGoals === 0 && $awayGoals === 0) {
            return 1.0 - $homeXG * $awayXG * $rho;
        }
        if ($homeGoals === 1 && $awayGoals === 0) {
            return 1.0 + $awayXG * $rho;
        }
        if ($homeGoals === 0 && $awayGoals === 1) {
            return 1.0 + $homeXG * $rho;
        }
        if ($homeGoals === 1 && $awayGoals === 1) {
            return 1.0 - $rho;
        }

        return 1.0;
    }

    /**
     * Generate match events: goals (with assists), yellow cards, red cards, injuries.
     * No substitution events — AI-vs-AI matches skip substitutions entirely.
     */
    private function generateEvents(
        Collection $homeXI,
        Collection $awayXI,
        string $homeTeamId,
        string $awayTeamId,
        int $homeScore,
        int $awayScore,
        Game $game,
    ): array {
        $events = [];

        // Generate goals and assists
        $this->generateGoalEvents($events, $homeXI, $homeTeamId, $awayTeamId, $homeScore);
        $this->generateGoalEvents($events, $awayXI, $awayTeamId, $homeTeamId, $awayScore);

        // Generate yellow cards
        $this->generateCardEvents($events, $homeXI, $homeTeamId);
        $this->generateCardEvents($events, $awayXI, $awayTeamId);

        // Generate injuries
        $this->generateInjuryEvents($events, $homeXI, $homeTeamId, $game);
        $this->generateInjuryEvents($events, $awayXI, $awayTeamId, $game);

        return $events;
    }

    private function generateGoalEvents(array &$events, Collection $lineup, string $teamId, string $opponentTeamId, int $goals): void
    {
        $ownGoalChance = (float) config('match_simulation.own_goal_chance', 1.0);
        $assistChance = (float) config('match_simulation.assist_chance', 60.0);
        $goalCounts = [];

        for ($i = 0; $i < $goals; $i++) {
            $minute = mt_rand(1, 90);

            // Own goal check (scored by opponent defender)
            if (mt_rand(1, 1000) <= $ownGoalChance * 10) {
                // Own goals don't have a player picker in the opponent lineup for simplicity;
                // we skip own goals in AI-vs-AI resolver to keep event assignment clean.
                // The goal is simply assigned to the scoring team's forward instead.
            }

            $scorer = $this->pickWeightedPlayer($lineup, self::GOAL_SCORING_WEIGHTS, $goalCounts);
            if (! $scorer) {
                continue;
            }

            $goalCounts[$scorer->id] = ($goalCounts[$scorer->id] ?? 0) + 1;

            $events[] = [
                'team_id' => $teamId,
                'game_player_id' => $scorer->id,
                'minute' => $minute,
                'event_type' => 'goal',
                'metadata' => null,
            ];

            // Assist
            if (mt_rand(1, 1000) <= $assistChance * 10) {
                $assister = $this->pickWeightedPlayer(
                    $lineup->filter(fn ($p) => $p->id !== $scorer->id),
                    self::ASSIST_WEIGHTS,
                );
                if ($assister) {
                    $events[] = [
                        'team_id' => $teamId,
                        'game_player_id' => $assister->id,
                        'minute' => $minute,
                        'event_type' => 'assist',
                        'metadata' => null,
                    ];
                }
            }
        }
    }

    private function generateCardEvents(array &$events, Collection $lineup, string $teamId): void
    {
        $avgYellows = (float) config('match_simulation.yellow_cards_per_team', 1.4);
        $directRedChance = (float) config('match_simulation.direct_red_chance', 0.5);

        // Poisson-sample number of yellows
        $yellowCount = $this->poissonRandom($avgYellows);

        $cardedPlayerIds = [];
        for ($i = 0; $i < $yellowCount; $i++) {
            $player = $this->pickWeightedPlayer(
                $lineup->filter(fn ($p) => ! isset($cardedPlayerIds[$p->id])),
                self::CARD_WEIGHTS,
            );
            if (! $player) {
                break;
            }

            $cardedPlayerIds[$player->id] = true;
            $events[] = [
                'team_id' => $teamId,
                'game_player_id' => $player->id,
                'minute' => mt_rand(5, 90),
                'event_type' => 'yellow_card',
                'metadata' => null,
            ];
        }

        // Direct red card
        if (mt_rand(1, 1000) <= $directRedChance * 10) {
            $player = $this->pickWeightedPlayer($lineup, self::CARD_WEIGHTS);
            if ($player) {
                $events[] = [
                    'team_id' => $teamId,
                    'game_player_id' => $player->id,
                    'minute' => mt_rand(15, 85),
                    'event_type' => 'red_card',
                    'metadata' => ['second_yellow' => false],
                ];
            }
        }
    }

    private function generateInjuryEvents(array &$events, Collection $lineup, string $teamId, Game $game): void
    {
        $injuryChance = (float) config('match_simulation.injury_chance', 1.0);

        foreach ($lineup as $player) {
            if ($player->position === 'Goalkeeper') {
                continue; // GKs rarely get match injuries
            }

            if (mt_rand(1, 10000) <= $injuryChance * 100) {
                $injuryTypes = ['Muscle strain', 'Ligament damage', 'Ankle sprain', 'Knee injury'];
                $injuryType = $injuryTypes[array_rand($injuryTypes)];
                $weeksOut = mt_rand(1, 8);

                $events[] = [
                    'team_id' => $teamId,
                    'game_player_id' => $player->id,
                    'minute' => mt_rand(10, 85),
                    'event_type' => 'injury',
                    'metadata' => [
                        'injury_type' => $injuryType,
                        'weeks_out' => $weeksOut,
                    ],
                ];

                break; // max 1 injury per team per match
            }
        }
    }

    /**
     * Pick a player using position-based weights with quality multiplier.
     * Matches the MatchSimulator approach (dampened sqrt for goals).
     */
    private function pickWeightedPlayer(Collection $players, array $weights, array $goalCounts = []): ?GamePlayer
    {
        if ($players->isEmpty()) {
            return null;
        }

        $weighted = [];
        foreach ($players as $player) {
            $posWeight = $weights[$player->position] ?? 5;
            if ($posWeight === 0) {
                continue;
            }

            $qualityMultiplier = pow($player->overall_score / 70, 0.5);
            $weight = $posWeight * $qualityMultiplier;

            // Diminishing returns for repeat goals
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
            return $players->first();
        }

        return $weighted[array_rand($weighted)];
    }

    /**
     * Pick MVP: best overall player from winning team, or random top player on draw.
     */
    private function pickMvp(Collection $homeXI, Collection $awayXI, int $homeScore, int $awayScore): ?string
    {
        $winnerXI = match (true) {
            $homeScore > $awayScore => $homeXI,
            $awayScore > $homeScore => $awayXI,
            default => mt_rand(0, 1) === 0 ? $homeXI : $awayXI,
        };

        $mvp = $winnerXI->sortByDesc('overall_score')->first();

        return $mvp?->id;
    }

    /**
     * Generate a Poisson-distributed random integer.
     */
    private function poissonRandom(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }

        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L);

        return $k - 1;
    }
}
