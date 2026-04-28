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

        $gamePlayer = GamePlayer::with(['player', 'activeLoan'])
            ->where('game_id', $gameId)
            ->userOwned($game)
            ->findOrFail($playerId);

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

        // Release is permitted for any user-owned player physically present
        // on a user roster (first team or reserve, including filial call-ups
        // who sit on the first team via internal loan). The team_id check
        // excludes players currently loaned out to a third-party club —
        // those must wait for the loan to end before they can be released.
        $canRelease = $game->isCareerMode()
            && $gamePlayer->isUserOwned($game)
            && in_array($gamePlayer->team_id, $game->userTeamIds(), true)
            && !$gamePlayer->isRetiring()
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
        ]);
    }
}
