<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Models\TransferOffer;

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
        // but owned by another club) and incoming pre-contract signings
        // (still at their current club but joining next season). userOwned()
        // would 404 on those, so we accept any of the three relations.
        $userTeamIds = $game->userTeamIds();

        $gamePlayer = GamePlayer::with(['careerRecord', 'activeLoan'])
            ->where('game_id', $gameId)
            ->where(function ($q) use ($userTeamIds, $game) {
                $q->whereIn('team_id', $userTeamIds)
                    ->orWhereHas('activeLoan', fn ($loan) => $loan->whereIn('parent_team_id', $userTeamIds))
                    ->orWhereHas('transferOffers', fn ($offer) => $offer
                        ->where('game_id', $game->id)
                        ->incomingFor($game->team_id)
                        ->agreedPreContract());
            })
            ->findOrFail($playerId);

        // True when this player is joining the user's team on a signed
        // pre-contract — drives the banner in the detail modal so the user
        // sees at a glance "this isn't mine yet, but they're coming".
        $incomingPreContract = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $gamePlayer->id)
            ->incomingFor($game->team_id)
            ->agreedPreContract()
            ->exists();

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

        // A player with a committed deal completes it from wherever he sits
        // now, so ReserveTeamService refuses to move him; don't offer it.
        $canMoveBetweenSquads = ! $gamePlayer->hasCommittedDeal();
        $canCallUpToFirstTeam = $isOnReserve && $canMoveBetweenSquads;
        $canSendBackToReserve = $isCalledUpFromReserve && $canMoveBetweenSquads;

        // U23 first-team players (not currently called up from the reserve)
        // can be sent down to the reserve squad for development. Players on
        // an active call-up loan have their own "send back" path which closes
        // the loan cleanly; both can't be offered at the same time.
        $canSendDownToReserve = $game->isCareerMode()
            && $game->reserve_team_id !== null
            && $gamePlayer->team_id === $game->team_id
            && !$isCalledUpFromReserve
            && $canMoveBetweenSquads
            && $gamePlayer->date_of_birth !== null
            && $gamePlayer->date_of_birth >= $game->getU23BirthCutoff();

        // Release is permitted for any user-owned player physically present
        // on a user roster (first team or reserve, including filial call-ups
        // who sit on the first team via internal loan). The team_id check
        // excludes players currently loaned out to a third-party club —
        // those must wait for the loan to end before they can be released.
        $canRelease = $game->isCareerMode()
            && $gamePlayer->isUserOwned($game)
            && in_array($gamePlayer->team_id, $game->userTeamIds(), true)
            && !$gamePlayer->isRetiring()
            && !$gamePlayer->hasAgreedPreContractDeparture()
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
            'canCallUpToFirstTeam' => $canCallUpToFirstTeam,
            'canSendBackToReserve' => $canSendBackToReserve,
            'canSendDownToReserve' => $canSendDownToReserve,
            'incomingPreContract' => $incomingPreContract,
        ]);
    }
}
