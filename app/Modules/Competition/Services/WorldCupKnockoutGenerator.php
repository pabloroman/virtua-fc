<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Generates knockout bracket matchups for the FIFA World Cup 2026.
 *
 * 48 teams, 12 groups of 4:
 * - Group stage: 3 matchdays, top 2 per group + 8 best 3rd-place teams advance (32 total)
 * - Round of 32 → Round of 16 → Quarter-finals → Semi-finals → Third place → Final
 *
 * Uses a fixed bracket from bracket.json (no open draw).
 * Third-place team assignment uses the FIFA deterministic lookup table.
 */
class WorldCupKnockoutGenerator
{
    public const ROUND_OF_32 = 1;
    public const ROUND_OF_16 = 2;
    public const ROUND_QUARTER_FINALS = 3;
    public const ROUND_SEMI_FINALS = 4;
    public const ROUND_THIRD_PLACE = 5;
    public const ROUND_FINAL = 6;

    // Bracket slot indices in the third-place table: [1A, 1B, 1D, 1E, 1G, 1I, 1K, 1L]
    private const THIRD_PLACE_SLOT_KEYS = ['1A', '1B', '1D', '1E', '1G', '1I', '1K', '1L'];

    // Match numbers that receive third-place teams (from bracket.json)
    private const THIRD_PLACE_MATCH_MAP = [
        '1A' => 79,
        '1B' => 85,
        '1D' => 82,
        '1E' => 75,
        '1G' => 81,
        '1I' => 78,
        '1K' => 88,
        '1L' => 80,
    ];

    private ?array $bracket = null;
    private ?array $thirdPlaceTable = null;

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
     * @return array<array{0: string, 1: string, 2: int|null}> Array of [homeTeamId, awayTeamId, bracketPosition]
     */
    public function generateMatchups(Game $game, string $competitionId, int $round): array
    {
        if ($round === self::ROUND_OF_32) {
            return $this->generateRoundOf32($game, $competitionId);
        }

        return $this->generateFixedBracketRound($game, $competitionId, $round);
    }

    /**
     * Generate Round of 32 from group stage results using the fixed bracket.
     */
    private function generateRoundOf32(Game $game, string $competitionId): array
    {
        $bracket = $this->loadBracket();
        $r32Matches = $bracket['round_of_32'] ?? [];

        // Build group standings lookup: group_label + position → team_id
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->get();

        $positionMap = []; // e.g., "1A" => team_id, "2B" => team_id
        foreach ($standings as $standing) {
            $key = $standing->position . $standing->group_label;
            $positionMap[$key] = $standing->team_id;
        }

        // Resolve third-place team assignments
        $thirdPlaceAssignment = $this->resolveThirdPlaceAssignment($game->id, $competitionId);

        $matchups = [];
        foreach ($r32Matches as $match) {
            $homeTeamId = $this->resolveR32Slot($match['home'], $positionMap, $thirdPlaceAssignment);
            $awayTeamId = $this->resolveR32Slot($match['away'], $positionMap, $thirdPlaceAssignment);

            if ($homeTeamId && $awayTeamId) {
                $matchups[] = [$homeTeamId, $awayTeamId, $match['match_number']];
            }
        }

        return $matchups;
    }

    /**
     * Resolve a R32 bracket slot reference to a team ID.
     *
     * Handles: "1A" (group winner), "2B" (runner-up), "3ABCDF" (third-place from eligible groups).
     */
    private function resolveR32Slot(string $slot, array $positionMap, array $thirdPlaceAssignment): ?string
    {
        // Simple position + group: "1A", "2B", etc.
        if (preg_match('/^([12])([A-L])$/', $slot, $m)) {
            return $positionMap[$m[1] . $m[2]] ?? null;
        }

        // Third-place slot: "3ABCDF" — find which bracket match uses this slot label
        if (str_starts_with($slot, '3') && strlen($slot) > 2) {
            $bracket = $this->loadBracket();
            foreach ($bracket['round_of_32'] as $entry) {
                if ($entry['home'] === $slot || $entry['away'] === $slot) {
                    return $thirdPlaceAssignment[$entry['match_number']] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Determine which 8 third-place teams qualify and assign them to bracket slots.
     *
     * @return array<int, string> match_number → team_id
     */
    private function resolveThirdPlaceAssignment(string $gameId, string $competitionId): array
    {
        // Get all 3rd-place teams ranked
        $thirdPlaceStandings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->where('position', 3)
            ->orderByDesc('points')
            ->orderByRaw('(goals_for - goals_against) DESC')
            ->orderByDesc('goals_for')
            ->orderBy('group_label')
            ->get();

        // Take best 8
        $qualifyingThird = $thirdPlaceStandings->take(8);

        // Build the qualifying groups key (sorted alphabetically)
        $qualifyingGroups = $qualifyingThird->pluck('group_label')->sort()->values()->implode('');

        // Lookup in the FIFA third-place assignment table
        $table = $this->loadThirdPlaceTable();
        $assignment = $table[$qualifyingGroups] ?? null;

        if (!$assignment) {
            throw new \RuntimeException("No third-place assignment found for qualifying groups: {$qualifyingGroups}");
        }

        // Build team lookup by group letter
        $teamByGroup = [];
        foreach ($qualifyingThird as $standing) {
            $teamByGroup[$standing->group_label] = $standing->team_id;
        }

        // Map slot indices to match numbers and team IDs
        // assignment = [1A_group, 1B_group, 1D_group, 1E_group, 1G_group, 1I_group, 1K_group, 1L_group]
        $result = [];
        foreach (self::THIRD_PLACE_SLOT_KEYS as $index => $slotKey) {
            $groupLetter = $assignment[$index];
            $matchNumber = self::THIRD_PLACE_MATCH_MAP[$slotKey];
            $result[$matchNumber] = $teamByGroup[$groupLetter] ?? null;
        }

        return $result;
    }

    /**
     * Generate later knockout rounds using the fixed bracket (W73, RU101, etc.).
     */
    private function generateFixedBracketRound(Game $game, string $competitionId, int $round): array
    {
        $bracket = $this->loadBracket();

        $roundKey = match ($round) {
            self::ROUND_OF_16 => 'round_of_16',
            self::ROUND_QUARTER_FINALS => 'quarter_finals',
            self::ROUND_SEMI_FINALS => 'semi_finals',
            self::ROUND_THIRD_PLACE => 'third_place',
            self::ROUND_FINAL => 'final',
            default => throw new \RuntimeException("Unknown round: {$round}"),
        };

        $roundMatches = $bracket[$roundKey] ?? [];
        $matchups = [];

        foreach ($roundMatches as $match) {
            $homeTeamId = $this->resolveBracketReference($match['home'], $game->id, $competitionId);
            $awayTeamId = $this->resolveBracketReference($match['away'], $game->id, $competitionId);

            if ($homeTeamId && $awayTeamId) {
                $matchups[] = [$homeTeamId, $awayTeamId, $match['match_number']];
            }
        }

        return $matchups;
    }

    /**
     * Resolve a bracket reference like "W73" (winner of match 73) or "RU101" (loser of match 101).
     */
    private function resolveBracketReference(string $ref, string $gameId, string $competitionId): ?string
    {
        if (preg_match('/^W(\d+)$/', $ref, $m)) {
            $matchNumber = (int) $m[1];
            $tie = $this->findTieByBracketPosition($gameId, $competitionId, $matchNumber);

            return $tie?->winner_id;
        }

        if (preg_match('/^RU(\d+)$/', $ref, $m)) {
            $matchNumber = (int) $m[1];
            $tie = $this->findTieByBracketPosition($gameId, $competitionId, $matchNumber);

            return $tie?->getLoserId();
        }

        return null;
    }

    /**
     * Find a completed CupTie by its bracket_position (FIFA match number).
     */
    private function findTieByBracketPosition(string $gameId, string $competitionId, int $matchNumber): ?CupTie
    {
        return CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('bracket_position', $matchNumber)
            ->where('completed', true)
            ->first();
    }

    /**
     * Get teams that qualified from the group stage (top 2 per group + best 8 third-place).
     *
     * @return array<string> Team IDs
     */
    public function getQualifiedTeams(string $gameId, string $competitionId): array
    {
        // Top 2 from each group
        $top2 = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->where('position', '<=', 2)
            ->pluck('team_id')
            ->toArray();

        // Best 8 third-place teams
        $thirdPlace = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('group_label')
            ->where('position', 3)
            ->orderByDesc('points')
            ->orderByRaw('(goals_for - goals_against) DESC')
            ->orderByDesc('goals_for')
            ->orderBy('group_label')
            ->take(8)
            ->pluck('team_id')
            ->toArray();

        return array_merge($top2, $thirdPlace);
    }

    private function loadBracket(): array
    {
        if ($this->bracket === null) {
            $path = base_path('data/2025/WC2026/bracket.json');
            $this->bracket = json_decode(file_get_contents($path), true);
        }

        return $this->bracket;
    }

    private function loadThirdPlaceTable(): array
    {
        if ($this->thirdPlaceTable === null) {
            $path = base_path('data/2025/WC2026/third_place_table.json');
            $this->thirdPlaceTable = json_decode(file_get_contents($path), true);
        }

        return $this->thirdPlaceTable;
    }
}
