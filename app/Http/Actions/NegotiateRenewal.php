<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Modules\Finance\Services\SalaryCapService;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\DispositionService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NegotiateRenewal
{
    private const MAX_ROUNDS = ContractService::MAX_NEGOTIATION_ROUNDS;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly DispositionService $dispositionService,
        private readonly SalaryCapService $salaryCapService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in(['start', 'offer', 'accept_counter'])],
        ]);

        $game = Game::findOrFail($gameId);

        $action = $request->input('action');
        $eagerLoads = in_array($action, ['start', 'offer'])
            ? ['game', 'transferOffers']
            : ['game'];

        $player = GamePlayer::with($eagerLoads)
            ->where('game_id', $gameId)
            ->userOwned($game)
            ->findOrFail($playerId);

        return match ($action) {
            'start' => $this->handleStart($game, $player),
            'offer' => $this->handleOffer($request, $game, $player),
            'accept_counter' => $this->handleAcceptCounter($game, $player),
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
            $disposition = $this->contractService->calculateDisposition($player, NegotiationScenario::RENEWAL, round: $existing->round);
            $mood = $this->contractService->getMoodIndicator($disposition);

            return response()->json(array_merge($this->clausePayload($game, $player, (int) $existing->player_demand), [
                'status' => 'ok',
                'negotiation_status' => 'open',
                'round' => $existing->round,
                'max_rounds' => self::MAX_ROUNDS,
                'wage_floor' => (int) ($this->contractService->getMinimumWageForTeam($game->team) / 100),
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
            ]));
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

        // Player with plenty of contract left and good morale won't engage in talks.
        if (!$this->dispositionService->isWillingToNegotiateRenewal($player)) {
            return response()->json([
                'status' => 'ok',
                'negotiation_status' => 'rejected',
                'round' => 0,
                'max_rounds' => self::MAX_ROUNDS,
                'messages' => [
                    $this->agentMessage('rejected', [
                        'text' => __('transfers.chat_agent_not_interested', [
                            'player' => $player->name,
                        ]),
                    ]),
                ],
            ]);
        }

        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::RENEWAL);
        $disposition = $this->contractService->calculateDisposition($player, NegotiationScenario::RENEWAL);
        $mood = $this->contractService->getMoodIndicator($disposition);
        $wageFloorEuros = (int) ($this->contractService->getMinimumWageForTeam($game->team) / 100);

        return response()->json(array_merge($this->clausePayload($game, $player, (int) $demand['wage']), [
            'status' => 'ok',
            'negotiation_status' => 'open',
            'round' => 0,
            'max_rounds' => self::MAX_ROUNDS,
            'wage_floor' => $wageFloorEuros,
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
                    'canAccept' => true,
                    'suggestedWage' => (int) ($demand['wage'] / 100),
                    'preferredYears' => $demand['contractYears'],
                ]),
            ],
        ]));
    }

    private function handleOffer(Request $request, Game $game, GamePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'wage' => ['required', 'integer', 'min:1'],
            'years' => ['required', 'integer', 'min:1', 'max:5'],
            'clause' => ['nullable', 'integer', 'min:0'],
        ]);

        $offerWageEuros = $validated['wage'];
        $offeredYears = $validated['years'];
        $offerWageCents = $offerWageEuros * 100;

        // The clause control only exists for mandatory-clause (ES) clubs with the
        // feature on; everywhere else a clause is impossible, so any incoming value
        // is ignored. There is no upper cap — a clause above the floor just raises
        // the wage the player demands (ContractService::effectiveDemandWithReleaseClause),
        // so this layer forwards the manager's intent and the floor stays the only
        // server-side clamp.
        $requestedClauseCents = ($game->release_clauses_enabled
            && isset($validated['clause'])
            && in_array($game->country, config('finances.release_clause.mandatory_countries', []), true))
            ? $validated['clause'] * 100
            : null;

        // Salary cap: a renewal replaces the player's current wage, so only the
        // increase is charged against the cap. Wage cuts always pass.
        $freedWage = $this->salaryCapService->effectiveWageFor($player);
        if (! $this->salaryCapService->canCommitWage($game, $offerWageCents, $freedWage)) {
            return response()->json([
                'status' => 'error',
                'message' => $this->salaryCapService->blockMessage($game, $player->name, $offerWageCents, $freedWage),
            ], 422);
        }

        $result = $this->contractService->negotiateSync($player, $offerWageCents, $offeredYears, $requestedClauseCents);
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

    private function handleAcceptCounter(Game $game, GamePlayer $player): JsonResponse
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

        // Salary cap: re-check against the wage the player is holding out for.
        $freedWage = $this->salaryCapService->effectiveWageFor($player);
        if (! $this->salaryCapService->canCommitWage($game, (int) $negotiation->counter_offer, $freedWage)) {
            return response()->json([
                'status' => 'error',
                'message' => $this->salaryCapService->blockMessage($game, $player->name, (int) $negotiation->counter_offer, $freedWage),
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

    /**
     * Release-clause data for the renewal chat's clause control. Returned only for
     * mandatory-clause (ES) clubs with the feature on; non-ES clubs get nothing, so
     * the client never shows the control and never sends a clause. The client uses
     * market value + demand + the premium slope to advise the wage the player will
     * want for a given clause, but the server (effectiveDemandWithReleaseClause)
     * stays authoritative.
     *
     * @return array<string, mixed>
     */
    private function clausePayload(Game $game, GamePlayer $player, int $demandWageCents): array
    {
        if (! $game->release_clauses_enabled
            || ! in_array($game->country, config('finances.release_clause.mandatory_countries', []), true)) {
            return [];
        }

        $marketValueCents = (int) $player->market_value_cents;

        // Reuse the service for the floor so the es_floor_multiplier lives in one place.
        $floorCents = $this->contractService->releaseClauseFloorCents($marketValueCents);

        return [
            'clause_enabled' => true,
            'clause_floor' => (int) ($floorCents / 100),
            'clause_market_value' => (int) ($marketValueCents / 100),
            'clause_demand' => (int) ($demandWageCents / 100),
            'clause_premium_slope' => (float) config('finances.release_clause.tolerance.premium_slope', 2.5),
        ];
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
