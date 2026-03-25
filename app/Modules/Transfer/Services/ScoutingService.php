<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use App\Support\Money;
use App\Support\PositionMapper;
use Illuminate\Support\Collection;
use App\Modules\Player\PlayerAge;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Transfer\Services\ContractService;

class ScoutingService
{
    /**
     * Acceptance probability modifiers based on reputation gap (source - offering).
     * Gap ≤ 0 means moving up or lateral → no penalty.
     */
    private const REPUTATION_GAP_MODIFIERS = [
        0 => 1.00,
        1 => 0.75,
        2 => 0.45,
        3 => 0.20,
        4 => 0.08,
        5 => 0.02,
    ];

    /**
     * Minimum team reputation required for a free agent to accept, based on player tier.
     * Higher-tier free agents demand higher-reputation clubs.
     */
    private const MIN_REPUTATION_BY_PLAYER_TIER = [
        5 => ClubProfile::REPUTATION_CONTINENTAL,  // €50M+ World Class → need continental+
        4 => ClubProfile::REPUTATION_ESTABLISHED,   // €20M+ Excellent → need established+
        3 => ClubProfile::REPUTATION_MODEST,         // €5M+ Good → need modest+
        2 => ClubProfile::REPUTATION_LOCAL,           // €1M+ Average → any team
        1 => ClubProfile::REPUTATION_LOCAL,           // <€1M Developing → any team
    ];

    /** Default modifier for gaps of 5+. */
    private const REPUTATION_GAP_MAX_MODIFIER = 0.02;

    /**
     * Wage premium multipliers for players on expiring contracts (pre-contract signings).
     * Keyed by minimum market value in cents.
     * Checked in descending order — first match wins.
     */
    private const FREE_AGENT_WAGE_PREMIUMS = [
        10_000_000_000 => 1.50, // €100M+
        5_000_000_000  => 1.45, // €50M+
        2_000_000_000  => 1.40, // €20M+
        1_000_000_000  => 1.35, // €10M+
        500_000_000    => 1.30, // €5M+
        200_000_000    => 1.25, // €2M+
        0              => 1.20, // < €2M
    ];

    /**
     * Scouting tier effects on searches.
     * [weeks_reduction, extra_results, ability_fuzz_reduction]
     */
    private const SCOUTING_TIER_EFFECTS = [
        0 => [0, 0, 0],   // No scouting department
        1 => [0, 0, 0],   // Basic - domestic only, baseline
        2 => [0, 1, 2],   // Good - domestic only, 1 extra result, 2 less fuzz
        3 => [1, 2, 4],   // Excellent - international, 1 week faster, 2 extra results, 4 less fuzz
        4 => [1, 3, 6],   // World-class - international, 1 week faster, 3 extra results, 6 less fuzz
    ];

    /** Minimum scouting tier required for international searches. */
    private const INTERNATIONAL_SEARCH_MIN_TIER = 3;

    /**
     * Tracking tier configuration.
     * [max_concurrent_slots, matchdays_to_level_1, matchdays_to_level_2]
     */
    private const TRACKING_TIER_CONFIG = [
        0 => [0, 0, 0],
        1 => [1, 1, 3],
        2 => [2, 1, 2],
        3 => [3, 1, 2],
        4 => [4, 1, 1],
    ];

    /** Maximum players on the shortlist at any time. */
    public const MAX_SHORTLIST_SIZE = 20;

    /** Maximum scout search reports kept at any time. */
    public const MAX_SEARCH_HISTORY = 20;

    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    /**
     * Check if a game's scouting tier allows international searches.
     */
    public function canSearchInternationally(Game $game): bool
    {
        $tier = $game->currentInvestment->scouting_tier ?? 1;

        return $tier >= self::INTERNATIONAL_SEARCH_MIN_TIER;
    }

    // =========================================
    // SCOUT SEARCH
    // =========================================


    /**
     * Get the currently searching scout report for a game.
     */
    public function getActiveReport(Game $game): ?ScoutReport
    {
        return ScoutReport::where('game_id', $game->id)
            ->where('status', ScoutReport::STATUS_SEARCHING)
            ->first();
    }

    /**
     * Get all completed search reports for a game, ordered by most recent.
     */
    public function getSearchHistory(Game $game): Collection
    {
        return ScoutReport::where('game_id', $game->id)
            ->where('status', ScoutReport::STATUS_COMPLETED)
            ->orderByDesc('game_date')
            ->get();
    }

    /**
     * Start a new scout search.
     */
    public function startSearch(Game $game, array $filters): ScoutReport
    {
        // Enforce domestic-only scope for low scouting tiers
        if (!$this->canSearchInternationally($game)) {
            $filters['scope'] = ['domestic'];
        }

        $weeks = $this->calculateSearchWeeks($filters, $game);

        return ScoutReport::create([
            'game_id' => $game->id,
            'status' => ScoutReport::STATUS_SEARCHING,
            'filters' => $filters,
            'weeks_total' => $weeks,
            'weeks_remaining' => $weeks,
            'game_date' => $game->current_date,
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
        $scope = $filters['scope'] ?? ['domestic', 'international'];

        // Base weeks calculation
        $baseWeeks = 2; // Medium search

        // Broad search (position group like "any defender")
        if (str_starts_with($position, 'any_')) {
            $baseWeeks = 3;
        }
        // Narrow search (specific position + domestic only)
        elseif (count($scope) === 1 && in_array('domestic', $scope)) {
            $baseWeeks = 1;
        }

        // Apply scouting tier reduction
        if ($game) {
            $tier = $game->currentInvestment->scouting_tier ?? 1;
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
        $positions = PositionMapper::getPositionsForFilter($filters['position']) ?? [];

        $query = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $game->team_id)
            ->whereIn('position', $positions);

        // Scope filter (domestic / international) — enforce tier restriction
        $scope = $filters['scope'] ?? ['domestic', 'international'];
        if (!$this->canSearchInternationally($game)) {
            $scope = ['domestic'];
        }
        if (count($scope) === 1) {
            $teamCountry = $game->country;
            $scopeCompetitionIds = Competition::where('country', in_array('domestic', $scope) ? '=' : '!=', $teamCountry)
                ->pluck('id');
            $scopeTeamIds = Team::whereHas('competitions', function ($q) use ($scopeCompetitionIds) {
                $q->whereIn('competitions.id', $scopeCompetitionIds);
            })->pluck('id');
            $query->whereIn('team_id', $scopeTeamIds);
        }

        // Age filter (age is computed from players.date_of_birth, not a column)
        if (! empty($filters['age_min']) || ! empty($filters['age_max'])) {
            /** @var \Illuminate\Database\Connection $connection */
            $connection = $query->getQuery()->getConnection();
            $driver = $connection->getDriverName();
            $dobSubquery = '(SELECT date_of_birth FROM players WHERE players.id = game_players.player_id)';
            $gameDate = $game->current_date->toDateString();

            if ($driver === 'pgsql') {
                $ageExpr = "EXTRACT(YEAR FROM AGE(?::date, $dobSubquery))";
            } else {
                $ageExpr = "(strftime('%Y', ?) - strftime('%Y', $dobSubquery))";
            }

            if (! empty($filters['age_min'])) {
                $query->whereRaw("($ageExpr) >= ?", [$gameDate, (int) $filters['age_min']]);
            }
            if (! empty($filters['age_max'])) {
                $query->whereRaw("($ageExpr) <= ?", [$gameDate, (int) $filters['age_max']]);
            }
        }

        // Ability filter
        if (! empty($filters['ability_min']) || ! empty($filters['ability_max'])) {
            $query->where(function ($q) use ($filters) {
                $abilityExpr = '(COALESCE(game_players.game_technical_ability, (SELECT technical_ability FROM players WHERE players.id = game_players.player_id)) + COALESCE(game_players.game_physical_ability, (SELECT physical_ability FROM players WHERE players.id = game_players.player_id))) / 2';
                if (! empty($filters['ability_min'])) {
                    $q->whereRaw("($abilityExpr) >= ?", [(int) $filters['ability_min']]);
                }
                if (! empty($filters['ability_max'])) {
                    $q->whereRaw("($abilityExpr) <= ?", [(int) $filters['ability_max']]);
                }
            });
        }

        // Market value range filter
        if (! empty($filters['value_min'])) {
            $query->where('market_value_cents', '>=', $filters['value_min'] * 100);
        }
        if (! empty($filters['value_max'])) {
            $query->where('market_value_cents', '<=', $filters['value_max'] * 100);
        }

        // Expiring contract filter (last year of contract)
        $seasonEnd = $game->getSeasonEndDate();
        if (! empty($filters['expiring_contract'])) {
            $query->whereNotNull('contract_until')
                ->where('contract_until', '<=', $seasonEnd);
        } else {
            $query->where(function ($q) use ($seasonEnd) {
                $q->whereNull('contract_until')
                    ->orWhere('contract_until', '>', $seasonEnd);
            });
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

        // Also include free agents matching the position filter
        $freeAgentQuery = GamePlayer::with(['player'])
            ->where('game_id', $game->id)
            ->whereNull('team_id')
            ->whereIn('position', $positions);

        if (! empty($filters['age_min']) || ! empty($filters['age_max'])) {
            /** @var \Illuminate\Database\Connection $connection */
            $connection = $freeAgentQuery->getQuery()->getConnection();
            $driver = $connection->getDriverName();
            $dobSubquery = '(SELECT date_of_birth FROM players WHERE players.id = game_players.player_id)';
            $gameDate = $game->current_date->toDateString();
            $ageExpr = $driver === 'pgsql'
                ? "EXTRACT(YEAR FROM AGE(?::date, $dobSubquery))"
                : "(strftime('%Y', ?) - strftime('%Y', $dobSubquery))";
            if (! empty($filters['age_min'])) {
                $freeAgentQuery->whereRaw("($ageExpr) >= ?", [$gameDate, (int) $filters['age_min']]);
            }
            if (! empty($filters['age_max'])) {
                $freeAgentQuery->whereRaw("($ageExpr) <= ?", [$gameDate, (int) $filters['age_max']]);
            }
        }

        $freeAgents = $freeAgentQuery->get();
        $candidates = $candidates->merge($freeAgents);

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
        $tier = $game->currentInvestment->scouting_tier ?? 1;
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
        $ageModifier = $this->getAgeModifier($player->age($player->game->current_date));

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
        // Free agents have no team context — return neutral importance
        if ($player->team_id === null) {
            return 0.0;
        }

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
        $yearsLeft = $game->current_date->diffInYears($player->contract_until);

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
        if ($age < PlayerAge::YOUNG_END) {
            return 1.15;
        }
        if ($age <= PlayerAge::PRIME_END) {
            return 1.0;
        }

        return max(0.5, 1.0 - ($age - PlayerAge::PRIME_END) * 0.05);
    }

    // =========================================
    // TRANSFER BID EVALUATION
    // =========================================

    /**
     * Evaluate a transfer bid from the user.
     *
     * @return array{result: string, counter_amount: int|null, message: string}
     */
    /**
     * @return array{result: string, counter_amount: int|null, asking_price: int, message: string}
     */
    public function evaluateBid(GamePlayer $player, int $bidAmount, ?Game $game = null, ?int $previousCounter = null): array
    {
        // Reputation gate: player may refuse to join a lower-reputation club
        if ($game) {
            $reputationModifier = $this->calculateReputationModifier($game->team, $player);
            if ($reputationModifier < 1.0 && rand(1, 100) > (int) ($reputationModifier * 100)) {
                return [
                    'result' => 'rejected',
                    'counter_amount' => null,
                    'asking_price' => $this->calculateAskingPrice($player),
                    'message' => __('transfers.bid_rejected_not_interested', ['team' => $player->team?->name]),
                ];
            }
        }

        $askingPrice = $this->calculateAskingPrice($player);

        // Use the previous counter as ceiling so the club never raises their demand
        $ceiling = ($previousCounter !== null && $previousCounter < $askingPrice)
            ? $previousCounter
            : $askingPrice;

        $ratio = $bidAmount / max($ceiling, 1);
        $isKeyPlayer = $this->isKeyPlayer($player);

        $acceptThreshold = $isKeyPlayer ? 1.05 : 0.95;
        $counterThreshold = $isKeyPlayer ? 0.85 : 0.75;

        if ($ratio >= $acceptThreshold) {
            return [
                'result' => 'accepted',
                'counter_amount' => null,
                'asking_price' => $askingPrice,
                'message' => __('transfers.bid_accepted', ['team' => $player->team?->name]),
            ];
        }

        if ($ratio >= $counterThreshold) {
            $counterAmount = (int) (($bidAmount + $ceiling) / 2);
            $counterAmount = (int) (round($counterAmount / 10_000_000) * 10_000_000);

            // If rounding makes counter equal to bid, just accept the bid
            if ($counterAmount <= $bidAmount) {
                return [
                    'result' => 'accepted',
                    'counter_amount' => null,
                    'asking_price' => $askingPrice,
                    'message' => __('transfers.bid_accepted', ['team' => $player->team?->name]),
                ];
            }

            return [
                'result' => 'counter',
                'counter_amount' => $counterAmount,
                'asking_price' => $askingPrice,
                'message' => __('transfers.counter_offer_made', ['team' => $player->team?->name, 'amount' => Money::format($counterAmount)]),
            ];
        }

        return [
            'result' => 'rejected',
            'counter_amount' => null,
            'asking_price' => $askingPrice,
            'message' => __('transfers.bid_rejected_too_low', ['team' => $player->team?->name]),
        ];
    }

    /**
     * Evaluate the user's counter-offer from the AI buyer's perspective.
     *
     * Called when the user counters an unsolicited or listed offer with a higher asking price.
     * The AI club evaluates whether to accept, counter, or walk away.
     *
     * @return array{result: string, counter_amount: int|null}
     */
    public function evaluateCounterOffer(TransferOffer $offer, int $userAskingPrice, Game $game): array
    {
        $player = $offer->gamePlayer;
        $marketValue = $player->market_value_cents;

        // Calculate AI club's squad value to determine budget ceiling
        $offeringTeamSquadValue = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $offer->offering_team_id)
            ->sum('market_value_cents');

        // AI club's max willingness: min of squad-value ceiling and market-value ceiling
        $squadValueCeiling = (int) ($offeringTeamSquadValue * 0.25);
        $marketValueCeiling = (int) ($marketValue * 1.30);
        $maxWillingness = min($squadValueCeiling, $marketValueCeiling);

        // Ensure max willingness is at least the current offer
        $maxWillingness = max($maxWillingness, $offer->transfer_fee);

        if ($userAskingPrice <= (int) ($maxWillingness * 0.95)) {
            return [
                'result' => 'accepted',
                'counter_amount' => null,
            ];
        }

        if ($userAskingPrice <= (int) ($maxWillingness * 1.15)) {
            // Counter with midpoint of user's ask and AI's current bid, rounded to nearest €100K
            $counterAmount = (int) (($userAskingPrice + $offer->transfer_fee) / 2);
            $counterAmount = (int) (round($counterAmount / 10_000_000) * 10_000_000);

            // If rounding makes counter equal to or below the current bid, just accept
            if ($counterAmount <= $offer->transfer_fee) {
                return [
                    'result' => 'accepted',
                    'counter_amount' => null,
                ];
            }

            return [
                'result' => 'countered',
                'counter_amount' => $counterAmount,
            ];
        }

        return [
            'result' => 'rejected',
            'counter_amount' => null,
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
    public function evaluateLoanRequest(GamePlayer $player, ?Game $game = null): array
    {
        // Reputation gate: player may refuse to join a lower-reputation club
        if ($game) {
            $reputationModifier = $this->calculateReputationModifier($game->team, $player);
            if ($reputationModifier < 1.0 && rand(1, 100) > (int) ($reputationModifier * 100)) {
                return [
                    'result' => 'rejected',
                    'message' => __('transfers.loan_rejected_not_interested', ['player' => $player->name]),
                ];
            }
        }

        $importance = $this->calculatePlayerImportance($player);

        if ($importance > 0.7) {
            return [
                'result' => 'rejected',
                'message' => __('transfers.loan_rejected_key_player', ['team' => $player->team?->name, 'player' => $player->name]),
            ];
        }

        if ($importance > 0.4) {
            // 50% chance
            if (rand(0, 1) === 1) {
                return [
                    'result' => 'accepted',
                    'message' => __('transfers.loan_accepted', ['team' => $player->team?->name, 'player' => $player->name]),
                ];
            }

            return [
                'result' => 'rejected',
                'message' => __('transfers.loan_rejected_keep', ['team' => $player->team?->name, 'player' => $player->name]),
            ];
        }

        return [
            'result' => 'accepted',
            'message' => __('transfers.loan_accepted', ['team' => $player->team?->name, 'player' => $player->name]),
        ];
    }

    // =========================================
    // SYNCHRONOUS LOAN EVALUATION
    // =========================================

    /**
     * Deterministic loan request evaluation for sync negotiation.
     * Returns result, asking loan fee, mood, and rejection reason.
     *
     * @return array{result: string, loan_fee: int, disposition: float, rejection_reason: ?string}
     */
    public function evaluateLoanRequestSync(GamePlayer $player, Game $game): array
    {
        // Gate 1: Reputation — club won't negotiate with low-rep teams
        $reputationModifier = $this->calculateReputationModifier($game->team, $player);
        if ($reputationModifier < 0.50) {
            return [
                'result' => 'rejected',
                'disposition' => 0.10,
                'rejection_reason' => 'reputation',
            ];
        }

        $importance = $this->calculatePlayerImportance($player);

        // Gate 1: Key player — club refuses to loan
        if ($importance > 0.70) {
            return [
                'result' => 'rejected',
                'disposition' => 0.15,
                'rejection_reason' => 'key_player',
            ];
        }

        // Calculate disposition for mood indicator
        $disposition = 0.50;
        $disposition += (1.0 - $importance) * 0.30;
        $disposition += ($reputationModifier - 0.50) * 0.20;
        $disposition = max(0.10, min(0.95, $disposition));

        // Gate 2: Player willingness — player may not want to join
        $willingness = $this->calculateWillingness($player, $game, $importance);
        if (in_array($willingness['label'], ['not_interested', 'reluctant'])) {
            return [
                'result' => 'rejected',
                'disposition' => $disposition,
                'rejection_reason' => 'player_refused',
            ];
        }

        return [
            'result' => 'accepted',
            'disposition' => $disposition,
            'rejection_reason' => null,
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
        $minimumWage = $player->team
            ? $this->contractService->getMinimumWageForTeam($player->team)
            : $this->contractService->getDefaultMinimumWage();

        $wage = $this->contractService->calculateAnnualWage(
            $player->market_value_cents,
            $minimumWage,
            $player->age($player->game->current_date),
            deterministic: true,
        );

        // Round to nearest 100K (cents)
        return (int) (round($wage / 10_000_000) * 10_000_000);
    }

    /**
     * Calculate the wage a player on an expiring contract demands for a pre-contract signing.
     * Applies a premium on top of the base wage demand (represents signing bonus + agent fees + leverage).
     */
    public function calculatePreContractWageDemand(GamePlayer $player): int
    {
        $baseWage = $this->calculateWageDemand($player);
        $premium = $this->getFreeAgentWagePremium($player->market_value_cents);

        // Round to nearest 100K (cents)
        return (int) (round(($baseWage * $premium) / 10_000_000) * 10_000_000);
    }

    /**
     * Get the free agent wage premium multiplier based on market value.
     */
    private function getFreeAgentWagePremium(int $marketValueCents): float
    {
        foreach (self::FREE_AGENT_WAGE_PREMIUMS as $minValue => $premium) {
            if ($marketValueCents >= $minValue) {
                return $premium;
            }
        }

        return 1.20;
    }

    // =========================================
    // REPUTATION GATE
    // =========================================

    /**
     * Calculate the acceptance probability modifier based on reputation gap.
     * Compares the player's current team reputation to the bidding team's reputation.
     *
     * @return float Modifier between 0.02 and 1.0
     */
    public function calculateReputationModifier(Team $biddingTeam, GamePlayer $player): float
    {
        // Free agents have no current team context — no penalty
        if ($player->team_id === null) {
            return 1.0;
        }

        $gameId = $player->game_id;
        $sourceReputation = $gameId && $player->team_id
            ? TeamReputation::resolveLevel($gameId, $player->team_id)
            : ($player->team?->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_LOCAL);
        $offeringReputation = $gameId
            ? TeamReputation::resolveLevel($gameId, $biddingTeam->id)
            : ($biddingTeam->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_LOCAL);

        $sourceIndex = ClubProfile::getReputationTierIndex($sourceReputation);
        $offeringIndex = ClubProfile::getReputationTierIndex($offeringReputation);

        $gap = $sourceIndex - $offeringIndex;

        if ($gap <= 0) {
            return 1.0; // Moving up or lateral
        }

        return self::REPUTATION_GAP_MODIFIERS[$gap] ?? self::REPUTATION_GAP_MAX_MODIFIER;
    }

    // =========================================
    // FREE AGENT REPUTATION GATE
    // =========================================

    /**
     * Check whether a free agent is willing to sign for a given team,
     * based on the player's tier vs the team's reputation.
     */
    public function canSignFreeAgent(GamePlayer $player, string $gameId, string $teamId): bool
    {
        return $this->getFreeAgentWillingnessLevel($player, $gameId, $teamId) === 'willing';
    }

    /**
     * Determine a free agent's willingness to sign for a team.
     *
     * @return string 'willing' (will sign), 'reluctant' (1 tier below minimum), or 'unwilling' (2+ below)
     */
    public function getFreeAgentWillingnessLevel(GamePlayer $player, string $gameId, string $teamId): string
    {
        $playerTier = $player->tier ?? PlayerTierService::tierFromMarketValue($player->market_value_cents);
        $minReputation = self::MIN_REPUTATION_BY_PLAYER_TIER[$playerTier] ?? ClubProfile::REPUTATION_LOCAL;

        $teamReputation = TeamReputation::resolveLevel($gameId, $teamId);

        $teamIndex = ClubProfile::getReputationTierIndex($teamReputation);
        $minIndex = ClubProfile::getReputationTierIndex($minReputation);

        $gap = $minIndex - $teamIndex;

        if ($gap <= 0) {
            return 'willing';
        }

        if ($gap === 1) {
            return 'reluctant';
        }

        return 'unwilling';
    }

    // =========================================
    // SCOUTING REPORT DATA
    // =========================================

    /**
     * Evaluate whether a player accepts a pre-contract offer based on offered wage vs demand,
     * reputation gap, and free agent wage premium.
     *
     * @return array{accepted: bool, message: string}
     */
    public function evaluatePreContractOffer(GamePlayer $player, int $offeredWage, Team $biddingTeam): array
    {
        $wageDemand = $this->calculatePreContractWageDemand($player);

        if ($offeredWage >= $wageDemand) {
            $baseChance = 85;
        } elseif ($offeredWage >= (int) ($wageDemand * 0.85)) {
            $baseChance = 40;
        } else {
            return [
                'accepted' => false,
                'message' => __('messages.pre_contract_rejected', ['player' => $player->name]),
            ];
        }

        // Apply reputation modifier
        $reputationModifier = $this->calculateReputationModifier($biddingTeam, $player);
        $finalChance = (int) ($baseChance * $reputationModifier);

        $accepted = rand(1, 100) <= $finalChance;

        if ($accepted) {
            return [
                'accepted' => true,
                'message' => __('messages.pre_contract_accepted', ['player' => $player->name]),
            ];
        }

        return [
            'accepted' => false,
            'message' => __('messages.pre_contract_rejected', ['player' => $player->name]),
        ];
    }

    /**
     * Get scouting detail for a specific player.
     */
    public function getPlayerScoutingDetail(GamePlayer $player, Game $game): array
    {
        $isFreeAgent = $player->team_id === null;
        $askingPrice = $isFreeAgent ? 0 : $this->calculateAskingPrice($player);
        $wageDemand = $this->calculateWageDemand($player);
        $importance = $isFreeAgent ? 0.0 : $this->calculatePlayerImportance($player);

        // For expiring-contract players, show the premium wage demand
        $isExpiring = $player->contract_until && $player->contract_until <= $game->getSeasonEndDate();
        $preContractWageDemand = $isExpiring ? $this->calculatePreContractWageDemand($player) : null;

        $investment = $game->currentInvestment;
        $committedBudget = TransferOffer::committedBudget($game->id);
        $availableBudget = ($investment->transfer_budget ?? 0) - $committedBudget;
        $canAffordFee = $askingPrice <= $availableBudget;
        $canAffordLoan = $isFreeAgent || $wageDemand <= $availableBudget;

        // Fuzzy ability range - higher scouting tier = more accurate
        $techAbility = $player->current_technical_ability;
        $physAbility = $player->current_physical_ability;

        $tier = $game->currentInvestment->scouting_tier ?? 1;
        $fuzzReduction = self::SCOUTING_TIER_EFFECTS[$tier][2] ?? 0;
        $baseFuzz = rand(3, 7);
        $fuzz = max(1, $baseFuzz - $fuzzReduction);

        return [
            'player' => $player,
            'is_free_agent' => $isFreeAgent,
            'asking_price' => $askingPrice,
            'formatted_asking_price' => $isFreeAgent ? __('transfers.free_transfer') : Money::format($askingPrice),
            'wage_demand' => $wageDemand,
            'formatted_wage_demand' => Money::format($wageDemand),
            'pre_contract_wage_demand' => $preContractWageDemand,
            'importance' => $importance,
            'can_afford_fee' => $canAffordFee,
            'can_afford_loan' => $canAffordLoan,
            'available_budget' => $availableBudget,
            'transfer_budget' => $investment->transfer_budget ?? 0,
            'formatted_transfer_budget' => $investment ? $investment->formatted_transfer_budget : '€ 0',
            'tech_range' => [max(1, $techAbility - $fuzz), min(99, $techAbility + $fuzz)],
            'phys_range' => [max(1, $physAbility - $fuzz), min(99, $physAbility + $fuzz)],
        ];
    }

    // =========================================
    // PLAYER TRACKING
    // =========================================

    /**
     * Get tracking capacity info for a game.
     *
     * @return array{max_slots: int, used_slots: int, available_slots: int}
     */
    public function getTrackingCapacity(Game $game): array
    {
        $tier = $game->currentInvestment->scouting_tier ?? 0;
        $config = self::TRACKING_TIER_CONFIG[$tier] ?? self::TRACKING_TIER_CONFIG[0];
        $maxSlots = $config[0];

        $usedSlots = ShortlistedPlayer::where('game_id', $game->id)
            ->where('is_tracking', true)
            ->count();

        return [
            'max_slots' => $maxSlots,
            'used_slots' => $usedSlots,
            'available_slots' => max(0, $maxSlots - $usedSlots),
        ];
    }

    /**
     * Check if the shortlist is full.
     */
    public function isShortlistFull(Game $game): bool
    {
        return ShortlistedPlayer::where('game_id', $game->id)->count() >= self::MAX_SHORTLIST_SIZE;
    }

    /**
     * Start tracking a shortlisted player.
     */
    public function startTracking(ShortlistedPlayer $entry, Game $game): bool
    {
        if ($entry->is_tracking) {
            return false;
        }

        $capacity = $this->getTrackingCapacity($game);
        if ($capacity['available_slots'] <= 0) {
            return false;
        }

        $entry->update(['is_tracking' => true]);

        return true;
    }

    /**
     * Check if the search history is full.
     */
    public function isSearchHistoryFull(Game $game): bool
    {
        return ScoutReport::where('game_id', $game->id)
            ->whereIn('status', [ScoutReport::STATUS_SEARCHING, ScoutReport::STATUS_COMPLETED])
            ->count() >= self::MAX_SEARCH_HISTORY;
    }

    /**
     * Stop tracking a shortlisted player (retains gathered intel).
     */
    public function stopTracking(ShortlistedPlayer $entry): void
    {
        $entry->update(['is_tracking' => false]);
    }

    /**
     * Tick tracking progress for all tracked players. Called each matchday.
     * Returns entries that leveled up.
     */
    public function tickTracking(Game $game): Collection
    {
        $tier = $game->currentInvestment->scouting_tier ?? 0;
        $config = self::TRACKING_TIER_CONFIG[$tier] ?? self::TRACKING_TIER_CONFIG[0];
        $matchdaysToL1 = $config[1];
        $matchdaysToL2 = $config[1] + $config[2]; // cumulative

        $trackedEntries = ShortlistedPlayer::where('game_id', $game->id)
            ->where('is_tracking', true)
            ->where('intel_level', '<', ShortlistedPlayer::INTEL_DEEP)
            ->with('gamePlayer')
            ->get();

        $leveledUp = collect();

        foreach ($trackedEntries as $entry) {
            $newMatchdays = $entry->matchdays_tracked + 1;
            $oldLevel = $entry->intel_level;

            if ($newMatchdays >= $matchdaysToL2 && $oldLevel < ShortlistedPlayer::INTEL_DEEP) {
                $entry->update(['matchdays_tracked' => $newMatchdays, 'intel_level' => ShortlistedPlayer::INTEL_DEEP, 'is_tracking' => false]);
                $leveledUp->push($entry);
            } elseif ($newMatchdays >= $matchdaysToL1 && $oldLevel < ShortlistedPlayer::INTEL_REPORT) {
                $entry->update(['matchdays_tracked' => $newMatchdays, 'intel_level' => ShortlistedPlayer::INTEL_REPORT]);
                $leveledUp->push($entry);
            } else {
                $entry->update(['matchdays_tracked' => $newMatchdays]);
            }
        }

        return $leveledUp;
    }

    /**
     * Calculate a player's willingness to transfer (0-100 score mapped to label).
     *
     * @return array{score: int, label: string}
     */
    public function calculateWillingness(GamePlayer $player, Game $game, ?float $importance = null): array
    {
        $importance ??= $this->calculatePlayerImportance($player);

        // Base willingness: low importance players are more willing
        $score = (int) ((1.0 - $importance) * 50);

        // Contract length factor: fewer years left = more willing
        if ($player->contract_until) {
            $yearsLeft = max(0, $game->current_date->diffInYears($player->contract_until));
            if ($yearsLeft <= 1) {
                $score += 30;
            } elseif ($yearsLeft <= 2) {
                $score += 15;
            }
        } else {
            $score += 25; // No contract = very willing
        }

        // Age factor: older players at lower-rep clubs more open
        $age = $player->age($game->current_date);
        if ($age >= PlayerAge::PRIME_END) {
            $score += 10;
        } elseif ($age < PlayerAge::YOUNG_END) {
            $score += 5; // Young players seeking opportunities
        }

        // Reputation gap: penalize moving down, reward moving up
        $reputationModifier = $this->calculateReputationModifier($game->team, $player);
        if ($reputationModifier < 1.0) {
            // Moving down: scale the score down proportionally to the reputation gap
            $score = (int) ($score * $reputationModifier);
        } elseif ($player->team_id) {
            // Moving up: bonus based on how many tiers above the buying club is
            $sourceReputation = TeamReputation::resolveLevel($player->game_id, $player->team_id);
            $offeringReputation = TeamReputation::resolveLevel($player->game_id, $game->team_id);
            $sourceIndex = ClubProfile::getReputationTierIndex($sourceReputation);
            $offeringIndex = ClubProfile::getReputationTierIndex($offeringReputation);
            $upwardGap = $offeringIndex - $sourceIndex;

            if ($upwardGap >= 3) {
                $score += 30; // Dream move (e.g. local → elite)
            } elseif ($upwardGap === 2) {
                $score += 20; // Big step up
            } elseif ($upwardGap === 1) {
                $score += 10; // Step up
            }
        }

        $score = min(100, max(0, $score + rand(-5, 5)));

        $label = match (true) {
            $score >= 80 => 'very_interested',
            $score >= 60 => 'open',
            $score >= 40 => 'undecided',
            $score >= 20 => 'reluctant',
            default => 'not_interested',
        };

        return ['score' => $score, 'label' => $label];
    }

    /**
     * Calculate whether rival clubs are also interested in a player.
     */
    public function calculateRivalInterest(GamePlayer $player, ?float $importance = null): bool
    {
        $overallAbility = ($player->current_technical_ability + $player->current_physical_ability) / 2;
        $importance ??= $this->calculatePlayerImportance($player);

        // Higher ability + lower importance = more likely rivals want them
        $chance = ($overallAbility / 99) * 0.4 + (1.0 - $importance) * 0.3;

        return rand(1, 100) <= (int) ($chance * 100);
    }
}
