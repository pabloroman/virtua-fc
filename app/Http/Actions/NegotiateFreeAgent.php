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
        $existing = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_USER_BID)
            ->where('transfer_fee', 0)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('terms_status', 'countered')
            ->first();

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

        $wageDemand = $this->scoutingService->calculateWageDemand($player);
        $contractYears = $player->age($game->current_date) >= 32 ? 1 : 3;
        $mood = $this->getWillingnessMood($player, $game);
        $demandInEuros = (int) ($wageDemand / 100);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'terms_open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('demand', [
                    'text' => __('transfers.chat_free_agent_demand', [
                        'player' => $player->name,
                        'wage' => Money::format($wageDemand),
                        'years' => $contractYears,
                    ]),
                    'wage' => $demandInEuros,
                    'years' => $contractYears,
                    'mood' => $mood,
                ], [
                    'canAccept' => false,
                    'suggestedWage' => $demandInEuros,
                    'preferredYears' => $contractYears,
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
            ->where('offer_type', TransferOffer::TYPE_USER_BID)
            ->where('transfer_fee', 0)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->first();

        if (!$offer) {
            // Create on first round
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

        $offerWageCents = $validated['wage'] * 100;
        $offeredYears = $validated['years'];

        $result = $this->negotiateFreeAgentTerms($offer, $offerWageCents, $offeredYears, $game);

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
        $offer = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_USER_BID)
            ->where('transfer_fee', 0)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('terms_status', 'countered')
            ->first();

        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.transfer_failed'),
            ], 422);
        }

        $offer->update([
            'offered_wage' => $offer->wage_counter_offer,
            'offered_years' => $offer->preferred_years,
            'terms_status' => 'accepted',
        ]);

        $offer->refresh();

        return $this->completeFreeAgentSigning($offer, $game, $player);
    }

    private function negotiateFreeAgentTerms(TransferOffer $offer, int $offerWageCents, int $offeredYears, Game $game): array
    {
        $player = $offer->gamePlayer;

        if ($offer->terms_status === 'countered') {
            $offer->update([
                'terms_round' => min(($offer->terms_round ?? 1) + 1, self::MAX_ROUNDS),
                'offered_wage' => $offerWageCents,
                'offered_years' => $offeredYears,
                'wage_counter_offer' => null,
            ]);
        } else {
            $wageDemand = $this->scoutingService->calculateWageDemand($player);
            $contractYears = $player->age($game->current_date) >= 32 ? 1 : 3;
            $offer->update([
                'terms_status' => 'pending',
                'terms_round' => 1,
                'player_demand' => $wageDemand,
                'preferred_years' => $contractYears,
                'offered_wage' => $offerWageCents,
                'offered_years' => $offeredYears,
            ]);
        }

        // Evaluate using willingness as disposition
        $willingness = $this->scoutingService->calculateWillingness($player, $game);
        $disposition = $willingness['score'] / 100.0;
        $offer->update(['terms_disposition' => $disposition]);

        $flexibility = $disposition * 0.30;
        $minimumAcceptable = (int) ($offer->player_demand * (1.0 - $flexibility));

        $yearsModifier = $this->calculateYearsModifier($offeredYears, $offer->preferred_years);
        $effectiveOffer = (int) ($offerWageCents * $yearsModifier);

        if ($effectiveOffer >= $minimumAcceptable) {
            $offer->update([
                'terms_status' => 'accepted',
                'status' => TransferOffer::STATUS_COMPLETED,
                'resolved_at' => $game->current_date,
            ]);
            return ['result' => 'accepted', 'offer' => $offer->fresh()];
        }

        $counterThreshold = (int) ($minimumAcceptable * 0.85);

        if ($effectiveOffer >= $counterThreshold && $offer->terms_round < self::MAX_ROUNDS) {
            $counterWage = (int) (($minimumAcceptable + $offer->player_demand) / 2);
            $counterWage = (int) (round($counterWage / 10_000_000) * 10_000_000);

            $offer->update([
                'terms_status' => 'countered',
                'wage_counter_offer' => $counterWage,
            ]);
            return ['result' => 'countered', 'offer' => $offer->fresh()];
        }

        $offer->update([
            'terms_status' => 'rejected',
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => $game->current_date,
        ]);
        return ['result' => 'rejected', 'offer' => $offer->fresh()];
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

    private function calculateYearsModifier(int $offeredYears, int $preferredYears): float
    {
        $diff = $offeredYears - $preferredYears;

        return match (true) {
            $diff >= 2 => 1.15,
            $diff === 1 => 1.05,
            $diff === 0 => 1.0,
            $diff === -1 => 0.90,
            default => 0.80,
        };
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
