<?php

namespace App\Game\Promotions;

use App\Game\Contracts\PlayoffGenerator;
use App\Game\Contracts\PromotionRelegationRule;
use App\Game\Playoffs\ESP2PlayoffGenerator;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;

/**
 * Promotion and relegation rules for Spanish football.
 *
 * La Liga (ESP1) ↔ La Liga 2 (ESP2):
 * - Bottom 3 of La Liga are relegated
 * - Top 2 of La Liga 2 are directly promoted
 * - 3rd promoted team comes from La Liga 2 playoffs (3rd-6th)
 *
 * When a league is not played in-game, falls back to SimulatedSeason results.
 */
class SpanishPromotionRule implements PromotionRelegationRule
{
    public function __construct(
        private ESP2PlayoffGenerator $playoffGenerator,
    ) {}

    public function getTopDivision(): string
    {
        return 'ESP1';
    }

    public function getBottomDivision(): string
    {
        return 'ESP2';
    }

    public function getRelegatedPositions(): array
    {
        return [18, 19, 20];
    }

    public function getDirectPromotionPositions(): array
    {
        return [1, 2];
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
            $this->getBottomDivision(),
            $this->getDirectPromotionPositions()
        );

        if (!empty($promoted)) {
            // Real standings exist — check for playoff winner
            $playoffWinner = $this->getPlayoffWinner($game);
            if ($playoffWinner) {
                $promoted[] = $playoffWinner;
            } else {
                // No playoff played — promote 3rd place directly
                $thirdPlace = $this->getTeamsByPosition($game->id, $this->getBottomDivision(), [3]);
                $promoted = array_merge($promoted, $thirdPlace);
            }

            return $promoted;
        }

        // Fall back to simulated results — take top 3 (no playoffs in simulated leagues)
        return $this->getSimulatedTeamsByPosition(
            $game,
            $this->getBottomDivision(),
            [1, 2, 3]
        );
    }

    public function getRelegatedTeams(Game $game): array
    {
        // Try real standings first
        $relegated = $this->getTeamsByPosition(
            $game->id,
            $this->getTopDivision(),
            $this->getRelegatedPositions()
        );

        if (!empty($relegated)) {
            return $relegated;
        }

        // Fall back to simulated results
        return $this->getSimulatedTeamsByPosition(
            $game,
            $this->getTopDivision(),
            $this->getRelegatedPositions()
        );
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
     * Get teams from simulated season results at specific positions.
     *
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
            ->where('competition_id', $this->getBottomDivision())
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
