<?php

namespace App\Http\Views;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Squad\Services\DevelopmentCurve;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Modules\Transfer\Services\ContractService;
use App\Support\PositionMapper;

class ShowSquadV2
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $isCareerMode = $game->isCareerMode();

        // Get all players for user's team with relationships
        $allPlayers = GamePlayer::with(['player', 'activeLoan', 'transferOffers', 'suspensions', 'activeRenewalNegotiation', 'latestRenewalNegotiation'])
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get();

        $seasonEndDate = $game->getSeasonEndDate();
        $nextMatchday = $game->current_matchday + 1;

        // Enrich each player with computed data
        $allPlayers->each(function (GamePlayer $player) use ($game, $seasonEndDate, $nextMatchday) {
            // Availability
            $player->setAttribute('is_unavailable', !$player->isAvailable($game->current_date, $nextMatchday));
            $player->setAttribute('unavailability_reason', $player->getUnavailabilityReason($game->current_date, $nextMatchday));

            // Development projection
            $player->setAttribute('projection', $this->developmentService->getNextSeasonProjection($player));
            $player->setAttribute('dev_status', DevelopmentCurve::getStatus($player->age));

            // Computed stats
            $player->setAttribute('goal_contributions', $player->goals + $player->assists);
            $player->setAttribute('goals_per_game', $player->appearances > 0
                ? round($player->goals / $player->appearances, 2)
                : 0);
        });

        // Group by position
        $grouped = $allPlayers->groupBy(function ($player) {
            return match ($player->position_group) {
                'Goalkeeper' => 'goalkeepers',
                'Defender' => 'defenders',
                'Midfielder' => 'midfielders',
                'Forward' => 'forwards',
                default => 'midfielders',
            };
        });

        $goalkeepers = ($grouped->get('goalkeepers') ?? collect())->sortByDesc('overall_score')->values();
        $defenders = ($grouped->get('defenders') ?? collect())->sortByDesc('overall_score')->values();
        $midfielders = ($grouped->get('midfielders') ?? collect())->sortByDesc('overall_score')->values();
        $forwards = ($grouped->get('forwards') ?? collect())->sortByDesc('overall_score')->values();

        // --- Squad Dashboard KPIs ---
        $squadSize = $allPlayers->count();
        $avgAge = $squadSize > 0 ? round($allPlayers->avg(fn ($p) => $p->age), 1) : 0;
        $avgFitness = $squadSize > 0 ? round($allPlayers->avg('fitness')) : 0;
        $avgMorale = $squadSize > 0 ? round($allPlayers->avg('morale')) : 0;
        $avgOverall = $squadSize > 0 ? round($allPlayers->avg('overall_score')) : 0;
        $lowFitnessCount = $allPlayers->filter(fn ($p) => $p->fitness < 70)->count();
        $lowMoraleCount = $allPlayers->filter(fn ($p) => $p->morale < 65)->count();
        $injuredCount = $allPlayers->filter(fn ($p) => $p->isInjured($game->current_date))->count();

        // Age distribution
        $youngCount = $allPlayers->filter(fn ($p) => $p->age <= 23)->count();
        $primeCount = $allPlayers->filter(fn ($p) => $p->age >= 24 && $p->age <= 29)->count();
        $veteranCount = $allPlayers->filter(fn ($p) => $p->age >= 30)->count();

        // Career mode financial data
        $squadValue = 0;
        $wageBill = 0;
        $wageRatio = 0;
        $windowCountdown = null;

        if ($isCareerMode) {
            $squadValue = $allPlayers->sum('market_value_cents');
            $wageBill = $allPlayers->sum('annual_wage');
            $finances = $game->currentFinances;
            $projectedRevenue = $finances->projected_total_revenue ?? 0;
            $wageRatio = $projectedRevenue > 0 ? round($wageBill / $projectedRevenue * 100) : 0;
            $windowCountdown = $game->getWindowCountdown();
        }

        // --- Position Depth Chart ---
        $positionSlots = ['GK', 'CB', 'LB', 'RB', 'DM', 'CM', 'AM', 'LW', 'RW', 'CF'];
        $depthChart = $this->buildDepthChart($allPlayers, $positionSlots);

        // --- Contract Watchlist (career mode) ---
        $expiringThisSeason = collect();
        $expiringNextSeason = collect();
        $highEarners = collect();

        if ($isCareerMode) {
            $expiringThisSeason = $allPlayers->filter(fn ($p) => $p->isContractExpiring($seasonEndDate))
                ->sortByDesc('overall_score')
                ->values();

            $nextSeasonEnd = $seasonEndDate->copy()->addYear();
            $expiringNextSeason = $allPlayers->filter(function ($p) use ($seasonEndDate, $nextSeasonEnd) {
                return $p->contract_until
                    && $p->contract_until->gt($seasonEndDate)
                    && $p->contract_until->lte($nextSeasonEnd);
            })->sortByDesc('overall_score')->values();

            // Top 3 highest wage-to-overall ratio players
            $highEarners = $allPlayers->filter(fn ($p) => $p->annual_wage > 0 && $p->overall_score > 0)
                ->sortByDesc(fn ($p) => $p->annual_wage / $p->overall_score)
                ->take(3)
                ->values();
        }

        // --- Squad Health Alerts ---
        $alerts = $this->buildAlerts($allPlayers, $game, $depthChart, $injuredCount, $lowFitnessCount, $lowMoraleCount, $isCareerMode, $windowCountdown);

        // --- Renewal data (career mode) ---
        $renewalData = [];
        if ($isCareerMode) {
            $renewalEligible = $this->contractService->getPlayersEligibleForRenewal($game);
            foreach ($renewalEligible as $player) {
                $demand = $this->contractService->calculateRenewalDemand($player);
                $midpoint = (int) (ceil(($player->annual_wage + $demand['wage']) / 2 / 100 / 10000) * 10000);
                $disposition = $this->contractService->calculateDisposition($player);
                $mood = $this->contractService->getMoodIndicator($disposition);
                $renewalData[$player->id] = [
                    'demand' => $demand,
                    'midpoint' => $midpoint,
                    'mood' => $mood,
                ];
            }
        }

        $academyCount = 0;
        if ($isCareerMode) {
            $academyCount = AcademyPlayer::where('game_id', $gameId)->where('team_id', $game->team_id)->count();
        }

        return view('squad', [
            'game' => $game,
            'goalkeepers' => $goalkeepers,
            'defenders' => $defenders,
            'midfielders' => $midfielders,
            'forwards' => $forwards,
            'allPlayers' => $allPlayers,
            'seasonEndDate' => $seasonEndDate,
            // Dashboard KPIs
            'squadSize' => $squadSize,
            'avgAge' => $avgAge,
            'avgFitness' => $avgFitness,
            'avgMorale' => $avgMorale,
            'avgOverall' => $avgOverall,
            'injuredCount' => $injuredCount,
            'youngCount' => $youngCount,
            'primeCount' => $primeCount,
            'veteranCount' => $veteranCount,
            'squadValue' => $squadValue,
            'wageBill' => $wageBill,
            'wageRatio' => $wageRatio,
            // Sidebar data
            'depthChart' => $depthChart,
            'expiringThisSeason' => $expiringThisSeason,
            'expiringNextSeason' => $expiringNextSeason,
            'highEarners' => $highEarners,
            'alerts' => $alerts,
            // Renewal data
            'renewalData' => $renewalData,
            'academyCount' => $academyCount,
        ]);
    }

    /**
     * Build position depth chart with natural-position player counts per slot.
     */
    private function buildDepthChart($players, array $slots): array
    {
        // Map canonical positions to their primary slot
        $positionToSlot = [
            'Goalkeeper' => 'GK',
            'Centre-Back' => 'CB',
            'Left-Back' => 'LB',
            'Right-Back' => 'RB',
            'Defensive Midfield' => 'DM',
            'Central Midfield' => 'CM',
            'Attacking Midfield' => 'AM',
            'Left Midfield' => 'LW',   // Group with LW
            'Right Midfield' => 'RW',  // Group with RW
            'Left Winger' => 'LW',
            'Right Winger' => 'RW',
            'Centre-Forward' => 'CF',
            'Second Striker' => 'CF',   // Group with CF
        ];

        $depth = [];
        foreach ($slots as $slot) {
            $depth[$slot] = [
                'count' => 0,
                'players' => [],
            ];
        }

        foreach ($players as $player) {
            $slot = $positionToSlot[$player->position] ?? null;
            if ($slot && isset($depth[$slot])) {
                $depth[$slot]['count']++;
                $depth[$slot]['players'][] = $player->name;
            }
        }

        return $depth;
    }

    /**
     * Generate squad health alerts based on current state.
     */
    private function buildAlerts($players, Game $game, array $depthChart, int $injuredCount, int $lowFitnessCount, int $lowMoraleCount, bool $isCareerMode, ?array $windowCountdown): array
    {
        $alerts = [];

        if ($injuredCount >= 3) {
            $alerts[] = [
                'type' => 'warning',
                'message' => __('squad_v2.alert_many_injured', ['count' => $injuredCount]),
            ];
        }

        if ($lowMoraleCount >= 3) {
            $alerts[] = [
                'type' => 'warning',
                'message' => __('squad_v2.alert_low_morale', ['count' => $lowMoraleCount]),
            ];
        }

        if ($lowFitnessCount >= 3) {
            $alerts[] = [
                'type' => 'warning',
                'message' => __('squad_v2.alert_low_fitness', ['count' => $lowFitnessCount]),
            ];
        }

        // Position depth alerts
        foreach ($depthChart as $slot => $data) {
            if ($slot === 'GK' && $data['count'] < 2) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => __('squad_v2.alert_thin_position', ['position' => PositionMapper::slotToDisplayAbbreviation($slot), 'count' => $data['count']]),
                ];
            } elseif ($slot !== 'GK' && $data['count'] === 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => __('squad_v2.alert_no_cover', ['position' => PositionMapper::slotToDisplayAbbreviation($slot)]),
                ];
            }
        }

        if ($isCareerMode && $windowCountdown && $windowCountdown['action'] === 'closes' && $windowCountdown['matchdays'] <= 3) {
            $alerts[] = [
                'type' => 'info',
                'message' => __('squad_v2.alert_window_closing', ['window' => $windowCountdown['window'], 'matchdays' => $windowCountdown['matchdays']]),
            ];
        }

        return $alerts;
    }
}
