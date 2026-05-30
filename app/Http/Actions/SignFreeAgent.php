<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Finance\Services\SalaryCapService;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\DispositionService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Http\Request;

class SignFreeAgent
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly DispositionService $dispositionService,
        private readonly TransferService $transferService,
        private readonly SalaryCapService $salaryCapService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('game_id', $gameId)->findOrFail($playerId);

        if ($player->isUserOwned($game)) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('transfers.cannot_target_own_player'));
        }

        // Must be a free agent
        if ($player->team_id !== null) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.not_free_agent'));
        }

        // Reputation gate: free agent must be willing to join
        if (! $this->dispositionService->canSignFreeAgent($player, $game->id, $game->team_id)) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.free_agent_reputation_too_low'));
        }

        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::FREE_AGENT, $game->team);

        // Salary cap: a free signing costs no transfer fee but commits wages —
        // block it if the wage bill would breach the cap.
        if (! $this->salaryCapService->canCommitWage($game, $demand['wage'])) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', $this->salaryCapService->blockMessage($game, $player->name, $demand['wage']));
        }

        $this->transferService->signFreeAgent($game, $player, $demand['wage']);

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.free_agent_agreed', ['player' => $player->name]));
    }
}
