<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\TransferOffer;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegotiateCounterOffer
{
    private const MAX_ROUNDS = ContractService::MAX_NEGOTIATION_ROUNDS;

    public function __construct(
        private readonly TransferService $transferService,
        private readonly ScoutingService $scoutingService,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $offerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in(['start', 'counter', 'accept_counter', 'reject'])],
        ]);

        $game = Game::findOrFail($gameId);

        $offer = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'offeringTeam'])
            ->where('id', $offerId)
            ->where('game_id', $gameId)
            ->whereIn('offer_type', [TransferOffer::TYPE_UNSOLICITED, TransferOffer::TYPE_LISTED])
            ->where('status', TransferOffer::STATUS_PENDING)
            ->firstOrFail();

        // Verify the player belongs to the user's team
        if ($offer->gamePlayer->team_id !== $game->team_id) {
            abort(403);
        }

        return match ($request->input('action')) {
            'start' => $this->handleStart($game, $offer),
            'counter' => $this->handleCounter($request, $game, $offer),
            'accept_counter' => $this->handleAcceptCounter($game, $offer),
            'reject' => $this->handleReject($game, $offer),
            default => response()->json(['status' => 'error', 'message' => 'Invalid action'], 400),
        };
    }

    private function handleStart(Game $game, TransferOffer $offer): JsonResponse
    {
        $player = $offer->gamePlayer;
        $buyerName = $offer->offeringTeam->name;

        // Extend expiry to prevent mid-negotiation timeout
        if ($offer->expires_at && $offer->expires_at->diffInDays($game->current_date) < 5) {
            $offer->update(['expires_at' => $game->current_date->addDays(5)]);
        }

        // Check for existing negotiation to resume
        if ($offer->negotiation_round && $offer->asking_price && $offer->asking_price > $offer->transfer_fee) {
            $suggestedCounter = $this->calculateMidpointInEuros($offer->transfer_fee, $offer->asking_price);

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $offer->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_buyer_counter_resume', [
                            'team' => $buyerName,
                            'fee' => Money::format($offer->transfer_fee),
                        ]),
                        'fee' => (int) ($offer->transfer_fee / 100),
                    ], [
                        'canAccept' => true,
                        'suggestedFee' => $suggestedCounter,
                    ]),
                ],
            ]);
        }

        // New negotiation — show AI's current bid
        $suggestedCounter = (int) ($offer->transfer_fee / 100);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('demand', [
                    'text' => __('transfers.chat_buyer_opening', [
                        'team' => $buyerName,
                        'fee' => Money::format($offer->transfer_fee),
                        'player' => $player->name,
                    ]),
                    'fee' => (int) ($offer->transfer_fee / 100),
                ], [
                    'canAccept' => true,
                    'suggestedFee' => $suggestedCounter,
                ]),
            ],
        ]);
    }

    private function handleCounter(Request $request, Game $game, TransferOffer $offer): JsonResponse
    {
        $validated = $request->validate([
            'bid' => ['required', 'integer', 'min:1'],
        ]);

        $userAskingCents = $validated['bid'] * 100;
        $buyerName = $offer->offeringTeam->name;
        $player = $offer->gamePlayer;

        // If user submits the same amount as the AI's offer, treat as acceptance
        if ($userAskingCents == $offer->transfer_fee) {
            return $this->handleAcceptCounter($game, $offer);
        }

        if ($userAskingCents < $offer->transfer_fee) {
            return response()->json([
                'status' => 'error',
                'message' => __('transfers.counter_must_be_higher'),
            ], 422);
        }

        $result = $this->transferService->negotiateCounterOfferSync($game, $offer, $userAskingCents, $this->scoutingService);
        $offer = $result['offer'];

        return match ($result['result']) {
            'accepted' => $this->completeSale($offer, $game, $player, $buyerName),
            'countered' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $offer->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_buyer_counter', [
                            'team' => $buyerName,
                            'fee' => Money::format($offer->transfer_fee),
                        ]),
                        'fee' => (int) ($offer->transfer_fee / 100),
                    ], [
                        'canAccept' => true,
                        'suggestedFee' => $this->calculateMidpointInEuros($offer->transfer_fee, $offer->asking_price),
                    ]),
                ],
            ]),
            default => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => $offer->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => __('transfers.chat_buyer_rejected', [
                            'team' => $buyerName,
                        ]),
                    ]),
                ],
            ]),
        };
    }

    private function handleAcceptCounter(Game $game, TransferOffer $offer): JsonResponse
    {
        $player = $offer->gamePlayer;
        $buyerName = $offer->offeringTeam->name;

        $completedImmediately = $this->transferService->acceptCounterOfferBid($offer);

        if ($completedImmediately) {
            $this->notificationService->notifyTransferComplete($game, $offer->refresh());
        }

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'completed',
            'round' => $offer->negotiation_round ?? 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => __('transfers.chat_buyer_deal_complete', [
                        'player' => $player->name,
                        'team' => $buyerName,
                        'fee' => Money::format($offer->transfer_fee),
                    ]),
                    'fee' => (int) ($offer->transfer_fee / 100),
                ]),
            ],
        ]);
    }

    private function completeSale(TransferOffer $offer, Game $game, $player, string $buyerName): JsonResponse
    {
        $completedImmediately = $this->transferService->acceptOffer($offer);

        if ($completedImmediately) {
            $this->notificationService->notifyTransferComplete($game, $offer->refresh());
        }

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'completed',
            'round' => $offer->negotiation_round ?? 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => __('transfers.chat_buyer_accepted', [
                        'team' => $buyerName,
                        'fee' => Money::format($offer->transfer_fee),
                        'player' => $player->name,
                    ]),
                    'fee' => (int) ($offer->transfer_fee / 100),
                ]),
            ],
        ]);
    }

    private function handleReject(Game $game, TransferOffer $offer): JsonResponse
    {
        $this->transferService->rejectOffer($offer);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'rejected',
            'messages' => [
                $this->agentMessage('rejected', [
                    'text' => __('transfers.chat_offer_rejected', [
                        'player' => $offer->gamePlayer->name,
                    ]),
                ]),
            ],
        ]);
    }

    private function agentMessage(string $type, array $content, ?array $options = null): array
    {
        return [
            'sender' => 'agent',
            'type' => $type,
            'content' => $content,
            'options' => $options,
        ];
    }

    private function calculateMidpointInEuros(int $centsA, int $centsB): int
    {
        return (int) (ceil(($centsA + $centsB) / 2 / 100 / 10000) * 10000);
    }
}
