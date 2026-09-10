<?php

namespace App\Modules\Squad\Services;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;
use App\Modules\Player\Services\PlayerDevelopmentService;
use App\Modules\Squad\Enums\PositionGroup;
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
    public const REASON_RESERVE_PROMOTED = 'reserve_promoted';

    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    /**
     * Build the squad projection for the start of next season: projects ages,
     * advances overall scores by one season of development, partitions the
     * squad into staying / outgoing / incoming based on contracts, retirements,
     * transfers, and loan return dates.
     *
     * incoming_academy is a sidecar collection of AcademyPlayer rows for
     * non-filial games — academy prospects who age out and auto-promote into
     * the first team at next season's setup. They're surfaced as INCOMING in
     * the planner but skip the GamePlayer-only role / action pipeline.
     *
     * @return array{
     *     staying: array{goalkeepers: Collection, defenders: Collection, midfielders: Collection, forwards: Collection},
     *     outgoing: Collection,
     *     incoming: Collection,
     *     incoming_academy: Collection,
     *     counts: array{staying: int, outgoing: int, incoming: int},
     *     seasonEndDate: \Carbon\Carbon,
     *     nextSeasonStartYear: int,
     * }
     */
    public function build(Game $game): array
    {
        $seasonEndDate = $game->getSeasonEndDate();
        $referenceDate = $seasonEndDate->copy()->addDay();

        $owned = $this->loadOwnedPlayers($game);
        $loanedIn = $this->loadLoanedInPlayers($game);
        $incomingPreContracts = $this->loadIncomingPreContracts($game);
        $incomingReservePromotions = $this->loadIncomingReservePromotions($game);
        $incomingAcademyPromotions = $this->loadIncomingAcademyPromotions($game, $referenceDate);

        $staying = collect();
        $outgoing = collect();
        $incoming = collect();

        foreach ($owned as $player) {
            $verdict = $this->classifyOwned($player, $seasonEndDate);
            $this->enrich($player, $referenceDate, $verdict['status'], $verdict['reason']);

            if ($verdict['status'] === self::STATUS_STAYING) {
                $staying->push($player);
            } else {
                $outgoing->push($player);
            }
        }

        foreach ($loanedIn as $player) {
            $reason = $this->classifyLoanedIn($player, $game, $seasonEndDate);
            $status = in_array($reason, [self::REASON_STILL_ON_LOAN, self::REASON_OWNED], true)
                ? self::STATUS_STAYING
                : self::STATUS_OUTGOING;
            $this->enrich($player, $referenceDate, $status, $reason);

            if ($status === self::STATUS_STAYING) {
                $staying->push($player);
            } else {
                $outgoing->push($player);
            }
        }

        foreach ($incomingPreContracts as $player) {
            $this->enrich($player, $referenceDate, self::STATUS_INCOMING, self::REASON_PRE_CONTRACT_JOINING);
            $incoming->push($player);
        }

        foreach ($incomingReservePromotions as $player) {
            $this->enrich($player, $referenceDate, self::STATUS_INCOMING, self::REASON_RESERVE_PROMOTED);
            $incoming->push($player);
        }

        $stayingByPosition = $this->groupByPosition($staying);

        return [
            'staying' => $stayingByPosition,
            'outgoing' => $outgoing->sortByDesc('overall_score')->values(),
            'incoming' => $incoming->sortByDesc('overall_score')->values(),
            'incoming_academy' => $incomingAcademyPromotions->sortByDesc('overall_score')->values(),
            'counts' => [
                'staying' => $staying->count(),
                'outgoing' => $outgoing->count(),
                'incoming' => $incoming->count() + $incomingAcademyPromotions->count(),
            ],
            'seasonEndDate' => $seasonEndDate,
            'nextSeasonStartYear' => $seasonEndDate->copy()->addDay()->year,
        ];
    }

    /**
     * The annual wage the club is committed to for the *start of next season*,
     * in cents.
     *
     * The current-season bill (SalaryCapService::committedWageBill) answers a
     * different question: what the club pays right now. The two diverge sharply
     * during the January–May pre-contract window, because most of what changes
     * at the season boundary is already known — contracts run out, borrowed
     * players go home, players retire, and pre-contracts arrive. Gating a
     * next-season commitment on the current-season bill therefore charges a
     * wage against liabilities that will not exist when that wage starts.
     *
     * Counts, using the same staying/outgoing/incoming classification the
     * squad planner shows the user:
     *  - owned players still on the books, at their agreed renewal wage where
     *    one is pending — minus anyone loaned out across the boundary, whose
     *    wage the borrowing club carries while he is away;
     *  - borrowed players whose loan runs past next-season kickoff (the
     *    borrowing club pays in full — there is no loan subsidy). Loans that
     *    end at the boundary are excluded: that freed wage is precisely what
     *    makes room for next season;
     *  - reserve players who age out of the filial and move up automatically;
     *  - agreed incoming pre-contracts and transfers, at the wage agreed
     *    rather than the wage the player earns at his current club.
     *
     * Agreed loan-ins are deliberately absent: a loan returns at season end, so
     * it is a current-season cost, not a next-season commitment.
     */
    public function nextSeasonWageBill(Game $game): int
    {
        $seasonEndDate = $game->getSeasonEndDate();

        $agreedIncoming = TransferOffer::query()
            ->where('game_id', $game->id)
            ->incomingFor($game->team_id)
            ->agreed()
            ->ofType(TransferOffer::TYPE_USER_BID, TransferOffer::TYPE_PRE_CONTRACT)
            ->get(['game_player_id', 'offered_wage']);

        // A player can be both on the roster and the subject of an agreed
        // incoming deal — signing someone the club currently has on loan. The
        // deal supersedes his present wage, so charge the agreed wage only.
        $agreedPlayerIds = $agreedIncoming->pluck('game_player_id')->flip();

        $total = (int) $agreedIncoming->sum('offered_wage');

        foreach ($this->loadOwnedPlayers($game) as $player) {
            if (isset($agreedPlayerIds[$player->id])) {
                continue;
            }

            $verdict = $this->classifyOwned($player, $seasonEndDate);

            if ($verdict['status'] !== self::STATUS_STAYING
                || $verdict['reason'] === self::REASON_STILL_ON_LOAN) {
                continue;
            }

            $total += $this->nextSeasonWageFor($player);
        }

        foreach ($this->loadLoanedInPlayers($game) as $player) {
            if (isset($agreedPlayerIds[$player->id])) {
                continue;
            }

            if ($this->classifyLoanedIn($player, $game, $seasonEndDate) === self::REASON_LOAN_ENDING) {
                continue;
            }

            $total += $this->nextSeasonWageFor($player);
        }

        foreach ($this->loadIncomingReservePromotions($game) as $player) {
            if (isset($agreedPlayerIds[$player->id])) {
                continue;
            }

            $total += $this->nextSeasonWageFor($player);
        }

        return $total;
    }

    /**
     * What a player already on the books will earn next season: the wage agreed
     * in a pending renewal if there is one, otherwise his current wage.
     */
    private function nextSeasonWageFor(GamePlayer $player): int
    {
        return $player->pending_annual_wage ?? $player->annual_wage ?? 0;
    }

    /**
     * Players the user owns: physically at the user's team (and not loaned-in
     * from elsewhere), or loaned-out from the user's team to another club.
     */
    private function loadOwnedPlayers(Game $game): Collection
    {
        return GamePlayer::with([
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
            'gamePlayer.team',
            'gamePlayer.matchState',
            'gamePlayer.transferOffers',
            'gamePlayer.activeRenewalNegotiation',
            'gamePlayer.latestRenewalNegotiation',
        ])
            ->where('game_id', $game->id)
            ->incomingFor($game->team_id)
            ->agreedPreContract()
            ->get();

        return $offers->map(fn (TransferOffer $offer) => $offer->gamePlayer)->filter()->values();
    }

    /**
     * Non-filial only: AcademyPlayer rows that will auto-promote into the
     * first team at the start of next season because they age past the
     * academy cutoff. Mirrors the eligibility rule in
     * YouthAcademyPromotionProcessor: any prospect whose date_of_birth makes
     * them ACADEMY_END or older at the next-season reference date must move
     * up. Filial games handle the equivalent via the reserve squad and do
     * not maintain an AcademyPlayer pool.
     *
     * @return Collection<int, AcademyPlayer>
     */
    private function loadIncomingAcademyPromotions(Game $game, \Carbon\Carbon $referenceDate): Collection
    {
        if ($game->reserve_team_id !== null) {
            return collect();
        }

        $cutoff = PlayerAge::dateOfBirthCutoff(PlayerAge::ACADEMY_END, $referenceDate);

        return AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('date_of_birth', '<=', $cutoff)
            ->get();
    }

    /**
     * Filial-only: reserve players who will auto-promote to the first team
     * at season close because they age past the reserve cutoff. Mirrors the
     * eligibility rule in ReserveOveragePromotionProcessor — a player must
     * move up if their date_of_birth predates next season's U-23 cutoff.
     *
     * Excludes anyone currently on a call-up loan to the first team: those
     * players are already physically on the first-team roster and surface
     * through loadLoanedInPlayers() as STAYING via isCalledUpFromReserve().
     */
    private function loadIncomingReservePromotions(Game $game): Collection
    {
        if ($game->reserve_team_id === null) {
            return collect();
        }

        $nextSeasonU23Cutoff = $game->getU23BirthCutoff((int) $game->season + 1);

        return GamePlayer::with([
            'team',
            'matchState',
            'activeLoan',
            'transferOffers',
            'activeRenewalNegotiation',
            'latestRenewalNegotiation',
        ])
            ->where('game_id', $game->id)
            ->where('team_id', $game->reserve_team_id)
            ->where('date_of_birth', '<', $nextSeasonU23Cutoff)
            ->whereDoesntHave('activeLoan', fn ($q) => $q->where('loan_team_id', $game->team_id))
            ->get();
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

        if ($player->hasAgreedTransfer() && ! $player->hasAgreedPreContractDeparture()) {
            return ['status' => self::STATUS_OUTGOING, 'reason' => self::REASON_TRANSFER_AGREED];
        }

        if ($player->hasAgreedPreContractDeparture()) {
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
     *
     * Reserve→first-team call-ups use the same loan table internally, but
     * they aren't "borrowed" from the user's perspective — the canterano is
     * theirs through the reserve, so we surface them as plain owned.
     */
    private function classifyLoanedIn(GamePlayer $player, Game $game, \Carbon\Carbon $seasonEndDate): string
    {
        if ($player->isCalledUpFromReserve($game)) {
            return self::REASON_OWNED;
        }

        $loan = $player->activeLoan;

        if ($loan && $loan->return_at !== null && $loan->return_at->gt($seasonEndDate)) {
            return self::REASON_STILL_ON_LOAN;
        }

        return self::REASON_LOAN_ENDING;
    }

    /**
     * Attach projection attributes to the player for the Blade layer to render.
     */
    private function enrich(
        GamePlayer $player,
        \Carbon\Carbon $referenceDate,
        string $status,
        string $reason,
    ): void {
        $player->setAttribute('next_season_status', $status);
        $player->setAttribute('next_season_reason', $reason);
        $player->setAttribute('next_season_age', $player->age($referenceDate));

        $projection = $this->developmentService->getNextSeasonProjection($player);
        $player->setAttribute('projection', $projection);
        $player->setAttribute('next_season_overall', max(1, min(99, $player->overall_score + $projection)));
    }

    /**
     * The pool of players actually available for next-season selection —
     * STAYING (minus still-on-loan) plus INCOMING. Shared by the classifier
     * and the advisor so both speak about the same roster.
     */
    public static function availablePool(array $projection): Collection
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
        $buckets = [
            PositionGroup::GOALKEEPER->pluralKey() => collect(),
            PositionGroup::DEFENDER->pluralKey() => collect(),
            PositionGroup::MIDFIELDER->pluralKey() => collect(),
            PositionGroup::FORWARD->pluralKey() => collect(),
        ];

        foreach ($players as $player) {
            $group = PositionGroup::tryFrom((string) $player->position_group);
            if ($group === null) {
                throw new \UnexpectedValueException(
                    "Unknown position_group '{$player->position_group}' for player {$player->id}"
                );
            }
            $buckets[$group->pluralKey()]->push($player);
        }

        return [
            'goalkeepers' => $buckets['goalkeepers']->sortByDesc('overall_score')->values(),
            'defenders' => $buckets['defenders']->sortByDesc('overall_score')->values(),
            'midfielders' => $buckets['midfielders']->sortByDesc('overall_score')->values(),
            'forwards' => $buckets['forwards']->sortByDesc('overall_score')->values(),
        ];
    }
}
