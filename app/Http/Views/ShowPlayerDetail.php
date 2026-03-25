<?php

namespace App\Http\Views;

use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;

class ShowPlayerDetail
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly YouthAcademyService $youthAcademyService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);

        $gamePlayer = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        $canRenew = $gamePlayer->canBeOfferedRenewal();
        $renewalNegotiation = null;

        if ($game->isCareerMode() && $gamePlayer->isContractExpiring()) {
            $renewalNegotiation = RenewalNegotiation::where('game_player_id', $gamePlayer->id)
                ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
                ->first();
        }

        // Release data
        $canRelease = $game->isCareerMode()
            && $gamePlayer->team_id === $game->team_id
            && !$gamePlayer->isRetiring()
            && !$gamePlayer->isLoanedIn($game->team_id)
            && !$gamePlayer->isLoanedOut($game->team_id)
            && !$gamePlayer->hasPreContractAgreement()
            && !$gamePlayer->hasRenewalAgreed()
            && !$gamePlayer->hasAgreedTransfer()
            && !$gamePlayer->hasActiveLoanSearch();

        $severance = $canRelease ? $this->contractService->calculateSeverance($game, $gamePlayer) : 0;

        $canSendToAcademy = $game->isCareerMode()
            && $this->youthAcademyService->canSendToAcademy($gamePlayer, $game);

        return view('partials.player-detail', [
            'game' => $game,
            'gamePlayer' => $gamePlayer,
            'canRenew' => $canRenew,
            'renewalNegotiation' => $renewalNegotiation,
            'canRelease' => $canRelease,
            'severance' => $severance,
            'canSendToAcademy' => $canSendToAcademy,
        ]);
    }
}
