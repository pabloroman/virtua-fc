<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use App\Modules\Player\Services\PlayerDevelopmentService;
use Illuminate\Support\Collection;

/**
 * Builds a projection of the user's squad for the start of the next season.
 *
 * Partitions players into three buckets:
 *   - STAYING:   currently owned and still owned at next-season kickoff
 *   - OUTGOING:  currently here but gone by next-season kickoff
 *   - INCOMING:  not yet here but on the books to arrive (pre-contracts,
 *                loan returns) by next-season kickoff
 *
 * Each player is enriched with a projected next-season age and overall_score
 * plus a reason code describing why they fall into their bucket. The service
 * is pure (no DB writes) and intended for a read-only planning surface.
 */
class NextSeasonProjectionService
{
    public const STATUS_STAYING = 'staying';
    public const STATUS_OUTGOING = 'outgoing';
    public const STATUS_INCOMING = 'incoming';

    public const HORIZON_CURRENT = 'current';
    public const HORIZON_NEXT = 'next';

    public const REASON_OWNED = 'owned';
    public const REASON_RETURNING_FROM_LOAN = 'returning_from_loan';
    public const REASON_STILL_ON_LOAN = 'still_on_loan';
    public const REASON_RENEWED = 'renewed';

    public const REASON_RETIRING = 'retiring';
    public const REASON_TRANSFER_AGREED = 'transfer_agreed';
    public const REASON_PRE_CONTRACT_DEPARTING = 'pre_contract_departing';
    public const REASON_CONTRACT_EXPIRING_UNRENEWED = 'contract_expiring_unrenewed';
    public const REASON_LOAN_ENDING = 'loan_ending';

    public const REASON_PRE_CONTRACT_JOINING = 'pre_contract_joining';

    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    /**
     * Build the squad projection for the chosen horizon.
     *
     * - HORIZON_NEXT (default): season+1 — projects ages, advances overall
     *   scores by one season of development, partitions the squad into
     *   staying / outgoing / incoming based on contracts, retirements,
     *   transfers, and loan return dates.
     * - HORIZON_CURRENT: today — every owned player sits in `staying` with
     *   reason `owned`, no age advance, no projection delta. Outgoing /
     *   incoming are empty. Provides a "what does the team look like right
     *   now?" comparison view.
     *
     * @return array{
     *     staying: array{goalkeepers: Collection, defenders: Collection, midfielders: Collection, forwards: Collection},
     *     outgoing: Collection,
     *     incoming: Collection,
     *     counts: array{staying: int, outgoing: int, incoming: int},
     *     seasonEndDate: \Carbon\Carbon,
     *     nextSeasonStartYear: int,
     *     horizon: string,
     * }
     */
    public function build(Game $game, string $horizon = self::HORIZON_NEXT): array
    {
        $horizon = $horizon === self::HORIZON_CURRENT ? self::HORIZON_CURRENT : self::HORIZON_NEXT;
        $seasonEndDate = $game->getSeasonEndDate();
        $referenceDate = $horizon === self::HORIZON_CURRENT
            ? $game->current_date
            : $seasonEndDate->copy()->addDay();

        $owned = $this->loadOwnedPlayers($game);
        $loanedIn = $this->loadLoanedInPlayers($game);
        $incomingPreContracts = $horizon === self::HORIZON_NEXT
            ? $this->loadIncomingPreContracts($game)
            : collect();

        $staying = collect();
        $outgoing = collect();
        $incoming = collect();

        foreach ($owned as $player) {
            // Current-mode keeps everyone on the books; next-mode partitions.
            $verdict = $horizon === self::HORIZON_CURRENT
                ? ['status' => self::STATUS_STAYING, 'reason' => self::REASON_OWNED]
                : $this->classifyOwned($player, $seasonEndDate);
            $this->enrich($player, $game, $referenceDate, $verdict['status'], $verdict['reason'], $horizon);

            if ($verdict['status'] === self::STATUS_STAYING) {
                $staying->push($player);
            } else {
                $outgoing->push($player);
            }
        }

        foreach ($loanedIn as $player) {
            if ($horizon === self::HORIZON_CURRENT) {
                $this->enrich($player, $game, $referenceDate, self::STATUS_STAYING, self::REASON_OWNED, $horizon);
                $staying->push($player);
                continue;
            }

            $reason = $this->classifyLoanedIn($player, $seasonEndDate);
            $status = $reason === self::REASON_STILL_ON_LOAN
                ? self::STATUS_STAYING
                : self::STATUS_OUTGOING;
            $this->enrich($player, $game, $referenceDate, $status, $reason, $horizon);

            if ($status === self::STATUS_STAYING) {
                $staying->push($player);
            } else {
                $outgoing->push($player);
            }
        }

        foreach ($incomingPreContracts as $player) {
            $this->enrich($player, $game, $referenceDate, self::STATUS_INCOMING, self::REASON_PRE_CONTRACT_JOINING, $horizon);
            $incoming->push($player);
        }

        $stayingByPosition = $this->groupByPosition($staying);

        return [
            'staying' => $stayingByPosition,
            'outgoing' => $outgoing->sortByDesc('overall_score')->values(),
            'incoming' => $incoming->sortByDesc('overall_score')->values(),
            'counts' => [
                'staying' => $staying->count(),
                'outgoing' => $outgoing->count(),
                'incoming' => $incoming->count(),
            ],
            'seasonEndDate' => $seasonEndDate,
            'nextSeasonStartYear' => $seasonEndDate->copy()->addDay()->year,
            'horizon' => $horizon,
        ];
    }

    /**
     * Players the user owns: physically at the user's team (and not loaned-in
     * from elsewhere), or loaned-out from the user's team to another club.
     */
    private function loadOwnedPlayers(Game $game): Collection
    {
        return GamePlayer::with([
            'game',
            'team',
            'matchState',
            'activeLoan',
            'transferOffers',
            'activeRenewalNegotiation',
            'latestRenewalNegotiation',
        ])
            ->where('game_id', $game->id)
            ->ownedByTeam($game->team_id)
            ->get();
    }

    /**
     * Players currently at the user's team but borrowed from another club.
     * Their owning club regains them at loan return.
     */
    private function loadLoanedInPlayers(Game $game): Collection
    {
        return GamePlayer::with([
            'game',
            'team',
            'matchState',
            'activeLoan',
            'transferOffers',
        ])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereHas('activeLoan', fn ($q) => $q->where('loan_team_id', $game->team_id))
            ->get();
    }

    /**
     * Players the user has signed on a pre-contract that arrive at season end.
     * The TransferOffer carries the agreement; the player still belongs to their
     * current club until the deal completes. Pre-loads transferOffers so the
     * action recommender and advisor can read renewal/pre-contract state
     * without lazy-loading once per row.
     */
    private function loadIncomingPreContracts(Game $game): Collection
    {
        $offers = TransferOffer::with([
            'gamePlayer.game',
            'gamePlayer.team',
            'gamePlayer.matchState',
            'gamePlayer.transferOffers',
            'gamePlayer.activeRenewalNegotiation',
            'gamePlayer.latestRenewalNegotiation',
        ])
            ->where('game_id', $game->id)
            ->where('offering_team_id', $game->team_id)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->get();

        return $offers->map(fn (TransferOffer $offer) => $offer->gamePlayer)->filter()->values();
    }

    /**
     * Classify an owned player into STAYING / OUTGOING with a reason.
     *
     * @return array{status: string, reason: string}
     */
    private function classifyOwned(GamePlayer $player, \Carbon\Carbon $seasonEndDate): array
    {
        if ($player->isRetiring()) {
            return ['status' => self::STATUS_OUTGOING, 'reason' => self::REASON_RETIRING];
        }

        if ($player->hasAgreedTransfer() && ! $player->hasPreContractAgreement()) {
            return ['status' => self::STATUS_OUTGOING, 'reason' => self::REASON_TRANSFER_AGREED];
        }

        if ($player->hasPreContractAgreement()) {
            return ['status' => self::STATUS_OUTGOING, 'reason' => self::REASON_PRE_CONTRACT_DEPARTING];
        }

        if ($player->isContractExpiring($seasonEndDate) && ! $player->hasRenewalAgreed()) {
            return ['status' => self::STATUS_OUTGOING, 'reason' => self::REASON_CONTRACT_EXPIRING_UNRENEWED];
        }

        // Loaned out: user retains ownership, but the player is away. Distinguish
        // between loans that wrap up before next season starts (returning home)
        // and loans that extend past it (still away when next season kicks off).
        $activeLoan = $player->activeLoan;
        if ($activeLoan !== null && $activeLoan->return_at !== null) {
            if ($activeLoan->return_at->gt($seasonEndDate)) {
                return ['status' => self::STATUS_STAYING, 'reason' => self::REASON_STILL_ON_LOAN];
            }

            return ['status' => self::STATUS_STAYING, 'reason' => self::REASON_RETURNING_FROM_LOAN];
        }

        if ($player->hasRenewalAgreed()) {
            return ['status' => self::STATUS_STAYING, 'reason' => self::REASON_RENEWED];
        }

        return ['status' => self::STATUS_STAYING, 'reason' => self::REASON_OWNED];
    }

    /**
     * Loaned-in players are physically at the user's team but borrowed.
     * They leave when the loan returns home, which usually happens at season end.
     */
    private function classifyLoanedIn(GamePlayer $player, \Carbon\Carbon $seasonEndDate): string
    {
        $loan = $player->activeLoan;

        if ($loan && $loan->return_at !== null && $loan->return_at->gt($seasonEndDate)) {
            return self::REASON_STILL_ON_LOAN;
        }

        return self::REASON_LOAN_ENDING;
    }

    /**
     * Attach projection attributes to the player for the Blade layer to render.
     *
     * When viewing the current horizon, no development projection is applied —
     * we expose today's age and today's overall_score so the user sees the
     * current squad as-is.
     */
    private function enrich(
        GamePlayer $player,
        Game $game,
        \Carbon\Carbon $referenceDate,
        string $status,
        string $reason,
        string $horizon = self::HORIZON_NEXT,
    ): void {
        $player->setAttribute('next_season_status', $status);
        $player->setAttribute('next_season_reason', $reason);
        $player->setAttribute('next_season_age', $player->age($referenceDate));

        $projection = $horizon === self::HORIZON_NEXT
            ? $this->developmentService->getNextSeasonProjection($player)
            : 0;
        $player->setAttribute('projection', $projection);
        $player->setAttribute('next_season_overall', max(1, min(99, $player->overall_score + $projection)));
    }

    /**
     * The pool of players actually available for next-season selection —
     * STAYING (minus still-on-loan) plus INCOMING. Shared by the classifier
     * and the advisor so both speak about the same roster.
     */
    public function availablePool(array $projection): Collection
    {
        $staying = collect()
            ->merge($projection['staying']['goalkeepers'])
            ->merge($projection['staying']['defenders'])
            ->merge($projection['staying']['midfielders'])
            ->merge($projection['staying']['forwards']);

        return $staying
            ->reject(fn (GamePlayer $p) => $p->next_season_reason === self::REASON_STILL_ON_LOAN)
            ->merge($projection['incoming']);
    }

    /**
     * Group a collection of players by position group, sorted by overall_score.
     *
     * @return array{goalkeepers: Collection, defenders: Collection, midfielders: Collection, forwards: Collection}
     */
    private function groupByPosition(Collection $players): array
    {
        $grouped = $players->groupBy(fn (GamePlayer $p) => match ($p->position_group) {
            'Goalkeeper' => 'goalkeepers',
            'Defender' => 'defenders',
            'Midfielder' => 'midfielders',
            'Forward' => 'forwards',
            default => 'midfielders',
        });

        return [
            'goalkeepers' => ($grouped->get('goalkeepers') ?? collect())->sortByDesc('overall_score')->values(),
            'defenders' => ($grouped->get('defenders') ?? collect())->sortByDesc('overall_score')->values(),
            'midfielders' => ($grouped->get('midfielders') ?? collect())->sortByDesc('overall_score')->values(),
            'forwards' => ($grouped->get('forwards') ?? collect())->sortByDesc('overall_score')->values(),
        ];
    }
}
