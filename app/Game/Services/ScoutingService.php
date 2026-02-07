<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\ScoutReport;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Support\Money;
use Illuminate\Support\Collection;

class ScoutingService
{
    /**
     * Scouting tier effects on searches.
     * [weeks_reduction, extra_results, ability_fuzz_reduction]
     */
    private const SCOUTING_TIER_EFFECTS = [
        0 => [0, 0, 0],   // No scouting department
        1 => [0, 0, 0],   // Basic - baseline
        2 => [0, 1, 2],   // Good - 1 extra result, 2 less fuzz
        3 => [1, 2, 4],   // Excellent - 1 week faster, 2 extra results, 4 less fuzz
        4 => [1, 3, 6],   // World-class - 1 week faster, 3 extra results, 6 less fuzz
    ];

    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    // =========================================
    // SCOUT SEARCH
    // =========================================

    /**
     * Position groups for broad searches.
     */
    private const POSITION_GROUPS = [
        'any_defender' => ['Centre-Back', 'Left-Back', 'Right-Back'],
        'any_midfielder' => ['Defensive Midfield', 'Central Midfield', 'Attacking Midfield', 'Left Midfield', 'Right Midfield'],
        'any_forward' => ['Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker'],
    ];

    /**
     * Map filter position values to actual position strings.
     */
    private const POSITION_MAP = [
        'GK' => ['Goalkeeper'],
        'CB' => ['Centre-Back'],
        'LB' => ['Left-Back'],
        'RB' => ['Right-Back'],
        'DM' => ['Defensive Midfield'],
        'CM' => ['Central Midfield'],
        'AM' => ['Attacking Midfield'],
        'LW' => ['Left Winger'],
        'RW' => ['Right Winger'],
        'CF' => ['Centre-Forward'],
        'SS' => ['Second Striker'],
        'LM' => ['Left Midfield'],
        'RM' => ['Right Midfield'],
        'any_defender' => ['Centre-Back', 'Left-Back', 'Right-Back'],
        'any_midfielder' => ['Defensive Midfield', 'Central Midfield', 'Attacking Midfield', 'Left Midfield', 'Right Midfield'],
        'any_forward' => ['Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker'],
    ];

    /**
     * Get the active scout report for a game.
     */
    public function getActiveReport(Game $game): ?ScoutReport
    {
        return ScoutReport::where('game_id', $game->id)
            ->whereIn('status', [ScoutReport::STATUS_SEARCHING, ScoutReport::STATUS_COMPLETED])
            ->latest()
            ->first();
    }

    /**
     * Start a new scout search.
     */
    public function startSearch(Game $game, array $filters): ScoutReport
    {
        $weeks = $this->calculateSearchWeeks($filters, $game);

        return ScoutReport::create([
            'game_id' => $game->id,
            'status' => ScoutReport::STATUS_SEARCHING,
            'filters' => $filters,
            'weeks_total' => $weeks,
            'weeks_remaining' => $weeks,
        ]);
    }

    /**
     * Cancel an active scout search.
     */
    public function cancelSearch(ScoutReport $report): void
    {
        $report->update(['status' => ScoutReport::STATUS_CANCELLED]);
    }

    /**
     * Calculate how many weeks a search takes.
     * Higher scouting tier = faster searches.
     */
    public function calculateSearchWeeks(array $filters, ?Game $game = null): int
    {
        $position = $filters['position'] ?? '';
        $league = $filters['league'] ?? null;

        // Base weeks calculation
        $baseWeeks = 2; // Medium search

        // Broad search (position group like "any defender")
        if (str_starts_with($position, 'any_')) {
            $baseWeeks = 3;
        }
        // Narrow search (specific position + specific league)
        elseif ($league && $league !== 'all') {
            $baseWeeks = 1;
        }

        // Apply scouting tier reduction
        if ($game) {
            $tier = $game->currentInvestment?->scouting_tier ?? 1;
            $reduction = self::SCOUTING_TIER_EFFECTS[$tier][0] ?? 0;
            $baseWeeks = max(1, $baseWeeks - $reduction);
        }

        return $baseWeeks;
    }

    /**
     * Tick scout search progress. Called on matchday advance.
     * If search completes, generates results.
     */
    public function tickSearch(Game $game): ?ScoutReport
    {
        $report = ScoutReport::where('game_id', $game->id)
            ->where('status', ScoutReport::STATUS_SEARCHING)
            ->first();

        if (! $report) {
            return null;
        }

        $completed = $report->tickWeek();

        if ($completed) {
            $this->generateResults($game, $report);
        }

        return $report;
    }

    /**
     * Generate scout results for a completed search.
     */
    public function generateResults(Game $game, ScoutReport $report): void
    {
        $filters = $report->filters;
        $positions = self::POSITION_MAP[$filters['position']] ?? [];

        $query = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->where('team_id', '!=', $game->team_id)
            ->whereIn('position', $positions);

        // League filter
        if (! empty($filters['league']) && $filters['league'] !== 'all') {
            $leagueTeamIds = Team::whereHas('competitions', function ($q) use ($filters) {
                $q->where('competitions.id', $filters['league']);
            })->pluck('id');

            $query->whereIn('team_id', $leagueTeamIds);
        }

        // Age filter
        if (! empty($filters['age_min']) || ! empty($filters['age_max'])) {
            $query->whereHas('player', function ($q) use ($filters) {
                if (! empty($filters['age_min'])) {
                    $q->where('age', '>=', $filters['age_min']);
                }
                if (! empty($filters['age_max'])) {
                    $q->where('age', '<=', $filters['age_max']);
                }
            });
        }

        // Max budget filter
        if (! empty($filters['max_budget'])) {
            $budgetCents = $filters['max_budget'] * 100; // Convert euros to cents
            $query->where('market_value_cents', '<=', $budgetCents);
        }

        // Exclude players already on loan
        $loanedPlayerIds = Loan::where('game_id', $game->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->pluck('game_player_id');

        $query->whereNotIn('id', $loanedPlayerIds);

        // Exclude players with agreed transfers
        $agreedPlayerIds = TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->pluck('game_player_id');

        $query->whereNotIn('id', $agreedPlayerIds);

        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            $report->update([
                'status' => ScoutReport::STATUS_COMPLETED,
                'player_ids' => [],
            ]);

            return;
        }

        // Pre-load all team rosters for candidates to avoid N+1 queries
        $candidateTeamIds = $candidates->pluck('team_id')->unique();
        $teamRosters = GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $candidateTeamIds)
            ->get()
            ->groupBy('team_id');

        // Score each player by availability (lower importance = more available)
        $scored = $candidates->map(function ($player) use ($teamRosters) {
            $teammates = $teamRosters->get($player->team_id, collect());
            $importance = $this->calculatePlayerImportance($player, $teammates);

            return [
                'player' => $player,
                'importance' => $importance,
                'availability_score' => 1.0 - $importance + (mt_rand(0, 100) / 200), // Add randomness
            ];
        });

        // Sort by availability (highest = most available)
        $sorted = $scored->sortByDesc('availability_score');

        // Base result count: 5-8 players
        $baseCount = rand(5, 8);

        // Apply scouting tier bonus for extra results
        $tier = $game->currentInvestment?->scouting_tier ?? 1;
        $extraResults = self::SCOUTING_TIER_EFFECTS[$tier][1] ?? 0;

        // Take players, biased toward available ones but include 1-2 stretch targets
        $count = min($candidates->count(), $baseCount + $extraResults);

        // Get the most available ones
        $available = $sorted->take(max($count - 2, 3));

        // Add 1-2 stretch targets (high importance but good stats)
        $stretchTargets = $sorted->filter(fn ($s) => $s['importance'] > 0.6)
            ->sortByDesc(fn ($s) => $s['player']->overall_score)
            ->take(min(2, $count - $available->count()));

        $selected = $available->merge($stretchTargets)->unique(fn ($s) => $s['player']->id)->take($count);

        $playerIds = $selected->pluck('player.id')->values()->toArray();

        $report->update([
            'status' => ScoutReport::STATUS_COMPLETED,
            'player_ids' => $playerIds,
        ]);
    }

    // =========================================
    // ASKING PRICE CALCULATION
    // =========================================

    /**
     * Calculate the AI's asking price for a player.
     */
    public function calculateAskingPrice(GamePlayer $player): int
    {
        $base = $player->market_value_cents;
        $importance = $this->calculatePlayerImportance($player);

        // Importance multiplier: 1.0x for worst, 2.0x for best
        $importanceMultiplier = 1.0 + ($importance * 1.0);

        // Contract modifier
        $contractModifier = $this->getContractModifier($player);

        // Age modifier
        $ageModifier = $this->getAgeModifier($player->age);

        $askingPrice = $base * $importanceMultiplier * $contractModifier * $ageModifier;

        // Round to nearest €100K (in cents)
        return (int) (round($askingPrice / 10_000_000) * 10_000_000);
    }

    /**
     * Calculate player importance within their team (0.0 to 1.0).
     *
     * @param GamePlayer $player
     * @param Collection|null $teammates Pre-loaded teammates to avoid repeated queries
     */
    public function calculatePlayerImportance(GamePlayer $player, ?Collection $teammates = null): float
    {
        if ($teammates === null) {
            $teammates = GamePlayer::where('game_id', $player->game_id)
                ->where('team_id', $player->team_id)
                ->get();
        }

        if ($teammates->isEmpty()) {
            return 0.5;
        }

        // Rank by overall ability (technical + physical average)
        $sorted = $teammates->sortByDesc(function ($p) {
            return ($p->current_technical_ability + $p->current_physical_ability) / 2;
        })->values();

        $rank = $sorted->search(fn ($p) => $p->id === $player->id);

        if ($rank === false) {
            return 0.5;
        }

        // Convert rank to 0.0-1.0 scale (0 = worst, 1 = best)
        $total = $sorted->count();

        return 1.0 - ($rank / max($total - 1, 1));
    }

    /**
     * Get contract years modifier for asking price.
     */
    private function getContractModifier(GamePlayer $player): float
    {
        if (! $player->contract_until) {
            return 0.5;
        }

        $game = $player->game;
        $yearsLeft = $player->contract_until->diffInYears($game->current_date);

        if ($yearsLeft >= 4) {
            return 1.2;
        }
        if ($yearsLeft >= 3) {
            return 1.1;
        }
        if ($yearsLeft >= 2) {
            return 1.0;
        }
        if ($yearsLeft >= 1) {
            return 0.85;
        }

        return 0.5; // Expiring
    }

    /**
     * Get age modifier for asking price.
     */
    private function getAgeModifier(int $age): float
    {
        if ($age < 23) {
            return 1.15;
        }
        if ($age <= 29) {
            return 1.0;
        }

        return max(0.5, 1.0 - ($age - 29) * 0.05);
    }

    // =========================================
    // TRANSFER BID EVALUATION
    // =========================================

    /**
     * Evaluate a transfer bid from the user.
     *
     * @return array{result: string, counter_amount: int|null, message: string}
     */
    public function evaluateBid(GamePlayer $player, int $bidAmount): array
    {
        $askingPrice = $this->calculateAskingPrice($player);
        $ratio = $bidAmount / max($askingPrice, 1);
        $isKeyPlayer = $this->isKeyPlayer($player);

        $acceptThreshold = $isKeyPlayer ? 1.05 : 0.95;
        $counterThreshold = $isKeyPlayer ? 0.85 : 0.75;

        if ($ratio >= $acceptThreshold) {
            return [
                'result' => 'accepted',
                'counter_amount' => null,
                'asking_price' => $askingPrice,
                'message' => $player->team->name.' have accepted your bid.',
            ];
        }

        if ($ratio >= $counterThreshold) {
            $counterAmount = (int) (($bidAmount + $askingPrice) / 2);
            $counterAmount = (int) (round($counterAmount / 10_000_000) * 10_000_000);

            // If rounding makes counter equal to bid, just accept the bid
            if ($counterAmount <= $bidAmount) {
                return [
                    'result' => 'accepted',
                    'counter_amount' => null,
                    'asking_price' => $askingPrice,
                    'message' => $player->team->name.' have accepted your bid.',
                ];
            }

            return [
                'result' => 'counter',
                'counter_amount' => $counterAmount,
                'asking_price' => $askingPrice,
                'message' => $player->team->name.' have made a counter-offer of '.Money::format($counterAmount).'.',
            ];
        }

        return [
            'result' => 'rejected',
            'counter_amount' => null,
            'asking_price' => $askingPrice,
            'message' => $player->team->name.' have rejected your bid. It was too far below their valuation.',
        ];
    }

    /**
     * Check if player is a key player (top 3 by ability on their team).
     */
    private function isKeyPlayer(GamePlayer $player): bool
    {
        $importance = $this->calculatePlayerImportance($player);

        return $importance > 0.85; // Roughly top 3 out of ~25 players
    }

    // =========================================
    // LOAN REQUEST EVALUATION
    // =========================================

    /**
     * Evaluate a loan request from the user.
     *
     * @return array{result: string, message: string}
     */
    public function evaluateLoanRequest(GamePlayer $player): array
    {
        $importance = $this->calculatePlayerImportance($player);

        if ($importance > 0.7) {
            return [
                'result' => 'rejected',
                'message' => $player->team->name.' rejected the loan request. '.$player->name.' is a key player for them.',
            ];
        }

        if ($importance > 0.4) {
            // 50% chance
            if (rand(0, 1) === 1) {
                return [
                    'result' => 'accepted',
                    'message' => $player->team->name.' have agreed to loan '.$player->name.' to your club.',
                ];
            }

            return [
                'result' => 'rejected',
                'message' => $player->team->name.' decided to keep '.$player->name.' for now.',
            ];
        }

        return [
            'result' => 'accepted',
            'message' => $player->team->name.' have agreed to loan '.$player->name.' to your club.',
        ];
    }

    // =========================================
    // WAGE DEMAND
    // =========================================

    /**
     * Calculate the wage a player would demand to join.
     */
    public function calculateWageDemand(GamePlayer $player): int
    {
        $minimumWage = $this->contractService->getMinimumWageForTeam($player->team);

        $wage = $this->contractService->calculateAnnualWage(
            $player->market_value_cents,
            $minimumWage,
            $player->age,
        );

        // Round to nearest 100K (cents)
        return (int) (round($wage / 10_000_000) * 10_000_000);
    }

    // =========================================
    // SCOUTING REPORT DATA
    // =========================================

    /**
     * Get scouting detail for a specific player.
     */
    public function getPlayerScoutingDetail(GamePlayer $player, Game $game): array
    {
        $askingPrice = $this->calculateAskingPrice($player);
        $wageDemand = $this->calculateWageDemand($player);
        $importance = $this->calculatePlayerImportance($player);

        $investment = $game->currentInvestment;
        $finances = $game->currentFinances;
        $canAffordFee = $investment ? $askingPrice <= $investment->transfer_budget : false;
        $currentWageBill = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');
        // Allow up to 10% over projected wages for flexibility
        $maxWages = $finances ? (int) ($finances->projected_wages * 1.10) : 0;
        $canAffordWage = ($currentWageBill + $wageDemand) <= $maxWages;

        // Fuzzy ability range - higher scouting tier = more accurate
        $techAbility = $player->current_technical_ability;
        $physAbility = $player->current_physical_ability;

        $tier = $game->currentInvestment?->scouting_tier ?? 1;
        $fuzzReduction = self::SCOUTING_TIER_EFFECTS[$tier][2] ?? 0;
        $baseFuzz = rand(3, 7);
        $fuzz = max(1, $baseFuzz - $fuzzReduction);

        return [
            'player' => $player,
            'asking_price' => $askingPrice,
            'formatted_asking_price' => Money::format($askingPrice),
            'wage_demand' => $wageDemand,
            'formatted_wage_demand' => Money::format($wageDemand),
            'importance' => $importance,
            'can_afford_fee' => $canAffordFee,
            'can_afford_wage' => $canAffordWage,
            'transfer_budget' => $finances?->transfer_budget ?? 0,
            'formatted_transfer_budget' => $finances ? $finances->formatted_transfer_budget : '€ 0',
            'tech_range' => [max(1, $techAbility - $fuzz), min(99, $techAbility + $fuzz)],
            'phys_range' => [max(1, $physAbility - $fuzz), min(99, $physAbility + $fuzz)],
        ];
    }
}
