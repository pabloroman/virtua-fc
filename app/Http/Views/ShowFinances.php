<?php

namespace App\Http\Views;

use App\Game\Services\ContractService;
use App\Game\Services\FinancialService;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;

class ShowFinances
{
    public function __construct(
        private readonly FinancialService $financialService,
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        // Initialize finances if not exists
        if (!$game->finances) {
            $this->financialService->initializeFinances($game);
            $game->load('finances');
        }

        $finances = $game->finances;

        // Calculate current squad metrics
        $squadValue = $this->financialService->calculateSquadValue($game);
        $wageBill = $this->financialService->calculateAnnualWageBill($game);

        // Get highest earners
        $highestEarners = $this->contractService->getHighestEarners($game, 5);

        // Get most valuable players
        $mostValuable = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->orderByDesc('market_value_cents')
            ->limit(5)
            ->get();

        // Get expiring contracts (current season + 1)
        $currentYear = (int) $game->season;
        $expiringThisSeason = $this->contractService->getExpiringContracts($game, $currentYear);
        $expiringNextSeason = $this->contractService->getExpiringContracts($game, $currentYear + 1);

        // Calculate budget usage
        $wageUsagePercent = $finances->wage_budget > 0
            ? min(100, round(($wageBill / $finances->wage_budget) * 100))
            : 0;

        // Get recent transactions
        $transactions = FinancialTransaction::with('relatedPlayer.player')
            ->where('game_id', $gameId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('finances', [
            'game' => $game,
            'finances' => $finances,
            'squadValue' => $squadValue,
            'wageBill' => $wageBill,
            'wageUsagePercent' => $wageUsagePercent,
            'highestEarners' => $highestEarners,
            'mostValuable' => $mostValuable,
            'expiringThisSeason' => $expiringThisSeason,
            'expiringNextSeason' => $expiringNextSeason,
            'transactions' => $transactions,
        ]);
    }
}
