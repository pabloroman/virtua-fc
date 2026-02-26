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
     * Tier configuration: [capacity, min_arrivals, max_arrivals, min_potential, max_potential, min_ability, max_ability]
     */
    private const TIER_CONFIG = [
        0 => [0, 0, 0, 0, 0, 0, 0],
        1 => [4, 2, 3, 60, 70, 35, 50],
        2 => [6, 3, 5, 65, 75, 40, 55],
        3 => [8, 5, 7, 70, 82, 45, 60],
        4 => [10, 6, 8, 75, 90, 50, 70],
    ];

    private const ESTIMATED_MATCHDAYS = 38;

    /**
     * Season growth rates for development.
     */
    private const GROWTH_RATE_ACADEMY = 0.35;
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
        [, $minArrivals, $maxArrivals, $minPotential, $maxPotential, $minAbility, $maxAbility] = $config;

        $count = rand($minArrivals, $maxArrivals);
        $prospects = collect();

        for ($i = 0; $i < $count; $i++) {
            $prospects->push($this->createAcademyProspect($game, $minPotential, $maxPotential, $minAbility, $maxAbility));
        }

        return $prospects;
    }

    /**
     * Develop all academy players' abilities for one matchday.
     * Growth is applied each matchday as a small increment toward potential.
     * Only non-loaned players develop (loaned players develop off-screen at season end).
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
     * Get the reveal phase based on the game's current matchday.
     *
     * Phase 0: Stats hidden (matchdays 1-9)
     * Phase 1: Abilities visible (matchday 10 until winter window)
     * Phase 2: Potential visible (winter window onward)
     */
    public static function getRevealPhase(Game $game): int
    {
        if ($game->isWinterWindowOpen() || $game->isStartOfWinterWindow()) {
            return 2;
        }

        // After winter window (February onward), stay at phase 2
        if ($game->current_date && $game->current_date->month >= 2 && $game->current_date->month <= 6) {
            return 2;
        }

        if ($game->current_matchday >= 10) {
            return 1;
        }

        return 0;
    }

    /**
     * Get the academy capacity (max seats) for a given tier.
     */
    public static function getCapacity(int $tier): int
    {
        return self::TIER_CONFIG[$tier][0] ?? 0;
    }

    /**
     * Get the number of currently occupied seats (non-loaned players).
     */
    public static function getOccupiedSeats(Game $game): int
    {
        return AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', false)
            ->count();
    }

    /**
     * Get the total number of academy players (including loaned).
     */
    public static function getTotalPlayers(Game $game): int
    {
        return AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->count();
    }

    /**
     * Mark a player as loaned out.
     */
    public function loanPlayer(AcademyPlayer $player): void
    {
        $player->update(['is_on_loan' => true, 'evaluation_needed' => false]);
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
     * Check if a player must be promoted or dismissed (age 21+).
     */
    public static function mustDecide(AcademyPlayer $player): bool
    {
        return $player->age >= 21;
    }

    /**
     * Get the expected new arrivals range for a tier.
     *
     * @return array{min: int, max: int}
     */
    public static function getArrivalsRange(int $tier): array
    {
        $config = self::TIER_CONFIG[$tier] ?? self::TIER_CONFIG[0];

        return ['min' => $config[1], 'max' => $config[2]];
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
            'min_prospects' => $config[1],
            'max_prospects' => $config[2],
            'potential_range' => $config[3] > 0 ? "{$config[3]}-{$config[4]}" : 'N/A',
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
     */
    private function createAcademyProspect(
        Game $game,
        int $minPotential,
        int $maxPotential,
        int $minAbility,
        int $maxAbility,
    ): AcademyPlayer {
        $position = $this->selectPosition();
        $technical = rand($minAbility, $maxAbility);
        $physical = rand($minAbility, $maxAbility);

        $age = rand(16, 19);
        $currentYear = (int) $game->season;
        $dateOfBirth = Carbon::createFromDate($currentYear - $age, rand(1, 12), rand(1, 28));

        $potential = rand($minPotential, $maxPotential);
        $potentialVariance = rand(3, 8);
        $potentialLow = max($potential - $potentialVariance, max($technical, $physical));
        $potentialHigh = min($potential + $potentialVariance, 99);

        $teamName = $game->team->name;
        $nationalityFilter = self::CANTERA_TEAMS[$teamName] ?? null;
        $teamCountry = $nationalityFilter ? null : $game->team->country;
        $excludedNames = $this->getExistingPlayerNames($game);
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
