<?php

namespace App\Http\Views;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;

class ShowPreseason
{
    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        // Redirect to dashboard if not in pre-season
        if (!$game->isInPreseason()) {
            return redirect()->route('show-game', $gameId);
        }

        // Get squad players
        $squad = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get()
            ->sortBy(fn($p) => $this->positionSortOrder($p->position));

        // Get incoming offers for our players
        $incomingOffers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', fn($q) => $q->where('team_id', $game->team_id))
            ->orderByDesc('transfer_fee')
            ->get();

        // Get players with offers (for highlighting in squad list)
        $playersWithOffers = $incomingOffers->pluck('game_player_id')->toArray();

        // Get completed transfers this window
        $completedTransfers = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->whereHas('gamePlayer', function ($q) use ($game) {
                $q->where('team_id', $game->team_id)
                    ->orWhere('team_id', '!=', $game->team_id);
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        // Separate into incoming and outgoing transfers
        $transfersIn = $completedTransfers->filter(fn($t) =>
            $t->direction === TransferOffer::DIRECTION_INCOMING
            || $t->gamePlayer->team_id === $game->team_id
        );
        $transfersOut = $completedTransfers->filter(fn($t) =>
            ($t->direction === TransferOffer::DIRECTION_OUTGOING || $t->direction === null)
            && $t->gamePlayer->team_id !== $game->team_id
        );

        // Get listed players
        $listedPlayers = $squad->filter(fn($p) => $p->isTransferListed());

        // Get first competitive match date
        $firstMatch = $game->getFirstCompetitiveMatch();

        // Get financial summary for this window
        $windowTransactions = FinancialTransaction::where('game_id', $gameId)
            ->whereIn('category', [
                FinancialTransaction::CATEGORY_TV_RIGHTS,
                FinancialTransaction::CATEGORY_WAGE,
                FinancialTransaction::CATEGORY_TRANSFER_IN,
                FinancialTransaction::CATEGORY_TRANSFER_OUT,
            ])
            ->where('transaction_date', '>=', $game->getPreseasonStartDate())
            ->get();

        $financialSummary = [
            'tv_rights' => $windowTransactions
                ->where('category', FinancialTransaction::CATEGORY_TV_RIGHTS)
                ->sum('amount'),
            'wages_paid' => $windowTransactions
                ->where('category', FinancialTransaction::CATEGORY_WAGE)
                ->sum('amount'),
            'transfer_income' => $windowTransactions
                ->where('category', FinancialTransaction::CATEGORY_TRANSFER_IN)
                ->sum('amount'),
            'transfer_spending' => $windowTransactions
                ->where('category', FinancialTransaction::CATEGORY_TRANSFER_OUT)
                ->sum('amount'),
        ];

        return view('preseason', [
            'game' => $game,
            'squad' => $squad,
            'incomingOffers' => $incomingOffers,
            'playersWithOffers' => $playersWithOffers,
            'transfersIn' => $transfersIn,
            'transfersOut' => $transfersOut,
            'listedPlayers' => $listedPlayers,
            'firstMatch' => $firstMatch,
            'financialSummary' => $financialSummary,
            'currentWeek' => $game->getPreseasonWeek(),
            'totalWeeks' => $game->getPreseasonTotalWeeks(),
            'weeksRemaining' => $game->getPreseasonWeeksRemaining(),
            'progressPercent' => $game->getPreseasonProgressPercent(),
        ]);
    }

    private function positionSortOrder(string $position): int
    {
        return match ($position) {
            'Goalkeeper' => 1,
            'Centre-Back' => 10,
            'Left-Back' => 11,
            'Right-Back' => 12,
            'Defensive Midfield' => 20,
            'Central Midfield' => 21,
            'Attacking Midfield' => 22,
            'Left Midfield' => 23,
            'Right Midfield' => 24,
            'Left Winger' => 30,
            'Right Winger' => 31,
            'Second Striker' => 32,
            'Centre-Forward' => 33,
            default => 99,
        };
    }
}
