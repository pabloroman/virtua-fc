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

/**
 * A config-driven promotion/relegation rule.
 *
 * Takes its parameters (divisions, positions, playoff generator) from
 * config/countries.php rather than hardcoding them. The actual promotion
 * and relegation logic is generic and works for any two-division pair.
 */
class ConfigDrivenPromotionRule implements PromotionRelegationRule
{
    public function __construct(
        private string $topDivision,
        private string $bottomDivision,
        private array $relegatedPositions,
        private array $directPromotionPositions,
        private ?PlayoffGenerator $playoffGenerator = null,
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
        $expectedCount = count($this->relegatedPositions);

        // Try real standings first
        $promoted = $this->getEligibleDirectPromotions($game);

        if (!empty($promoted)) {
            // Real standings exist — check for playoff winner
            if ($this->playoffGenerator) {
                $playoffWinner = $this->getPlayoffWinner($game);
                if ($playoffWinner) {
                    $promoted[] = $playoffWinner;
                } else {
                    // No playoff played — promote next eligible position directly
                    $promoted = array_merge($promoted, $this->getNextEligibleTeam($game, $promoted));
                }
            }

            $this->validateTeamCount($promoted, $expectedCount, 'promoted', $this->bottomDivision, $game);

            return $promoted;
        }

        // Fall back to simulated results — take top N (no playoffs in simulated leagues)
        $totalPromoted = count($this->directPromotionPositions) + ($this->playoffGenerator ? 1 : 0);

        $promoted = $this->getEligibleSimulatedPromotions($game, $totalPromoted);

        $this->validateTeamCount($promoted, $expectedCount, 'promoted', $this->bottomDivision, $game);

        return $promoted;
    }

    public function getRelegatedTeams(Game $game): array
    {
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
     * Validate that the expected number of teams were found for promotion/relegation.
     *
     * @param  array  $teams  The teams found
     * @param  int  $expectedCount  How many were expected
     * @param  string  $type  'promoted' or 'relegated'
     * @param  string  $competitionId  The competition being queried
     */
    private function validateTeamCount(array $teams, int $expectedCount, string $type, string $competitionId, ?Game $game = null): void
    {
        if (count($teams) !== $expectedCount) {
            $teamIds = array_column($teams, 'teamId');
            $season = $game?->season ?? 'unknown';

            $standingsCount = $game ? GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)->count() : 'N/A';

            $simulatedExists = $game ? SimulatedSeason::where('game_id', $game->id)
                ->where('season', $season)
                ->where('competition_id', $competitionId)
                ->exists() : false;

            throw new \RuntimeException(
                "Promotion/relegation imbalance: expected {$expectedCount} {$type} teams " .
                "from {$competitionId}, got " . count($teams) . ". " .
                "Team IDs: " . json_encode($teamIds) . ". " .
                "Divisions: {$this->topDivision} <-> {$this->bottomDivision}. " .
                "Season: {$season}. Standings rows: {$standingsCount}. " .
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
    private function getEligibleDirectPromotions(Game $game): array
    {
        $filter = app(ReserveTeamFilter::class);
        $topDivisionTeamIds = $filter->getTopDivisionTeamIds($game, $this->bottomDivision);

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

        $eligible = $standings->filter(
            fn ($s) => !$filter->isBlockedReserveTeam($s->team_id, $topDivisionTeamIds)
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
    private function getNextEligibleTeam(Game $game, array $alreadyPromoted): array
    {
        $filter = app(ReserveTeamFilter::class);
        $topDivisionTeamIds = $filter->getTopDivisionTeamIds($game, $this->bottomDivision);
        $promotedIds = array_column($alreadyPromoted, 'teamId');

        $nextPosition = max($this->directPromotionPositions) + 1;
        $maxPosition = $nextPosition + 4; // Check a few positions ahead

        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $this->bottomDivision)
            ->whereBetween('position', [$nextPosition, $maxPosition])
            ->with('team')
            ->orderBy('position')
            ->get();

        $eligible = $standings
            ->filter(fn ($s) => !in_array($s->team_id, $promotedIds))
            ->filter(fn ($s) => !$filter->isBlockedReserveTeam($s->team_id, $topDivisionTeamIds))
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
    private function getEligibleSimulatedPromotions(Game $game, int $totalNeeded): array
    {
        $filter = app(ReserveTeamFilter::class);
        $topDivisionTeamIds = $filter->getTopDivisionTeamIds($game, $this->bottomDivision);

        if ($topDivisionTeamIds->isEmpty()) {
            return $this->getSimulatedTeamsByPosition($game, $this->bottomDivision, range(1, $totalNeeded));
        }

        // Fetch extra positions to account for skipped reserve teams
        $positions = range(1, $totalNeeded + 3);
        $candidates = $this->getSimulatedTeamsByPosition($game, $this->bottomDivision, $positions);

        $eligible = [];
        foreach ($candidates as $candidate) {
            if (!$filter->isBlockedReserveTeam($candidate['teamId'], $topDivisionTeamIds)) {
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
}
