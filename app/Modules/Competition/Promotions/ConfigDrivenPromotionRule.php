<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\Contracts\PromotionRelegationRule;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * A config-driven promotion/relegation rule.
 *
 * Takes its parameters (divisions, positions, playoff generator) from
 * config/countries.php rather than hardcoding them. The actual promotion
 * and relegation logic is generic and works for any two-division pair.
 */
class ConfigDrivenPromotionRule implements PromotionRelegationRule
{
    /**
     * Extra positions to check beyond the required count when skipping
     * blocked reserve teams. Covers the unlikely case of multiple
     * reserve teams clustered at the top of the standings.
     */
    private const RESERVE_TEAM_BUFFER = 3;

    public function __construct(
        private string $topDivision,
        private string $bottomDivision,
        private array $relegatedPositions,
        private array $directPromotionPositions,
        private ?PlayoffGenerator $playoffGenerator = null,
        private ?ReserveTeamFilter $reserveTeamFilter = null,
    ) {}

    public function getTopDivision(): string
    {
        return $this->topDivision;
    }

    public function getBottomDivision(): string
    {
        return $this->bottomDivision;
    }

    public function getRelegatedPositions(): array
    {
        return $this->relegatedPositions;
    }

    public function getDirectPromotionPositions(): array
    {
        return $this->directPromotionPositions;
    }

    public function getPlayoffGenerator(): ?PlayoffGenerator
    {
        return $this->playoffGenerator;
    }

    public function getPromotedTeams(Game $game): array
    {
        if (!$this->hasDataSource($game, $this->bottomDivision)) {
            return [];
        }

        $expectedCount = count($this->relegatedPositions);
        $filter = $this->getFilter();
        $topDivisionTeamIds = $filter->getTopDivisionTeamIds($game, $this->bottomDivision);

        // Try real standings first
        $promoted = $this->getEligibleDirectPromotions($game, $filter, $topDivisionTeamIds);

        if (!empty($promoted)) {
            // Real standings exist — check for playoff winner
            if ($this->playoffGenerator) {
                $playoffWinner = $this->getPlayoffWinner($game);
                if ($playoffWinner) {
                    $promoted[] = $playoffWinner;
                } else {
                    // No playoff played — promote next eligible position directly
                    $promoted = array_merge($promoted, $this->getNextEligibleTeam($game, $promoted, $filter, $topDivisionTeamIds));
                }
            }

            $this->validateTeamCount($promoted, $expectedCount, 'promoted', $this->bottomDivision, $game);

            return $promoted;
        }

        // Fall back to simulated results — take top N (no playoffs in simulated leagues)
        $totalPromoted = count($this->directPromotionPositions) + ($this->playoffGenerator ? 1 : 0);

        $promoted = $this->getEligibleSimulatedPromotions($game, $totalPromoted, $filter, $topDivisionTeamIds);

        $this->validateTeamCount($promoted, $expectedCount, 'promoted', $this->bottomDivision, $game);

        return $promoted;
    }

    public function getRelegatedTeams(Game $game): array
    {
        if (!$this->hasDataSource($game, $this->topDivision)) {
            return [];
        }

        $expectedCount = count($this->relegatedPositions);

        // Try real standings first
        $relegated = $this->getTeamsByPosition(
            $game->id,
            $this->topDivision,
            $this->relegatedPositions
        );

        if (!empty($relegated)) {
            $this->validateTeamCount($relegated, $expectedCount, 'relegated', $this->topDivision, $game);

            return $relegated;
        }

        // Fall back to simulated results
        $relegated = $this->getSimulatedTeamsByPosition($game, $this->topDivision, $this->relegatedPositions);

        $this->validateTeamCount($relegated, $expectedCount, 'relegated', $this->topDivision, $game);

        return $relegated;
    }

    /**
     * Check whether any data source (real standings or simulated season) exists
     * for the given competition. Returns false when neither exists, which happens
     * when the season-end view is rendered before the closing pipeline has run.
     */
    private function hasDataSource(Game $game, string $competitionId): bool
    {
        $hasStandings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->exists();

        if ($hasStandings) {
            return true;
        }

        return SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competitionId)
            ->exists();
    }

    /**
     * Validate that the expected number of teams were found for promotion/relegation.
     */
    private function validateTeamCount(array $teams, int $expectedCount, string $type, string $competitionId, Game $game): void
    {
        if (count($teams) !== $expectedCount) {
            $teamIds = array_column($teams, 'teamId');

            $standingsCount = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)->count();

            $simulatedExists = SimulatedSeason::where('game_id', $game->id)
                ->where('season', $game->season)
                ->where('competition_id', $competitionId)
                ->exists();

            throw new \RuntimeException(
                "Promotion/relegation imbalance: expected {$expectedCount} {$type} teams " .
                "from {$competitionId}, got " . count($teams) . ". " .
                "Team IDs: " . json_encode($teamIds) . ". " .
                "Divisions: {$this->topDivision} <-> {$this->bottomDivision}. " .
                "Season: {$game->season}. Standings rows: {$standingsCount}. " .
                "Simulated data exists: " . ($simulatedExists ? 'yes' : 'no') . "."
            );
        }
    }

    /**
     * Get eligible teams for direct promotion, skipping blocked reserve teams.
     *
     * If a reserve team (e.g. Real Sociedad B) finishes in a direct promotion
     * spot but their parent (Real Sociedad) is in the top division, the next
     * eligible team below slides into their promotion spot.
     *
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function getEligibleDirectPromotions(Game $game, ReserveTeamFilter $filter, Collection $topDivisionTeamIds): array
    {
        if ($topDivisionTeamIds->isEmpty()) {
            return $this->getTeamsByPosition($game->id, $this->bottomDivision, $this->directPromotionPositions);
        }

        $requiredCount = count($this->directPromotionPositions);
        $maxPosition = max($this->directPromotionPositions) + $requiredCount;

        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $this->bottomDivision)
            ->whereBetween('position', [min($this->directPromotionPositions), $maxPosition])
            ->with('team')
            ->orderBy('position')
            ->get();

        if ($standings->isEmpty()) {
            return [];
        }

        $teamIds = $standings->pluck('team_id')->all();
        $parentMap = $filter->loadParentTeamIds($teamIds);

        $eligible = $standings->filter(
            fn ($s) => !$filter->isBlockedReserveTeam($s->team_id, $topDivisionTeamIds, $parentMap)
        )->take($requiredCount);

        return $eligible->map(fn ($standing) => [
            'teamId' => $standing->team_id,
            'position' => $standing->position,
            'teamName' => $standing->team->name ?? 'Unknown',
        ])->values()->toArray();
    }

    /**
     * Get the next eligible team after the already-promoted teams (for non-playoff fallback).
     *
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function getNextEligibleTeam(Game $game, array $alreadyPromoted, ReserveTeamFilter $filter, Collection $topDivisionTeamIds): array
    {
        $promotedIds = array_column($alreadyPromoted, 'teamId');

        $nextPosition = max($this->directPromotionPositions) + 1;
        $maxPosition = $nextPosition + self::RESERVE_TEAM_BUFFER + 1;

        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $this->bottomDivision)
            ->whereBetween('position', [$nextPosition, $maxPosition])
            ->with('team')
            ->orderBy('position')
            ->get();

        $teamIds = $standings->pluck('team_id')->all();
        $parentMap = $filter->loadParentTeamIds($teamIds);

        $eligible = $standings
            ->filter(fn ($s) => !in_array($s->team_id, $promotedIds))
            ->filter(fn ($s) => !$filter->isBlockedReserveTeam($s->team_id, $topDivisionTeamIds, $parentMap))
            ->first();

        if (!$eligible) {
            return [];
        }

        return [[
            'teamId' => $eligible->team_id,
            'position' => $eligible->position,
            'teamName' => $eligible->team->name ?? 'Unknown',
        ]];
    }

    /**
     * Get eligible simulated promotions, skipping blocked reserve teams.
     *
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function getEligibleSimulatedPromotions(Game $game, int $totalNeeded, ReserveTeamFilter $filter, Collection $topDivisionTeamIds): array
    {
        if ($topDivisionTeamIds->isEmpty()) {
            return $this->getSimulatedTeamsByPosition($game, $this->bottomDivision, range(1, $totalNeeded));
        }

        // Fetch extra positions to account for skipped reserve teams
        $positions = range(1, $totalNeeded + self::RESERVE_TEAM_BUFFER);
        $candidates = $this->getSimulatedTeamsByPosition($game, $this->bottomDivision, $positions);

        $candidateTeamIds = array_column($candidates, 'teamId');
        $parentMap = $filter->loadParentTeamIds($candidateTeamIds);

        $eligible = [];
        foreach ($candidates as $candidate) {
            if (!$filter->isBlockedReserveTeam($candidate['teamId'], $topDivisionTeamIds, $parentMap)) {
                $eligible[] = $candidate;
            }
            if (count($eligible) >= $totalNeeded) {
                break;
            }
        }

        return $eligible;
    }

    /**
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function getTeamsByPosition(string $gameId, string $competitionId, array $positions): array
    {
        return GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereIn('position', $positions)
            ->with('team')
            ->orderBy('position')
            ->get()
            ->map(fn ($standing) => [
                'teamId' => $standing->team_id,
                'position' => $standing->position,
                'teamName' => $standing->team->name ?? 'Unknown',
            ])
            ->toArray();
    }

    /**
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function getSimulatedTeamsByPosition(Game $game, string $competitionId, array $positions): array
    {
        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competitionId)
            ->first();

        if (!$simulated) {
            return [];
        }

        $teamIds = $simulated->getTeamIdsAtPositions($positions);
        $teams = Team::whereIn('id', $teamIds)->get()->keyBy('id');

        $results = [];
        foreach ($positions as $position) {
            $index = $position - 1;
            $teamId = $simulated->results[$index] ?? null;

            if ($teamId && $teams->has($teamId)) {
                $results[] = [
                    'teamId' => $teamId,
                    'position' => $position,
                    'teamName' => $teams[$teamId]->name,
                ];
            }
        }

        return $results;
    }

    /**
     * @return array{teamId: string, position: string, teamName: string}|null
     */
    private function getPlayoffWinner(Game $game): ?array
    {
        $finalRound = $this->playoffGenerator->getTotalRounds();

        $finalTie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->bottomDivision)
            ->where('round_number', $finalRound)
            ->where('completed', true)
            ->with('winner')
            ->first();

        if (!$finalTie?->winner) {
            return null;
        }

        return [
            'teamId' => $finalTie->winner_id,
            'position' => 'Playoff',
            'teamName' => $finalTie->winner->name,
        ];
    }

    private function getFilter(): ReserveTeamFilter
    {
        return $this->reserveTeamFilter ?? app(ReserveTeamFilter::class);
    }
}
