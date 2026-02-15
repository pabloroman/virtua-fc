<?php

namespace App\Game\Promotions;

use App\Game\Contracts\PlayoffGenerator;
use App\Game\Contracts\PromotionRelegationRule;
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
        // Try real standings first
        $promoted = $this->getTeamsByPosition(
            $game->id,
            $this->bottomDivision,
            $this->directPromotionPositions
        );

        if (!empty($promoted)) {
            // Real standings exist — check for playoff winner
            if ($this->playoffGenerator) {
                $playoffWinner = $this->getPlayoffWinner($game);
                if ($playoffWinner) {
                    $promoted[] = $playoffWinner;
                } else {
                    // No playoff played — promote next position directly
                    $nextPosition = max($this->directPromotionPositions) + 1;
                    $fallback = $this->getTeamsByPosition($game->id, $this->bottomDivision, [$nextPosition]);
                    $promoted = array_merge($promoted, $fallback);
                }
            }

            return $promoted;
        }

        // Fall back to simulated results — take top N (no playoffs in simulated leagues)
        $totalPromoted = count($this->directPromotionPositions) + ($this->playoffGenerator ? 1 : 0);
        $positions = range(1, $totalPromoted);

        return $this->getSimulatedTeamsByPosition($game, $this->bottomDivision, $positions);
    }

    public function getRelegatedTeams(Game $game): array
    {
        // Try real standings first
        $relegated = $this->getTeamsByPosition(
            $game->id,
            $this->topDivision,
            $this->relegatedPositions
        );

        if (!empty($relegated)) {
            return $relegated;
        }

        // Fall back to simulated results
        return $this->getSimulatedTeamsByPosition($game, $this->topDivision, $this->relegatedPositions);
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
