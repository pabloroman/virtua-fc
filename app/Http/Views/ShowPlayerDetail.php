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

        // The modal is reachable from any squad page that lists a player on
        // the user's roster, including loaned-in players (physically present
        // but owned by another club). userOwned() would 404 on those, so we
        // accept "physically here" as well as "owned via active loan-out".
        $userTeamIds = $game->userTeamIds();

        $gamePlayer = GamePlayer::with(['careerRecord', 'activeLoan'])
            ->where('game_id', $gameId)
            ->where(function ($q) use ($userTeamIds) {
                $q->whereIn('team_id', $userTeamIds)
                    ->orWhereHas('activeLoan', fn ($loan) => $loan->whereIn('parent_team_id', $userTeamIds));
            })
            ->findOrFail($playerId);

        $isCalledUpFromReserve = $gamePlayer->isCalledUpFromReserve($game);

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
            'isOnReserve' => $isOnReserve,
            'isCalledUpFromReserve' => $isCalledUpFromReserve,
        ]);
    }
}
