<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegotiateLoan
{
    private const MAX_ROUNDS = ContractService::MAX_NEGOTIATION_ROUNDS;

    public function __construct(
        private readonly TransferService $transferService,
        private readonly ScoutingService $scoutingService,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in([
                'start', 'offer', 'accept_counter',
            ])],
        ]);

        $game = Game::findOrFail($gameId);

        $player = GamePlayer::with(['player', 'game', 'team'])
            ->where('game_id', $gameId)
            ->findOrFail($playerId);

        return match ($request->input('action')) {
            'start' => $this->handleStart($game, $player),
            'offer' => $this->handleOffer($request, $game, $player),
            'accept_counter' => $this->handleAcceptCounter($game, $player),
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

        if (ContractService::isSquadFull($game)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.squad_full'),
            ], 422);
        }

        // Check for existing countered offer to resume
        $existing = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_LOAN_IN)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereNotNull('negotiation_round')
            ->where('asking_price', '>', 0)
            ->first();

        if ($existing && $existing->asking_price > $existing->transfer_fee) {
            $disposition = $this->transferService->calculateLoanDisposition($player, $this->scoutingService);
            $mood = $this->transferService->getLoanMoodIndicator($disposition);
            $teamName = $player->team?->name ?? 'Unknown';

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $existing->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_loan_counter', [
                            'team' => $teamName,
                            'fee' => Money::format($existing->asking_price),
                        ]),
                        'fee' => (int) ($existing->asking_price / 100),
                        'mood' => $mood,
                    ], [
                        'canAccept' => true,
                        'suggestedFee' => $this->calculateMidpointInEuros($existing->transfer_fee, $existing->asking_price),
                    ]),
                ],
            ]);
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

        // Evaluate loan feasibility
        $evaluation = $this->scoutingService->evaluateLoanRequestSync($player, $game);
        $teamName = $player->team?->name ?? 'Unknown';

        if ($evaluation['result'] === 'rejected') {
            $text = $evaluation['rejection_reason'] === 'key_player'
                ? __('transfers.chat_loan_rejected_key_player', ['team' => $teamName, 'player' => $player->name])
                : __('transfers.chat_loan_rejected_reputation', ['player' => $player->name]);

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => 0,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => $text,
                    ]),
                ],
            ]);
        }

        $disposition = $evaluation['disposition'];
        $mood = $this->transferService->getLoanMoodIndicator($disposition);

        if ($evaluation['result'] === 'accepted') {
            // Free loan — club agrees directly, create offer and complete
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
                'disposition' => $disposition,
            ]);

            $result = $this->transferService->completeSyncLoan($offer, $game);
            $offer = $result['offer'];

            $this->notificationService->notifyLoanRequestResult($game, $offer, 'accepted');

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'completed',
                'round' => 0,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('accepted', [
                        'text' => __('transfers.chat_loan_accepted_free', [
                            'team' => $teamName,
                            'player' => $player->name,
                        ]),
                        'mood' => $mood,
                    ]),
                ],
            ]);
        }

        // Conditional — club demands a loan fee
        $loanFee = $evaluation['loan_fee'];
        $suggestedBid = (int) (round(($loanFee * 0.85) / 10_000_000) * 10_000_000);
        $suggestedBidEuros = (int) ($suggestedBid / 100);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('demand', [
                    'text' => __('transfers.chat_loan_demand', [
                        'team' => $teamName,
                        'player' => $player->name,
                        'fee' => Money::format($loanFee),
                    ]),
                    'fee' => (int) ($loanFee / 100),
                    'mood' => $mood,
                ], [
                    'canAccept' => false,
                    'suggestedFee' => $suggestedBidEuros,
                ]),
            ],
        ]);
    }

    private function handleOffer(Request $request, Game $game, GamePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'bid' => ['required', 'integer', 'min:0'],
        ]);

        $bidCents = $validated['bid'] * 100;
        $teamName = $player->team?->name ?? 'Unknown';

        try {
            $result = $this->transferService->negotiateLoanFeeSync($game, $player, $bidCents, $this->scoutingService);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $offer = $result['offer'];

        return match ($result['result']) {
            'accepted' => $this->completeLoanNegotiation($offer, $game, $player),
            'countered' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $offer->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_loan_counter', [
                            'team' => $teamName,
                            'fee' => Money::format($offer->asking_price),
                        ]),
                        'fee' => (int) ($offer->asking_price / 100),
                        'mood' => $this->transferService->getLoanMoodIndicator(
                            $this->transferService->calculateLoanDisposition($player, $this->scoutingService)
                        ),
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
                        'text' => __('transfers.chat_loan_rejected', [
                            'team' => $teamName,
                        ]),
                    ]),
                ],
            ]),
        };
    }

    private function handleAcceptCounter(Game $game, GamePlayer $player): JsonResponse
    {
        $offer = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_LOAN_IN)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereNotNull('negotiation_round')
            ->first();

        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.transfer_failed'),
            ], 422);
        }

        try {
            $result = $this->transferService->acceptLoanFeeCounter($game, $offer);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return $this->completeLoanNegotiation($result['offer'], $game, $player);
    }

    private function completeLoanNegotiation(TransferOffer $offer, Game $game, GamePlayer $player): JsonResponse
    {
        $this->notificationService->notifyLoanRequestResult($game, $offer, 'accepted');

        $completedNow = $offer->status === TransferOffer::STATUS_COMPLETED;
        $text = $completedNow
            ? __('transfers.chat_loan_completed', ['player' => $player->name])
            : __('transfers.chat_loan_agreed', ['player' => $player->name]);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'completed',
            'round' => $offer->negotiation_round ?? 1,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => $text,
                ]),
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

    private function calculateMidpointInEuros(int $centsA, int $centsB): int
    {
        return (int) (ceil(($centsA + $centsB) / 2 / 100 / 10000) * 10000);
    }
}
