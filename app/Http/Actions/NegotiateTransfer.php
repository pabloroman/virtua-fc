<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use App\Modules\Finance\Services\BudgetLoanService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegotiateTransfer
{
    private const MAX_ROUNDS = ContractService::MAX_NEGOTIATION_ROUNDS;

    public function __construct(
        private readonly TransferService $transferService,
        private readonly ContractService $contractService,
        private readonly ScoutingService $scoutingService,
        private readonly NotificationService $notificationService,
        private readonly BudgetLoanService $budgetLoanService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in([
                'start', 'offer', 'accept_counter',
                'start_terms', 'offer_terms', 'accept_terms_counter',
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
            'start_terms' => $this->handleStartTerms($game, $player),
            'offer_terms' => $this->handleOfferTerms($request, $game, $player),
            'accept_terms_counter' => $this->handleAcceptTermsCounter($game, $player),
            default => response()->json(['status' => 'error', 'message' => 'Invalid action'], 400),
        };
    }

    // ── Club Fee Negotiation ──

    private function handleStart(Game $game, GamePlayer $player): JsonResponse
    {
        // Check for existing countered offer to resume
        $existing = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereNotNull('negotiation_round')
            ->where('asking_price', '>', 0)
            ->first();

        if ($existing && $existing->asking_price > $existing->transfer_fee) {
            $disposition = $this->transferService->calculateClubDisposition($player, $this->scoutingService);
            $mood = $this->transferService->getClubMoodIndicator($disposition);
            $teamName = $player->team?->name ?? 'Unknown';

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $existing->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'available_budget' => (int) (($this->transferService->availableBudget($game) + $existing->transfer_fee) / 100),
                'budget_loan_available' => $this->budgetLoanService->canRequestLoan($game),
                'budget_loan_url' => route('game.finances', $game->id),
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_club_counter_resume', [
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

        // Fee already agreed — tell frontend to transition to personal terms
        $feeAgreed = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_FEE_AGREED)
            ->first();

        if ($feeAgreed) {
            $teamName = $player->team?->name ?? 'Unknown';

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'fee_agreed',
                'round' => $feeAgreed->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('accepted', [
                        'text' => __('transfers.chat_club_accepted', [
                            'team' => $teamName,
                            'fee' => Money::format($feeAgreed->transfer_fee),
                            'player' => $player->name,
                        ]),
                        'fee' => (int) ($feeAgreed->transfer_fee / 100),
                    ]),
                ],
            ]);
        }

        // Prevent duplicate pending offers
        $hasPending = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_PENDING)
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

        // New negotiation — show asking price
        $askingPrice = $this->scoutingService->calculateAskingPrice($player);
        $disposition = $this->transferService->calculateClubDisposition($player, $this->scoutingService);
        $mood = $this->transferService->getClubMoodIndicator($disposition);
        $teamName = $player->team?->name ?? 'Unknown';

        $suggestedBidEuros = (int) ($askingPrice / 100);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'available_budget' => (int) ($this->transferService->availableBudget($game) / 100),
            'budget_loan_available' => $this->budgetLoanService->canRequestLoan($game),
            'budget_loan_url' => route('game.finances', $game->id),
            'messages' => [
                $this->agentMessage('demand', [
                    'text' => __('transfers.chat_club_demand', [
                        'team' => $teamName,
                        'fee' => Money::format($askingPrice),
                        'player' => $player->name,
                    ]),
                    'fee' => (int) ($askingPrice / 100),
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
            'bid' => ['required', 'integer', 'min:1'],
        ]);

        $bidCents = $validated['bid'] * 100;
        $teamName = $player->team?->name ?? 'Unknown';

        try {
            $result = $this->transferService->negotiateTransferFeeSync($game, $player, $bidCents, $this->scoutingService);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $offer = $result['offer'];

        return match ($result['result']) {
            'accepted' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'fee_agreed',
                'round' => $offer->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('accepted', [
                        'text' => __('transfers.chat_club_accepted', [
                            'team' => $teamName,
                            'fee' => Money::format($offer->transfer_fee),
                            'player' => $player->name,
                        ]),
                        'fee' => (int) ($offer->transfer_fee / 100),
                    ]),
                ],
            ]),
            'countered' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $offer->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'available_budget' => (int) (($this->transferService->availableBudget($game) + $offer->transfer_fee) / 100),
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_club_counter', [
                            'team' => $teamName,
                            'fee' => Money::format($offer->asking_price),
                        ]),
                        'fee' => (int) ($offer->asking_price / 100),
                        'mood' => $this->transferService->getClubMoodIndicator(
                            $this->transferService->calculateClubDisposition($player, $this->scoutingService)
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
                        'text' => __('transfers.chat_club_rejected', [
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
            $offer = $this->transferService->acceptTransferFeeCounter($game, $offer);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $teamName = $player->team?->name ?? 'Unknown';

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'fee_agreed',
            'round' => $offer->negotiation_round,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => __('transfers.chat_club_accepted', [
                        'team' => $teamName,
                        'fee' => Money::format($offer->transfer_fee),
                        'player' => $player->name,
                    ]),
                    'fee' => (int) ($offer->transfer_fee / 100),
                ]),
            ],
        ]);
    }

    // ── Personal Terms Negotiation ──

    private function handleStartTerms(Game $game, GamePlayer $player): JsonResponse
    {
        $offer = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_FEE_AGREED)
            ->first();

        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.transfer_failed'),
            ], 422);
        }

        // Check for existing countered terms to resume
        if ($offer->terms_status === 'countered') {
            $disposition = $this->contractService->calculateTransferDisposition($player, $game, $offer->terms_round ?? 1);
            $mood = $this->contractService->getMoodIndicator($disposition, 'transfer');

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'terms_open',
                'round' => $offer->terms_round ?? 0,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_player_counter_transfer', [
                            'player' => $player->name,
                            'wage' => Money::format($offer->wage_counter_offer),
                            'years' => $offer->preferred_years,
                        ]),
                        'wage' => (int) ($offer->wage_counter_offer / 100),
                        'years' => $offer->preferred_years,
                        'mood' => $mood,
                    ], [
                        'canAccept' => true,
                        'suggestedWage' => $this->calculateMidpointInEuros($offer->offered_wage, $offer->wage_counter_offer),
                        'preferredYears' => $offer->preferred_years,
                    ]),
                ],
            ]);
        }

        // Reputation gate: player may refuse to negotiate with a lower-reputation club
        $willingness = $this->contractService->checkPlayerWillingness($offer, $game, $this->scoutingService);
        if (! $willingness['willing']) {
            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => 0,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => __('transfers.chat_player_not_interested', [
                            'player' => $player->name,
                        ]),
                    ]),
                ],
            ]);
        }

        // Reuse stored demand if already calculated, otherwise compute and persist
        if ($offer->player_demand && $offer->preferred_years) {
            $demand = [
                'wage' => $offer->player_demand,
                'contractYears' => $offer->preferred_years,
                'formattedWage' => Money::format($offer->player_demand),
            ];
        } else {
            $demand = $this->contractService->calculateTransferWageDemand($player, $this->scoutingService);
            $offer->update([
                'player_demand' => $demand['wage'],
                'preferred_years' => $demand['contractYears'],
            ]);
        }

        $disposition = $this->contractService->calculateTransferDisposition($player, $game);
        $mood = $this->contractService->getMoodIndicator($disposition, 'transfer');

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'terms_open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('demand', [
                    'text' => __('transfers.chat_player_demand_transfer', [
                        'player' => $player->name,
                        'wage' => $demand['formattedWage'],
                        'years' => $demand['contractYears'],
                    ]),
                    'wage' => (int) ($demand['wage'] / 100),
                    'years' => $demand['contractYears'],
                    'mood' => $mood,
                ], [
                    'canAccept' => false,
                    'suggestedWage' => (int) ($demand['wage'] / 100),
                    'preferredYears' => $demand['contractYears'],
                ]),
            ],
        ]);
    }

    private function handleOfferTerms(Request $request, Game $game, GamePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'wage' => ['required', 'integer', 'min:1'],
            'years' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $offer = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_FEE_AGREED)
            ->first();

        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.transfer_failed'),
            ], 422);
        }

        $offerWageCents = $validated['wage'] * 100;
        $offeredYears = $validated['years'];

        $result = $this->contractService->negotiateTransferTermsSync(
            $offer, $offerWageCents, $offeredYears, $game, $this->scoutingService
        );

        $offer = $result['offer'];

        return match ($result['result']) {
            'accepted' => $this->completeTransferNegotiation($offer, $game, $player),
            'countered' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'terms_open',
                'round' => $offer->terms_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_player_counter_transfer', [
                            'player' => $player->name,
                            'wage' => Money::format($offer->wage_counter_offer),
                            'years' => $offer->preferred_years,
                        ]),
                        'wage' => (int) ($offer->wage_counter_offer / 100),
                        'years' => $offer->preferred_years,
                        'mood' => $this->contractService->getMoodIndicator($offer->terms_disposition, 'transfer'),
                    ], [
                        'canAccept' => true,
                        'suggestedWage' => $this->calculateMidpointInEuros($offer->offered_wage, $offer->wage_counter_offer),
                        'preferredYears' => $offer->preferred_years,
                    ]),
                ],
            ]),
            default => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => $offer->terms_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => __('transfers.chat_terms_rejected', [
                            'player' => $player->name,
                        ]),
                    ]),
                ],
            ]),
        };
    }

    private function handleAcceptTermsCounter(Game $game, GamePlayer $player): JsonResponse
    {
        $offer = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_FEE_AGREED)
            ->where('terms_status', 'countered')
            ->first();

        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.transfer_failed'),
            ], 422);
        }

        $this->contractService->acceptTransferTermsCounter($offer);
        $offer->refresh();

        return $this->completeTransferNegotiation($offer, $game, $player);
    }

    private function completeTransferNegotiation(TransferOffer $offer, Game $game, GamePlayer $player): JsonResponse
    {
        $completedImmediately = $this->transferService->acceptIncomingOffer($offer);

        // Transfer was rejected by a safety check (squad full, budget exceeded)
        if ($completedImmediately === false && $offer->refresh()->status === TransferOffer::STATUS_REJECTED) {
            $reason = ContractService::isSquadFull($game)
                ? __('messages.squad_full', ['max' => ContractService::MAX_SQUAD_SIZE])
                : __('messages.transfer_failed');

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => $offer->terms_round ?? $offer->negotiation_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => $reason,
                    ]),
                ],
            ]);
        }

        if ($completedImmediately) {
            $this->notificationService->notifyTransferComplete($game, $offer->refresh());
        }

        $messageKey = $completedImmediately
            ? 'transfers.chat_transfer_complete'
            : 'transfers.chat_transfer_complete_pending';

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'completed',
            'round' => $offer->terms_round ?? $offer->negotiation_round,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => __($messageKey, [
                        'player' => $player->name,
                    ]),
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
