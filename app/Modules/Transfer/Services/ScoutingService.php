<?php

namespace App\Modules\Transfer\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Support\Money;
use App\Support\PlayerDossierPresenter;
use App\Support\PositionMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Modules\Player\PlayerAge;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;

class ScoutingService
{

    /**
     * Scouting tier effects on searches.
     * [weeks_reduction, extra_results]
     */
    private const SCOUTING_TIER_EFFECTS = [
        0 => [0, 0],   // No scouting department
        1 => [0, 0],   // Basic - domestic only, baseline
        2 => [0, 1],   // Good - domestic only, 1 extra result
        3 => [1, 2],   // Excellent - international, 1 week faster, 2 extra results
        4 => [1, 3],   // World-class - international, 1 week faster, 3 extra results
    ];

    /** Minimum scouting tier required for international searches. */
    private const INTERNATIONAL_SEARCH_MIN_TIER = 3;

    /**
     * Willingness threshold (0-100) for a candidate to count as "willing" — the
     * willingness axis of the three-pass selection. Pinned to the "open" label
     * boundary so a willing candidate is exactly one labelled "open" or better
     * in the dossier (never "undecided"); anything between
     * PERSUASION_WILLINGNESS_MIN and this bar is classed as persuadable.
     */
    public const WILLINGNESS_THRESHOLD = DispositionService::WILLINGNESS_OPEN_MIN;

    /**
     * Lower bound of the persuasion bucket. Pinned to the "reluctant" label
     * boundary: candidates below it are "not_interested" — too uninterested to
     * be worth chasing — and are dropped rather than presented as long shots.
     * So the persuasion bucket holds exactly the "undecided" + "reluctant" band.
     */
    private const PERSUASION_WILLINGNESS_MIN = DispositionService::WILLINGNESS_RELUCTANT_MIN;

    /**
     * Max asking-price-to-available-budget ratio for the ambitious bucket.
     * A candidate whose asking price is over budget but within this multiple
     * is shown as "close to affordable". Anything more expensive is dropped,
     * keeping bigger-league stars out of lower-tier scouting results.
     */
    private const AMBITIOUS_BUDGET_MULTIPLIER = 3.0;

    /** Per-bucket cap on scout results. Primary bucket also gets the tier bonus. */
    private const BUCKET_CAP = 5;

    /**
     * Desirability cut-off (0-1) above which rival clubs are shown as interested.
     * A deterministic threshold on the same ability/importance signal, so the
     * "other clubs interested" flag is a stable property of the player rather
     * than a coin flip re-rolled on every dossier render.
     */
    private const RIVAL_INTEREST_THRESHOLD = 0.45;

    /** Maximum players on the shortlist at any time. */
    public const MAX_SHORTLIST_SIZE = 20;

    /** Maximum scout search reports kept at any time. */
    public const MAX_SEARCH_HISTORY = 20;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly DispositionService $dispositionService,
        private readonly SquadNeedService $squadNeedService,
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
    private function calculateSearchWeeks(array $filters, ?Game $game = null): int
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
     *
     * Every SQL-filtered candidate is evaluated against three binary axes:
     *   - improves:   candidate's overall ability beats our squad average at
     *                 the searched position (trivially true if we have no one
     *                 who plays there — positional gap).
     *   - affordable: asking price fits within available transfer budget.
     *   - willing:    willingness score ≥ WILLINGNESS_THRESHOLD.
     *
     * Candidates who don't improve us are dropped regardless of the other
     * axes. The remaining candidates fall into one of three labelled buckets:
     *   - primary    — all three axes met.
     *   - ambitious  — improves + willing, price ≤ AMBITIOUS_BUDGET_MULTIPLIER × budget.
     *   - persuasion — improves + affordable, willingness ∈ [PERSUASION_WILLINGNESS_MIN, threshold).
     * Candidates that fit no bucket are dropped.
     */
    private function generateResults(Game $game, ScoutReport $report): void
    {
        $filters = $report->filters;
        $positions = PositionMapper::getPositionsForFilter($filters['position']) ?? [];

        if (!$this->canSearchInternationally($game)) {
            $filters['scope'] = ['domestic'];
        }

        $queryBuilder = app(ScoutSearchQueryBuilder::class);
        $candidates = $queryBuilder->buildCandidateQuery($game, $filters, $positions)->get();

        if ($candidates->isEmpty()) {
            $this->persistEmptyResults($report);

            return;
        }

        // Pre-load candidate team rosters once so importance can be computed
        // without N+1 queries inside the candidate loop.
        $candidateTeamIds = $candidates->pluck('team_id')->unique();
        $teamRosters = GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $candidateTeamIds)
            ->get()
            ->groupBy('team_id');

        $squadAverage = $this->calculateOwnSquadAverageForPositions($game, $positions);
        $availableBudget = $this->availableTransferBudget($game);

        $primary = collect();
        $ambitious = collect();
        $persuasion = collect();

        foreach ($candidates as $candidate) {
            $evaluation = $this->evaluateCandidate(
                $candidate,
                $game,
                $teamRosters->get($candidate->team_id, collect()),
                $squadAverage,
                $availableBudget,
            );

            if (!$evaluation['improves']) {
                continue;
            }

            if ($evaluation['affordable'] && $evaluation['willing']) {
                $primary->push($evaluation);
            } elseif ($evaluation['willing']
                && $availableBudget > 0
                && $evaluation['asking_price'] <= $availableBudget * self::AMBITIOUS_BUDGET_MULTIPLIER
            ) {
                $ambitious->push($evaluation);
            } elseif ($evaluation['affordable']
                && !$evaluation['willing']
                && $evaluation['willingness_score'] >= self::PERSUASION_WILLINGNESS_MIN
            ) {
                $persuasion->push($evaluation);
            }
        }

        $tier = $game->currentInvestment->scouting_tier ?? 1;
        $primaryCap = self::BUCKET_CAP + (self::SCOUTING_TIER_EFFECTS[$tier][1] ?? 0);

        $primaryIds = $primary
            ->sortByDesc(fn ($e) => $e['overall_ability'] * 1000 + $e['willingness_score'])
            ->take($primaryCap)
            ->pluck('player.id')->values()->toArray();

        $ambitiousIds = $ambitious
            ->sortByDesc(fn ($e) => $e['overall_ability'])
            ->take(self::BUCKET_CAP)
            ->pluck('player.id')->values()->toArray();

        $persuasionIds = $persuasion
            ->sortByDesc(fn ($e) => $e['willingness_score'] * 1000 + $e['overall_ability'])
            ->take(self::BUCKET_CAP)
            ->pluck('player.id')->values()->toArray();

        $allIds = array_values(array_unique(array_merge($primaryIds, $ambitiousIds, $persuasionIds)));

        $report->update([
            'status' => ScoutReport::STATUS_COMPLETED,
            'player_ids' => $allIds,
            'filters' => array_merge($report->filters, [
                'primary_player_ids' => $primaryIds,
                'ambitious_player_ids' => $ambitiousIds,
                'persuasion_player_ids' => $persuasionIds,
            ]),
        ]);
    }

    /**
     * Persist an empty scout report. Used when no candidate clears the SQL
     * pre-filter or no candidate passes the three-pass evaluation — either
     * way the UI renders the "no realistic candidates" empty state.
     */
    private function persistEmptyResults(ScoutReport $report): void
    {
        $report->update([
            'status' => ScoutReport::STATUS_COMPLETED,
            'player_ids' => [],
            'filters' => array_merge($report->filters, [
                'primary_player_ids' => [],
                'ambitious_player_ids' => [],
                'persuasion_player_ids' => [],
            ]),
        ]);
    }

    /**
     * Evaluate a candidate on the three selection axes and compute the
     * supporting numbers the ranker needs.
     *
     * @param  Collection<int, GamePlayer>  $teammates
     * @return array{
     *     player: GamePlayer,
     *     overall_ability: float,
     *     asking_price: int,
     *     willingness_score: int,
     *     improves: bool,
     *     affordable: bool,
     *     willing: bool,
     * }
     */
    private function evaluateCandidate(
        GamePlayer $candidate,
        Game $game,
        Collection $teammates,
        ?float $squadAverage,
        int $availableBudget,
    ): array {
        $overallAbility = (float) $candidate->overall_score;
        $importance = $this->calculatePlayerImportance($candidate, $teammates);
        $askingPrice = $this->calculateAskingPrice($candidate, $game->current_date);
        $willingness = $this->dispositionService->playerTransferWillingness($candidate, $game, $importance)['score'];

        // If we have no one at this position, any candidate fills the gap.
        // Otherwise allow candidates within 5 ability points of our squad
        // average so depth/rotation signings still surface — clearly worse
        // players are still filtered out.
        $improves = $squadAverage === null || $overallAbility >= ($squadAverage - 5);
        $affordable = $askingPrice <= $availableBudget;
        $willing = $willingness >= self::WILLINGNESS_THRESHOLD;

        return [
            'player' => $candidate,
            'overall_ability' => $overallAbility,
            'asking_price' => $askingPrice,
            'willingness_score' => $willingness,
            'improves' => $improves,
            'affordable' => $affordable,
            'willing' => $willing,
        ];
    }

    /**
     * Average overall ability of our current players who can play any of the
     * requested positions (primary OR secondary). Returns null if we have no
     * such player — the caller treats that as "positional gap, any candidate
     * improves us".
     *
     * @param  string[]  $positions
     */
    private function calculateOwnSquadAverageForPositions(Game $game, array $positions): ?float
    {
        if (empty($positions)) {
            return null;
        }

        $positionSet = array_flip($positions);

        $squad = GamePlayer::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        $matching = $squad->filter(function (GamePlayer $player) use ($positionSet) {
            if (isset($positionSet[$player->position])) {
                return true;
            }

            foreach ($player->secondary_positions ?? [] as $secondary) {
                if (isset($positionSet[$secondary])) {
                    return true;
                }
            }

            return false;
        });

        if ($matching->isEmpty()) {
            return null;
        }

        return $matching->avg(fn (GamePlayer $p) => $p->overall_score);
    }

    /**
     * Current available transfer budget: the season's transfer_budget minus
     * the fees already committed in outstanding TransferOffers. Extracted so
     * scouting result selection and per-player scouting detail share one
     * source of truth.
     */
    private function availableTransferBudget(Game $game): int
    {
        $investment = $game->currentInvestment;
        $committed = TransferOffer::committedBudget($game->id);

        return ($investment->transfer_budget ?? 0) - $committed;
    }

    // =========================================
    // ASKING PRICE CALCULATION
    // =========================================

    /**
     * Calculate the AI's asking price for a player.
     */
    public function calculateAskingPrice(GamePlayer $player, Carbon $currentDate, ?Collection $teammates = null): int
    {
        $base = $player->market_value_cents;
        $importance = $this->calculatePlayerImportance($player, $teammates);

        // Contract leverage: a club can only charge an importance premium if
        // it has leverage to refuse bids. As the contract runs down that
        // leverage decays — an expiring star is worth about what a buyer
        // would pay for any player they can pick up free next window.
        $leverage = $this->getContractLeverage($player, $currentDate);
        $effectiveImportance = $importance * $leverage;

        // Importance multiplier: 0.8x for worst (or no leverage), 1.0x for
        // average, 1.2x for the club's best player on a long contract.
        $importanceMultiplier = 0.8 + ($effectiveImportance * 0.4);

        // Contract modifier (fee discount from less time remaining)
        $contractModifier = $this->getContractModifier($player, $currentDate);

        // Age modifier
        $ageModifier = $this->getAgeModifier($player->age($currentDate));

        $totalMultiplier = $importanceMultiplier * $contractModifier * $ageModifier;

        // Important players are never sold below market value — the club's
        // reluctance (see DispositionService::clubSellDisposition) is driven
        // by raw importance, so the asking price floor must be too. Contract
        // leverage decay still reduces the premium above market value via
        // $importanceMultiplier, but shouldn't drag a key player's price
        // below 1.0x. Non-key players can be discounted down to 0.75x.
        $floor = $importance >= 0.5 ? 1.0 : 0.75;
        $totalMultiplier = min(max($totalMultiplier, $floor), 1.5);

        $askingPrice = $base * $totalMultiplier;

        return Money::roundPrice((int) $askingPrice);
    }

    /**
     * Calculate player importance within their team (0.0 to 1.0).
     *
     * @param GamePlayer $player
     * @param Collection|null $teammates Pre-loaded teammates to avoid repeated queries
     */
    public function calculatePlayerImportance(GamePlayer $player, ?Collection $teammates = null): float
    {
        return $this->dispositionService->playerImportance($player, $teammates);
    }

    /**
     * Get the contract leverage factor (0.0 to 1.0).
     *
     * A club can only charge an importance premium if it has leverage to
     * refuse bids. As the contract runs down that leverage decays — at the
     * expiring end there is no premium because the buyer can simply wait and
     * sign free.
     */
    private function getContractLeverage(GamePlayer $player, Carbon $currentDate): float
    {
        if (! $player->contract_until) {
            return 0.0;
        }

        $yearsLeft = $currentDate->diffInYears($player->contract_until);

        if ($yearsLeft >= 4) {
            return 1.0;
        }
        if ($yearsLeft >= 3) {
            return 0.85;
        }
        if ($yearsLeft >= 2) {
            return 0.65;
        }
        if ($yearsLeft >= 1) {
            return 0.30;
        }

        return 0.0; // Expiring
    }

    /**
     * Get contract years modifier for asking price.
     */
    private function getContractModifier(GamePlayer $player, Carbon $currentDate): float
    {
        if (! $player->contract_until) {
            return 0.5;
        }

        $yearsLeft = $currentDate->diffInYears($player->contract_until);

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
        $currentDate = $game?->current_date ?? $player->game->current_date;
        $askingPrice = $this->calculateAskingPrice($player, $currentDate);

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
            $counterAmount = Money::roundPrice($counterAmount);

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
     * Counter-offer willingness premium curve, applied to market value:
     *   desire 0.0 → 0.95× MV  (no need: rejects premium counters, settles just below market)
     *   desire 1.0 → 1.50× MV  (clear need/upgrade: pays a real premium)
     */
    private const COUNTER_PREMIUM_FLOOR = 0.95;
    private const COUNTER_PREMIUM_CEIL = 1.50;

    /** Reproducible ±band variance on the premium, seeded from the offer uuid. */
    private const COUNTER_PREMIUM_JITTER = 0.05;

    /** Below this desire, the buyer won't floor its willingness at its current bid. */
    private const COUNTER_FLOOR_DESIRE_THRESHOLD = 0.40;

    /** A club won't spend more than this fraction of its squad value on one player. */
    private const COUNTER_SQUAD_VALUE_RATIO = 0.25;

    /**
     * Evaluate the user's counter-offer from the AI buyer's perspective.
     *
     * Called when the user counters an unsolicited or listed offer with a higher asking price.
     * The AI club evaluates whether to accept, counter, or walk away.
     *
     * The buyer's max willingness is market_value × a premium that scales with
     * how badly it wants the player (SquadNeedService::desireScore), clamped by
     * an affordability ceiling. A club that needs the player and is upgrading
     * pays a real premium; a deep-squad club with no need won't be talked up to
     * (or even back up to) market value — so a countered offer no longer always
     * beats market. This is the buyer-side counterpart to calculateAskingPrice's
     * seller-side reluctance model; keep the two axes separate.
     *
     * @return array{result: string, counter_amount: int|null}
     */
    public function evaluateCounterOffer(TransferOffer $offer, int $userAskingPrice, Game $game): array
    {
        $player = $offer->gamePlayer;
        $marketValue = $player->market_value_cents;

        // Load the buyer roster once (rows, not a SUM): both the affordability
        // clamp and the desire score derive from it, keeping this to one query.
        $buyerRoster = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $offer->offering_team_id)
            ->get(['id', 'team_id', 'position', 'overall_score', 'market_value_cents']);

        $squadValueCeiling = (int) ($buyerRoster->sum('market_value_cents') * self::COUNTER_SQUAD_VALUE_RATIO);

        // Desire (0..1) drives how far above — or below — market the buyer goes.
        $desire = $this->squadNeedService->desireScore($buyerRoster, $player);
        $premium = self::COUNTER_PREMIUM_FLOOR + $desire * (self::COUNTER_PREMIUM_CEIL - self::COUNTER_PREMIUM_FLOOR);
        $premium += $this->squadNeedService->jitter($offer->id, self::COUNTER_PREMIUM_JITTER);
        $premium = max(self::COUNTER_PREMIUM_FLOOR - self::COUNTER_PREMIUM_JITTER, $premium);

        $maxWillingness = min($squadValueCeiling, (int) ($marketValue * $premium));

        // Only floor at the club's current bid when it genuinely wants the
        // player. This lets a low-desire buyer settle below its own (possibly
        // pre-change) opening bid rather than being forced above market.
        if ($desire >= self::COUNTER_FLOOR_DESIRE_THRESHOLD) {
            $maxWillingness = max($maxWillingness, $offer->transfer_fee);
        }

        if ($userAskingPrice <= (int) ($maxWillingness * 0.95)) {
            return [
                'result' => 'accepted',
                'counter_amount' => null,
            ];
        }

        if ($userAskingPrice <= (int) ($maxWillingness * 1.15)) {
            // Counter with midpoint of user's ask and AI's current bid
            $counterAmount = (int) (($userAskingPrice + $offer->transfer_fee) / 2);
            $counterAmount = Money::roundPrice($counterAmount);

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
        return $this->dispositionService->evaluateLoanRequest($player, $game);
    }

    // =========================================
    // SYNCHRONOUS LOAN EVALUATION
    // =========================================

    /**
     * Deterministic loan request evaluation for sync negotiation.
     * Returns result, asking loan fee, mood, and rejection reason.
     *
     * @return array{result: string, disposition: float, rejection_reason: ?string}
     */
    public function evaluateLoanRequestSync(GamePlayer $player, Game $game): array
    {
        return $this->dispositionService->evaluateLoanRequestSync($player, $game);
    }

    // =========================================
    // WAGE DEMAND
    // =========================================

    // =========================================
    // REPUTATION GATE
    // =========================================

    // =========================================
    // FREE AGENT REPUTATION GATE
    // =========================================

    /**
     * Check whether a free agent is willing to sign for a given team,
     * based on the player's tier vs the team's reputation.
     */
    public function canSignFreeAgent(GamePlayer $player, string $gameId, string $teamId): bool
    {
        return $this->dispositionService->canSignFreeAgent($player, $gameId, $teamId);
    }

    /**
     * Determine a free agent's willingness to sign for a team.
     *
     * @return string 'willing' (will sign), 'reluctant' (1 tier below minimum), or 'unwilling' (2+ below)
     */
    public function getFreeAgentWillingnessLevel(GamePlayer $player, string $gameId, string $teamId): string
    {
        return $this->dispositionService->freeAgentWillingnessLevel($player, $gameId, $teamId);
    }

    // =========================================
    // SCOUTING REPORT DATA
    // =========================================

    /**
     * Evaluate whether a player accepts a pre-contract offer based on offered wage vs demand,
     * reputation gap, and player ambition.
     *
     * @return array{accepted: bool, message: string}
     */
    public function evaluatePreContractOffer(GamePlayer $player, int $offeredWage, Team $biddingTeam): array
    {
        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::PRE_CONTRACT, $biddingTeam);

        return $this->dispositionService->evaluatePreContractOffer($player, $offeredWage, $demand['wage'], $biddingTeam);
    }

    /**
     * Get scouting detail for a specific player.
     */
    public function getPlayerScoutingDetail(GamePlayer $player, Game $game): array
    {
        $isFreeAgent = $player->team_id === null;
        $isOnLoan = !$isFreeAgent && Loan::where('game_player_id', $player->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->exists();
        $askingPrice = $isFreeAgent ? 0 : $this->calculateAskingPrice($player, $game->current_date);
        $transferDemand = $this->contractService->calculateWageDemand($player, NegotiationScenario::TRANSFER, $game->team);
        $wageDemand = $transferDemand['wage'];
        $importance = $isFreeAgent ? 0.0 : $this->calculatePlayerImportance($player);

        // For expiring-contract players, show the premium wage demand
        $isExpiring = $player->contract_until && $player->contract_until <= $game->getSeasonEndDate();
        $preContractWageDemand = $isExpiring
            ? $this->contractService->calculateWageDemand($player, NegotiationScenario::PRE_CONTRACT, $game->team)['wage']
            : null;

        $investment = $game->currentInvestment;
        $availableBudget = $this->availableTransferBudget($game);
        $canAffordFee = $askingPrice <= $availableBudget;
        $canAffordLoan = $isFreeAgent || $wageDemand <= $availableBudget;

        return [
            'player' => $player,
            'is_free_agent' => $isFreeAgent,
            'is_on_loan' => $isOnLoan,
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
        ];
    }

    /**
     * Assemble the self-contained dossier payload for a shortlisted target. The
     * payload is the same shape produced for every other surface (see {@see
     * PlayerDossierPresenter}), so a shortlist card opens the shared
     * <x-player-dossier-modal>; the scouting-only intel (willingness, finances,
     * rival interest) and the shortlist remove action are overlaid here. Shared
     * by the scouting hub (page load, in bulk) and the shortlist toggle (single
     * add) so a freshly-starred card renders identically to one rendered on page load.
     *
     * @param  array<string, mixed>|null  $offerStatus  This player's row from TransferOffer::getOfferStatusesForPlayers(), or null.
     * @param  Collection<int, GamePlayer>|null  $teammates  Pre-loaded squad of the player's team (avoids an N+1 in importance).
     * @return array<string, mixed>
     */
    public function buildTargetData(
        GamePlayer $gp,
        Game $game,
        ?array $offerStatus,
        ?Collection $teammates = null,
    ): array {
        $detail = $this->getPlayerScoutingDetail($gp, $game);
        $importance = $this->calculatePlayerImportance($gp, $teammates);
        $willingness = $this->calculateWillingness($gp, $game, $importance);

        // Overlay scouting-only intel + offer state + the shortlist remove action
        // onto the base scouting detail, then build the shared dossier payload.
        $scouting = $detail + [
            'has_existing_offer' => ($offerStatus['status'] ?? null) !== null,
            'offer_status' => $offerStatus['status'] ?? null,
            'offer_is_counter' => $offerStatus['isCounter'] ?? false,
            'on_cooldown' => $offerStatus['onCooldown'] ?? false,
            'willingness_label' => $willingness['label'],
            'rival_interest' => $this->calculateRivalInterest($gp, $importance),
            'is_shortlisted' => true,
            'remove_url' => route('game.scouting.shortlist.remove', [$game->id, $gp->id]),
        ];

        return PlayerDossierPresenter::build($gp, $game, $scouting);
    }

    /**
     * Check if the shortlist is full.
     */
    public function isShortlistFull(Game $game): bool
    {
        return ShortlistedPlayer::where('game_id', $game->id)->count() >= self::MAX_SHORTLIST_SIZE;
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
     * Calculate a player's willingness to transfer (0-100 score mapped to label).
     *
     * @return array{score: int, label: string}
     */
    public function calculateWillingness(GamePlayer $player, Game $game, ?float $importance = null): array
    {
        return $this->dispositionService->playerTransferWillingness($player, $game, $importance);
    }

    /**
     * Calculate whether rival clubs are also interested in a player.
     */
    public function calculateRivalInterest(GamePlayer $player, ?float $importance = null): bool
    {
        $overallAbility = $player->overall_score;
        $importance ??= $this->calculatePlayerImportance($player);

        // Higher ability + lower importance = more desirable to rivals.
        $desirability = ($overallAbility / 99) * 0.4 + (1.0 - $importance) * 0.3;

        return $desirability >= self::RIVAL_INTEREST_THRESHOLD;
    }
}
