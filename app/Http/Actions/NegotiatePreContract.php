<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegotiatePreContract
{
    private const MAX_ROUNDS = ContractService::MAX_NEGOTIATION_ROUNDS;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly ScoutingService $scoutingService,
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
        // Validate pre-contract eligibility
        if (!$game->isPreContractPeriod()) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.pre_contract_not_available'),
            ], 422);
        }

        $isExpiring = $player->contract_until && $player->contract_until <= $game->getSeasonEndDate();
        if (!$isExpiring) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.pre_contract_not_available'),
            ], 422);
        }

        // Check for existing countered offer to resume
        $existing = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
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
                        'text' => __('transfers.chat_pre_contract_counter', [
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
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
            ->exists();

        if ($hasPending) {
            return response()->json([
                'status' => 'error',
                'message' => __('transfers.already_bidding'),
            ], 422);
        }

        // Calculate wage demand (deterministic — no variance)
        $demand = $this->contractService->calculatePreContractWageDemand($player, $this->scoutingService);
        $mood = $this->getWillingnessMood($player, $game);
        $demandInEuros = (int) ($demand['wage'] / 100);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'terms_open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('demand', [
                    'text' => __('transfers.chat_pre_contract_demand', [
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

        // Find or create the pre-contract offer
        $offer = TransferOffer::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->first();

        if (!$offer) {
            // Create on first round
            $offer = TransferOffer::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => $player->team_id,
                'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => 0,
                'status' => TransferOffer::STATUS_PENDING,
                'expires_at' => $game->current_date->addDays(TransferOffer::PRE_CONTRACT_OFFER_EXPIRY_DAYS),
                'game_date' => $game->current_date,
                'negotiation_round' => 1, // Mark as sync-negotiated
            ]);
        }

        $offerWageCents = $validated['wage'] * 100;
        $offeredYears = $validated['years'];

        $result = $this->contractService->negotiatePreContractTermsSync(
            $offer, $offerWageCents, $offeredYears, $game, $this->scoutingService
        );

        $offer = $result['offer'];

        return match ($result['result']) {
            'accepted' => $this->completePreContractNegotiation($offer, $game, $player),
            'countered' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'terms_open',
                'round' => $offer->terms_round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_pre_contract_counter', [
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
                        'text' => __('transfers.chat_pre_contract_rejected', [
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
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('terms_status', 'countered')
            ->first();

        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.transfer_failed'),
            ], 422);
        }

        $this->contractService->acceptPreContractTermsCounter($offer);
        $offer->refresh();

        return $this->completePreContractNegotiation($offer, $game, $player);
    }

    private function completePreContractNegotiation(TransferOffer $offer, Game $game, GamePlayer $player): JsonResponse
    {
        $this->notificationService->notifyPreContractResult($game, $offer->refresh());

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'completed',
            'round' => $offer->terms_round ?? 1,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => __('transfers.chat_pre_contract_accepted', [
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

    /**
     * Build the mood indicator from the scout willingness score,
     * so the negotiation modal matches the scout report.
     */
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
