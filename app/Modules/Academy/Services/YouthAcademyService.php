<?php

namespace App\Modules\Academy\Services;

use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GamePlayer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Modules\Squad\Services\PlayerGeneratorService;

class YouthAcademyService
{
    /**
     * Tier configuration: [min_arrivals, max_arrivals]
     */
    private const TIER_CONFIG = [
        0 => [0, 0],
        1 => [2, 3],
        2 => [3, 5],
        3 => [4, 6],
        4 => [4, 6],
    ];

    /**
     * Ability ranges corresponding to each PlayerTier (derived from PlayerValuationService anchors).
     */
    private const TIER_ABILITY_RANGES = [
        1 => [40, 57],   // Developing (< €1M)
        2 => [58, 67],   // Average (€1M-€5M)
        3 => [68, 77],   // Good (€5M-€20M)
        4 => [78, 83],   // Excellent (€20M-€50M)
        5 => [84, 90],   // World Class (€50M+)
    ];

    /**
     * How many tiers below the first team's median tier academy players spawn.
     */
    private const ACADEMY_TIER_OFFSET = [
        0 => 0,
        1 => 2,  // basic: 2 tiers below
        2 => 2,  // good: 2 tiers below
        3 => 1,  // elite: 1 tier below
        4 => 0,  // world-class: same tier as first team
    ];

    /**
     * How many tiers above the target ability tier the potential ceiling reaches.
     */
    private const POTENTIAL_CEILING_OFFSET = [
        0 => 0,
        1 => 1,
        2 => 1,
        3 => 2,
        4 => 2,
    ];

    /**
     * Absolute minimum potential guaranteed by academy tier, regardless of team level.
     */
    private const POTENTIAL_FLOOR = [
        0 => 0,
        1 => 45,
        2 => 50,
        3 => 55,
        4 => 60,
    ];

    private const ESTIMATED_MATCHDAYS = 38;

    /**
     * Season growth rates for development.
     */
    private const GROWTH_RATE_ACADEMY = 0.45;
    private const GROWTH_RATE_LOAN = 0.50;

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
     * Generate a batch of new academy prospects at season start.
     *
     * @return Collection<int, AcademyPlayer>
     */
    public function generateSeasonBatch(Game $game): Collection
    {
        $tier = $game->currentInvestment->youth_academy_tier ?? 0;

        if ($tier === 0) {
            return collect();
        }

        $config = self::TIER_CONFIG[$tier];
        [$minArrivals, $maxArrivals] = $config;

        $count = rand($minArrivals, $maxArrivals);
        $teamMedianTier = $this->getTeamMedianTier($game);
        $excludedNames = $this->getExistingPlayerNames($game);

        $prospects = collect();

        for ($i = 0; $i < $count; $i++) {
            $prospect = $this->createAcademyProspect($game, $tier, $teamMedianTier, $excludedNames);
            $excludedNames[] = $prospect->name;
            $prospects->push($prospect);
        }

        return $prospects;
    }

    /**
     * Develop all academy players' abilities for one matchday.
     * Growth is applied each matchday as a small increment toward potential.
     * Only non-loaned players develop (loaned develop at season end).
     */
    public function developPlayers(Game $game): void
    {
        $players = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', false)
            ->get();

        if ($players->isEmpty()) {
            return;
        }

        // Compute growth in memory, batch update changed players in a single query
        $updates = []; // [id => [technical_ability => N, physical_ability => N]]
        foreach ($players as $player) {
            $computed = $this->computeGrowth($player, self::GROWTH_RATE_ACADEMY);
            if ($computed) {
                $updates[$player->id] = $computed;
            }
        }

        if (! empty($updates)) {
            $this->bulkUpdateAbilities($updates);
        }
    }

    /**
     * Compute one matchday of growth for a player (pure calculation, no DB).
     *
     * @return array{technical_ability: int, physical_ability: int}|null Null if no change
     */
    private function computeGrowth(AcademyPlayer $player, float $seasonRate): ?array
    {
        $growthPerMatchday = fn (int $current, int $potential) => max(0, ($potential - $current) * $seasonRate / self::ESTIMATED_MATCHDAYS);

        $techGrowth = $growthPerMatchday($player->technical_ability, $player->potential);
        $physGrowth = $growthPerMatchday($player->physical_ability, $player->potential);

        $newTech = min($player->potential, $player->technical_ability + $techGrowth);
        $newPhys = min($player->potential, $player->physical_ability + $physGrowth);

        $techInt = (int) round($newTech);
        $physInt = (int) round($newPhys);

        if ($techInt === $player->technical_ability && $physInt === $player->physical_ability) {
            return null;
        }

        return ['technical_ability' => $techInt, 'physical_ability' => $physInt];
    }

    /**
     * Bulk update abilities using CASE WHEN (1 query instead of N).
     *
     * @param  array<string, array{technical_ability: int, physical_ability: int}>  $updates
     */
    private function bulkUpdateAbilities(array $updates): void
    {
        $ids = array_keys($updates);
        $idList = "'" . implode("','", $ids) . "'";

        $techCases = [];
        $physCases = [];
        foreach ($updates as $id => $values) {
            $techCases[] = "WHEN id = '{$id}' THEN {$values['technical_ability']}";
            $physCases[] = "WHEN id = '{$id}' THEN {$values['physical_ability']}";
        }

        \Illuminate\Support\Facades\DB::statement("
            UPDATE academy_players
            SET technical_ability = CASE " . implode(' ', $techCases) . " ELSE technical_ability END,
                physical_ability = CASE " . implode(' ', $physCases) . " ELSE physical_ability END
            WHERE id IN ({$idList})
        ");
    }

    /**
     * Apply a full season of off-screen development to loaned players.
     * Called at season end when loans return.
     */
    public function developLoanedPlayers(Game $game): void
    {
        $loanedPlayers = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', true)
            ->get();

        foreach ($loanedPlayers as $player) {
            // Apply full season growth at loan rate
            $techGrowth = ($player->potential - $player->technical_ability) * self::GROWTH_RATE_LOAN;
            $physGrowth = ($player->potential - $player->physical_ability) * self::GROWTH_RATE_LOAN;

            $player->update([
                'technical_ability' => min($player->potential, (int) round($player->technical_ability + $techGrowth)),
                'physical_ability' => min($player->potential, (int) round($player->physical_ability + $physGrowth)),
            ]);
        }
    }

    /**
     * Return all loaned players to the academy at season end.
     *
     * @return Collection<int, AcademyPlayer> The returned players
     */
    public function returnLoans(Game $game): Collection
    {
        $loanedPlayers = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', true)
            ->get();

        foreach ($loanedPlayers as $player) {
            $player->update(['is_on_loan' => false]);
        }

        return $loanedPlayers;
    }

    /**
     * Mark a player as loaned out.
     */
    public function loanPlayer(AcademyPlayer $player): void
    {
        $player->update(['is_on_loan' => true]);
    }

    /**
     * Dismiss a player from the academy (permanently removed).
     */
    public function dismissPlayer(AcademyPlayer $player): void
    {
        $player->delete();
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
     * Get the expected new arrivals range for a tier.
     *
     * @return array{min: int, max: int}
     */
    public static function getArrivalsRange(int $tier): array
    {
        $config = self::TIER_CONFIG[$tier] ?? self::TIER_CONFIG[0];

        return ['min' => $config[0], 'max' => $config[1]];
    }

    public static function getTierDescription(int $tier): string
    {
        return __('squad.academy_tier_'.(match ($tier) {
            0, 1, 2, 3, 4 => $tier,
            default => 'unknown',
        }));
    }

    public static function getProspectInfo(int $tier): array
    {
        $config = self::TIER_CONFIG[$tier] ?? self::TIER_CONFIG[0];

        return [
            'min_prospects' => $config[0],
            'max_prospects' => $config[1],
        ];
    }

    /**
     * Teams whose academy only produces domestic players (cantera philosophy).
     * Maps team name → exact nationality filter (overrides weighted selection).
     */
    private const CANTERA_TEAMS = [
        'Athletic Bilbao' => 'Spain',
    ];

    /**
     * Create an academy prospect record.
     * Quality is calibrated relative to the first team's median PlayerTier.
     */
    private function createAcademyProspect(
        Game $game,
        int $academyTier,
        int $teamMedianTier,
        array $excludedNames,
    ): AcademyPlayer {
        $position = $this->selectPosition();

        // Determine target ability tier and potential ceiling tier
        $targetTier = max(1, $teamMedianTier - self::ACADEMY_TIER_OFFSET[$academyTier]);
        $ceilingTier = min(5, $teamMedianTier + self::POTENTIAL_CEILING_OFFSET[$academyTier]);

        $abilityRange = self::TIER_ABILITY_RANGES[$targetTier];
        $ceilingRange = self::TIER_ABILITY_RANGES[$ceilingTier];

        $technical = rand($abilityRange[0], $abilityRange[1]);
        $physical = rand($abilityRange[0], $abilityRange[1]);

        // Potential ranges from the top of target tier to the top of ceiling tier,
        // with a guaranteed floor based on academy investment
        $potentialFloor = max($abilityRange[1], self::POTENTIAL_FLOOR[$academyTier]);
        $potential = rand($potentialFloor, $ceilingRange[1]);
        $potential = min(95, max($potential, max($technical, $physical)));

        $potentialVariance = rand(3, 8);
        $potentialLow = max($potential - $potentialVariance, max($technical, $physical));
        $potentialHigh = min($potential + $potentialVariance, 99);

        $age = rand(17, 19);
        $dateOfBirth = $game->current_date->copy()->subYears($age)->subDays(rand(0, 364));

        $teamName = $game->team->name;
        $nationalityFilter = self::CANTERA_TEAMS[$teamName] ?? null;
        $teamCountry = $nationalityFilter ? null : $game->team->country;
        $identity = $this->playerGenerator->pickRandomIdentity($nationalityFilter, $teamCountry, $excludedNames);

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
            'is_on_loan' => false,
            'joined_season' => (int) $game->season,
            'initial_technical' => $technical,
            'initial_physical' => $physical,
        ]);
    }

    /**
     * Get the median PlayerTier of the first team's squad.
     */
    private function getTeamMedianTier(Game $game): int
    {
        $tiers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->pluck('tier')
            ->sort()
            ->values();

        if ($tiers->isEmpty()) {
            return 2; // fallback for empty squads
        }

        $midIndex = intdiv($tiers->count(), 2);

        return $tiers[$midIndex];
    }

    /**
     * Get names of existing first-team and academy players (to prevent duplicate names).
     *
     * @return string[]
     */
    private function getExistingPlayerNames(Game $game): array
    {
        $firstTeamNames = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->join('players', 'game_players.player_id', '=', 'players.id')
            ->pluck('players.name')
            ->toArray();

        $academyNames = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->pluck('name')
            ->toArray();

        return array_merge($firstTeamNames, $academyNames);
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
}
