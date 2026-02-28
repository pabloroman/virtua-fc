<?php

namespace App\Modules\Transfer\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Notification\Services\NotificationService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Simulates AI transfer market activity at the end of each transfer window.
 *
 * Called from MatchdayOrchestrator when a transfer window closes:
 * - Summer (September): free agent signings + AI-to-AI transfers (0-3 per team)
 * - Winter (February): remaining free agents + AI-to-AI transfers (0-1 per team)
 */
class AITransferMarketService
{
    /** Probability distribution for number of departures (summer) */
    private const DEPARTURE_WEIGHTS_SUMMER = [0 => 35, 1 => 30, 2 => 20, 3 => 15];
    private const DEPARTURE_WEIGHTS_WINTER = [0 => 60, 1 => 40];

    /** Chance of a departure going to a foreign club (vs domestic transfer) */
    private const FOREIGN_DEPARTURE_CHANCE = 40;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Process AI transfer activity when a transfer window closes.
     */
    public function processWindowClose(Game $game, string $window): void
    {
        $isSummer = $window === 'summer';

        // Single load: all AI players with team relation, grouped by team
        $teamRosters = GamePlayer::with('team')
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $game->team_id)
            ->get()
            ->groupBy('team_id');

        // Set game relation in-memory to avoid lazy-loading from age accessor
        $teamRosters->flatten()->each(fn (GamePlayer $p) => $p->setRelation('game', $game));

        // Pre-calculate team averages and build team name lookup
        $teamAverages = $teamRosters->map(fn ($players) => $this->calculateTeamAverage($players));
        $teamNames = $teamRosters->map(fn ($players) => $players->first()?->team?->name ?? 'Unknown');

        // Pre-load Team models for wage calculation (single query)
        $teams = Team::whereIn('id', $teamRosters->keys())->get()->keyBy('id');

        // Phase 1: Sign free agents (players with null team_id)
        $freeAgentSignings = $this->processFreeAgentSignings($game, $teamRosters, $teamAverages, $teamNames, $teams);

        // Phase 2: AI-to-AI transfers (reuse same rosters — now updated with free agent signings)
        $transfers = $this->processAITransfers($game, $isSummer, $teamRosters, $teamAverages, $teamNames, $teams);

        // Phase 3: Create summary notification (only if there's activity)
        if ($freeAgentSignings->isNotEmpty() || $transfers->isNotEmpty()) {
            $this->notificationService->notifyAITransferSummary(
                $game,
                $transfers->toArray(),
                $freeAgentSignings->toArray(),
                $window,
            );
        }
    }

    /**
     * Phase 1: Match free agents to AI teams that need players at their position.
     */
    private function processFreeAgentSignings(
        Game $game,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamNames,
        Collection $teams,
    ): Collection {
        $freeAgents = GamePlayer::where('game_id', $game->id)
            ->whereNull('team_id')
            ->get();

        if ($freeAgents->isEmpty()) {
            return collect();
        }

        // Set game relation in-memory for age accessor
        $freeAgents->each(fn (GamePlayer $p) => $p->setRelation('game', $game));

        $signings = collect();

        foreach ($freeAgents->shuffle() as $freeAgent) {
            $bestTeam = $this->findBestTeamForFreeAgent($freeAgent, $teamRosters, $teamAverages);

            if (! $bestTeam) {
                continue;
            }

            $teamId = $bestTeam['teamId'];

            // Sign the free agent
            $seasonYear = (int) $game->season;
            $contractYears = $freeAgent->age >= 32 ? 1 : mt_rand(1, 2);
            $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

            $team = $teams->get($teamId);
            $minimumWage = $team ? $this->contractService->getMinimumWageForTeam($team) : 0;
            $newWage = $this->contractService->calculateAnnualWage(
                $freeAgent->market_value_cents,
                $minimumWage,
                $freeAgent->age,
            );

            $freeAgent->update([
                'team_id' => $teamId,
                'number' => GamePlayer::nextAvailableNumber($game->id, $teamId),
                'contract_until' => $newContractEnd,
                'annual_wage' => $newWage,
            ]);

            $signings->push([
                'playerName' => $freeAgent->name,
                'position' => $freeAgent->position,
                'toTeamId' => $teamId,
                'toTeamName' => $teamNames[$teamId] ?? 'Unknown',
                'age' => $freeAgent->age,
                'formattedFee' => __('transfers.free_transfer'),
                'type' => 'free_agent',
            ]);

            // Update roster cache
            if (! $teamRosters->has($teamId)) {
                $teamRosters[$teamId] = collect();
            }
            $teamRosters[$teamId]->push($freeAgent);
        }

        return $signings;
    }

    /**
     * Phase 2: Process AI-to-AI transfers across all divisions.
     */
    private function processAITransfers(
        Game $game,
        bool $isSummer,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamNames,
        Collection $teams,
    ): Collection {
        $weights = $isSummer ? self::DEPARTURE_WEIGHTS_SUMMER : self::DEPARTURE_WEIGHTS_WINTER;

        // Get foreign team names for narrative — exclude teams that exist in this game
        $foreignTeams = Team::where('country', '!=', 'ES')
            ->where('type', 'club')
            ->whereNotIn('id', $teamRosters->keys())
            ->inRandomOrder()
            ->limit(30)
            ->pluck('name')
            ->all();
        $foreignIndex = 0;

        $allTransfers = collect();
        $departingPlayerIds = collect();

        foreach ($teamRosters as $teamId => $players) {
            $teamAvg = $teamAverages[$teamId] ?? 55;
            $numDepartures = $this->weightedRandom($weights);

            if ($numDepartures === 0) {
                continue;
            }

            // Only consider non-retiring players for departure
            $eligible = $players->filter(fn (GamePlayer $p) => ! $p->retiring_at_season);

            $candidates = $this->scoreExpendability($eligible, $teamAvg);
            $departures = $candidates->sortByDesc('score')->take($numDepartures);

            foreach ($departures as $candidate) {
                /** @var GamePlayer $player */
                $player = $candidate['player'];

                if ($departingPlayerIds->contains($player->id)) {
                    continue;
                }

                $fromTeamName = $teamNames[$teamId] ?? 'Unknown';
                $fromTeamId = $teamId;
                $fee = $player->market_value_cents;

                if (mt_rand(1, 100) <= self::FOREIGN_DEPARTURE_CHANCE) {
                    $foreignName = $foreignTeams[$foreignIndex % count($foreignTeams)] ?? 'Foreign Club';
                    $foreignIndex++;

                    $allTransfers->push([
                        'playerName' => $player->name,
                        'position' => $player->position,
                        'fromTeamId' => $fromTeamId,
                        'fromTeamName' => $fromTeamName,
                        'toTeamId' => null,
                        'toTeamName' => $foreignName,
                        'fee' => $fee,
                        'formattedFee' => Money::format($fee),
                        'type' => 'foreign',
                    ]);

                    $departingPlayerIds->push($player->id);
                    $player->delete();
                } else {
                    $buyer = $this->findBuyerTeam($player, $teamRosters, $teamAverages, $teamNames, $game, $departingPlayerIds);

                    if ($buyer) {
                        $buyerTeamId = $buyer['teamId'];
                        $buyerTeamName = $buyer['teamName'];

                        $seasonYear = (int) $game->season;
                        $contractYears = mt_rand(2, 3);
                        $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

                        $team = $teams->get($buyerTeamId);
                        $minimumWage = $team ? $this->contractService->getMinimumWageForTeam($team) : 0;
                        $newWage = $this->contractService->calculateAnnualWage(
                            $player->market_value_cents,
                            $minimumWage,
                            $player->age,
                        );

                        $player->update([
                            'team_id' => $buyerTeamId,
                            'number' => GamePlayer::nextAvailableNumber($game->id, $buyerTeamId),
                            'contract_until' => $newContractEnd,
                            'annual_wage' => $newWage,
                        ]);

                        $allTransfers->push([
                            'playerName' => $player->name,
                            'position' => $player->position,
                            'fromTeamId' => $fromTeamId,
                            'fromTeamName' => $fromTeamName,
                            'toTeamId' => $buyerTeamId,
                            'toTeamName' => $buyerTeamName,
                            'fee' => $fee,
                            'formattedFee' => Money::format($fee),
                            'type' => 'domestic',
                        ]);

                        $departingPlayerIds->push($player->id);

                        if ($teamRosters->has($buyerTeamId)) {
                            $teamRosters[$buyerTeamId]->push($player);
                        }
                    } else {
                        $foreignName = $foreignTeams[$foreignIndex % count($foreignTeams)] ?? 'Foreign Club';
                        $foreignIndex++;

                        $allTransfers->push([
                            'playerName' => $player->name,
                            'position' => $player->position,
                            'fromTeamId' => $fromTeamId,
                            'fromTeamName' => $fromTeamName,
                            'toTeamId' => null,
                            'toTeamName' => $foreignName,
                            'fee' => $fee,
                            'formattedFee' => Money::format($fee),
                            'type' => 'foreign',
                        ]);

                        $departingPlayerIds->push($player->id);
                        $player->delete();
                    }
                }
            }
        }

        return $allTransfers;
    }

    /**
     * Find the best AI team for a free agent to sign with.
     */
    private function findBestTeamForFreeAgent(GamePlayer $freeAgent, Collection $teamRosters, Collection $teamAverages): ?array
    {
        $positionGroup = $this->getPositionGroup($freeAgent->position);
        $playerAbility = $this->getPlayerAbility($freeAgent);
        $bestScore = -1;
        $bestTeamId = null;

        foreach ($teamRosters as $teamId => $players) {
            if ($players->count() >= 26) {
                continue;
            }

            $teamAvg = $teamAverages[$teamId] ?? 55;

            if (abs($playerAbility - $teamAvg) > 20) {
                continue;
            }

            $groupCount = $players->filter(
                fn ($p) => $this->getPositionGroup($p->position) === $positionGroup
            )->count();

            $groupNeed = match ($positionGroup) {
                'Goalkeeper' => max(0, 2 - $groupCount),
                'Defender' => max(0, 5 - $groupCount),
                'Midfielder' => max(0, 5 - $groupCount),
                'Forward' => max(0, 3 - $groupCount),
                default => 0,
            };

            $score = $groupNeed * 10 + mt_rand(0, 5);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTeamId = $teamId;
            }
        }

        if ($bestScore <= 0 || ! $bestTeamId) {
            return null;
        }

        return [
            'teamId' => $bestTeamId,
            'teamName' => $teamRosters[$bestTeamId]->first()?->team?->name ?? 'Unknown',
        ];
    }

    /**
     * Find a suitable buying team for a domestic transfer.
     */
    private function findBuyerTeam(
        GamePlayer $player,
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $teamNames,
        Game $game,
        Collection $departingPlayerIds,
    ): ?array {
        $positionGroup = $this->getPositionGroup($player->position);
        $playerAbility = $this->getPlayerAbility($player);
        $candidates = [];

        foreach ($teamRosters as $teamId => $players) {
            if ($teamId === $player->team_id || $teamId === $game->team_id) {
                continue;
            }

            $effectiveSize = $players->count() - $departingPlayerIds->intersect($players->pluck('id'))->count();
            if ($effectiveSize >= 26) {
                continue;
            }

            $teamAvg = $teamAverages[$teamId] ?? 55;

            if (abs($playerAbility - $teamAvg) > 15) {
                continue;
            }

            $groupCount = $players->filter(
                fn ($p) => $this->getPositionGroup($p->position) === $positionGroup
                    && ! $departingPlayerIds->contains($p->id)
            )->count();

            $groupNeed = match ($positionGroup) {
                'Goalkeeper' => max(0, 2 - $groupCount),
                'Defender' => max(0, 6 - $groupCount),
                'Midfielder' => max(0, 6 - $groupCount),
                'Forward' => max(0, 4 - $groupCount),
                default => 0,
            };

            $score = $groupNeed * 10 + mt_rand(0, 8);

            if ($score > 0) {
                $candidates[] = [
                    'teamId' => $teamId,
                    'teamName' => $teamNames[$teamId] ?? 'Unknown',
                    'score' => $score,
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0];
    }

    /**
     * Score each player's expendability for transfer selection.
     */
    private function scoreExpendability(Collection $players, int $teamAvg): Collection
    {
        return $players
            ->map(function (GamePlayer $player) use ($teamAvg) {
                $ability = $this->getPlayerAbility($player);
                $score = 0;

                // Below average ability
                if ($ability < $teamAvg - 15) {
                    $score += 4;
                } elseif ($ability < $teamAvg - 5) {
                    $score += 2;
                }

                // Declining development status (age 29+)
                if ($player->age >= 29) {
                    $score += 2;
                    if ($player->age >= 32) {
                        $score += 1;
                    }
                }

                // Random variance
                $score += mt_rand(0, 2);

                if ($score < 3) {
                    return null;
                }

                return ['player' => $player, 'score' => $score];
            })
            ->filter();
    }

    private function calculateTeamAverage(Collection $players): int
    {
        if ($players->isEmpty()) {
            return 55;
        }

        $total = $players->sum(fn (GamePlayer $p) => $this->getPlayerAbility($p));

        return (int) round($total / $players->count());
    }

    private function getPlayerAbility(GamePlayer $player): int
    {
        $tech = $player->game_technical_ability ?? 50;
        $phys = $player->game_physical_ability ?? 50;

        return (int) round(($tech + $phys) / 2);
    }

    private function getPositionGroup(string $position): string
    {
        return match ($position) {
            'Goalkeeper' => 'Goalkeeper',
            'Centre-Back', 'Left-Back', 'Right-Back' => 'Defender',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield' => 'Midfielder',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker' => 'Forward',
            default => 'Midfielder',
        };
    }

    private function weightedRandom(array $weights): int
    {
        $total = array_sum($weights);
        $roll = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
