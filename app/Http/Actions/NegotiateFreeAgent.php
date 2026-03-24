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

class NegotiateFreeAgent
{
    private const MAX_ROUNDS = ContractService::MAX_NEGOTIATION_ROUNDS;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly ScoutingService $scoutingService,
        private readonly TransferService $transferService,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in([
                'start', 'offer_terms', 'accept_terms_counter',
            ])],
        ]);

        $game = Game::findOrFail($gameId);

        $player = GamePlayer::with(['player', 'game', 'team'])
            ->where('game_id', $gameId)
            ->findOrFail($playerId);

        return match ($request->input('action')) {
            'start' => $this->handleStart($game, $player),
            'offer_terms' => $this->handleOfferTerms($request, $game, $player),
            'accept_terms_counter' => $this->handleAcceptTermsCounter($game, $player),
            default => response()->json(['status' => 'error', 'message' => 'Invalid action'], 400),
        };
    }

    private function handleStart(Game $game, GamePlayer $player): JsonResponse
    {
        if ($player->team_id !== null) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.not_free_agent'),
            ], 422);
        }

        if (ContractService::isSquadFull($game)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.squad_full', ['max' => ContractService::MAX_SQUAD_SIZE]),
            ], 422);
        }

        if (! $this->scoutingService->canSignFreeAgent($player, $game->id, $game->team_id)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.free_agent_reputation_too_low'),
            ], 422);
        }

        // Check for existing countered offer to resume
        $existing = $this->findPendingFreeAgentOffer($game, $player, 'countered');

        if ($existing) {
            $mood = $this->getWillingnessMood($player, $game);

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'terms_open',
                'round' => $existing->terms_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_free_agent_counter', [
                            'player' => $player->name,
                            'wage' => Money::format($existing->wage_counter_offer),
                            'years' => $existing->preferred_years,
                        ]),
                        'wage' => (int) ($existing->wage_counter_offer / 100),
                        'years' => $existing->preferred_years,
                        'mood' => $mood,
                    ], [
                        'canAccept' => true,
                        'suggestedWage' => $this->calculateMidpointInEuros($existing->offered_wage, $existing->wage_counter_offer),
                        'preferredYears' => $existing->preferred_years,
                    ]),
                ],
            ]);
        }

        // Prevent duplicate pending/agreed offers
        $hasPending = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_USER_BID)
            ->where('transfer_fee', 0)
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

        $demand = $this->contractService->calculateFreeAgentWageDemand($player, $this->scoutingService);
        $mood = $this->getWillingnessMood($player, $game);
        $demandInEuros = (int) ($demand['wage'] / 100);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'terms_open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('demand', [
                    'text' => __('transfers.chat_free_agent_demand', [
                        'player' => $player->name,
                        'wage' => $demand['formattedWage'],
                        'years' => $demand['contractYears'],
                    ]),
                    'wage' => $demandInEuros,
                    'years' => $demand['contractYears'],
                    'mood' => $mood,
                ], [
                    'canAccept' => false,
                    'suggestedWage' => $demandInEuros,
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

        $offer = $this->findPendingFreeAgentOffer($game, $player);

        if (!$offer) {
            $offer = TransferOffer::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => null,
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => 0,
                'status' => TransferOffer::STATUS_PENDING,
                'expires_at' => $game->current_date->addDays(14),
                'game_date' => $game->current_date,
                'negotiation_round' => 1,
            ]);
        }

        $result = $this->contractService->negotiateFreeAgentTermsSync(
            $offer, $validated['wage'] * 100, $validated['years'], $game, $this->scoutingService
        );

        $offer = $result['offer'];

        return match ($result['result']) {
            'accepted' => $this->completeFreeAgentSigning($offer, $game, $player),
            'countered' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'terms_open',
                'round' => $offer->terms_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_free_agent_counter', [
                            'player' => $player->name,
                            'wage' => Money::format($offer->wage_counter_offer),
                            'years' => $offer->preferred_years,
                        ]),
                        'wage' => (int) ($offer->wage_counter_offer / 100),
                        'years' => $offer->preferred_years,
                        'mood' => $this->getWillingnessMood($player, $game),
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
                        'text' => __('transfers.chat_free_agent_rejected', [
                            'player' => $player->name,
                        ]),
                    ]),
                ],
            ]),
        };
    }

    private function handleAcceptTermsCounter(Game $game, GamePlayer $player): JsonResponse
    {
        $offer = $this->findPendingFreeAgentOffer($game, $player, 'countered');

        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.transfer_failed'),
            ], 422);
        }

        $this->contractService->acceptFreeAgentTermsCounter($offer);
        $offer->refresh();

        return $this->completeFreeAgentSigning($offer, $game, $player);
    }

    private function completeFreeAgentSigning(TransferOffer $offer, Game $game, GamePlayer $player): JsonResponse
    {
        $this->transferService->completeFreeAgentSigning($game, $player, $offer);
        $this->notificationService->notifyTransferComplete($game, $offer->refresh());

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'completed',
            'round' => $offer->terms_round ?? 1,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => __('transfers.chat_free_agent_accepted', [
                        'player' => $player->name,
                    ]),
                ]),
            ],
        ]);
    }

    // ── Helpers ──

    private function findPendingFreeAgentOffer(Game $game, GamePlayer $player, ?string $termsStatus = null): ?TransferOffer
    {
        $query = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_USER_BID)
            ->where('transfer_fee', 0)
            ->where('status', TransferOffer::STATUS_PENDING);

        if ($termsStatus) {
            $query->where('terms_status', $termsStatus);
        }

        return $query->first();
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

    private function getWillingnessMood(GamePlayer $player, Game $game): array
    {
        $willingness = $this->scoutingService->calculateWillingness($player, $game);

        return match ($willingness['label']) {
            'very_interested', 'open' => ['label' => __('transfers.mood_willing_sign'), 'color' => 'green'],
            'undecided' => ['label' => __('transfers.mood_open_sign'), 'color' => 'amber'],
            default => ['label' => __('transfers.mood_reluctant_sign'), 'color' => 'red'],
        };
    }
}
