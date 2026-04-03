<?php

namespace App\Modules\Match\Services;

use App\Models\GamePlayer;
use App\Modules\Lineup\Services\SubstitutionService;
use App\Support\PositionMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AISubstitutionService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('match_simulation.ai_substitutions');
    }
    /**
     * Generate substitution timing windows for an AI team.
     *
     * Uses Poisson distribution for realistic timing: most subs cluster
     * around minute 70, with a possible halftime sub.
     *
     * @return array<int, int[]> Map of window minute => [sub indices]. Each window groups subs
     *                           that happen within window_grouping_minutes of each other.
     */
    public function generateSubstitutionWindows(int $totalSubs, int $fromMinute = 0): array
    {
        $lambda = $this->config['poisson_lambda'];
        $minMinute = $this->config['min_minute'];
        $maxMinute = $this->config['max_minute'];
        $halftimeChance = $this->config['halftime_sub_chance'];
        $groupingMinutes = $this->config['window_grouping_minutes'];

        $minutes = [];

        // Possibly add a halftime sub (minute 46)
        if ($fromMinute < 46 && rand(1, 100) <= $halftimeChance && $totalSubs > 0) {
            $minutes[] = 46;
            $totalSubs--;
        }

        // Generate remaining sub minutes from Poisson distribution
        for ($i = 0; $i < $totalSubs; $i++) {
            $offset = $this->poissonRandom($lambda);
            $minute = min($minMinute + $offset, $maxMinute);
            // Ensure minute is after fromMinute
            $minute = max($minute, $fromMinute + 1);
            $minutes[] = $minute;
        }

        sort($minutes);

        // Group into windows: subs within groupingMinutes of each other = same window
        $windows = [];
        $currentWindowMinute = null;

        foreach ($minutes as $minute) {
            if ($currentWindowMinute === null || $minute - $currentWindowMinute > $groupingMinutes) {
                $currentWindowMinute = $minute;
                $windows[$currentWindowMinute] = [];
            }
            $windows[$currentWindowMinute][] = count($windows[$currentWindowMinute]);
        }

        // Enforce max 3 windows by merging the two closest if exceeded
        while (count($windows) > SubstitutionService::MAX_WINDOWS) {
            $windowMinutes = array_keys($windows);
            $minGap = PHP_INT_MAX;
            $mergeIndex = 0;

            for ($i = 0; $i < count($windowMinutes) - 1; $i++) {
                $gap = $windowMinutes[$i + 1] - $windowMinutes[$i];
                if ($gap < $minGap) {
                    $minGap = $gap;
                    $mergeIndex = $i;
                }
            }

            // Merge window at mergeIndex+1 into mergeIndex
            $keepMinute = $windowMinutes[$mergeIndex];
            $mergeMinute = $windowMinutes[$mergeIndex + 1];
            $windows[$keepMinute] = array_merge($windows[$keepMinute], $windows[$mergeMinute]);
            unset($windows[$mergeMinute]);
        }

        return $windows;
    }

    /**
     * Decide how many total substitutions an AI team should make.
     *
     * @param  int  $benchSize  Number of available bench players
     * @param  int  $subsAlreadyUsed  Subs already made (e.g. injury auto-subs)
     */
    public function decideTotalSubs(int $benchSize, int $subsAlreadyUsed = 0): int
    {
        $minTarget = $this->config['min_subs'];
        $maxTarget = $this->config['max_subs'];

        $remaining = $maxTarget - $subsAlreadyUsed;
        if ($remaining <= 0) {
            return 0;
        }

        // Target between min and max, weighted toward 3-4
        $effectiveMin = min($minTarget, $remaining);
        $effectiveMax = min($remaining, $maxTarget);
        $target = $this->weightedSubCount($effectiveMin, $effectiveMax);

        // Can't sub more players than we have on the bench
        return min($target, $benchSize);
    }

    /**
     * Choose which players to sub out and who to bring on.
     *
     * @param  Collection<GamePlayer>  $lineup  Current on-pitch players
     * @param  Collection<GamePlayer>  $bench  Available bench players
     * @param  int  $windowMinute  The minute these subs happen
     * @param  int  $subsInWindow  How many subs to make in this window
     * @param  int  $goalDifference  Positive = winning, negative = losing
     * @param  array<string>  $yellowCardPlayerIds  Players on a yellow card
     * @param  float  $tacticalDrainMultiplier  Energy drain multiplier from tactics
     * @param  Carbon  $currentDate  Game's current date (for age calculations)
     * @return array<array{player_out: GamePlayer, player_in: GamePlayer}>
     */
    public function chooseSubstitutions(
        Collection $lineup,
        Collection $bench,
        int $windowMinute,
        int $subsInWindow,
        int $goalDifference,
        array $yellowCardPlayerIds,
        float $tacticalDrainMultiplier,
        Carbon $currentDate,
        array $previouslySubbedInIds = [],
    ): array {
        if ($bench->isEmpty() || $subsInWindow <= 0) {
            return [];
        }

        $energyThreshold = $this->config['energy_threshold'];
        $yellowCardWeight = $this->config['yellow_card_weight'];

        $substitutions = [];
        $availableBench = $bench->values();
        $currentLineup = $lineup->values();
        $protectedIds = $previouslySubbedInIds;

        for ($i = 0; $i < $subsInWindow; $i++) {
            if ($availableBench->isEmpty()) {
                break;
            }

            // Score each lineup player for substitution urgency
            // Exclude players subbed in during this match (current or previous windows)
            $candidates = $this->scoreSubstitutionUrgency(
                $currentLineup->reject(fn ($p) => in_array($p->id, $protectedIds)),
                $windowMinute, $yellowCardPlayerIds,
                $energyThreshold, $yellowCardWeight, $tacticalDrainMultiplier,
                $currentDate,
            );

            if ($candidates->isEmpty()) {
                break;
            }

            // Pick the most urgent player to sub out
            $playerOut = $candidates->sortByDesc('urgency')->first()['player'];

            // Find best replacement from bench
            $playerIn = $this->findBestReplacement($playerOut, $availableBench, $goalDifference);
            if (! $playerIn) {
                break;
            }

            $substitutions[] = [
                'player_out' => $playerOut,
                'player_in' => $playerIn,
            ];

            // Update state for next sub in this window
            $protectedIds[] = $playerIn->id;
            $currentLineup = $currentLineup->reject(fn ($p) => $p->id === $playerOut->id)
                ->push($playerIn)->values();
            $availableBench = $availableBench->reject(fn ($p) => $p->id === $playerIn->id)->values();

            // Remove subbed-out player from yellow card list (no longer on pitch)
            $yellowCardPlayerIds = array_values(array_filter(
                $yellowCardPlayerIds,
                fn ($id) => $id !== $playerOut->id
            ));
        }

        return $substitutions;
    }

    /**
     * Score each lineup player for how urgently they should be substituted.
     *
     * @return Collection<array{player: GamePlayer, urgency: float}>
     */
    private function scoreSubstitutionUrgency(
        Collection $lineup,
        int $minute,
        array $yellowCardPlayerIds,
        float $energyThreshold,
        float $yellowCardWeight,
        float $tacticalDrainMultiplier,
        Carbon $currentDate,
    ): Collection {
        return $lineup
            ->filter(fn (GamePlayer $p) => $p->position !== 'Goalkeeper')
            ->map(function (GamePlayer $player) use ($minute, $yellowCardPlayerIds, $energyThreshold, $yellowCardWeight, $tacticalDrainMultiplier, $currentDate) {
                $energy = EnergyCalculator::energyAtMinute(
                    $player->physical_ability,
                    $player->age($currentDate),
                    false,
                    $minute,
                    0, // started from minute 0
                    $tacticalDrainMultiplier,
                );

                // Base urgency from tiredness (0.0 to 1.0)
                $urgency = max(0, (100 - $energy)) / 100;

                // Bonus urgency when below energy threshold
                if ($energy < $energyThreshold) {
                    $urgency += 0.2;
                }

                // Yellow card risk
                if (in_array($player->id, $yellowCardPlayerIds)) {
                    $urgency += $yellowCardWeight;
                }

                // Small random factor to avoid deterministic patterns
                $urgency += (rand(0, 100) / 1000); // 0.0 to 0.1

                return ['player' => $player, 'urgency' => $urgency];
            })
            ->values();
    }

    /**
     * Choose a reactive substitution for the team that received a red card.
     *
     * Tries to bring on a backup goalkeeper or defender when a player in
     * those positions is sent off, sacrificing a random attacker or midfielder.
     *
     * @return array{player_out: GamePlayer, player_in: GamePlayer}|null
     */
    public function chooseRedCardReactiveSubstitution(
        Collection $lineup,
        Collection $bench,
        string $sentOffPosition,
    ): ?array {
        if ($bench->isEmpty()) {
            return null;
        }

        $sentOffGroup = PositionMapper::getPositionGroup($sentOffPosition);

        // Only react to GK or Defender red cards
        if ($sentOffGroup === 'Goalkeeper') {
            $replacement = $bench->firstWhere('position', 'Goalkeeper');
        } elseif ($sentOffGroup === 'Defender') {
            $replacement = $bench->filter(fn ($p) => PositionMapper::getPositionGroup($p->position) === 'Defender')
                ->sortByDesc(fn ($p) => $p->overall_score)
                ->first();
        } else {
            return null;
        }

        if (! $replacement) {
            return null;
        }

        $playerOut = $this->findOutfieldPlayerToSacrifice($lineup);

        return $playerOut ? ['player_out' => $playerOut, 'player_in' => $replacement] : null;
    }

    /**
     * Pick a random forward or midfielder to sacrifice for a tactical sub.
     */
    private function findOutfieldPlayerToSacrifice(Collection $lineup): ?GamePlayer
    {
        $candidates = $lineup->filter(fn ($p) => in_array(
            PositionMapper::getPositionGroup($p->position),
            ['Forward', 'Midfielder']
        ));

        return $candidates->isNotEmpty() ? $candidates->random() : null;
    }

    /**
     * Find the best bench replacement for a player being subbed out.
     *
     * Priority: same position > same group > best available.
     * When losing, bias toward attacking players. When winning, bias defensive.
     */
    private function findBestReplacement(
        GamePlayer $playerOut,
        Collection $bench,
        int $goalDifference,
    ): ?GamePlayer {
        if ($bench->isEmpty()) {
            return null;
        }

        $outPosition = $playerOut->position;
        $outGroup = PositionMapper::getPositionGroup($outPosition);

        // Priority 1: Same exact position
        $samePosition = $bench->filter(fn ($p) => $p->position === $outPosition);
        if ($samePosition->isNotEmpty()) {
            return $samePosition->sortByDesc(fn ($p) => $p->overall_score)->first();
        }

        // Priority 2: Same position group
        $sameGroup = $bench->filter(fn ($p) => PositionMapper::getPositionGroup($p->position) === $outGroup);
        if ($sameGroup->isNotEmpty()) {
            return $sameGroup->sortByDesc(fn ($p) => $p->overall_score)->first();
        }

        // Priority 3: Best available with situational bias
        $candidates = $bench->reject(fn ($p) => $p->position === 'Goalkeeper');
        if ($candidates->isEmpty()) {
            return null;
        }

        // When losing, prefer attackers; when winning big, prefer defenders
        if ($goalDifference < 0 && rand(1, 100) <= $this->config['losing_attack_bias'] * 100) {
            $attackers = $candidates->filter(fn ($p) => in_array(
                PositionMapper::getPositionGroup($p->position),
                ['Forward', 'Midfielder']
            ));
            if ($attackers->isNotEmpty()) {
                return $attackers->sortByDesc(fn ($p) => $p->overall_score)->first();
            }
        }

        if ($goalDifference >= 2) {
            $defenders = $candidates->filter(fn ($p) => PositionMapper::getPositionGroup($p->position) === 'Defender');
            if ($defenders->isNotEmpty()) {
                return $defenders->sortByDesc(fn ($p) => $p->overall_score)->first();
            }
        }

        return $candidates->sortByDesc(fn ($p) => $p->overall_score)->first();
    }

    /**
     * Generate a weighted sub count, biased toward 3-4 subs.
     */
    private function weightedSubCount(int $min, int $max): int
    {
        if ($min >= $max) {
            return $min;
        }

        // Weight distribution: 3 subs = 35%, 4 subs = 35%, 5 subs = 30%
        $roll = rand(1, 100);

        if ($max >= 5 && $min <= 3) {
            if ($roll <= 35) {
                return 3;
            }
            if ($roll <= 70) {
                return 4;
            }

            return 5;
        }

        if ($max >= 4 && $min <= 3) {
            return $roll <= 55 ? 3 : 4;
        }

        return rand($min, $max);
    }

    /**
     * Generate a Poisson-distributed random number.
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

        return max(0, $k - 1);
    }
}
