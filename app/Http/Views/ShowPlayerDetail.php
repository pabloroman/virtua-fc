<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;

class ShowPlayerDetail
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);

        $allowedTeamIds = array_filter([$game->team_id, $game->reserve_team_id]);

        $gamePlayer = GamePlayer::with(['player', 'careerRecord', 'activeLoan'])
            ->where('game_id', $gameId)
            ->whereIn('team_id', $allowedTeamIds)
            ->findOrFail($playerId);

        // Player is "called up" from the filial: currently registered to the
        // first team via a Loan whose parent is the reserve team. For action
        // purposes we treat them like a normal first-team player and offer
        // an additional "send back to filial" button.
        $isCalledUpFromReserve = $game->reserve_team_id !== null
            && $gamePlayer->team_id === $game->team_id
            && $gamePlayer->activeLoan
            && $gamePlayer->activeLoan->parent_team_id === $game->reserve_team_id
            && $gamePlayer->activeLoan->loan_team_id === $game->team_id;

        $canRenew = $gamePlayer->canBeOfferedRenewal(currentDate: $game->current_date);
        $renewalNegotiation = null;
        $renewalCooldown = false;

        if ($game->isCareerMode() && $gamePlayer->contract_until) {
            $renewalNegotiation = RenewalNegotiation::where('game_player_id', $gamePlayer->id)
                ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
                ->first();

            if (!$canRenew && !$renewalNegotiation) {
                $renewalCooldown = RenewalNegotiation::hasRenewalCooldown($gamePlayer->id, $game->current_date);
            }
        }

        $isOnReserve = $game->reserve_team_id !== null
            && $gamePlayer->team_id === $game->reserve_team_id;

        // Release data — allowed for first-team and reserve-team players,
        // including filial players currently called up via internal loan
        // (which technically appears as "loaned in" but is treated as
        // user-owned for management purposes).
        $canRelease = $game->isCareerMode()
            && in_array($gamePlayer->team_id, [$game->team_id, $game->reserve_team_id], true)
            && !$gamePlayer->isRetiring()
            && ($isCalledUpFromReserve || !$gamePlayer->isLoanedIn($game->team_id))
            && !$gamePlayer->isLoanedOut($game->team_id)
            && !$gamePlayer->hasPreContractAgreement()
            && !$gamePlayer->hasRenewalAgreed()
            && !$gamePlayer->hasAgreedTransfer()
            && !$gamePlayer->hasActiveLoanSearch();

        $severance = $canRelease ? $this->contractService->calculateSeverance($game, $gamePlayer) : 0;

        return view('partials.player-detail', [
            'game' => $game,
            'gamePlayer' => $gamePlayer,
            'canRenew' => $canRenew,
            'renewalNegotiation' => $renewalNegotiation,
            'renewalCooldown' => $renewalCooldown,
            'canRelease' => $canRelease,
            'severance' => $severance,
            'isOnReserve' => $isOnReserve,
            'isCalledUpFromReserve' => $isCalledUpFromReserve,
        ]);
    }
}
