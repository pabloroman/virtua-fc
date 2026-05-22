<?php

namespace App\Modules\ReserveTeam\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\ReserveTeam\Exceptions\FirstTeamSquadFullException;
use App\Modules\ReserveTeam\Exceptions\FirstTeamSquadMinimumException;
use App\Modules\ReserveTeam\Exceptions\ReserveSquadMinimumException;
use App\Modules\Squad\Services\SquadMinimumService;
use App\Modules\Squad\Services\SquadNumberService;
use App\Modules\Transfer\Enums\TransferWindowType;
use App\Modules\Transfer\Services\LoanService;
use Illuminate\Support\Collection;

/**
 * Filial-aware reserve squad operations: list the reserve squad, call players
 * up to the first team via Loan, send them back, and auto-promote those who
 * age past the reserve cutoff at season close.
 *
 * Filial semantics: a reserve player belongs to the reserve team. The first
 * team calls them up via a Loan (parent=reserve, loan=first); ending the loan
 * sends them back. Age-23 promotion is the one permanent move (one-way
 * transfer recorded via GameTransfer::TYPE_INTERNAL_PROMOTION).
 */
class ReserveTeamService
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly SquadNumberService $squadNumberService,
        private readonly NotificationService $notificationService,
        private readonly SquadMinimumService $squadMinimumService,
    ) {}

    /**
     * Return all GamePlayers who belong to the reserve team — players currently
     * registered there plus those temporarily called up (active loan with
     * parent_team_id == reserve_team_id). Empty collection for non-filial games.
     *
     * @return Collection<int, GamePlayer>
     */
    public function getReserveSquad(Game $game): Collection
    {
        if ($game->reserve_team_id === null) {
            return collect();
        }

        return GamePlayer::ownedByTeam($game->reserve_team_id)
            ->where('game_id', $game->id)
            ->with(['activeLoan', 'careerRecord'])
            ->get();
    }

    /**
     * Call a reserve player up to the first team. Creates a Loan
     * (parent=reserve, loan=first), flips team_id, assigns squad number.
     *
     * @throws FirstTeamSquadFullException when no squad number is available
     * @throws ReserveSquadMinimumException when the reserve would fall below
     *         its squad-composition minimum after the call-up
     */
    public function callUpToFirstTeam(GamePlayer $player, Game $game): void
    {
        $this->assertFilial($game);

        if ($player->team_id !== $game->reserve_team_id) {
            throw new \DomainException('Player is not currently registered to the reserve team.');
        }

        // Reserve must keep enough players to field a squad after the call-up.
        $breach = $this->squadMinimumService->validateRemoval($game, $player, $game->reserve_team_id);
        if ($breach !== null) {
            throw new ReserveSquadMinimumException($breach);
        }

        $effectiveStart = $game->getLoanEffectiveStartDate();
        $returnDate = $game->getSeasonEndDateFor($effectiveStart);

        Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $game->reserve_team_id,
            'loan_team_id' => $game->team_id,
            'started_at' => $effectiveStart,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // Null the reserve number first so flipping team_id can't violate the
        // (game_id, team_id, number) unique constraint when a first-team
        // player already wears the same shirt.
        $previousNumber = $player->number;
        $player->update(['number' => null, 'team_id' => $game->team_id]);

        $number = $this->squadNumberService->assignAcademyNumberForNewPlayer($game, $player);

        if ($number === null) {
            // Roll back the move, restore previous number, and drop the loan.
            $player->update(['team_id' => $game->reserve_team_id, 'number' => $previousNumber]);
            Loan::where('game_player_id', $player->id)
                ->where('status', Loan::STATUS_ACTIVE)
                ->delete();

            throw new FirstTeamSquadFullException(
                'First-team squad is full; release a player before calling up another.'
            );
        }

        $player->update(['number' => $number]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $game->reserve_team_id,
            toTeamId: $game->team_id,
            transferFee: 0,
            type: GameTransfer::TYPE_LOAN,
            season: $game->season,
            window: TransferWindowType::currentValue($game->current_date),
        );
    }

    /**
     * Send a called-up player back to the reserve team. Ends the active
     * call-up loan, flips team_id back, nulls squad number.
     *
     * @throws FirstTeamSquadMinimumException when the first team would fall
     *         below its squad-composition minimum after the move
     */
    public function sendBackToReserve(GamePlayer $player, Game $game): void
    {
        $this->assertFilial($game);

        $loan = Loan::where('game_player_id', $player->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->where('parent_team_id', $game->reserve_team_id)
            ->where('loan_team_id', $game->team_id)
            ->first();

        if ($loan === null) {
            throw new \DomainException('Player is not currently called up from the reserve team.');
        }

        // First team must keep enough players to field a squad after send-back.
        $breach = $this->squadMinimumService->validateRemoval($game, $player, $game->team_id);
        if ($breach !== null) {
            throw new FirstTeamSquadMinimumException($breach);
        }

        // returnLoan handles team_id flip and number reassignment. For loans
        // returning to the reserve team (not the user's team), it sets number
        // to null automatically.
        $this->loanService->returnLoan($loan);
    }

    /**
     * At season close, permanently promote any reserve player who has aged
     * past the reserve cutoff (over 23) to the first team. One-way move,
     * recorded as a GameTransfer of type INTERNAL_PROMOTION. Any active
     * call-up loan for the player is closed first.
     *
     * Defaults to the user's filial pair ($game->reserve_team_id /
     * $game->team_id). Pass explicit ids to operate on an AI club's reserve
     * — in that case the user-only side effects (squad-number assignment via
     * SquadNumberService, UserSquadCareerRecord, and the user notification)
     * are skipped, since SquadNumberService is hard-wired to $game->team_id
     * and the career/notification streams are user-scoped.
     *
     * Returns the list of promoted players for downstream processors.
     *
     * @return Collection<int, GamePlayer>
     */
    public function autoPromoteOverageReservePlayers(
        Game $game,
        ?string $reserveTeamId = null,
        ?string $parentTeamId = null,
    ): Collection {
        $reserveTeamId ??= $game->reserve_team_id;
        $parentTeamId ??= $game->team_id;

        if ($reserveTeamId === null || $parentTeamId === null) {
            return collect();
        }

        $isUserFilial = ($reserveTeamId === $game->reserve_team_id);

        // Season close runs before $game->season is incremented, so we evaluate
        // against next season's U-23 cutoff: players who'd be overage (NOT
        // U-23) under next season's rule must move up to the first team now.
        $nextSeasonU23Cutoff = $game->getU23BirthCutoff((int) $game->season + 1);

        $candidates = GamePlayer::ownedByTeam($reserveTeamId)
            ->where('game_id', $game->id)
            ->where('date_of_birth', '<', $nextSeasonU23Cutoff)
            ->with(['activeLoan'])
            ->get();

        $promoted = collect();

        foreach ($candidates as $player) {
            // Close any active call-up loan first so returnLoan() doesn't
            // later try to flip the player back to the reserve team.
            if ($player->activeLoan && $player->activeLoan->parent_team_id === $reserveTeamId) {
                $player->activeLoan->update(['status' => Loan::STATUS_COMPLETED]);
            }

            // Permanent move to first team. Null the reserve number first so
            // the (game_id, team_id, number) unique constraint can't fire on
            // the team_id flip. AI promotions leave the number null — AI
            // squads don't depend on shirt numbers and SquadNumberService is
            // user-scoped.
            $player->update(['number' => null, 'team_id' => $parentTeamId]);

            if ($isUserFilial) {
                $reserveTeamName = $game->reserveTeam?->name;
                $number = $this->squadNumberService->assignNumberForNewPlayer($game, $player);
                if ($number !== null) {
                    $player->update(['number' => $number]);
                }

                \App\Models\UserSquadCareerRecord::updateOrCreate(
                    ['game_player_id' => $player->id],
                    [
                        'game_id' => $game->id,
                        'team_id' => $parentTeamId,
                        'joined_season' => (int) $game->season,
                        'joined_from' => $reserveTeamName ?? \App\Models\UserSquadCareerRecord::ORIGIN_ACADEMY,
                    ],
                );
            }

            GameTransfer::record(
                gameId: $game->id,
                gamePlayerId: $player->id,
                fromTeamId: $reserveTeamId,
                toTeamId: $parentTeamId,
                transferFee: 0,
                type: GameTransfer::TYPE_INTERNAL_PROMOTION,
                season: $game->season,
                window: TransferWindowType::currentValue($game->current_date),
            );

            if ($isUserFilial) {
                $this->notificationService->create(
                    game: $game,
                    type: \App\Models\GameNotification::TYPE_ACADEMY_PROSPECT,
                    title: __('notifications.reserve_overage_promoted_title'),
                    message: __('notifications.reserve_overage_promoted_message', [
                        'player' => $player->name ?? '',
                    ]),
                    priority: \App\Models\GameNotification::PRIORITY_INFO,
                );
            }

            $promoted->push($player);
        }

        return $promoted;
    }

    /**
     * At season close, permanently promote any reserve player still on a
     * call-up loan to the first team. The active call-up loan is closed and
     * the move is recorded as TYPE_INTERNAL_PROMOTION so the player no longer
     * snaps back to the reserve team in subsequent seasons. The player keeps
     * their existing first-team shirt number (assigned on call-up).
     *
     * @return Collection<int, GamePlayer>
     */
    public function permanentlyPromoteCalledUpPlayers(Game $game): Collection
    {
        if ($game->reserve_team_id === null) {
            return collect();
        }

        $callUpLoans = Loan::with(['gamePlayer'])
            ->where('game_id', $game->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->where('parent_team_id', $game->reserve_team_id)
            ->where('loan_team_id', $game->team_id)
            ->get();

        $promoted = collect();

        foreach ($callUpLoans as $loan) {
            $player = $loan->gamePlayer;
            if ($player === null) {
                continue;
            }

            $this->finalizeCalledUpPromotion($player, $loan, $game);
            $promoted->push($player);
        }

        return $promoted;
    }

    /**
     * Permanently promote a single called-up reserve player to the first team
     * outside the season-close sweep — used when the user lists the player for
     * loan to a third club, which implicitly commits to keeping them on the
     * first-team roster. Closes the active call-up loan and records the move
     * as TYPE_INTERNAL_PROMOTION. No-op if the player isn't currently called
     * up from the reserve.
     */
    public function permanentlyPromoteCalledUpPlayer(GamePlayer $player, Game $game): void
    {
        if ($game->reserve_team_id === null) {
            return;
        }

        $loan = Loan::where('game_player_id', $player->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->where('parent_team_id', $game->reserve_team_id)
            ->where('loan_team_id', $game->team_id)
            ->first();

        if ($loan === null) {
            return;
        }

        $this->finalizeCalledUpPromotion($player, $loan, $game);
    }

    /**
     * Shared promotion bookkeeping: close the call-up loan, write the squad
     * career record, log the internal-promotion transfer, and notify the user.
     * Player team_id is left as-is — the call-up already had the player on
     * the first-team roster.
     */
    private function finalizeCalledUpPromotion(GamePlayer $player, Loan $loan, Game $game): void
    {
        $loan->update(['status' => Loan::STATUS_COMPLETED]);

        $reserveTeamName = $game->reserveTeam?->name;
        \App\Models\UserSquadCareerRecord::updateOrCreate(
            ['game_player_id' => $player->id],
            [
                'game_id' => $game->id,
                'team_id' => $game->team_id,
                'joined_season' => (int) $game->season,
                'joined_from' => $reserveTeamName ?? \App\Models\UserSquadCareerRecord::ORIGIN_ACADEMY,
            ],
        );

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $game->reserve_team_id,
            toTeamId: $game->team_id,
            transferFee: 0,
            type: GameTransfer::TYPE_INTERNAL_PROMOTION,
            season: $game->season,
            window: TransferWindowType::currentValue($game->current_date),
        );

        $this->notificationService->create(
            game: $game,
            type: \App\Models\GameNotification::TYPE_ACADEMY_PROSPECT,
            title: __('notifications.reserve_overage_promoted_title'),
            message: __('notifications.reserve_overage_promoted_message', [
                'player' => $player->name ?? '',
            ]),
            priority: \App\Models\GameNotification::PRIORITY_INFO,
        );
    }

    /**
     * AI-side: at season close, permanently promote the top young prospects
     * from an AI club's reserve to its parent first team. Ranked per
     * position group by a blend of current ability and potential; only the
     * clearest "this kid is going somewhere" profiles are picked (blend
     * score ≥ MIN_PROSPECT_BLEND), so the typical reserve loses one or two
     * stars per season rather than its whole squad.
     *
     * Permanent move (TYPE_INTERNAL_PROMOTION), so the call-up doesn't
     * oscillate back to the reserve via LoanReturnProcessor next season.
     * User-only side effects (UserSquadCareerRecord, notifications,
     * SquadNumberService) are skipped — this method is invoked only for AI
     * parent clubs by AIReserveCallUpProcessor.
     *
     * @return Collection<int, GamePlayer>
     */
    public function autoPromoteAIReserveProspects(
        Game $game,
        string $reserveTeamId,
        string $parentTeamId,
    ): Collection {
        // Still-U23 next season: complements the overage promotion that
        // already moved age-24+ players up at priority 4.
        $nextSeasonU23Cutoff = $game->getU23BirthCutoff((int) $game->season + 1);

        $candidates = GamePlayer::ownedByTeam($reserveTeamId)
            ->where('game_id', $game->id)
            ->where('date_of_birth', '>=', $nextSeasonU23Cutoff)
            ->whereNotNull('overall_score')
            ->with(['activeLoan'])
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        // Blend current ability and potential: catches both ready-now
        // prospects (high overall) and high-upside ones (modest overall,
        // huge potential).
        $scored = $candidates->map(function (GamePlayer $p) {
            $potential = $p->potential ?? $p->overall_score;
            $blend = ($p->overall_score + $potential) / 2;

            return ['player' => $p, 'blend' => $blend];
        });

        $byPosition = $scored->groupBy(fn ($row) => $row['player']->position);

        $picks = collect();
        foreach ($byPosition as $rows) {
            $top = collect($rows)->sortByDesc('blend')->first();
            if ($top !== null && $top['blend'] >= self::MIN_PROSPECT_BLEND) {
                $picks->push($top);
            }
        }

        $picks = $picks->sortByDesc('blend')->take(self::MAX_PROSPECTS_PER_SEASON);

        $promoted = collect();
        foreach ($picks as $entry) {
            /** @var GamePlayer $player */
            $player = $entry['player'];

            // Close any active call-up loan from this reserve first so a
            // later LoanReturnProcessor sweep (next season) doesn't try to
            // flip the player back.
            if ($player->activeLoan && $player->activeLoan->parent_team_id === $reserveTeamId) {
                $player->activeLoan->update(['status' => Loan::STATUS_COMPLETED]);
            }

            $player->update(['number' => null, 'team_id' => $parentTeamId]);

            GameTransfer::record(
                gameId: $game->id,
                gamePlayerId: $player->id,
                fromTeamId: $reserveTeamId,
                toTeamId: $parentTeamId,
                transferFee: 0,
                type: GameTransfer::TYPE_INTERNAL_PROMOTION,
                season: $game->season,
                window: TransferWindowType::currentValue($game->current_date),
            );

            $promoted->push($player);
        }

        return $promoted;
    }

    /**
     * Blended score (overall + potential) / 2 floor for promoting a reserve
     * prospect to the AI parent first team. 76 corresponds to clearly
     * above-development-squad profiles (e.g. 72 + 80, 70 + 82, 68 + 84);
     * average reserve players (65/75 blend = 70) stay put.
     */
    private const MIN_PROSPECT_BLEND = 76;

    /**
     * Per-AI-parent cap on prospects promoted in a single season. Prevents
     * a wholesale gutting of the reserve when several players cross the
     * blend threshold in the same year.
     */
    private const MAX_PROSPECTS_PER_SEASON = 3;

    private function assertFilial(Game $game): void
    {
        if ($game->reserve_team_id === null) {
            throw new \DomainException('Reserve team operations require a filial game.');
        }
    }
}
