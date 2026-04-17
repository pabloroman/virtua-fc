<?php

namespace App\Modules\Competition\Playoffs;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Modules\Finance\Services\SeasonSimulationService;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use Illuminate\Support\Collection;

/**
 * Playoff generator for Primera RFEF (Spanish tier 3).
 *
 * Primera RFEF is modeled as two separate flat leagues (ESP3A, ESP3B), with
 * this generator producing a shared promotion playoff that lives under the
 * separate ESP3PO competition. Only positions 1 in each group earn direct
 * promotion — the other 2 promotion spots come from this playoff.
 *
 * Format:
 * - 8 qualifying teams: positions 2, 3, 4, 5 from each group.
 * - Two fixed brackets:
 *     Bracket 1 → [A2 vs A5] and [B3 vs B4]
 *     Bracket 2 → [B2 vs B5] and [A3 vs A4]
 * - Round 1 (Semifinals): 4 two-legged ties, lower seed hosts first leg.
 * - Round 2 (Bracket Finals): the two semifinal winners in each bracket play
 *   a two-legged final. Both bracket winners are promoted to La Liga 2.
 *
 * Sister-group handling: this generator is registered under both ESP3A and
 * ESP3B in PlayoffGeneratorFactory. Whichever group the player is in triggers
 * it when that group's regular season ends. The sister group's standings are
 * resolved either from real GameStanding rows (if the player happens to be in
 * ESP3B too — shouldn't happen per se, but symmetrical) or from a lazy
 * SimulatedSeason row created on-the-fly via SeasonSimulationService. The
 * SeasonSimulationProcessor also creates SimulatedSeason rows at season
 * closing, but the playoff fires mid-calendar (before closing), so lazy
 * simulation is needed.
 */
class PrimeraRFEFPlayoffGenerator implements PlayoffGenerator
{
    private const GROUP_A_ID = 'ESP3A';
    private const GROUP_B_ID = 'ESP3B';
    private const PLAYOFF_ID = 'ESP3PO';
    private const TOP_DIVISION = 'ESP2';

    public const BRACKET_A = 1;
    public const BRACKET_B = 2;

    public function __construct(
        private readonly string $competitionId = self::PLAYOFF_ID,
        private readonly array $qualifyingPositions = [2, 3, 4, 5],
        private readonly array $directPromotionPositions = [1],
        private readonly int $triggerMatchday = 38,
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
        return 2; // Bracket semifinals + bracket finals
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

    /**
     * Generate the matchups for a playoff round.
     *
     * Return shape: each matchup is [homeTeamId, awayTeamId, bracketPosition].
     * The third element is consumed by LeagueWithPlayoffHandler when calling
     * createTie(), so the bracket wiring is preserved on the CupTie row.
     *
     * @return array<array{0: string, 1: string, 2: int}>
     */
    public function generateMatchups(Game $game, int $round): array
    {
        return match ($round) {
            1 => $this->generateSemifinalMatchups($game),
            2 => $this->generateBracketFinalMatchups($game),
            default => throw new \InvalidArgumentException("Invalid playoff round: {$round}"),
        };
    }

    public function isComplete(Game $game): bool
    {
        // Both bracket finals must exist, both must be completed, and both
        // must have a winner_id set. Any missing piece means the playoff is
        // not fully resolved — treat as incomplete.
        $finals = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', $this->getTotalRounds())
            ->get();

        if ($finals->count() !== 2) {
            return false;
        }

        return $finals->every(fn ($tie) => $tie->completed === true && $tie->winner_id !== null);
    }

    public function state(Game $game): PlayoffState
    {
        if ($this->isComplete($game)) {
            return PlayoffState::Completed;
        }

        $anyTieExists = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->exists();

        return $anyTieExists ? PlayoffState::InProgress : PlayoffState::NotStarted;
    }

    /**
     * Round 1 (Semifinals). Pulls the top 5 from each group (real or simulated),
     * skips reserve teams whose parent already plays in ESP2, populates
     * ESP3PO::CompetitionEntry with the 8 qualified teams, and returns 4 matchups.
     *
     * Two brackets:
     *   Bracket 1: (A2 vs A5) and (B3 vs B4)
     *   Bracket 2: (B2 vs B5) and (A3 vs A4)
     * Lower-seeded team hosts the first leg (matches ESP2PlayoffGenerator's
     * convention so two-leg home/away ordering is consistent across playoffs).
     *
     * @return array<array{0: string, 1: string, 2: int}>
     */
    private function generateSemifinalMatchups(Game $game): array
    {
        $topDivisionTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', self::TOP_DIVISION)
            ->pluck('team_id');

        $filter = app(ReserveTeamFilter::class);

        $groupATop = $this->eligibleTopTeams($game, self::GROUP_A_ID, $filter, $topDivisionTeamIds);
        $groupBTop = $this->eligibleTopTeams($game, self::GROUP_B_ID, $filter, $topDivisionTeamIds);

        if (count($groupATop) < 4 || count($groupBTop) < 4) {
            throw new \RuntimeException(
                'Not enough eligible teams for Primera RFEF playoff: '
                . 'Group A has ' . count($groupATop) . ', Group B has ' . count($groupBTop) . ' (need 4 each).'
            );
        }

        // $groupATop and $groupBTop are positions 2..5 (zero-indexed 0..3).
        // Index 0 = 2nd, 1 = 3rd, 2 = 4th, 3 = 5th.
        [$a2, $a3, $a4, $a5] = array_slice($groupATop, 0, 4);
        [$b2, $b3, $b4, $b5] = array_slice($groupBTop, 0, 4);

        // Populate ESP3PO CompetitionEntry with all 8 qualified teams (idempotent).
        $this->populatePlayoffEntries($game, [$a2, $a3, $a4, $a5, $b2, $b3, $b4, $b5]);

        // Lower seed hosts first leg — so the 5th-place team is listed as "home"
        // in the matchup, and the 4th-place team is listed as "home" in the second
        // ESP3PO tie within each bracket. See createTie() which treats the first
        // slot as first-leg home.
        return [
            [$a5, $a2, self::BRACKET_A],
            [$b4, $b3, self::BRACKET_A],
            [$b5, $b2, self::BRACKET_B],
            [$a4, $a3, self::BRACKET_B],
        ];
    }

    /**
     * Round 2 (Bracket Finals). The two semifinal winners inside each bracket
     * play a two-legged final. Both winners are promoted — one per bracket.
     *
     * @return array<array{0: string, 1: string, 2: int}>
     */
    private function generateBracketFinalMatchups(Game $game): array
    {
        $semifinals = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', 1)
            ->where('completed', true)
            ->orderBy('bracket_position')
            ->orderBy('id')
            ->get();

        $byBracket = $semifinals->groupBy('bracket_position');

        $matchups = [];
        foreach ([self::BRACKET_A, self::BRACKET_B] as $bracket) {
            $ties = $byBracket->get($bracket);

            if ($ties === null || $ties->count() !== 2) {
                throw new \RuntimeException(
                    "Cannot generate Primera RFEF bracket {$bracket} final: expected 2 completed semifinals, got "
                    . ($ties?->count() ?? 0)
                );
            }

            $winners = $ties->pluck('winner_id')->filter()->values();
            if ($winners->count() !== 2) {
                throw new \RuntimeException(
                    "Bracket {$bracket} semifinals have not all resolved winners yet."
                );
            }

            // Insertion order of $ties matches bracket seeding: the first tie
            // in the bracket was the lower-seed (5 vs 2 or 4 vs 3) matchup.
            // Its winner hosts the first leg of the bracket final.
            $matchups[] = [$winners[0], $winners[1], $bracket];
        }

        return $matchups;
    }

    /**
     * Return the top 4 eligible (non-reserve-blocked) teams at positions 2–5
     * of a group, preferring real GameStanding rows and falling back to
     * SimulatedSeason. When the sister group has no SimulatedSeason yet
     * (because the closing pipeline hasn't run), one is created lazily.
     *
     * Reserve teams whose parent club plays in ESP2 are skipped; the next
     * eligible team slides into their playoff slot.
     *
     * @return array<string> Team UUIDs in seed order [2nd, 3rd, 4th, 5th].
     */
    private function eligibleTopTeams(
        Game $game,
        string $groupCompetitionId,
        ReserveTeamFilter $filter,
        Collection $topDivisionTeamIds,
    ): array {
        $hasRealStandings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $groupCompetitionId)
            ->exists();

        $orderedTeamIds = $hasRealStandings
            ? $this->orderedTeamsFromStandings($game->id, $groupCompetitionId)
            : $this->orderedTeamsFromSimulation($game, $groupCompetitionId);

        if (empty($orderedTeamIds)) {
            return [];
        }

        // Skip position 1 (direct promotion); consider positions 2 onward, applying
        // reserve-team filtering. Reserve filter blocks teams whose parent is
        // already in ESP2, since a reserve team can't play in the same division
        // as its parent club.
        $candidates = array_slice($orderedTeamIds, 1);
        $parentMap = $filter->loadParentTeamIds($candidates);

        $eligible = [];
        foreach ($candidates as $teamId) {
            if ($filter->isBlockedReserveTeam($teamId, $topDivisionTeamIds, $parentMap)) {
                continue;
            }
            $eligible[] = $teamId;
            if (count($eligible) === 4) {
                break;
            }
        }

        return $eligible;
    }

    /**
     * @return array<string> Team UUIDs ordered 1st..Nth.
     */
    private function orderedTeamsFromStandings(string $gameId, string $competitionId): array
    {
        return GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->orderBy('position')
            ->pluck('team_id')
            ->toArray();
    }

    /**
     * Fetch ordered team IDs from SimulatedSeason, lazily creating the row
     * via SeasonSimulationService if it doesn't exist yet.
     *
     * @return array<string>
     */
    private function orderedTeamsFromSimulation(Game $game, string $competitionId): array
    {
        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competitionId)
            ->first();

        if (!$simulated) {
            $competition = Competition::find($competitionId);
            if (!$competition) {
                return [];
            }
            $simulated = app(SeasonSimulationService::class)->simulateLeague($game, $competition);
        }

        return $simulated->results ?? [];
    }

    /**
     * Idempotently insert CompetitionEntry rows for the 8 playoff teams.
     * These entries are cleared at season end by PrimeraRFEFPromotionRule.
     *
     * @param array<string> $teamIds
     */
    private function populatePlayoffEntries(Game $game, array $teamIds): void
    {
        foreach ($teamIds as $teamId) {
            CompetitionEntry::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'competition_id' => $this->competitionId,
                    'team_id' => $teamId,
                ],
                ['entry_round' => 1],
            );
        }
    }
}
