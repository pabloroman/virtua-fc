<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegotiateLoan
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly ScoutingService $scoutingService,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in(['start', 'confirm'])],
        ]);

        $game = Game::findOrFail($gameId);

        $player = GamePlayer::with(['player', 'game', 'team'])
            ->where('game_id', $gameId)
            ->findOrFail($playerId);

        return match ($request->input('action')) {
            'start' => $this->handleStart($game, $player),
            'confirm' => $this->handleConfirm($game, $player),
            default => response()->json(['status' => 'error', 'message' => 'Invalid action'], 400),
        };
    }

    private function handleStart(Game $game, GamePlayer $player): JsonResponse
    {
        // Validate loan eligibility
        if (!$player->team_id) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.loan_not_available'),
            ], 422);
        }

        // Prevent duplicate pending offers
        $hasPending = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_LOAN_IN)
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
            ->exists();

        if ($hasPending) {
            return response()->json([
                'status' => 'error',
                'message' => __('transfers.already_bidding'),
            ], 422);
        }

        // Cooldown: must wait at least one matchday after a rejected negotiation
        if (TransferOffer::hasNegotiationCooldown($game->id, $player->id, $game->team_id, $game->current_date)) {
            return response()->json([
                'status' => 'error',
                'message' => __('transfers.negotiation_cooldown'),
            ], 422);
        }

        // Evaluate loan feasibility (two gates: club + player)
        $evaluation = $this->scoutingService->evaluateLoanRequestSync($player, $game);
        $teamName = $player->team?->name ?? 'Unknown';

        if ($evaluation['result'] === 'rejected') {
            $text = match ($evaluation['rejection_reason']) {
                'key_player' => __('transfers.chat_loan_rejected_key_player', ['team' => $teamName, 'player' => $player->name]),
                'player_refused' => __('transfers.chat_loan_rejected_player', ['player' => $player->name]),
                default => __('transfers.chat_loan_rejected_reputation', ['player' => $player->name]),
            };

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'messages' => [
                    $this->agentMessage('rejected', ['text' => $text]),
                ],
            ]);
        }

        // Both club and player accept — show salary and await confirmation
        $disposition = $evaluation['disposition'];
        $mood = $this->loanService->getLoanMoodIndicator($disposition);
        $salary = Money::format($player->annual_wage);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'awaiting_confirmation',
            'messages' => [
                $this->agentMessage('awaiting_confirmation', [
                    'text' => __('transfers.chat_loan_accepted', [
                        'team' => $teamName,
                        'player' => $player->name,
                        'salary' => $salary,
                    ]),
                    'mood' => $mood,
                ], [
                    'canConfirm' => true,
                ]),
            ],
        ]);
    }

    private function handleConfirm(Game $game, GamePlayer $player): JsonResponse
    {
        // Re-validate eligibility
        if (!$player->team_id) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.loan_not_available'),
            ], 422);
        }

        $hasPending = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_LOAN_IN)
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
            ->exists();

        if ($hasPending) {
            return response()->json([
                'status' => 'error',
                'message' => __('transfers.already_bidding'),
            ], 422);
        }

        // Re-evaluate loan feasibility
        $evaluation = $this->scoutingService->evaluateLoanRequestSync($player, $game);
        if ($evaluation['result'] === 'rejected') {
            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => __('transfers.chat_loan_rejected', [
                            'team' => $player->team?->name ?? 'Unknown',
                        ]),
                    ]),
                ],
            ]);
        }

        // Create offer and complete the loan
        $offer = TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_LOAN_IN,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $game->current_date->addDays(30),
            'game_date' => $game->current_date,
            'negotiation_round' => 1,
            'disposition' => $evaluation['disposition'],
        ]);

        $result = $this->loanService->completeSyncLoan($offer, $game);
        $offer = $result['offer'];

        $this->notificationService->notifyLoanRequestResult($game, $offer, 'accepted');

        $completedNow = $offer->status === TransferOffer::STATUS_COMPLETED;
        $text = $completedNow
            ? __('transfers.chat_loan_completed', ['player' => $player->name])
            : __('transfers.chat_loan_agreed', ['player' => $player->name]);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'completed',
            'messages' => [
                $this->agentMessage('accepted', ['text' => $text]),
            ],
        ]);
    }

    // ── Helpers ──

    private function agentMessage(string $type, array $content, ?array $options = null): array
    {
        return [
            'sender' => 'agent',
            'type' => $type,
            'content' => $content,
            'options' => $options,
        ];
    }
}
