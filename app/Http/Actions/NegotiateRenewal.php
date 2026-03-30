<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Modules\Transfer\Services\ContractService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegotiateRenewal
{
    private const MAX_ROUNDS = ContractService::MAX_NEGOTIATION_ROUNDS;

    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in(['start', 'offer', 'accept_counter'])],
        ]);

        $game = Game::findOrFail($gameId);

        $action = $request->input('action');
        $eagerLoads = in_array($action, ['start', 'offer'])
            ? ['player', 'game', 'transferOffers']
            : ['player', 'game'];

        $player = GamePlayer::with($eagerLoads)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        return match ($action) {
            'start' => $this->handleStart($game, $player),
            'offer' => $this->handleOffer($request, $game, $player),
            'accept_counter' => $this->handleAcceptCounter($player),
            default => response()->json(['status' => 'error', 'message' => 'Invalid action'], 400),
        };
    }

    private function handleStart(Game $game, GamePlayer $player): JsonResponse
    {
        // Check if there's an existing countered negotiation to resume
        $existing = RenewalNegotiation::where('game_player_id', $player->id)
            ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
            ->first();

        if ($existing) {
            $disposition = $this->contractService->calculateDisposition($player, $existing->round);
            $mood = $this->contractService->getMoodIndicator($disposition);

            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $existing->round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_counter_resume', [
                            'player' => $player->name,
                            'wage' => Money::format($existing->counter_offer),
                            'years' => $existing->preferred_years,
                        ]),
                        'wage' => (int) ($existing->counter_offer / 100),
                        'years' => $existing->preferred_years,
                        'mood' => $mood,
                    ], [
                        'canAccept' => true,
                        'suggestedWage' => $this->calculateMidpointInEuros($existing->user_offer, $existing->counter_offer),
                        'preferredYears' => $existing->preferred_years,
                    ]),
                ],
            ]);
        }

        // Cooldown: must wait at least one matchday after a rejected negotiation
        if (RenewalNegotiation::hasRenewalCooldown($player->id, $game->current_date)) {
            return response()->json([
                'status' => 'error',
                'message' => __('transfers.renewal_cooldown'),
            ], 422);
        }

        // New negotiation — show player demand
        if (!$player->canBeOfferedRenewal(currentDate: $game->current_date)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.cannot_renew'),
            ], 422);
        }

        $demand = $this->contractService->calculateRenewalDemand($player);
        $disposition = $this->contractService->calculateDisposition($player);
        $mood = $this->contractService->getMoodIndicator($disposition);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('demand', [
                    'text' => __('transfers.chat_agent_demand', [
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

    private function handleOffer(Request $request, Game $game, GamePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'wage' => ['required', 'integer', 'min:1'],
            'years' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $offerWageEuros = $validated['wage'];
        $offeredYears = $validated['years'];
        $offerWageCents = $offerWageEuros * 100;

        $result = $this->contractService->negotiateSync($player, $offerWageCents, $offeredYears);
        $negotiation = $result['negotiation'];

        return match ($result['result']) {
            'accepted' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'accepted',
                'round' => $negotiation->round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('accepted', [
                        'text' => __('transfers.chat_agent_accepted', [
                            'player' => $player->name,
                            'wage' => Money::format($negotiation->user_offer),
                            'years' => $negotiation->contract_years,
                        ]),
                        'wage' => (int) ($negotiation->user_offer / 100),
                        'years' => $negotiation->contract_years,
                    ]),
                ],
            ]),
            'countered' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $negotiation->round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('counter', [
                        'text' => __('transfers.chat_agent_counter', [
                            'player' => $player->name,
                            'wage' => Money::format($negotiation->counter_offer),
                            'years' => $negotiation->preferred_years,
                        ]),
                        'wage' => (int) ($negotiation->counter_offer / 100),
                        'years' => $negotiation->preferred_years,
                        'mood' => $this->contractService->getMoodIndicator($negotiation->disposition),
                    ], [
                        'canAccept' => true,
                        'suggestedWage' => $this->calculateMidpointInEuros($negotiation->user_offer, $negotiation->counter_offer),
                        'preferredYears' => $negotiation->preferred_years,
                    ]),
                ],
            ]),
            default => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => $negotiation->round,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => __('transfers.chat_agent_rejected', [
                            'player' => $player->name,
                        ]),
                    ]),
                ],
            ]),
        };
    }

    private function handleAcceptCounter(GamePlayer $player): JsonResponse
    {
        $negotiation = RenewalNegotiation::where('game_player_id', $player->id)
            ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
            ->first();

        if (!$negotiation) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.renewal_failed'),
            ], 422);
        }

        $success = $this->contractService->acceptCounterOffer($negotiation);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.renewal_failed'),
            ], 422);
        }

        $negotiation->refresh();

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'accepted',
            'round' => $negotiation->round,
            'max_rounds' => self::MAX_ROUNDS,
            'messages' => [
                $this->agentMessage('accepted', [
                    'text' => __('transfers.chat_agent_accepted', [
                        'player' => $player->name,
                        'wage' => Money::format($negotiation->counter_offer),
                        'years' => $negotiation->contract_years,
                    ]),
                    'wage' => (int) ($negotiation->counter_offer / 100),
                    'years' => $negotiation->contract_years,
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

    /**
     * Calculate midpoint between two wages, in euros, rounded to nearest 10K.
     */
    private function calculateMidpointInEuros(int $wageCentsA, int $wageCentsB): int
    {
        return (int) (ceil(($wageCentsA + $wageCentsB) / 2 / 100 / 10000) * 10000);
    }
}
