<?php

namespace App\Game\Services;

use App\Game\DTO\PlayoffRoundConfig;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use Carbon\Carbon;

/**
 * Generates knockout bracket matchups for Swiss format competitions.
 *
 * 2024+ UEFA format:
 * - Knockout Playoff (round 1): Positions 9-24, seeded brackets
 * - Round of 16 (round 2): Top 8 + playoff winners, seeded
 * - Quarter-finals (round 3): Open draw
 * - Semi-finals (round 4): Open draw
 * - Final (round 5): Single match
 */
class SwissKnockoutGenerator
{
    public const ROUND_KNOCKOUT_PLAYOFF = 1;
    public const ROUND_OF_16 = 2;
    public const ROUND_QUARTER_FINALS = 3;
    public const ROUND_SEMI_FINALS = 4;
    public const ROUND_FINAL = 5;

    /**
     * Seeding brackets for the knockout playoff.
     * Each pair is [higher_seed_positions, lower_seed_positions].
     * Within each bracket, specific matchups are drawn randomly.
     */
    private const PLAYOFF_BRACKETS = [
        [[9, 10], [23, 24]],
        [[11, 12], [21, 22]],
        [[13, 14], [19, 20]],
        [[15, 16], [17, 18]],
    ];

    /**
     * Seeding brackets for the Round of 16.
     * Top 8 teams are matched against playoff winners from specific brackets.
     * [top_seed_positions, bracket_index (from playoffs)]
     */
    private const R16_BRACKETS = [
        [[1, 2], [3, 4]],   // 1/2 vs winners from bracket 4 (15/16 vs 17/18)
        [[3, 4], [2, 3]],   // 3/4 vs winners from bracket 3 (13/14 vs 19/20)
        [[5, 6], [1, 2]],   // 5/6 vs winners from bracket 2 (11/12 vs 21/22)
        [[7, 8], [0, 1]],   // 7/8 vs winners from bracket 1 (9/10 vs 23/24)
    ];

    public function getRoundConfig(int $round, int $seasonYear): PlayoffRoundConfig
    {
        $playoffYear = $seasonYear + 1;

        return match ($round) {
            self::ROUND_KNOCKOUT_PLAYOFF => new PlayoffRoundConfig(
                round: $round,
                name: 'Playoff de eliminación',
                twoLegged: true,
                firstLegDate: Carbon::parse("{$playoffYear}-02-11"),
                secondLegDate: Carbon::parse("{$playoffYear}-02-18"),
            ),
            self::ROUND_OF_16 => new PlayoffRoundConfig(
                round: $round,
                name: 'Octavos de final',
                twoLegged: true,
                firstLegDate: Carbon::parse("{$playoffYear}-03-04"),
                secondLegDate: Carbon::parse("{$playoffYear}-03-11"),
            ),
            self::ROUND_QUARTER_FINALS => new PlayoffRoundConfig(
                round: $round,
                name: 'Cuartos de final',
                twoLegged: true,
                firstLegDate: Carbon::parse("{$playoffYear}-04-08"),
                secondLegDate: Carbon::parse("{$playoffYear}-04-15"),
            ),
            self::ROUND_SEMI_FINALS => new PlayoffRoundConfig(
                round: $round,
                name: 'Semifinal',
                twoLegged: true,
                firstLegDate: Carbon::parse("{$playoffYear}-04-29"),
                secondLegDate: Carbon::parse("{$playoffYear}-05-06"),
            ),
            self::ROUND_FINAL => new PlayoffRoundConfig(
                round: $round,
                name: 'Final',
                twoLegged: false,
                firstLegDate: Carbon::parse("{$playoffYear}-05-31"),
            ),
            default => throw new \InvalidArgumentException("Invalid knockout round: {$round}"),
        };
    }

    /**
     * Generate matchups for a knockout round.
     *
     * @return array<array{0: string, 1: string}> Array of [homeTeamId, awayTeamId] pairs
     */
    public function generateMatchups(Game $game, string $competitionId, int $round): array
    {
        return match ($round) {
            self::ROUND_KNOCKOUT_PLAYOFF => $this->generatePlayoffMatchups($game, $competitionId),
            self::ROUND_OF_16 => $this->generateR16Matchups($game, $competitionId),
            self::ROUND_QUARTER_FINALS,
            self::ROUND_SEMI_FINALS => $this->generateOpenDraw($game, $competitionId, $round),
            self::ROUND_FINAL => $this->generateFinalMatchup($game, $competitionId),
            default => throw new \InvalidArgumentException("Invalid knockout round: {$round}"),
        };
    }

    /**
     * Knockout Playoff: Positions 9-24, seeded brackets.
     * Higher-seeded team hosts the second leg.
     */
    private function generatePlayoffMatchups(Game $game, string $competitionId): array
    {
        $standings = $this->getLeaguePhaseStandings($game->id, $competitionId);
        $matchups = [];

        foreach (self::PLAYOFF_BRACKETS as $bracket) {
            [$higherPositions, $lowerPositions] = $bracket;

            // Pick one team from each side of the bracket
            $higherTeams = collect($higherPositions)->map(fn ($pos) => $standings[$pos] ?? null)->filter()->shuffle();
            $lowerTeams = collect($lowerPositions)->map(fn ($pos) => $standings[$pos] ?? null)->filter()->shuffle();

            for ($i = 0; $i < min($higherTeams->count(), $lowerTeams->count()); $i++) {
                // Lower seed hosts first leg, higher seed hosts second leg
                $matchups[] = [$lowerTeams[$i], $higherTeams[$i]];
            }
        }

        return $matchups;
    }

    /**
     * Round of 16: Top 8 seeded + 8 playoff winners, seeded brackets.
     * Top 8 team hosts second leg.
     */
    private function generateR16Matchups(Game $game, string $competitionId): array
    {
        $standings = $this->getLeaguePhaseStandings($game->id, $competitionId);

        // Get playoff winners grouped by their original bracket
        $playoffTies = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', self::ROUND_KNOCKOUT_PLAYOFF)
            ->where('completed', true)
            ->get();

        // Map playoff winners back to their brackets
        $bracketWinners = [];
        foreach ($playoffTies as $tie) {
            $bracketIndex = $this->findPlayoffBracket($tie, $standings);
            $bracketWinners[$bracketIndex][] = $tie->winner_id;
        }

        $matchups = [];

        foreach (self::R16_BRACKETS as [$topPositions, $bracketIndices]) {
            $topTeams = collect($topPositions)
                ->map(fn ($pos) => $standings[$pos] ?? null)
                ->filter()
                ->shuffle();

            $opponents = collect($bracketIndices)
                ->flatMap(fn ($idx) => $bracketWinners[$idx] ?? [])
                ->shuffle();

            for ($i = 0; $i < min($topTeams->count(), $opponents->count()); $i++) {
                // Playoff winner hosts first leg, top seed hosts second leg
                $matchups[] = [$opponents[$i], $topTeams[$i]];
            }
        }

        return $matchups;
    }

    /**
     * Quarter-finals and Semi-finals: Open draw.
     */
    private function generateOpenDraw(Game $game, string $competitionId, int $round): array
    {
        $previousRound = $round - 1;

        $winners = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $previousRound)
            ->where('completed', true)
            ->pluck('winner_id')
            ->shuffle();

        $matchups = [];

        for ($i = 0; $i < $winners->count(); $i += 2) {
            if ($i + 1 < $winners->count()) {
                $matchups[] = [$winners[$i], $winners[$i + 1]];
            }
        }

        return $matchups;
    }

    /**
     * Final: Single match between semi-final winners.
     */
    private function generateFinalMatchup(Game $game, string $competitionId): array
    {
        $winners = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', self::ROUND_SEMI_FINALS)
            ->where('completed', true)
            ->pluck('winner_id')
            ->shuffle();

        if ($winners->count() !== 2) {
            throw new \RuntimeException('Cannot generate final: semi-finals not complete');
        }

        return [[$winners[0], $winners[1]]];
    }

    /**
     * Get league phase standings indexed by position.
     *
     * @return array<int, string> position => team_id
     */
    private function getLeaguePhaseStandings(string $gameId, string $competitionId): array
    {
        return GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->orderBy('position')
            ->pluck('team_id', 'position')
            ->toArray();
    }

    /**
     * Determine which playoff bracket a tie belongs to, based on the teams' league positions.
     */
    private function findPlayoffBracket(CupTie $tie, array $standings): int
    {
        $positions = array_flip($standings);
        $homePos = $positions[$tie->home_team_id] ?? 99;
        $awayPos = $positions[$tie->away_team_id] ?? 99;
        $higherSeedPos = min($homePos, $awayPos);

        foreach (self::PLAYOFF_BRACKETS as $index => $bracket) {
            [$higherPositions] = $bracket;
            if (in_array($higherSeedPos, $higherPositions)) {
                return $index;
            }
        }

        return 0;
    }
}
