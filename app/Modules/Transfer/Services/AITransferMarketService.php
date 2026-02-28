<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Notification\Services\NotificationService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Simulates AI transfer market activity at the end of each transfer window.
 *
 * Called from MatchdayOrchestrator when a transfer window closes:
 * - Summer (September): free agent signings + AI-to-AI transfers (1-5 per team)
 * - Winter (February): remaining free agents + AI-to-AI transfers (1-3 per team)
 *
 * Two types of AI-to-AI transfers:
 * 1. Squad Clearing — surplus/backup players move to equal or lower reputation clubs
 * 2. Talent Upgrading — quality players move to equal or higher reputation clubs
 */
class AITransferMarketService
{
    /** Per-team transfer activity budget (summer: 1-5, winter: 1-3) */
    private const TRANSFER_COUNT_WEIGHTS_SUMMER = [1 => 15, 2 => 30, 3 => 30, 4 => 15, 5 => 10];
    private const TRANSFER_COUNT_WEIGHTS_WINTER = [1 => 50, 2 => 35, 3 => 15];

    /** Percentage chance each sell is a squad clearing type (vs talent upgrade) */
    private const CLEARING_CHANCE = 65;

    /** Chance of foreign departure when no domestic buyer is found */
    private const FOREIGN_FALLBACK_CHANCE = 50;

    /** Ideal squad depth per position group */
    private const IDEAL_GROUP_COUNTS = [
        'Goalkeeper' => 3,
        'Defender' => 6,
        'Midfielder' => 6,
        'Forward' => 4,
    ];

    /** Minimum group counts — never sell below this */
    private const MIN_GROUP_COUNTS = [
        'Goalkeeper' => 2,
        'Defender' => 5,
        'Midfielder' => 5,
        'Forward' => 3,
    ];

    /** Minimum squad size below which a team will not sell */
    private const MIN_SQUAD_SIZE = 20;

    /** Maximum squad size — buyers can't exceed this */
    private const MAX_SQUAD_SIZE = 26;

    /** Reputation tiers ordered highest to lowest (index 0 = highest) */
    private const REPUTATION_TIERS = [
        ClubProfile::REPUTATION_ELITE,
        ClubProfile::REPUTATION_CONTENDERS,
        ClubProfile::REPUTATION_CONTINENTAL,
        ClubProfile::REPUTATION_ESTABLISHED,
        ClubProfile::REPUTATION_MODEST,
        ClubProfile::REPUTATION_PROFESSIONAL,
        ClubProfile::REPUTATION_LOCAL,
    ];

    public function __construct(
        private readonly ContractService $contractService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Process AI transfer activity when a transfer window closes.
     */
    public function processWindowClose(Game $game, string $window): void
    {
        $isSummer = $window === 'summer';

        // Single load: all AI players with team relation, grouped by team
        $teamRosters = GamePlayer::with('team')
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $game->team_id)
            ->get()
            ->groupBy('team_id');

        // Set game relation in-memory to avoid lazy-loading from age accessor
        $teamRosters->flatten()->each(fn (GamePlayer $p) => $p->setRelation('game', $game));

        // Pre-calculate team averages and build team name lookup
        $teamAverages = $teamRosters->map(fn ($players) => $this->calculateTeamAverage($players));
        $teamNames = $teamRosters->map(fn ($players) => $players->first()?->team?->name ?? 'Unknown');

        // Pre-load Team models for wage calculation (single query)
        $teams = Team::whereIn('id', $teamRosters->keys())->get()->keyBy('id');

        // Phase 1: Sign free agents (players with null team_id)
        $freeAgentSignings = $this->processFreeAgentSignings($game, $teamRosters, $teamAverages, $teamNames, $teams);

        // Phase 2: AI-to-AI transfers with reputation-based matching
        $transfers = $this->processAITransfers($game, $isSummer, $teamRosters, $teamAverages, $teamNames, $teams);

        // Phase 3: Create summary notification (only if there's activity)
        if ($freeAgentSignings->isNotEmpty() || $transfers->isNotEmpty()) {
            $this->notificationService->notifyAITransferSummary(
                $game,
                $transfers->toArray(),
                $freeAgentSignings->toArray(),
                $window,
            );
        }
    }

    /**
     * Phase 1: Match free agents to AI teams that need players at their position.
     */
    private function processFreeAgentSignings(
        Game $game,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamNames,
        Collection $teams,
    ): Collection {
        $freeAgents = GamePlayer::where('game_id', $game->id)
            ->whereNull('team_id')
            ->get();

        if ($freeAgents->isEmpty()) {
            return collect();
        }

        // Set game relation in-memory for age accessor
        $freeAgents->each(fn (GamePlayer $p) => $p->setRelation('game', $game));

        $signings = collect();

        foreach ($freeAgents->shuffle() as $freeAgent) {
            $bestTeam = $this->findBestTeamForFreeAgent($freeAgent, $teamRosters, $teamAverages);

            if (! $bestTeam) {
                continue;
            }

            $teamId = $bestTeam['teamId'];

            // Sign the free agent
            $seasonYear = (int) $game->season;
            $contractYears = $freeAgent->age >= 32 ? 1 : mt_rand(1, 2);
            $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

            $team = $teams->get($teamId);
            $minimumWage = $team ? $this->contractService->getMinimumWageForTeam($team) : 0;
            $newWage = $this->contractService->calculateAnnualWage(
                $freeAgent->market_value_cents,
                $minimumWage,
                $freeAgent->age,
            );

            $freeAgent->update([
                'team_id' => $teamId,
                'number' => GamePlayer::nextAvailableNumber($game->id, $teamId),
                'contract_until' => $newContractEnd,
                'annual_wage' => $newWage,
            ]);

            $signings->push([
                'playerName' => $freeAgent->name,
                'position' => $freeAgent->position,
                'toTeamId' => $teamId,
                'toTeamName' => $teamNames[$teamId] ?? 'Unknown',
                'age' => $freeAgent->age,
                'formattedFee' => __('transfers.free_transfer'),
                'type' => 'free_agent',
            ]);

            // Update roster cache
            if (! $teamRosters->has($teamId)) {
                $teamRosters[$teamId] = collect();
            }
            $teamRosters[$teamId]->push($freeAgent);
        }

        return $signings;
    }

    /**
     * Phase 2: Process AI-to-AI transfers with two transfer types.
     *
     * Type 1 (Squad Clearing): surplus players → equal or lower reputation buyers
     * Type 2 (Talent Upgrading): quality players → equal or higher reputation buyers
     */
    private function processAITransfers(
        Game $game,
        bool $isSummer,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamNames,
        Collection $teams,
    ): Collection {
        // Load reputation data for all AI teams
        $teamReputations = $this->loadTeamReputations($teamRosters);

        // Pre-compute position group counts per team (mutated as transfers happen)
        $groupCounts = $teamRosters->map(
            fn ($players) => $players->groupBy(fn ($p) => $this->getPositionGroup($p->position))->map->count()
        );

        // Track net squad size changes per team (incremented/decremented as transfers happen)
        $teamSizeDeltas = $teamRosters->map(fn () => 0);

        // Foreign team names for narrative
        $foreignTeams = Team::where('country', '!=', $game->country)
            ->where('type', 'club')
            ->whereNotIn('id', $teamRosters->keys())
            ->inRandomOrder()
            ->limit(40)
            ->pluck('name')
            ->all();
        $foreignIndex = 0;

        // Determine each team's transfer activity budget (1-5 total moves)
        $weights = $isSummer ? self::TRANSFER_COUNT_WEIGHTS_SUMMER : self::TRANSFER_COUNT_WEIGHTS_WINTER;
        $teamBudgets = $teamRosters->map(fn () => [
            'max' => $this->weightedRandom($weights),
            'sells' => 0,
            'buys' => 0,
        ]);

        $allTransfers = collect();
        // Set of player IDs already transferred — used only to prevent double-transfers
        $transferredPlayerIds = [];

        // Build all sell candidates across all teams, tagged by type
        $sellOffers = $this->buildSellOffers(
            $teamRosters, $teamAverages, $teamReputations, $groupCounts, $teamBudgets
        );

        // Shuffle to avoid systematic bias (e.g., always processing the same team first)
        $sellOffers = $sellOffers->shuffle();

        // Match each sell offer with a buyer
        foreach ($sellOffers as $offer) {
            $player = $offer['player'];
            $sellerTeamId = $offer['sellerTeamId'];
            $transferType = $offer['transferType'];

            if (isset($transferredPlayerIds[$player->id])) {
                continue;
            }

            // Re-check seller budget
            $sellerBudget = $teamBudgets->get($sellerTeamId);
            if (! $sellerBudget || ($sellerBudget['sells'] + $sellerBudget['buys']) >= $sellerBudget['max']) {
                continue;
            }

            // Re-check seller squad size using delta tracking
            $sellerBaseSize = $teamRosters->get($sellerTeamId, collect())->count();
            $effectiveSellerSize = $sellerBaseSize + ($teamSizeDeltas->get($sellerTeamId, 0));
            if ($effectiveSellerSize <= self::MIN_SQUAD_SIZE) {
                continue;
            }

            // Re-check position group minimum for seller (groupCounts is kept accurate)
            $posGroup = $this->getPositionGroup($player->position);
            $sellerGroupCounts = $groupCounts->get($sellerTeamId, collect());
            if (($sellerGroupCounts->get($posGroup, 0)) <= (self::MIN_GROUP_COUNTS[$posGroup] ?? 2)) {
                continue;
            }

            // Find a buyer based on transfer type
            $buyer = $transferType === 'clearing'
                ? $this->findClearingBuyer($player, $sellerTeamId, $teamRosters, $teamAverages, $teamReputations, $teamBudgets, $groupCounts, $teamSizeDeltas, $game)
                : $this->findUpgradeBuyer($player, $sellerTeamId, $teamRosters, $teamAverages, $teamReputations, $teamBudgets, $groupCounts, $teamSizeDeltas, $game);

            $fromTeamName = $teamNames[$sellerTeamId] ?? 'Unknown';

            if ($buyer) {
                $buyerTeamId = $buyer['teamId'];

                // Execute domestic transfer
                $transfer = $this->executeDomesticTransfer(
                    $game, $player, $sellerTeamId, $fromTeamName, $buyerTeamId, $buyer['teamName'], $teams
                );
                $allTransfers->push($transfer);
                $transferredPlayerIds[$player->id] = true;

                // Update budgets
                $this->incrementBudget($teamBudgets, $sellerTeamId, 'sells');
                $this->incrementBudget($teamBudgets, $buyerTeamId, 'buys');

                // Update caches: seller loses player, buyer gains player
                $this->adjustGroupCount($groupCounts, $sellerTeamId, $posGroup, -1);
                $this->adjustGroupCount($groupCounts, $buyerTeamId, $posGroup, +1);
                $teamSizeDeltas->put($sellerTeamId, ($teamSizeDeltas->get($sellerTeamId, 0)) - 1);
                $teamSizeDeltas->put($buyerTeamId, ($teamSizeDeltas->get($buyerTeamId, 0)) + 1);
            } else {
                // No domestic buyer — try foreign departure
                if (mt_rand(1, 100) <= self::FOREIGN_FALLBACK_CHANCE && ! empty($foreignTeams)) {
                    $foreignName = $foreignTeams[$foreignIndex % count($foreignTeams)];
                    $foreignIndex++;

                    $allTransfers->push($this->executeForeignTransfer($player, $sellerTeamId, $fromTeamName, $foreignName));
                    $transferredPlayerIds[$player->id] = true;
                    $this->incrementBudget($teamBudgets, $sellerTeamId, 'sells');
                    $this->adjustGroupCount($groupCounts, $sellerTeamId, $posGroup, -1);
                    $teamSizeDeltas->put($sellerTeamId, ($teamSizeDeltas->get($sellerTeamId, 0)) - 1);
                }
            }
        }

        return $allTransfers;
    }

    /**
     * Build all sell offers across all teams, scored and tagged by type.
     *
     * @return Collection<int, array{player: GamePlayer, sellerTeamId: string, transferType: string, score: int}>
     */
    private function buildSellOffers(
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamReputations,
        Collection $groupCounts,
        Collection $teamBudgets,
    ): Collection {
        $offers = collect();

        foreach ($teamRosters as $teamId => $players) {
            $budget = $teamBudgets->get($teamId);
            if (! $budget || $budget['max'] <= 0) {
                continue;
            }

            if ($players->count() <= self::MIN_SQUAD_SIZE) {
                continue;
            }

            $teamAvg = $teamAverages[$teamId] ?? 55;
            $teamRepIndex = $this->getReputationIndex($teamId, $teamReputations);
            $teamGroupCounts = $groupCounts->get($teamId, collect());

            $eligible = $players->filter(fn (GamePlayer $p) => ! $p->retiring_at_season);

            // Determine max sells for this team (at most 60% of budget, at least 1)
            $maxSells = max(1, (int) ceil($budget['max'] * 0.6));

            // Score clearing and upgrade candidates separately
            $clearingCandidates = $eligible
                ->map(fn ($p) => $this->scoreClearingCandidate($p, $teamAvg, $teamGroupCounts))
                ->filter()
                ->sortByDesc('score');

            $upgradeCandidates = $eligible
                ->map(fn ($p) => $this->scoreUpgradeCandidate($p, $teamAvg, $teamRepIndex, $teamGroupCounts))
                ->filter()
                ->sortByDesc('score');

            $usedPlayerIds = [];

            for ($i = 0; $i < $maxSells; $i++) {
                $isClearing = mt_rand(1, 100) <= self::CLEARING_CHANCE;

                if ($isClearing) {
                    $candidate = $clearingCandidates->first(fn ($c) => ! isset($usedPlayerIds[$c['player']->id]));
                    $type = 'clearing';
                } else {
                    $candidate = $upgradeCandidates->first(fn ($c) => ! isset($usedPlayerIds[$c['player']->id]));
                    $type = 'upgrade';
                }

                // Fallback to the other type if preferred type has no candidates
                if (! $candidate) {
                    if ($isClearing) {
                        $candidate = $upgradeCandidates->first(fn ($c) => ! isset($usedPlayerIds[$c['player']->id]));
                        $type = 'upgrade';
                    } else {
                        $candidate = $clearingCandidates->first(fn ($c) => ! isset($usedPlayerIds[$c['player']->id]));
                        $type = 'clearing';
                    }
                }

                if (! $candidate) {
                    break;
                }

                $usedPlayerIds[$candidate['player']->id] = true;
                $offers->push([
                    'player' => $candidate['player'],
                    'sellerTeamId' => $teamId,
                    'transferType' => $type,
                    'score' => $candidate['score'],
                ]);
            }
        }

        return $offers;
    }

    /**
     * Score a player as a squad clearing candidate (surplus/backup player).
     */
    private function scoreClearingCandidate(GamePlayer $player, int $teamAvg, Collection $teamGroupCounts): ?array
    {
        $ability = $this->getPlayerAbility($player);
        $group = $this->getPositionGroup($player->position);
        $groupCount = $teamGroupCounts->get($group, 0);

        // Never sell below minimum depth
        if ($groupCount <= (self::MIN_GROUP_COUNTS[$group] ?? 2)) {
            return null;
        }

        $score = 0;

        // Position surplus: more surplus = more expendable
        $surplus = $groupCount - (self::IDEAL_GROUP_COUNTS[$group] ?? 4);
        if ($surplus > 0) {
            $score += $surplus * 3;
        }

        // Below-average ability
        $abilityGap = $teamAvg - $ability;
        if ($abilityGap > 15) {
            $score += 5;
        } elseif ($abilityGap > 5) {
            $score += 3;
        } elseif ($abilityGap > 0) {
            $score += 1;
        }

        // Aging player
        if ($player->age >= 33) {
            $score += 3;
        } elseif ($player->age >= 30) {
            $score += 2;
        }

        // Random variance
        $score += mt_rand(0, 2);

        if ($score < 3) {
            return null;
        }

        return ['player' => $player, 'score' => $score];
    }

    /**
     * Score a player as a talent upgrade candidate (quality player attractive to bigger clubs).
     */
    private function scoreUpgradeCandidate(GamePlayer $player, int $teamAvg, int $teamRepIndex, Collection $teamGroupCounts): ?array
    {
        // Elite clubs have no higher-reputation domestic buyer
        if ($teamRepIndex <= 0) {
            return null;
        }

        $ability = $this->getPlayerAbility($player);
        $group = $this->getPositionGroup($player->position);
        $groupCount = $teamGroupCounts->get($group, 0);

        // Never sell below minimum depth
        if ($groupCount <= (self::MIN_GROUP_COUNTS[$group] ?? 2)) {
            return null;
        }

        // Must be at or above team average — this is a quality player
        if ($ability < $teamAvg) {
            return null;
        }

        $score = 0;

        // How much above average (more = more attractive to bigger clubs)
        $abilityGap = $ability - $teamAvg;
        $score += min(5, (int) ($abilityGap / 3));

        // Prime age premium
        if ($player->age >= 22 && $player->age <= 28) {
            $score += 3;
        } elseif ($player->age >= 19 && $player->age <= 21) {
            $score += 1;
        }

        // Surplus bonus — easier to let go if position group is stocked
        $surplus = $groupCount - (self::IDEAL_GROUP_COUNTS[$group] ?? 4);
        if ($surplus > 0) {
            $score += min(4, $surplus * 2);
        }

        // Random variance
        $score += mt_rand(0, 2);

        if ($score < 3) {
            return null;
        }

        return ['player' => $player, 'score' => $score];
    }

    /**
     * Find a buyer for a squad clearing transfer (equal or lower reputation).
     */
    private function findClearingBuyer(
        GamePlayer $player,
        string $sellerTeamId,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamReputations,
        Collection $teamBudgets,
        Collection $groupCounts,
        Collection $teamSizeDeltas,
        Game $game,
    ): ?array {
        $sellerRepIndex = $this->getReputationIndex($sellerTeamId, $teamReputations);
        $posGroup = $this->getPositionGroup($player->position);
        $playerAbility = $this->getPlayerAbility($player);
        $candidates = [];

        foreach ($teamRosters as $teamId => $players) {
            if ($teamId === $sellerTeamId || $teamId === $game->team_id) {
                continue;
            }

            // Check buyer budget
            $budget = $teamBudgets->get($teamId);
            if ($budget && ($budget['sells'] + $budget['buys']) >= $budget['max']) {
                continue;
            }

            // Buyer must be equal or lower reputation (higher or equal index)
            $buyerRepIndex = $this->getReputationIndex($teamId, $teamReputations);
            if ($buyerRepIndex < $sellerRepIndex) {
                continue;
            }

            // Squad size check using delta tracking
            $effectiveSize = $players->count() + ($teamSizeDeltas->get($teamId, 0));
            if ($effectiveSize >= self::MAX_SQUAD_SIZE) {
                continue;
            }

            // Ability fit
            $buyerAvg = $teamAverages[$teamId] ?? 55;
            if (abs($playerAbility - $buyerAvg) > 15) {
                continue;
            }

            // Position need (groupCounts is kept accurate via adjustGroupCount)
            $buyerGroupCounts = $groupCounts->get($teamId, collect());
            $currentGroupCount = $buyerGroupCounts->get($posGroup, 0);
            $need = max(0, (self::IDEAL_GROUP_COUNTS[$posGroup] ?? 4) - $currentGroupCount);

            $score = $need * 10;
            // Reputation proximity bonus (closer = more realistic)
            $repDistance = $buyerRepIndex - $sellerRepIndex;
            $score += max(0, 8 - $repDistance * 2);
            $score += mt_rand(0, 5);

            if ($score > 0) {
                $candidates[] = [
                    'teamId' => $teamId,
                    'teamName' => $teamRosters[$teamId]->first()?->team?->name ?? 'Unknown',
                    'score' => $score,
                ];
            }
        }

        return $this->selectBestCandidate($candidates);
    }

    /**
     * Find a buyer for a talent upgrade transfer (equal or higher reputation).
     */
    private function findUpgradeBuyer(
        GamePlayer $player,
        string $sellerTeamId,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamReputations,
        Collection $teamBudgets,
        Collection $groupCounts,
        Collection $teamSizeDeltas,
        Game $game,
    ): ?array {
        $sellerRepIndex = $this->getReputationIndex($sellerTeamId, $teamReputations);
        $posGroup = $this->getPositionGroup($player->position);
        $playerAbility = $this->getPlayerAbility($player);
        $candidates = [];

        foreach ($teamRosters as $teamId => $players) {
            if ($teamId === $sellerTeamId || $teamId === $game->team_id) {
                continue;
            }

            // Check buyer budget
            $budget = $teamBudgets->get($teamId);
            if ($budget && ($budget['sells'] + $budget['buys']) >= $budget['max']) {
                continue;
            }

            // Buyer must be equal or higher reputation (lower or equal index)
            $buyerRepIndex = $this->getReputationIndex($teamId, $teamReputations);
            if ($buyerRepIndex > $sellerRepIndex) {
                continue;
            }

            // Squad size check using delta tracking
            $effectiveSize = $players->count() + ($teamSizeDeltas->get($teamId, 0));
            if ($effectiveSize >= self::MAX_SQUAD_SIZE) {
                continue;
            }

            // Ability fit: player should not be too weak for the buying team
            $buyerAvg = $teamAverages[$teamId] ?? 55;
            if ($playerAbility < $buyerAvg - 10) {
                continue;
            }

            // Position need (groupCounts is kept accurate via adjustGroupCount)
            $buyerGroupCounts = $groupCounts->get($teamId, collect());
            $currentGroupCount = $buyerGroupCounts->get($posGroup, 0);
            $need = max(0, (self::IDEAL_GROUP_COUNTS[$posGroup] ?? 4) - $currentGroupCount);

            $score = $need * 10;
            // Reputation distance bonus: one step up is most common
            $repDistance = $sellerRepIndex - $buyerRepIndex;
            $score += match (true) {
                $repDistance === 0 => 8,
                $repDistance === 1 => 12,
                $repDistance === 2 => 6,
                default => 2,
            };
            // Ability fit bonus
            if (abs($playerAbility - $buyerAvg) <= 5) {
                $score += 5;
            }
            $score += mt_rand(0, 5);

            if ($score > 0) {
                $candidates[] = [
                    'teamId' => $teamId,
                    'teamName' => $teamRosters[$teamId]->first()?->team?->name ?? 'Unknown',
                    'score' => $score,
                ];
            }
        }

        return $this->selectBestCandidate($candidates);
    }

    /**
     * Select the best candidate from a scored list using weighted random among top 3.
     */
    private function selectBestCandidate(array $candidates): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($candidates, 0, 3);

        $totalWeight = array_sum(array_column($top, 'score'));
        if ($totalWeight <= 0) {
            return $top[0];
        }

        $roll = mt_rand(1, $totalWeight);
        $cumulative = 0;
        foreach ($top as $c) {
            $cumulative += $c['score'];
            if ($roll <= $cumulative) {
                return $c;
            }
        }

        return $top[0];
    }

    /**
     * Execute a domestic transfer between two AI teams.
     */
    private function executeDomesticTransfer(
        Game $game,
        GamePlayer $player,
        string $fromTeamId,
        string $fromTeamName,
        string $toTeamId,
        string $toTeamName,
        Collection $teams,
    ): array {
        $fee = $player->market_value_cents;
        $seasonYear = (int) $game->season;
        $contractYears = mt_rand(2, 3);
        $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

        $team = $teams->get($toTeamId);
        $minimumWage = $team ? $this->contractService->getMinimumWageForTeam($team) : 0;
        $newWage = $this->contractService->calculateAnnualWage(
            $player->market_value_cents,
            $minimumWage,
            $player->age,
        );

        $player->update([
            'team_id' => $toTeamId,
            'number' => GamePlayer::nextAvailableNumber($game->id, $toTeamId),
            'contract_until' => $newContractEnd,
            'annual_wage' => $newWage,
        ]);

        return [
            'playerName' => $player->name,
            'position' => $player->position,
            'fromTeamId' => $fromTeamId,
            'fromTeamName' => $fromTeamName,
            'toTeamId' => $toTeamId,
            'toTeamName' => $toTeamName,
            'fee' => $fee,
            'formattedFee' => Money::format($fee),
            'type' => 'domestic',
        ];
    }

    /**
     * Execute a foreign transfer (player leaves the game entirely).
     */
    private function executeForeignTransfer(
        GamePlayer $player,
        string $fromTeamId,
        string $fromTeamName,
        string $foreignTeamName,
    ): array {
        $fee = $player->market_value_cents;

        $transfer = [
            'playerName' => $player->name,
            'position' => $player->position,
            'fromTeamId' => $fromTeamId,
            'fromTeamName' => $fromTeamName,
            'toTeamId' => null,
            'toTeamName' => $foreignTeamName,
            'fee' => $fee,
            'formattedFee' => Money::format($fee),
            'type' => 'foreign',
        ];

        $player->delete();

        return $transfer;
    }

    /**
     * Find the best AI team for a free agent to sign with.
     */
    private function findBestTeamForFreeAgent(GamePlayer $freeAgent, Collection $teamRosters, Collection $teamAverages): ?array
    {
        $positionGroup = $this->getPositionGroup($freeAgent->position);
        $playerAbility = $this->getPlayerAbility($freeAgent);
        $bestScore = -1;
        $bestTeamId = null;

        foreach ($teamRosters as $teamId => $players) {
            if ($players->count() >= self::MAX_SQUAD_SIZE) {
                continue;
            }

            $teamAvg = $teamAverages[$teamId] ?? 55;

            if (abs($playerAbility - $teamAvg) > 20) {
                continue;
            }

            $groupCount = $players->filter(
                fn ($p) => $this->getPositionGroup($p->position) === $positionGroup
            )->count();

            $groupNeed = max(0, (self::MIN_GROUP_COUNTS[$positionGroup] ?? 2) - $groupCount);

            $score = $groupNeed * 10 + mt_rand(0, 5);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTeamId = $teamId;
            }
        }

        if ($bestScore <= 0 || ! $bestTeamId) {
            return null;
        }

        return [
            'teamId' => $bestTeamId,
            'teamName' => $teamRosters[$bestTeamId]->first()?->team?->name ?? 'Unknown',
        ];
    }

    /**
     * Load reputation tier indices for all AI teams.
     *
     * @return Collection<string, int> teamId => reputation index (0 = elite, 6 = local)
     */
    private function loadTeamReputations(Collection $teamRosters): Collection
    {
        $clubProfiles = ClubProfile::whereIn('team_id', $teamRosters->keys())
            ->get()
            ->keyBy('team_id');

        return $teamRosters->keys()->mapWithKeys(function ($teamId) use ($clubProfiles) {
            $level = $clubProfiles->get($teamId)?->reputation_level ?? ClubProfile::REPUTATION_LOCAL;
            $index = array_search($level, self::REPUTATION_TIERS);

            return [$teamId => $index !== false ? $index : 6];
        });
    }

    private function getReputationIndex(string $teamId, Collection $teamReputations): int
    {
        return $teamReputations->get($teamId, 6);
    }

    /**
     * Increment a team's budget counter.
     */
    private function incrementBudget(Collection $teamBudgets, string $teamId, string $field): void
    {
        $budget = $teamBudgets->get($teamId);
        if ($budget) {
            $budget[$field]++;
            $teamBudgets->put($teamId, $budget);
        }
    }

    /**
     * Adjust a team's position group count in the cache.
     */
    private function adjustGroupCount(Collection $groupCounts, string $teamId, string $posGroup, int $delta): void
    {
        if ($groupCounts->has($teamId)) {
            $counts = $groupCounts->get($teamId);
            $counts->put($posGroup, max(0, ($counts->get($posGroup, 0)) + $delta));
        }
    }

    private function calculateTeamAverage(Collection $players): int
    {
        if ($players->isEmpty()) {
            return 55;
        }

        $total = $players->sum(fn (GamePlayer $p) => $this->getPlayerAbility($p));

        return (int) round($total / $players->count());
    }

    private function getPlayerAbility(GamePlayer $player): int
    {
        $tech = $player->game_technical_ability ?? 50;
        $phys = $player->game_physical_ability ?? 50;

        return (int) round(($tech + $phys) / 2);
    }

    private function getPositionGroup(string $position): string
    {
        return match ($position) {
            'Goalkeeper' => 'Goalkeeper',
            'Centre-Back', 'Left-Back', 'Right-Back' => 'Defender',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield' => 'Midfielder',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker' => 'Forward',
            default => 'Midfielder',
        };
    }

    private function weightedRandom(array $weights): int
    {
        $total = array_sum($weights);
        $roll = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
