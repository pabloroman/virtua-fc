<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use Illuminate\Support\Collection;

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
     * [top_seed_positions, playoff_bracket_index]
     * Each playoff bracket (0-3) maps to exactly one R16 bracket.
     */
    private const R16_BRACKETS = [
        [[1, 2], 3],   // 1/2 vs winners from bracket 3 (15/16 vs 17/18)
        [[3, 4], 2],   // 3/4 vs winners from bracket 2 (13/14 vs 19/20)
        [[5, 6], 1],   // 5/6 vs winners from bracket 1 (11/12 vs 21/22)
        [[7, 8], 0],   // 7/8 vs winners from bracket 0 (9/10 vs 23/24)
    ];

    public function getRoundConfig(int $round, string $competitionId, ?string $gameSeason = null): PlayoffRoundConfig
    {
        $competition = Competition::find($competitionId);
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds($competitionId, $competition->season, $gameSeason);

        foreach ($rounds as $config) {
            if ($config->round === $round) {
                return $config;
            }
        }

        throw new \RuntimeException("No knockout round config found for {$competitionId} round {$round}");
    }

    /**
     * Generate matchups for a knockout round.
     *
     * Tuple shape: [homeTeamId, awayTeamId, bracketPosition]. `bracketPosition`
     * is the playoff bracket index (0-3) for round 1, and null for later rounds.
     * Persisting it on the playoff CupTie lets R16 group winners by their
     * original bracket without re-deriving from (potentially shifted) standings.
     *
     * @return array<array{0: string, 1: string, 2: ?int}>
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

        foreach (self::PLAYOFF_BRACKETS as $bracketIndex => $bracket) {
            [$higherPositions, $lowerPositions] = $bracket;

            // Pick one team from each side of the bracket
            $higherTeams = collect($higherPositions)->map(fn ($pos) => $standings[$pos] ?? null)->filter()->shuffle();
            $lowerTeams = collect($lowerPositions)->map(fn ($pos) => $standings[$pos] ?? null)->filter()->shuffle();

            for ($i = 0; $i < min($higherTeams->count(), $lowerTeams->count()); $i++) {
                // Lower seed hosts first leg, higher seed hosts second leg.
                // Stamp the bracket index so R16 can group winners without
                // re-deriving from current standings (which may have shifted
                // due to non-deterministic ordering of tied teams).
                $matchups[] = [$lowerTeams[$i], $higherTeams[$i], $bracketIndex];
            }
        }

        if (count($matchups) !== 8) {
            throw new \RuntimeException("Playoff generation failed: expected 8 matchups, got " . count($matchups));
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

        // Prefer the persisted bracket_position; fall back to rank-based
        // grouping for legacy ties created before that column was populated.
        $bracketWinners = $playoffTies->every(fn ($tie) => $tie->bracket_position !== null)
            ? $playoffTies->groupBy('bracket_position')
                ->map(fn ($ties) => $ties->pluck('winner_id')->all())
                ->all()
            : $this->groupWinnersByPlayoffRank($playoffTies, $standings);

        $matchups = [];

        foreach (self::R16_BRACKETS as [$topPositions, $bracketIndex]) {
            $topTeams = collect($topPositions)
                ->map(fn ($pos) => $standings[$pos] ?? null)
                ->filter()
                ->shuffle();

            $opponents = collect($bracketWinners[$bracketIndex] ?? [])->shuffle();

            for ($i = 0; $i < min($topTeams->count(), $opponents->count()); $i++) {
                // Playoff winner hosts first leg, top seed hosts second leg
                $matchups[] = [$opponents[$i], $topTeams[$i], null];
            }
        }

        if (count($matchups) !== 8) {
            $distribution = array_map('count', $bracketWinners);
            throw new \RuntimeException(
                "R16 generation failed: expected 8 matchups, got " . count($matchups)
                . ". Completed playoff ties: " . $playoffTies->count()
                . ". Bracket distribution: " . json_encode($distribution)
            );
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

        $expectedMatchups = match ($round) {
            self::ROUND_QUARTER_FINALS => 4,
            self::ROUND_SEMI_FINALS => 2,
            default => null,
        };

        if ($expectedMatchups !== null && $winners->count() !== $expectedMatchups * 2) {
            throw new \RuntimeException(
                "Open draw for round {$round} failed: expected " . ($expectedMatchups * 2)
                . " winners, got " . $winners->count()
            );
        }

        $matchups = [];

        for ($i = 0; $i < $winners->count(); $i += 2) {
            if ($i + 1 < $winners->count()) {
                $matchups[] = [$winners[$i], $winners[$i + 1], null];
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

        return [[$winners[0], $winners[1], null]];
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
     * Sequentially assign the 8 playoff ties to 4 brackets, ordered by each
     * tie's higher-seed rank within the 16-team playoff field. Rank-within-
     * field is invariant to global standings drift, and sequential assignment
     * guarantees a 2-2-2-2 layout — unlike position-based classification,
     * which silently routes drifted ties to a default bracket.
     *
     * @return array<int, array<int, string>> bracketIndex => [winnerId, ...]
     */
    private function groupWinnersByPlayoffRank(Collection $playoffTies, array $standings): array
    {
        $positions = array_flip($standings);

        $teamRanks = $playoffTies
            ->flatMap(fn ($tie) => [$tie->home_team_id, $tie->away_team_id])
            ->unique()
            ->sortBy(fn ($teamId) => $positions[$teamId] ?? PHP_INT_MAX)
            ->values()
            ->flip()
            ->all();

        $sortedTies = $playoffTies->sortBy(fn ($tie) => min(
            $teamRanks[$tie->home_team_id] ?? PHP_INT_MAX,
            $teamRanks[$tie->away_team_id] ?? PHP_INT_MAX,
        ))->values();

        $bracketWinners = [];
        foreach ($sortedTies as $i => $tie) {
            $bracketWinners[intdiv($i, 2)][] = $tie->winner_id;
        }

        return $bracketWinners;
    }
}
