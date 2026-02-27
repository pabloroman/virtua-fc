<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\Money;
use Illuminate\Http\Request;

class SignFreeAgent
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::with('player')->findOrFail($playerId);

        // Must be a free agent
        if ($player->team_id !== null) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.not_free_agent'));
        }

        // Must be during transfer window
        if (! $game->isTransferWindowOpen()) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.transfer_window_closed'));
        }

        // Check wage affordability
        $wageDemand = $this->scoutingService->calculateWageDemand($player);
        $currentWageBill = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');
        $finances = $game->currentFinances;
        $maxWages = $finances ? (int) ($finances->projected_wages * 1.10) : 0;

        if (($currentWageBill + $wageDemand) > $maxWages) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.wage_budget_exceeded'));
        }

        // Sign the free agent
        $seasonYear = (int) $game->season;
        $contractYears = $player->age >= 32 ? 1 : mt_rand(2, 3);
        $newContractEnd = \Carbon\Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

        $player->update([
            'team_id' => $game->team_id,
            'number' => GamePlayer::nextAvailableNumber($game->id, $game->team_id),
            'contract_until' => $newContractEnd,
            'annual_wage' => $wageDemand,
        ]);

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.free_agent_signed', ['player' => $player->name]));
    }
}
