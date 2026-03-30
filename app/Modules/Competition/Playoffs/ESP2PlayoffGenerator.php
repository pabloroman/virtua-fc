<?php

namespace App\Modules\Competition\Playoffs;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Playoff generator for a two-round promotion playoff (semifinal + final).
 *
 * Format:
 * - Teams at qualifying positions enter playoffs
 * - Semifinal: highest vs lowest, 2nd-highest vs 2nd-lowest (two legs, lower seed hosts first leg)
 * - Final: Winners play (two legs)
 * - Winner is promoted alongside the directly promoted teams
 *
 * Originally built for Spanish Segunda División, but parameterized via constructor
 * to support any league with the same playoff format.
 */
class ESP2PlayoffGenerator implements PlayoffGenerator
{
    public function __construct(
        private readonly string $competitionId,
        private readonly array $qualifyingPositions = [3, 4, 5, 6],
        private readonly array $directPromotionPositions = [1, 2],
        private readonly int $triggerMatchday = 42,
    ) {}

    public function getCompetitionId(): string
    {
        return $this->competitionId;
    }

    public function getQualifyingPositions(): array
    {
        return $this->qualifyingPositions;
    }

    public function getDirectPromotionPositions(): array
    {
        return $this->directPromotionPositions;
    }

    public function getTriggerMatchday(): int
    {
        return $this->triggerMatchday;
    }

    public function getTotalRounds(): int
    {
        return 2; // Semifinal + Final
    }

    public function getRoundConfig(int $round, ?string $gameSeason = null): PlayoffRoundConfig
    {
        $competition = Competition::find($this->competitionId);
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds($this->competitionId, $competition->season, $gameSeason);

        foreach ($rounds as $config) {
            if ($config->round === $round) {
                return $config;
            }
        }

        throw new \RuntimeException("No knockout round config found for {$this->competitionId} round {$round}");
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
     * Semifinal matchups: highest seed vs lowest, 2nd vs 3rd.
     * Lower-seeded team hosts the first leg.
     *
     * Reserve teams whose parent club is in the top division are excluded
     * from playoffs. The next-placed eligible team slides into their spot.
     */
    private function generateSemifinalMatchups(Game $game): array
    {
        $requiredCount = count($this->qualifyingPositions);
        $filter = app(ReserveTeamFilter::class);

        // Fetch more standings than needed in case we need to skip reserve teams
        $maxPosition = max($this->qualifyingPositions) + $requiredCount;
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->whereBetween('position', [min($this->qualifyingPositions), $maxPosition])
            ->orderBy('position')
            ->get();

        // Filter out ineligible reserve teams
        $topDivisionTeamIds = $filter->getTopDivisionTeamIds($game, $this->competitionId);
        $eligible = $standings->filter(
            fn ($s) => !$filter->isBlockedReserveTeam($s->team_id, $topDivisionTeamIds)
        )->take($requiredCount);

        if ($eligible->count() < $requiredCount) {
            throw new \RuntimeException(
                "Not enough eligible teams for playoffs in {$this->competitionId}: " .
                "need {$requiredCount}, found {$eligible->count()}"
            );
        }

        $teamIds = $eligible->pluck('team_id')->values()->toArray();

        // Last vs first, second-to-last vs second
        return [
            [$teamIds[3], $teamIds[0]],
            [$teamIds[2], $teamIds[1]],
        ];
    }

    /**
     * Final matchup: Winners of the two semifinals.
     * The winner from the higher-seed tie hosts the second leg.
     */
    private function generateFinalMatchup(Game $game): array
    {
        $semifinalWinners = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', 1)
            ->where('completed', true)
            ->orderBy('bracket_position')
            ->pluck('winner_id')
            ->toArray();

        if (count($semifinalWinners) !== 2) {
            throw new \RuntimeException('Cannot generate final: semifinals not complete');
        }

        // Winner of lower-seed tie hosts first leg, winner of higher-seed tie hosts second leg
        return [[$semifinalWinners[1], $semifinalWinners[0]]];
    }

    public function isComplete(Game $game): bool
    {
        $finalTie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', $this->getTotalRounds())
            ->first();

        return $finalTie->completed ?? false;
    }
}
