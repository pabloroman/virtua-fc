<?php

namespace App\Modules\Transfer\Services;

use App\Models\Game;
use App\Models\GameNotification;
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
    /** Max departures per AI team per window */
    private const MAX_DEPARTURES_SUMMER = 3;
    private const MAX_DEPARTURES_WINTER = 1;

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

        // Phase 1: Sign free agents (players with null team_id)
        $freeAgentSignings = $this->processFreeAgentSignings($game);

        // Phase 2: AI-to-AI transfers
        $transfers = $this->processAITransfers($game, $isSummer);

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
     * Delete unsigned free agents. Called during season end pipeline.
     */
    public function cleanupUnsignedFreeAgents(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->whereNull('team_id')
            ->delete();
    }

    /**
     * Phase 1: Match free agents to AI teams that need players at their position.
     */
    private function processFreeAgentSignings(Game $game): Collection
    {
        $freeAgents = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->whereNull('team_id')
            ->get();

        if ($freeAgents->isEmpty()) {
            return collect();
        }

        // Get AI team rosters grouped by team
        $teamRosters = GamePlayer::where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $game->team_id)
            ->get()
            ->groupBy('team_id');

        $signings = collect();

        // Shuffle free agents so it's not deterministic
        $freeAgents = $freeAgents->shuffle();

        foreach ($freeAgents as $freeAgent) {
            $bestTeam = $this->findBestTeamForFreeAgent($freeAgent, $teamRosters, $game);

            if (!$bestTeam) {
                continue;
            }

            $teamId = $bestTeam['teamId'];
            $teamName = $bestTeam['teamName'];

            // Sign the free agent
            $seasonYear = (int) $game->season;
            $contractYears = $freeAgent->age >= 32 ? 1 : mt_rand(1, 2);
            $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

            $minimumWage = $this->contractService->getMinimumWageForTeam(Team::find($teamId));
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
                'toTeamName' => $teamName,
                'age' => $freeAgent->age,
                'formattedFee' => __('transfers.free_transfer'),
                'type' => 'free_agent',
            ]);

            // Update roster cache
            if (!$teamRosters->has($teamId)) {
                $teamRosters[$teamId] = collect();
            }
            $teamRosters[$teamId]->push($freeAgent);
        }

        return $signings;
    }

    /**
     * Phase 2: Process AI-to-AI transfers across all divisions.
     */
    private function processAITransfers(Game $game, bool $isSummer): Collection
    {
        $maxDepartures = $isSummer ? self::MAX_DEPARTURES_SUMMER : self::MAX_DEPARTURES_WINTER;
        $weights = $isSummer ? self::DEPARTURE_WEIGHTS_SUMMER : self::DEPARTURE_WEIGHTS_WINTER;

        // Load all AI players grouped by team
        $teamRosters = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $game->team_id)
            ->whereNull('retiring_at_season')
            ->get()
            ->groupBy('team_id');

        // Pre-calculate team averages
        $teamAverages = $teamRosters->map(fn ($players) => $this->calculateTeamAverage($players));

        // Get foreign team names for narrative
        $foreignTeams = Team::where('country', '!=', 'ES')
            ->where('type', 'club')
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

            // Score expendability for each player
            $candidates = $this->scoreExpendability($players, $teamAvg);

            // Pick top candidates
            $departures = $candidates
                ->sortByDesc('score')
                ->take($numDepartures);

            foreach ($departures as $candidate) {
                /** @var GamePlayer $player */
                $player = $candidate['player'];

                // Skip if already departed (moved to another team this window)
                if ($departingPlayerIds->contains($player->id)) {
                    continue;
                }

                $fromTeamName = $player->team?->name ?? 'Unknown';
                $fromTeamId = $player->team_id;
                $fee = $player->market_value_cents;

                // Decide: foreign departure or domestic transfer
                if (mt_rand(1, 100) <= self::FOREIGN_DEPARTURE_CHANCE) {
                    // Foreign departure — player leaves the game
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
                    // Domestic transfer — find a buying team
                    $buyer = $this->findBuyerTeam($player, $teamRosters, $teamAverages, $game, $departingPlayerIds);

                    if ($buyer) {
                        $buyerTeamId = $buyer['teamId'];
                        $buyerTeamName = $buyer['teamName'];

                        $seasonYear = (int) $game->season;
                        $contractYears = mt_rand(2, 3);
                        $newContractEnd = Carbon::createFromDate($seasonYear + $contractYears + 1, 6, 30);

                        $minimumWage = $this->contractService->getMinimumWageForTeam(Team::find($buyerTeamId));
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

                        // Update roster cache
                        if ($teamRosters->has($buyerTeamId)) {
                            $teamRosters[$buyerTeamId]->push($player);
                        }
                    } else {
                        // No buyer found — fall back to foreign departure
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
    private function findBestTeamForFreeAgent(GamePlayer $freeAgent, Collection $teamRosters, Game $game): ?array
    {
        $positionGroup = $this->getPositionGroup($freeAgent->position);
        $playerAbility = $this->getPlayerAbility($freeAgent);
        $bestScore = -1;
        $bestTeam = null;

        foreach ($teamRosters as $teamId => $players) {
            if ($players->count() >= 26) {
                continue;
            }

            $teamAvg = $this->calculateTeamAverage($players);

            // Player should be within reasonable range of team quality
            if (abs($playerAbility - $teamAvg) > 20) {
                continue;
            }

            // Score based on positional need
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
                $bestTeam = [
                    'teamId' => $teamId,
                    'teamName' => $players->first()?->team?->name ?? 'Unknown',
                ];
            }
        }

        // Only sign if there's meaningful need (score > 0)
        return $bestScore > 0 ? $bestTeam : null;
    }

    /**
     * Find a suitable buying team for a domestic transfer.
     */
    private function findBuyerTeam(
        GamePlayer $player,
        Collection $teamRosters,
        Collection $teamAverages,
        Game $game,
        Collection $departingPlayerIds,
    ): ?array {
        $positionGroup = $this->getPositionGroup($player->position);
        $playerAbility = $this->getPlayerAbility($player);
        $candidates = [];

        foreach ($teamRosters as $teamId => $players) {
            // Can't buy from yourself or the user's team
            if ($teamId === $player->team_id || $teamId === $game->team_id) {
                continue;
            }

            // Squad size check (accounting for players already departing)
            $effectiveSize = $players->count() - $departingPlayerIds->intersect($players->pluck('id'))->count();
            if ($effectiveSize >= 26) {
                continue;
            }

            $teamAvg = $teamAverages[$teamId] ?? 55;

            // Player ability should be within ±15 of team average
            if (abs($playerAbility - $teamAvg) > 15) {
                continue;
            }

            // Score by positional need
            $groupCount = $players->filter(
                fn ($p) => $this->getPositionGroup($p->position) === $positionGroup
                    && !$departingPlayerIds->contains($p->id)
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
                    'teamName' => $players->first()?->team?->name ?? 'Unknown',
                    'score' => $score,
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Pick the best candidate with some randomness
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

                // Must meet minimum threshold to be a candidate
                if ($score < 3) {
                    return null;
                }

                return ['player' => $player, 'score' => $score];
            })
            ->filter();
    }

    /**
     * Calculate team average ability.
     */
    private function calculateTeamAverage(Collection $players): int
    {
        if ($players->isEmpty()) {
            return 55;
        }

        $total = $players->sum(fn (GamePlayer $p) => $this->getPlayerAbility($p));

        return (int) round($total / $players->count());
    }

    /**
     * Get a player's average ability.
     */
    private function getPlayerAbility(GamePlayer $player): int
    {
        $tech = $player->game_technical_ability ?? 50;
        $phys = $player->game_physical_ability ?? 50;

        return (int) round(($tech + $phys) / 2);
    }

    /**
     * Map a position to its group.
     */
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

    /**
     * Pick a number from a weighted probability distribution.
     */
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
