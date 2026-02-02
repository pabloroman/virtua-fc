<?php

namespace App\Game\Promotions;

use App\Game\Contracts\PlayoffGenerator;
use App\Game\Contracts\PromotionRelegationRule;
use App\Game\Playoffs\ESP2PlayoffGenerator;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Promotion and relegation rules for Spanish football.
 *
 * La Liga (ESP1) ↔ La Liga 2 (ESP2):
 * - Bottom 3 of La Liga are relegated
 * - Top 2 of La Liga 2 are directly promoted
 * - 3rd promoted team comes from La Liga 2 playoffs (3rd-6th)
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
        // Direct promotions (1st and 2nd place)
        $promoted = $this->getTeamsByPosition(
            $game->id,
            $this->getBottomDivision(),
            $this->getDirectPromotionPositions()
        );

        // Playoff winner (3rd promoted team)
        $playoffWinner = $this->getPlayoffWinner($game);
        if ($playoffWinner) {
            $promoted[] = $playoffWinner;
        }

        return $promoted;
    }

    public function getRelegatedTeams(Game $game): array
    {
        return $this->getTeamsByPosition(
            $game->id,
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
