<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Generates knockout bracket matchups for World Cup group-stage-then-knockout competitions.
 *
 * FIFA 2026 format (48 teams, 12 groups of 4):
 * - Group stage: 3 matchdays, top 2 per group + 8 best 3rd-place teams advance
 * - Round of 32: Seeded bracket (group winners vs runners-up/3rd)
 * - Round of 16: Winners from R32
 * - Quarter-finals: Winners from R16
 * - Semi-finals: Winners from QF
 * - Final: Single match
 *
 * Simplified for current data (1 group of 4):
 * - R32 is skipped; R16 is the first knockout round if <=16 teams qualify
 * - Scales automatically based on number of groups in groups.json
 */
class WorldCupKnockoutGenerator
{
    public const ROUND_OF_32 = 1;
    public const ROUND_OF_16 = 2;
    public const ROUND_QUARTER_FINALS = 3;
    public const ROUND_SEMI_FINALS = 4;
    public const ROUND_FINAL = 5;

    /**
     * Get the first knockout round based on how many teams qualified.
     */
    public function getFirstKnockoutRound(int $qualifiedTeams): int
    {
        return match (true) {
            $qualifiedTeams > 16 => self::ROUND_OF_32,
            $qualifiedTeams > 8 => self::ROUND_OF_16,
            $qualifiedTeams > 4 => self::ROUND_QUARTER_FINALS,
            $qualifiedTeams > 2 => self::ROUND_SEMI_FINALS,
            default => self::ROUND_FINAL,
        };
    }

    /**
     * Get round config from schedule.json.
     */
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
     * Get the final round number from schedule.json.
     */
    public function getFinalRound(string $competitionId): int
    {
        $competition = Competition::find($competitionId);
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds($competitionId, $competition->season);

        if (empty($rounds)) {
            return self::ROUND_FINAL;
        }

        return max(array_map(fn ($r) => $r->round, $rounds));
    }

    /**
     * Generate matchups for a knockout round.
     *
     * @return array<array{0: string, 1: string}> Array of [homeTeamId, awayTeamId] pairs
     */
    public function generateMatchups(Game $game, string $competitionId, int $round): array
    {
        $firstRound = $this->getFirstKnockoutRoundForGame($game, $competitionId);

        if ($round === $firstRound) {
            return $this->generateFirstKnockoutRound($game, $competitionId);
        }

        return $this->generateOpenDraw($game, $competitionId, $round);
    }

    /**
     * Determine the first knockout round for a specific game based on qualified teams.
     */
    private function getFirstKnockoutRoundForGame(Game $game, string $competitionId): int
    {
        $qualifiedTeams = $this->getQualifiedTeams($game->id, $competitionId);

        return $this->getFirstKnockoutRound(count($qualifiedTeams));
    }

    /**
     * Generate the first knockout round from group stage results.
     *
     * Uses FIFA-style cross-group matching:
     * - Group winners are seeded against runners-up from other groups
     * - 1A vs 2B, 1B vs 2A, 1C vs 2D, 1D vs 2C, etc.
     * - Group winners get home advantage
     */
    private function generateFirstKnockoutRound(Game $game, string $competitionId): array
    {
        $qualifiedTeams = $this->getQualifiedTeams($game->id, $competitionId);

        // Separate into group winners (position 1) and runners-up (position 2)
        $winners = [];
        $runnersUp = [];

        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->orderBy('group_label')
            ->orderBy('position')
            ->get();

        foreach ($standings as $standing) {
            if (!in_array($standing->team_id, $qualifiedTeams)) {
                continue;
            }

            if ($standing->position === 1) {
                $winners[$standing->group_label] = $standing->team_id;
            } elseif ($standing->position === 2) {
                $runnersUp[$standing->group_label] = $standing->team_id;
            }
        }

        $groupLabels = array_keys($winners);
        $matchups = [];

        if (count($groupLabels) === 1) {
            // Single group: just match 1st vs 2nd (a final, essentially)
            $label = $groupLabels[0];
            $matchups[] = [$winners[$label], $runnersUp[$label]];

            return $matchups;
        }

        // Cross-group matching: pair groups in pairs (A↔B, C↔D, E↔F, etc.)
        for ($i = 0; $i < count($groupLabels); $i += 2) {
            $groupA = $groupLabels[$i];
            $groupB = $groupLabels[$i + 1] ?? $groupLabels[$i]; // Fallback for odd count

            if ($groupA === $groupB) {
                // Odd number of groups: winner vs runner-up of same group
                $matchups[] = [$winners[$groupA], $runnersUp[$groupA]];
            } else {
                // Standard cross: 1A vs 2B, 1B vs 2A
                $matchups[] = [$winners[$groupA], $runnersUp[$groupB]];
                $matchups[] = [$winners[$groupB], $runnersUp[$groupA]];
            }
        }

        return $matchups;
    }

    /**
     * Later rounds: open draw from previous round winners.
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
     * Get teams that qualified from the group stage.
     * Top 2 from each group qualify.
     *
     * @return array<string> Team IDs
     */
    private function getQualifiedTeams(string $gameId, string $competitionId): array
    {
        return GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->where('position', '<=', 2)
            ->pluck('team_id')
            ->toArray();
    }
}
