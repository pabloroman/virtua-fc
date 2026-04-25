<?php

namespace App\Modules\ReserveTeam\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Player\PlayerAge;
use App\Modules\ReserveTeam\Exceptions\FirstTeamSquadFullException;
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
            ->with(['player', 'activeLoan'])
            ->get();
    }

    /**
     * Call a reserve player up to the first team. Creates a Loan
     * (parent=reserve, loan=first), flips team_id, assigns squad number.
     *
     * @throws FirstTeamSquadFullException when no squad number is available
     */
    public function callUpToFirstTeam(GamePlayer $player, Game $game): void
    {
        $this->assertFilial($game);

        if ($player->team_id !== $game->reserve_team_id) {
            throw new \DomainException('Player is not currently registered to the reserve team.');
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

        // Move into first team first so SquadNumberService sees the player
        // among the user's squad while picking a free number. Call-ups are
        // temporary loans, so force a 26-99 number rather than letting them
        // claim a permanent first-team slot (1-25).
        $player->update(['team_id' => $game->team_id]);

        $number = $this->squadNumberService->assignNumberForNewPlayer($game, $player, forceAboveFirstTeamMax: true);

        if ($number === null) {
            // Roll back the move and the loan.
            $player->update(['team_id' => $game->reserve_team_id]);
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
     * Returns the list of promoted players for downstream processors.
     *
     * @return Collection<int, GamePlayer>
     */
    public function autoPromoteOverageReservePlayers(Game $game): Collection
    {
        if ($game->reserve_team_id === null) {
            return collect();
        }

        $cutoff = PlayerAge::dateOfBirthCutoff(PlayerAge::YOUNG_END + 1, $game->current_date);

        $candidates = GamePlayer::ownedByTeam($game->reserve_team_id)
            ->where('game_id', $game->id)
            ->whereHas('player', fn ($q) => $q->where('date_of_birth', '<=', $cutoff))
            ->with(['player', 'activeLoan'])
            ->get();

        $promoted = collect();

        foreach ($candidates as $player) {
            // Close any active call-up loan first so returnLoan() doesn't
            // later try to flip the player back to the reserve team.
            if ($player->activeLoan && $player->activeLoan->parent_team_id === $game->reserve_team_id) {
                $player->activeLoan->update(['status' => Loan::STATUS_COMPLETED]);
            }

            // Permanent move to first team.
            $player->update(['team_id' => $game->team_id]);
            $number = $this->squadNumberService->assignNumberForNewPlayer($game, $player);
            if ($number !== null) {
                $player->update(['number' => $number]);
            }

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
                    'player' => $player->player->name ?? '',
                ]),
                priority: \App\Models\GameNotification::PRIORITY_INFO,
            );

            $promoted->push($player);
        }

        return $promoted;
    }

    private function assertFilial(Game $game): void
    {
        if ($game->reserve_team_id === null) {
            throw new \DomainException('Reserve team operations require a filial game.');
        }
    }
}
