<?php

namespace App\Game\Services;

use App\Game\DTO\GeneratedPlayerData;
use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GamePlayer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class YouthAcademyService
{
    /**
     * Youth academy tier effects on prospect generation.
     * [min_prospects, max_prospects, min_potential, max_potential, min_ability, max_ability]
     */
    private const TIER_EFFECTS = [
        0 => [0, 0, 0, 0, 0, 0],           // No academy
        1 => [1, 1, 60, 70, 35, 50],       // Basic
        2 => [1, 2, 65, 75, 40, 55],       // Good
        3 => [2, 3, 70, 82, 45, 60],       // Excellent
        4 => [2, 4, 75, 90, 50, 70],       // World-class
    ];

    /**
     * Expected prospects per season by tier.
     */
    private const TIER_TARGET_PER_SEASON = [
        0 => 0,
        1 => 1,
        2 => 1.5,
        3 => 2.5,
        4 => 3,
    ];

    private const ESTIMATED_MATCHDAYS = 38;

    /**
     * Positions with weights for random selection.
     */
    private const POSITION_WEIGHTS = [
        'Goalkeeper' => 5,
        'Centre-Back' => 15,
        'Left-Back' => 8,
        'Right-Back' => 8,
        'Defensive Midfield' => 10,
        'Central Midfield' => 15,
        'Attacking Midfield' => 10,
        'Left Winger' => 8,
        'Right Winger' => 8,
        'Centre-Forward' => 13,
    ];

    public function __construct(
        private readonly PlayerGeneratorService $playerGenerator,
    ) {}

    /**
     * Try to spawn a new academy prospect during matchday advancement.
     * Returns the new prospect if one spawned, null otherwise.
     */
    public function trySpawnProspect(Game $game): ?AcademyPlayer
    {
        $investment = $game->currentInvestment;
        $tier = $investment->youth_academy_tier ?? 0;

        if ($tier === 0) {
            return null;
        }

        $target = self::TIER_TARGET_PER_SEASON[$tier];
        $spawnChance = $target / self::ESTIMATED_MATCHDAYS;

        if (mt_rand(1, 10000) > (int) ($spawnChance * 10000)) {
            return null;
        }

        return $this->createAcademyProspect($game, $tier);
    }

    /**
     * Create an academy prospect record.
     */
    private function createAcademyProspect(Game $game, int $tier): AcademyPlayer
    {
        $effects = self::TIER_EFFECTS[$tier];
        [, , $minPotential, $maxPotential, $minAbility, $maxAbility] = $effects;

        $position = $this->selectPosition();
        $technical = rand($minAbility, $maxAbility);
        $physical = rand($minAbility, $maxAbility);

        $age = rand(16, 18);
        $currentYear = (int) $game->season;
        $dateOfBirth = Carbon::createFromDate($currentYear - $age, rand(1, 12), rand(1, 28));

        $potential = rand($minPotential, $maxPotential);
        $potentialVariance = rand(3, 8);
        $potentialLow = max($potential - $potentialVariance, max($technical, $physical));
        $potentialHigh = min($potential + $potentialVariance, 99);

        $identity = $this->playerGenerator->pickRandomIdentity();

        return AcademyPlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'name' => $identity['name'],
            'nationality' => $identity['nationality'],
            'date_of_birth' => $dateOfBirth,
            'position' => $position,
            'technical_ability' => $technical,
            'physical_ability' => $physical,
            'potential' => $potential,
            'potential_low' => $potentialLow,
            'potential_high' => $potentialHigh,
            'appeared_at' => $game->current_date,
        ]);
    }

    /**
     * Promote an academy player to the first team.
     * Creates Player + GamePlayer records and deletes the AcademyPlayer.
     */
    public function promoteToFirstTeam(AcademyPlayer $academy, Game $game): GamePlayer
    {
        $gamePlayer = $this->playerGenerator->create($game, new GeneratedPlayerData(
            teamId: $academy->team_id,
            position: $academy->position,
            technical: $academy->technical_ability,
            physical: $academy->physical_ability,
            dateOfBirth: $academy->date_of_birth,
            contractYears: 2,
            name: $academy->name,
            nationality: $academy->nationality,
            potential: $academy->potential,
            potentialLow: $academy->potential_low,
            potentialHigh: $academy->potential_high,
            fitnessMin: 85,
            fitnessMax: 100,
            moraleMin: 70,
            moraleMax: 90,
        ));

        $academy->delete();

        return $gamePlayer;
    }

    /**
     * Generate youth prospects (batch) for the user's team based on academy tier.
     * Legacy method — kept for backward compatibility.
     *
     * @return Collection<GamePlayer> Generated prospects
     */
    public function generateProspects(Game $game): Collection
    {
        $investment = $game->currentInvestment;
        $tier = $investment->youth_academy_tier ?? 0;

        if ($tier === 0) {
            return collect();
        }

        $effects = self::TIER_EFFECTS[$tier];
        [$minProspects, $maxProspects, $minPotential, $maxPotential, $minAbility, $maxAbility] = $effects;

        $prospectCount = rand($minProspects, $maxProspects);
        $prospects = collect();

        for ($i = 0; $i < $prospectCount; $i++) {
            $prospect = $this->createProspect(
                $game,
                $minPotential,
                $maxPotential,
                $minAbility,
                $maxAbility
            );
            $prospects->push($prospect);
        }

        return $prospects;
    }

    private function createProspect(
        Game $game,
        int $minPotential,
        int $maxPotential,
        int $minAbility,
        int $maxAbility,
    ): GamePlayer {
        $position = $this->selectPosition();
        $technical = rand($minAbility, $maxAbility);
        $physical = rand($minAbility, $maxAbility);

        $age = rand(16, 18);
        $currentYear = (int) $game->season;
        $dateOfBirth = Carbon::createFromDate($currentYear - $age, rand(1, 12), rand(1, 28));

        $potential = rand($minPotential, $maxPotential);
        $potentialVariance = rand(3, 8);
        $potentialLow = max($potential - $potentialVariance, max($technical, $physical));
        $potentialHigh = min($potential + $potentialVariance, 99);

        return $this->playerGenerator->create($game, new GeneratedPlayerData(
            teamId: $game->team_id,
            position: $position,
            technical: $technical,
            physical: $physical,
            dateOfBirth: $dateOfBirth,
            contractYears: 3,
            potential: $potential,
            potentialLow: $potentialLow,
            potentialHigh: $potentialHigh,
            fitnessMin: 85,
            fitnessMax: 100,
            moraleMin: 70,
            moraleMax: 90,
        ));
    }

    private function selectPosition(): string
    {
        $totalWeight = array_sum(self::POSITION_WEIGHTS);
        $random = rand(1, $totalWeight);

        foreach (self::POSITION_WEIGHTS as $position => $weight) {
            $random -= $weight;
            if ($random <= 0) {
                return $position;
            }
        }

        return 'Central Midfield';
    }

    public static function getTierDescription(int $tier): string
    {
        return match ($tier) {
            0 => 'No Academy',
            1 => 'Basic Academy',
            2 => 'Good Academy',
            3 => 'Excellent Academy',
            4 => 'World-class Academy',
            default => 'Unknown',
        };
    }

    public static function getProspectInfo(int $tier): array
    {
        $effects = self::TIER_EFFECTS[$tier] ?? self::TIER_EFFECTS[0];

        return [
            'min_prospects' => $effects[0],
            'max_prospects' => $effects[1],
            'potential_range' => $effects[2] > 0 ? "{$effects[2]}-{$effects[3]}" : 'N/A',
        ];
    }
}
