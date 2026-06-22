<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Models\TransferOffer;
use App\Modules\Transfer\Services\ScoutingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

class ToggleShortlist
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $gamePlayer = GamePlayer::where('game_id', $gameId)->with('team')->findOrFail($playerId);

        $existing = ShortlistedPlayer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->first();

        // Removal is always allowed — even if the player has since become
        // user-owned (e.g. shortlisted before signing), we want the user to be
        // able to clear them out. Only block *adding* an own-club player.
        if (!$existing && $gamePlayer->isUserOwned($game)) {
            $message = __('transfers.cannot_target_own_player');

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        // Block shortlisting players the user already has an active pre-contract
        // with (pending or agreed) — the UI hides the action, this guards
        // against direct POSTs / replays.
        if (!$existing) {
            $preContractStatus = TransferOffer::getUserPreContractStatuses(
                $gameId, $game->team_id, [$playerId]
            )[$playerId] ?? null;

            if ($preContractStatus !== null) {
                $message = __('transfers.shortlist_disabled_pre_contract');

                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 422);
                }

                return redirect()->back()->with('error', $message);
            }
        }

        if ($existing) {
            $existing->delete();
            $message = __('messages.shortlist_removed', ['player' => $gamePlayer->name]);
            $action = 'removed';
        } elseif ($this->scoutingService->isShortlistFull($game)) {
            $message = __('messages.shortlist_full', ['max' => ScoutingService::MAX_SHORTLIST_SIZE]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->back()->with('error', $message);
        } else {
            try {
                ShortlistedPlayer::create([
                    'game_id' => $gameId,
                    'game_player_id' => $playerId,
                    'added_at' => $game->current_date,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // Concurrent toggle (e.g. double-click) — another request already
                // created the row. Treat as success.
            }

            $message = __('messages.shortlist_added', ['player' => $gamePlayer->name]);
            $action = 'added';
        }

        if ($request->ajax()) {
            $data = ['success' => true, 'message' => $message, 'action' => $action, 'playerId' => $playerId];

            if ($action === 'added') {
                $gamePlayer->load(['team']);
                // A freshly-starred target carries the same full dossier as one
                // rendered on page load — no offer yet, so pass a null status.
                $data['player'] = $this->scoutingService->buildTargetData(
                    $gamePlayer,
                    $game,
                    null,
                );
            }

            return response()->json($data);
        }

        return redirect()->back()->with('success', $message);
    }
}
