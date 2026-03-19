<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Modules\Transfer\Services\ContractService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NegotiateRenewal
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::with(['player', 'game', 'transferOffers'])
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        $action = $request->input('action');

        return match ($action) {
            'start' => $this->handleStart($game, $player),
            'offer' => $this->handleOffer($request, $game, $player),
            'accept_counter' => $this->handleAcceptCounter($player),
            'walk_away' => $this->handleWalkAway($player),
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
                'max_rounds' => 3,
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
                        'canCounter' => $existing->round < 3,
                        'canWalkAway' => true,
                        'suggestedWage' => $this->calculateMidpoint($existing->user_offer, $existing->counter_offer),
                        'preferredYears' => $existing->preferred_years,
                    ]),
                ],
            ]);
        }

        // New negotiation — show player demand
        if (!$player->canBeOfferedRenewal()) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.cannot_renew'),
            ], 422);
        }

        $demand = $this->contractService->calculateRenewalDemand($player);
        $disposition = $this->contractService->calculateDisposition($player);
        $mood = $this->contractService->getMoodIndicator($disposition);
        $midpoint = $this->calculateMidpoint($player->annual_wage, $demand['wage']);

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'open',
            'round' => 0,
            'max_rounds' => 3,
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
                    'canCounter' => true,
                    'canWalkAway' => true,
                    'suggestedWage' => $midpoint,
                    'preferredYears' => $demand['contractYears'],
                ]),
            ],
        ]);
    }

    private function handleOffer(Request $request, Game $game, GamePlayer $player): JsonResponse
    {
        $offerWageEuros = (int) $request->input('wage');
        $offeredYears = (int) $request->input('years', 3);
        $offerWageCents = $offerWageEuros * 100;

        if ($offerWageCents <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.renewal_invalid_offer'),
            ], 422);
        }

        $result = $this->contractService->negotiateSync($player, $offerWageCents, $offeredYears);
        $negotiation = $result['negotiation'];

        return match ($result['result']) {
            'accepted' => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'accepted',
                'round' => $negotiation->round,
                'max_rounds' => 3,
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
                'max_rounds' => 3,
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
                        'canCounter' => $negotiation->round < 3,
                        'canWalkAway' => true,
                        'suggestedWage' => $this->calculateMidpoint($negotiation->user_offer, $negotiation->counter_offer),
                        'preferredYears' => $negotiation->preferred_years,
                    ]),
                ],
            ]),
            default => response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => $negotiation->round,
                'max_rounds' => 3,
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

    private function handleWalkAway(GamePlayer $player): JsonResponse
    {
        $negotiation = RenewalNegotiation::where('game_player_id', $player->id)
            ->whereIn('status', [
                RenewalNegotiation::STATUS_OFFER_PENDING,
                RenewalNegotiation::STATUS_PLAYER_COUNTERED,
            ])
            ->first();

        if ($negotiation) {
            $this->contractService->cancelNegotiation($negotiation);
        }

        return response()->json([
            'status' => 'ok',
            'negotiation_status' => 'walked_away',
            'messages' => [
                $this->systemMessage(__('transfers.chat_walked_away', ['player' => $player->name])),
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

    private function systemMessage(string $text): array
    {
        return [
            'sender' => 'system',
            'type' => 'info',
            'content' => ['text' => $text],
            'options' => null,
        ];
    }

    /**
     * Calculate midpoint between two wages, in euros, rounded to nearest 10K.
     */
    private function calculateMidpoint(int $wageCentsA, int $wageCentsB): int
    {
        return (int) (ceil(($wageCentsA + $wageCentsB) / 2 / 100 / 10000) * 10000);
    }
}
