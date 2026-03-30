<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Player\PlayerAge;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Simulates AI transfer market activity across the transfer window and at close.
 *
 * Transfers are distributed across matchdays during the open window (processTransferBatch)
 * and finalized when the window closes (processWindowClose). All DB operations are batched
 * to minimize query count.
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
    private const MAX_SQUAD_SIZE = 30;

    /** Minimum free agents to preserve — AI stops signing when pool drops to this */
    private const MIN_FREE_AGENT_POOL = 15;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Process a batch of AI transfers during the open transfer window.
     *
     * Called each matchday from CareerActionProcessor while the window is open.
     * Processes ~batchSize seller teams per call, distributing transfers across the window.
     */
    public function processTransferBatch(Game $game, string $window, int $batchSize = 10): void
    {
        $isSummer = $window === 'summer';

        [
            'teamRosters' => $teamRosters,
            'teamAverages' => $teamAverages,
            'teams' => $teams,
            'takenNumbers' => $takenNumbers,
            'alreadyTransferredSet' => $alreadyTransferredSet,
            'completedCounts' => $completedCounts,
        ] = $this->loadTransferContext($game, $window);

        // Build deterministic budgets with completed activity subtracted
        $teamBudgets = $this->buildDeterministicBudgets($teamRosters, $game, $window, $isSummer, $completedCounts);

        // Filter to teams with remaining sell capacity
        $eligibleSellers = $teamBudgets->filter(function ($budget) {
            $maxSells = max(1, (int) ceil($budget['max'] * 0.6));

            return ($budget['sells'] + $budget['buys']) < $budget['max']
                && $budget['sells'] < $maxSells;
        })->keys();

        if ($eligibleSellers->isEmpty()) {
            return;
        }

        // Deterministic rotation: pick batch based on current date
        $rotation = $game->current_date->dayOfYear;
        $sorted = $eligibleSellers->sortBy(fn ($teamId) => crc32($teamId . $rotation));
        $batchTeamIds = $sorted->take($batchSize);

        $playerUpdates = [];
        $transferInserts = [];

        $this->processAITransfers(
            $game, $window, $teamRosters, $teamAverages, $teams,
            $takenNumbers, $playerUpdates, $transferInserts,
            $teamBudgets, $alreadyTransferredSet, $batchTeamIds
        );

        $this->flushBatchedOperations($playerUpdates, $transferInserts);
    }

    /**
     * Process remaining AI transfer activity when a transfer window closes.
     *
     * Handles teams not fully processed during the window + free agent signings.
     * When called without prior batch processing (e.g., SimulateTransfers command),
     * processes all teams in one go.
     */
    public function processWindowClose(Game $game, string $window): void
    {
        $isSummer = $window === 'summer';

        [
            'teamRosters' => $teamRosters,
            'teamAverages' => $teamAverages,
            'teams' => $teams,
            'takenNumbers' => $takenNumbers,
            'alreadyTransferredSet' => $alreadyTransferredSet,
            'windowTransfers' => $windowTransfers,
            'completedCounts' => $completedCounts,
        ] = $this->loadTransferContext($game, $window);

        $playerUpdates = [];
        $transferInserts = [];
        $priorCount = $windowTransfers->count();

        // Phase 1: Sign free agents
        $freeAgentCount = $this->processFreeAgentSignings(
            $game, $window, $teamRosters, $teamAverages, $teams,
            $takenNumbers, $playerUpdates, $transferInserts, $alreadyTransferredSet
        );

        // Phase 2: Remaining AI-to-AI transfers
        $teamBudgets = $this->buildDeterministicBudgets($teamRosters, $game, $window, $isSummer, $completedCounts);
        $transferCount = $this->processAITransfers(
            $game, $window, $teamRosters, $teamAverages, $teams,
            $takenNumbers, $playerUpdates, $transferInserts,
            $teamBudgets, $alreadyTransferredSet
        );

        // Flush all batched operations
        $this->flushBatchedOperations($playerUpdates, $transferInserts);

        // Notify with total count (prior window activity + close-time activity)
        $totalCount = $priorCount + $freeAgentCount + $transferCount;
        if ($totalCount > 0) {
            $this->notificationService->notifyAITransferSummary($game, $totalCount, $window);
        }
    }

    /**
     * Match free agents to AI teams that need players at their position.
     */
    private function processFreeAgentSignings(
        Game $game,
        string $window,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teams,
        Collection &$takenNumbers,
        array &$playerUpdates,
        array &$transferInserts,
        array &$alreadyTransferredSet,
    ): int {
        $freeAgents = GamePlayer::with(['player:id,date_of_birth'])
            ->select([
                'id', 'game_id', 'player_id', 'team_id', 'position',
                'market_value_cents', 'game_technical_ability', 'game_physical_ability',
                'retiring_at_season', 'number', 'contract_until', 'annual_wage',
            ])
            ->where('game_id', $game->id)
            ->whereNull('team_id')
            ->get();

        if ($freeAgents->isEmpty()) {
            return 0;
        }

        // Preserve a minimum pool of free agents for the user
        $maxSignings = max(0, $freeAgents->count() - self::MIN_FREE_AGENT_POOL);
        if ($maxSignings === 0) {
            return 0;
        }

        $count = 0;

        foreach ($freeAgents->shuffle() as $freeAgent) {
            if ($count >= $maxSignings) {
                break;
            }

            $bestTeam = $this->findBestTeamForFreeAgent($freeAgent, $teamRosters, $teamAverages, $teams);

            if (! $bestTeam) {
                continue;
            }

            $teamId = $bestTeam['teamId'];

            $seasonYear = (int) $game->season;
            $contractYears = $freeAgent->age($game->current_date) >= 32 ? 1 : mt_rand(1, 2);
            $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

            $team = $teams->get($teamId);
            $minimumWage = $team ? $this->contractService->getMinimumWageForTeam($team) : 0;
            $newWage = $this->contractService->calculateAnnualWage(
                $freeAgent->market_value_cents,
                $minimumWage,
                $freeAgent->age($game->current_date),
            );

            $number = $this->allocateSquadNumber($takenNumbers, $teamId);

            $playerUpdates[] = [
                'id' => $freeAgent->id,
                'game_id' => $freeAgent->game_id,
                'player_id' => $freeAgent->player_id,
                'team_id' => $teamId,
                'number' => $number,
                'position' => $freeAgent->position,
                'contract_until' => $newContractEnd->toDateString(),
                'annual_wage' => $newWage,
            ];

            $transferInserts[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'game_player_id' => $freeAgent->id,
                'from_team_id' => null,
                'to_team_id' => $teamId,
                'transfer_fee' => 0,
                'type' => GameTransfer::TYPE_FREE_AGENT,
                'season' => $game->season,
                'window' => $window,
            ];

            $count++;
            $alreadyTransferredSet[$freeAgent->id] = true;

            // Update roster cache
            if (! $teamRosters->has($teamId)) {
                $teamRosters[$teamId] = collect();
            }
            $teamRosters[$teamId]->push($freeAgent);
        }

        return $count;
    }

    /**
     * Process AI-to-AI transfers with two transfer types.
     *
     * Type 1 (Squad Clearing): surplus players → equal or lower reputation buyers
     * Type 2 (Talent Upgrading): quality players → equal or higher reputation buyers
     */
    private function processAITransfers(
        Game $game,
        string $window,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teams,
        Collection &$takenNumbers,
        array &$playerUpdates,
        array &$transferInserts,
        Collection $teamBudgets,
        array $alreadyTransferredSet,
        ?Collection $teamFilter = null,
    ): int {
        // Load reputation data for all AI teams
        $teamReputations = $this->loadTeamReputations($game, $teamRosters);

        // Pre-compute position group counts per team (mutated as transfers happen)
        $groupCounts = $teamRosters->map(
            fn ($players) => $players->groupBy(fn ($p) => $this->getPositionGroup($p->position))->map->count()
        );

        // Track net squad size changes per team (incremented/decremented as transfers happen)
        $teamSizeDeltas = $teamRosters->map(fn () => 0);

        // Foreign teams for cross-border transfers
        $foreignTeams = Team::transferMarketEligible()
            ->where('country', '!=', $game->country)
            ->whereNotIn('id', $teamRosters->keys())
            ->inRandomOrder()
            ->limit(40)
            ->get(['id', 'name'])
            ->all();
        $foreignIndex = 0;

        $count = 0;
        // Set of player IDs already transferred — used only to prevent double-transfers
        $transferredPlayerIds = [];

        // Build all sell candidates across all teams (or filtered subset), tagged by type
        $sellOffers = $this->buildSellOffers(
            $teamRosters, $teamAverages, $teamReputations, $groupCounts, $teamBudgets, $alreadyTransferredSet, $game->current_date, $teamFilter
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
                ? $this->findClearingBuyer($player, $sellerTeamId, $teamRosters, $teamAverages, $teamReputations, $teamBudgets, $groupCounts, $teamSizeDeltas, $game, $teams)
                : $this->findUpgradeBuyer($player, $sellerTeamId, $teamRosters, $teamAverages, $teamReputations, $teamBudgets, $groupCounts, $teamSizeDeltas, $game, $teams);

            if ($buyer) {
                $buyerTeamId = $buyer['teamId'];
                $assignedNumber = $this->allocateSquadNumber($takenNumbers, $buyerTeamId);

                // Prepare domestic transfer
                $this->prepareTransfer($game, $player, $sellerTeamId, $buyerTeamId, $teams->get($buyerTeamId), $window, $assignedNumber, $playerUpdates, $transferInserts);
                $count++;
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
                    $foreignTeam = $foreignTeams[$foreignIndex % count($foreignTeams)];
                    $foreignIndex++;
                    $assignedNumber = $this->allocateSquadNumber($takenNumbers, $foreignTeam->id);

                    $this->prepareTransfer($game, $player, $sellerTeamId, $foreignTeam->id, $foreignTeam, $window, $assignedNumber, $playerUpdates, $transferInserts, maxContractYears: 4);
                    $count++;
                    $transferredPlayerIds[$player->id] = true;
                    $this->incrementBudget($teamBudgets, $sellerTeamId, 'sells');
                    $this->adjustGroupCount($groupCounts, $sellerTeamId, $posGroup, -1);
                    $teamSizeDeltas->put($sellerTeamId, ($teamSizeDeltas->get($sellerTeamId, 0)) - 1);
                }
            }
        }

        return $count;
    }

    /**
     * Build all sell offers across all teams (or filtered subset), scored and tagged by type.
     *
     * @return Collection<int, array{player: GamePlayer, sellerTeamId: string, transferType: string, score: int}>
     */
    private function buildSellOffers(
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamReputations,
        Collection $groupCounts,
        Collection $teamBudgets,
        array $alreadyTransferredSet,
        Carbon $currentDate,
        ?Collection $teamFilter = null,
    ): Collection {
        $offers = collect();

        foreach ($teamRosters as $teamId => $players) {
            if ($teamFilter !== null && ! $teamFilter->contains($teamId)) {
                continue;
            }

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

            $eligible = $players->filter(
                fn (GamePlayer $p) => ! $p->retiring_at_season && ! isset($alreadyTransferredSet[$p->id])
            );

            // Determine remaining sells for this team (accounts for prior window activity)
            $maxSells = max(1, (int) ceil($budget['max'] * 0.6));
            $remainingSells = $maxSells - $budget['sells'];
            if ($remainingSells <= 0) {
                continue;
            }

            // Score clearing and upgrade candidates separately
            $clearingCandidates = $eligible
                ->map(fn ($p) => $this->scoreClearingCandidate($p, $teamAvg, $teamGroupCounts, $currentDate))
                ->filter()
                ->sortByDesc('score');

            $upgradeCandidates = $eligible
                ->map(fn ($p) => $this->scoreUpgradeCandidate($p, $teamAvg, $teamRepIndex, $teamGroupCounts, $currentDate))
                ->filter()
                ->sortByDesc('score');

            $usedPlayerIds = [];

            for ($i = 0; $i < $remainingSells; $i++) {
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
    private function scoreClearingCandidate(GamePlayer $player, int $teamAvg, Collection $teamGroupCounts, Carbon $currentDate): ?array
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
        $age = $player->age($currentDate);
        if ($age >= PlayerAge::PRIME_END) {
            $score += 3;
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
    private function scoreUpgradeCandidate(GamePlayer $player, int $teamAvg, int $teamRepIndex, Collection $teamGroupCounts, Carbon $currentDate): ?array
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
        $age = $player->age($currentDate);
        if ($age >= PlayerAge::YOUNG_END && $age <= PlayerAge::primePhaseAge(0.5)) {
            $score += 3;
        } elseif ($age >= PlayerAge::ACADEMY_END && $age < PlayerAge::YOUNG_END) {
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
        Collection $teams,
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
                    'teamName' => $teams->get($teamId)?->name ?? 'Unknown',
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
        Collection $teams,
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
                    'teamName' => $teams->get($teamId)?->name ?? 'Unknown',
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
     * Prepare a transfer between AI teams (batched, no DB queries).
     *
     * @param  int  $minContractYears  Minimum contract years (domestic: 2, foreign: 2)
     * @param  int  $maxContractYears  Maximum contract years (domestic: 3, foreign: 4)
     */
    private function prepareTransfer(
        Game $game,
        GamePlayer $player,
        string $fromTeamId,
        string $toTeamId,
        ?Team $toTeam,
        string $window,
        int $assignedNumber,
        array &$playerUpdates,
        array &$transferInserts,
        int $minContractYears = 2,
        int $maxContractYears = 3,
    ): void {
        $fee = $player->market_value_cents;
        $seasonYear = (int) $game->season;
        $contractYears = mt_rand($minContractYears, $maxContractYears);
        $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

        $minimumWage = $toTeam ? $this->contractService->getMinimumWageForTeam($toTeam) : 0;
        $newWage = $this->contractService->calculateAnnualWage(
            $player->market_value_cents,
            $minimumWage,
            $player->age($game->current_date),
        );

        $playerUpdates[] = [
            'id' => $player->id,
            'game_id' => $player->game_id,
            'player_id' => $player->player_id,
            'team_id' => $toTeamId,
            'number' => $assignedNumber,
            'position' => $player->position,
            'contract_until' => $newContractEnd->toDateString(),
            'annual_wage' => $newWage,
        ];

        $transferInserts[] = [
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'from_team_id' => $fromTeamId,
            'to_team_id' => $toTeamId,
            'transfer_fee' => $fee,
            'type' => GameTransfer::TYPE_TRANSFER,
            'season' => $game->season,
            'window' => $window,
        ];
    }

    /**
     * Find the best AI team for a free agent to sign with.
     */
    private function findBestTeamForFreeAgent(GamePlayer $freeAgent, Collection $teamRosters, Collection $teamAverages, Collection $teams): ?array
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
            'teamName' => $teams->get($bestTeamId)?->name ?? 'Unknown',
        ];
    }

    // ── Batch operation helpers ─────────────────────────────────────────

    /**
     * Load shared context needed by both processTransferBatch and processWindowClose.
     */
    private function loadTransferContext(Game $game, string $window): array
    {
        $teamRosters = $this->loadAIRosters($game);
        $teamAverages = $teamRosters->map(fn ($players) => $this->calculateTeamAverage($players));
        $teams = Team::whereIn('id', $teamRosters->keys())->get()->keyBy('id');
        $takenNumbers = $this->preloadSquadNumbers($game->id);

        $seasonTransfers = GameTransfer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->get(['id', 'game_id', 'game_player_id', 'from_team_id', 'to_team_id', 'window']);
        $alreadyTransferredSet = array_flip($seasonTransfers->pluck('game_player_id')->all());
        $windowTransfers = $seasonTransfers->where('window', $window);
        $completedCounts = $this->buildCompletedCounts($windowTransfers, $game->team_id);

        return [
            'teamRosters' => $teamRosters,
            'teamAverages' => $teamAverages,
            'teams' => $teams,
            'takenNumbers' => $takenNumbers,
            'alreadyTransferredSet' => $alreadyTransferredSet,
            'windowTransfers' => $windowTransfers,
            'completedCounts' => $completedCounts,
        ];
    }

    /**
     * Load all AI team rosters (excludes human player's team).
     */
    private function loadAIRosters(Game $game): Collection
    {
        return GamePlayer::with(['player:id,date_of_birth'])
            ->select([
                'id', 'game_id', 'player_id', 'team_id', 'position',
                'market_value_cents', 'game_technical_ability', 'game_physical_ability',
                'retiring_at_season', 'number', 'contract_until', 'annual_wage',
            ])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $game->team_id)
            ->get()
            ->groupBy('team_id');
    }

    /**
     * Pre-load all squad numbers for the game in a single query.
     *
     * @return Collection<string, int[]> teamId => array of taken numbers
     */
    private function preloadSquadNumbers(string $gameId): Collection
    {
        return GamePlayer::where('game_id', $gameId)
            ->whereNotNull('team_id')
            ->whereNotNull('number')
            ->get(['team_id', 'number'])
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->pluck('number')->all());
    }

    /**
     * Allocate the next available squad number from the in-memory map.
     * Mutates the map to reserve the number — no DB query.
     */
    private function allocateSquadNumber(Collection &$takenNumbers, string $teamId): int
    {
        $taken = $takenNumbers->get($teamId, []);
        $takenSet = array_flip($taken);

        for ($n = 2; $n <= 99; $n++) {
            if (! isset($takenSet[$n])) {
                $taken[] = $n;
                $takenNumbers->put($teamId, $taken);

                return $n;
            }
        }

        return 99;
    }

    /**
     * Flush all batched player updates and transfer inserts to the database.
     */
    private function flushBatchedOperations(array $playerUpdates, array $transferInserts): void
    {
        foreach (array_chunk($playerUpdates, 100) as $chunk) {
            GamePlayer::upsert($chunk, ['id'], ['team_id', 'number', 'contract_until', 'annual_wage']);
        }

        foreach (array_chunk($transferInserts, 100) as $chunk) {
            GameTransfer::insert($chunk);
        }
    }

    // ── Budget & tracking helpers ───────────────────────────────────────

    /**
     * Compute a deterministic transfer budget for a team using hash-based distribution.
     * Same inputs always produce the same budget — no state storage needed.
     */
    private function computeDeterministicBudget(string $teamId, string $season, string $window, bool $isSummer): int
    {
        $hash = crc32($teamId . $season . $window);
        $weights = $isSummer ? self::TRANSFER_COUNT_WEIGHTS_SUMMER : self::TRANSFER_COUNT_WEIGHTS_WINTER;
        $total = array_sum($weights);
        $roll = (($hash & 0x7FFFFFFF) % $total) + 1;
        $cumulative = 0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Build deterministic budgets for all AI teams, with prior activity subtracted.
     */
    private function buildDeterministicBudgets(
        Collection $teamRosters,
        Game $game,
        string $window,
        bool $isSummer,
        ?Collection $completedCounts = null,
    ): Collection {
        return $teamRosters->mapWithKeys(function ($players, $teamId) use ($game, $window, $isSummer, $completedCounts) {
            $completed = $completedCounts?->get($teamId, ['sells' => 0, 'buys' => 0]) ?? ['sells' => 0, 'buys' => 0];

            return [$teamId => [
                'max' => $this->computeDeterministicBudget($teamId, $game->season, $window, $isSummer),
                'sells' => $completed['sells'],
                'buys' => $completed['buys'],
            ]];
        });
    }

    /**
     * Count completed sells and buys per team from existing transfer records.
     */
    private function buildCompletedCounts(Collection $transfers, string $gameTeamId): Collection
    {
        $counts = collect();

        foreach ($transfers as $transfer) {
            if ($transfer->from_team_id && $transfer->from_team_id !== $gameTeamId) {
                $teamId = $transfer->from_team_id;
                $current = $counts->get($teamId, ['sells' => 0, 'buys' => 0]);
                $current['sells']++;
                $counts->put($teamId, $current);
            }

            if ($transfer->to_team_id && $transfer->to_team_id !== $gameTeamId) {
                $teamId = $transfer->to_team_id;
                $current = $counts->get($teamId, ['sells' => 0, 'buys' => 0]);
                $current['buys']++;
                $counts->put($teamId, $current);
            }
        }

        return $counts;
    }

    // ── Data loading helpers ────────────────────────────────────────────

    /**
     * Load reputation tier indices for all AI teams.
     *
     * @return Collection<string, int> teamId => reputation index (0 = elite, 6 = local)
     */
    private function loadTeamReputations(Game $game, Collection $teamRosters): Collection
    {
        $levels = TeamReputation::resolveLevels($game->id, $teamRosters->keys()->all());

        return $teamRosters->keys()->mapWithKeys(function ($teamId) use ($levels) {
            $level = $levels->get($teamId) ?? ClubProfile::REPUTATION_LOCAL;

            // Invert: ClubProfile uses 0=local,6=elite; this service needs 0=elite,6=local
            return [$teamId => 6 - ClubProfile::getReputationTierIndex($level)];
        });
    }

    private function getReputationIndex(string $teamId, Collection $teamReputations): int
    {
        return $teamReputations->get($teamId, 6);
    }

    // ── Utility helpers ─────────────────────────────────────────────────

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
}
