<?php

namespace App\Game\Playoffs;

use App\Game\Contracts\PlayoffGenerator;
use App\Game\DTO\PlayoffRoundConfig;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use Carbon\Carbon;

/**
 * Playoff generator for Spanish Segunda División (La Liga 2).
 *
 * Format:
 * - Teams finishing 3rd-6th qualify for playoffs
 * - Semifinal: 3rd vs 6th, 4th vs 5th (two legs, lower seed hosts first leg)
 * - Final: Winners play (two legs)
 * - Winner is promoted as the 3rd team to La Liga
 */
class ESP2PlayoffGenerator implements PlayoffGenerator
{
    public function getCompetitionId(): string
    {
        return 'ESP2';
    }

    public function getQualifyingPositions(): array
    {
        return [3, 4, 5, 6];
    }

    public function getDirectPromotionPositions(): array
    {
        return [1, 2];
    }

    public function getTriggerMatchday(): int
    {
        return 42;
    }

    public function getTotalRounds(): int
    {
        return 2; // Semifinal + Final
    }

    public function getRoundConfig(int $round, int $seasonYear): PlayoffRoundConfig
    {
        // Playoffs happen in June of the year after the season starts
        // (e.g., 2024-25 season playoffs are in June 2025)
        $playoffYear = $seasonYear + 1;

        return match ($round) {
            1 => new PlayoffRoundConfig(
                round: 1,
                name: 'Playoff Semifinal',
                twoLegged: true,
                firstLegDate: Carbon::parse("first Sunday of June {$playoffYear}"),
                secondLegDate: Carbon::parse("first Sunday of June {$playoffYear}")->addDays(7),
            ),
            2 => new PlayoffRoundConfig(
                round: 2,
                name: 'Playoff Final',
                twoLegged: true,
                firstLegDate: Carbon::parse("third Sunday of June {$playoffYear}"),
                secondLegDate: Carbon::parse("third Sunday of June {$playoffYear}")->addDays(7),
            ),
            default => throw new \InvalidArgumentException("Invalid playoff round: {$round}"),
        };
    }

    public function generateMatchups(Game $game, int $round): array
    {
        return match ($round) {
            1 => $this->generateSemifinalMatchups($game),
            2 => $this->generateFinalMatchup($game),
            default => throw new \InvalidArgumentException("Invalid playoff round: {$round}"),
        };
    }

    /**
     * Semifinal matchups: 3rd vs 6th, 4th vs 5th
     * Lower-seeded team hosts the first leg.
     */
    private function generateSemifinalMatchups(Game $game): array
    {
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $this->getCompetitionId())
            ->whereIn('position', $this->getQualifyingPositions())
            ->orderBy('position')
            ->pluck('team_id', 'position')
            ->toArray();

        return [
            [$standings[6], $standings[3]], // 6th hosts first leg vs 3rd
            [$standings[5], $standings[4]], // 5th hosts first leg vs 4th
        ];
    }

    /**
     * Final matchup: Winners of the two semifinals.
     * The winner from the 3v6 tie hosts the second leg (higher seed advantage).
     */
    private function generateFinalMatchup(Game $game): array
    {
        $semifinalWinners = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->getCompetitionId())
            ->where('round_number', 1)
            ->where('completed', true)
            ->orderBy('id')
            ->pluck('winner_id')
            ->toArray();

        if (count($semifinalWinners) !== 2) {
            throw new \RuntimeException('Cannot generate final: semifinals not complete');
        }

        // Winner of 4v5 hosts first leg, winner of 3v6 hosts second leg
        return [[$semifinalWinners[1], $semifinalWinners[0]]];
    }

    public function isComplete(Game $game): bool
    {
        $finalTie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->getCompetitionId())
            ->where('round_number', $this->getTotalRounds())
            ->first();

        return $finalTie?->completed ?? false;
    }
}
