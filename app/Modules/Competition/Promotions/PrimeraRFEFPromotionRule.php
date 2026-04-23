<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\Contracts\SelfSwappingPromotionRule;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Playoffs\PrimeraRFEFPlayoffGenerator;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;

/**
 * Promotion/relegation rule for ESP2 ↔ Primera RFEF (ESP3A + ESP3B + ESP3PO).
 *
 * This rule doesn't fit the one-top-one-bottom shape of ConfigDrivenPromotionRule:
 *
 *   - 4 teams are promoted: the ESP3A group winner, the ESP3B group winner,
 *     and the two ESP3PO bracket final winners.
 *   - 4 teams relegate from ESP2 (positions 19–22) and must be redistributed
 *     between ESP3A and ESP3B based on how many promoted teams departed each
 *     group, so both groups return to 20 teams.
 *
 * Because of that redistribution it implements SelfSwappingPromotionRule and
 * owns its performSwap() logic; PromotionRelegationProcessor delegates to it
 * rather than calling the generic swapTeams() helper.
 *
 * Fallback: when the player isn't in ESP3 at all (so ESP3PO is never populated
 * mid-season), this rule synthesises the two playoff winners from the
 * simulated standings — positions 2 of each group stand in as the playoff
 * winners. This keeps the 4-for-4 balance even when the playoff wasn't played.
 */
class PrimeraRFEFPromotionRule implements SelfSwappingPromotionRule
{
    private const GROUP_A_ID = 'ESP3A';
    private const GROUP_B_ID = 'ESP3B';
    private const PLAYOFF_ID = 'ESP3PO';
    private const TOP_DIVISION = 'ESP2';
    private const RELEGATED_POSITIONS = [19, 20, 21, 22];

    public function __construct(
        private readonly ?PlayoffGenerator $playoffGenerator = null,
        private readonly ?ReserveTeamFilter $reserveTeamFilter = null,
    ) {}

    public function getTopDivision(): string
    {
        return self::TOP_DIVISION;
    }

    public function getBottomDivision(): string
    {
        // Nominal — the real logic spans ESP3A, ESP3B, and ESP3PO. Returned
        // here only so diagnostic messages in the processor have something
        // to print.
        return self::GROUP_A_ID;
    }

    public function getRelegatedPositions(): array
    {
        return self::RELEGATED_POSITIONS;
    }

    public function getDirectPromotionPositions(): array
    {
        return [1];
    }

    public function getPlayoffGenerator(): ?PlayoffGenerator
    {
        return $this->playoffGenerator;
    }

    /**
     * @return array<array{teamId: string, position: int|string, teamName: string, origin: string}>
     */
    public function getPromotedTeams(Game $game): array
    {
        if (!$this->isActiveForGame($game)) {
            return [];
        }

        if (!$this->hasDataSource($game, self::GROUP_A_ID) || !$this->hasDataSource($game, self::GROUP_B_ID)) {
            return [];
        }

        $promoted = [];

        // Direct promotions: position 1 of each group.
        $promoted[] = $this->directPromotion($game, self::GROUP_A_ID);
        $promoted[] = $this->directPromotion($game, self::GROUP_B_ID);
        $promoted = array_values(array_filter($promoted));

        if (count($promoted) !== 2) {
            // Data source was present but the position-1 lookup failed on at
            // least one group — surface the issue rather than silently under-
            // reporting promotions.
            return $promoted;
        }

        // Branch on the ESP3PO playoff's lifecycle state. Historically the
        // code silently substituted simulated stand-ins whenever the playoff
        // didn't yield winners — which masked the "final not yet played"
        // case and promoted a losing team. Explicit state handling prevents
        // that class of bug.
        $state = $this->resolvePlayoffState($game);

        return match ($state) {
            PlayoffState::Completed => array_merge($promoted, $this->requirePlayoffWinners($game)),
            PlayoffState::InProgress => throw PlayoffInProgressException::forCompetition(self::PLAYOFF_ID),
            PlayoffState::NotStarted => array_merge($promoted, $this->simulatedPlayoffStandIns($game, $promoted)),
        };
    }

    /**
     * Determine the ESP3PO playoff state. Prefers the generator's own
     * state() method when available; otherwise inspects CupTie rows directly
     * so the rule remains usable even without a constructor-injected generator
     * (e.g. when instantiated manually by the PromotionRelegationFactory
     * before the generator is wired).
     */
    private function resolvePlayoffState(Game $game): PlayoffState
    {
        if ($this->playoffGenerator instanceof PrimeraRFEFPlayoffGenerator) {
            return $this->playoffGenerator->state($game);
        }

        $ties = CupTie::where('game_id', $game->id)
            ->where('competition_id', self::PLAYOFF_ID)
            ->get();

        if ($ties->isEmpty()) {
            return PlayoffState::NotStarted;
        }

        $finals = $ties->where('round_number', 2);
        $complete = $finals->count() === 2
            && $finals->every(fn ($t) => $t->completed === true && $t->winner_id !== null);

        return $complete ? PlayoffState::Completed : PlayoffState::InProgress;
    }

    /**
     * PlayoffState::Completed guarantees the two bracket finals exist and
     * are resolved. If playoffWinners() still returns empty, we have a data
     * invariant violation — throw rather than guess.
     *
     * @return array<array{teamId: string, position: string, teamName: string, origin: string}>
     */
    private function requirePlayoffWinners(Game $game): array
    {
        $winners = $this->playoffWinners($game);

        if (count($winners) !== 2) {
            throw new \RuntimeException(
                'Primera RFEF playoff reports state=Completed, but did not resolve 2 bracket '
                . 'final winners (got ' . count($winners) . '). Data invariant violated — refusing '
                . 'to guess promotions.'
            );
        }

        return $winners;
    }

    /**
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    public function getRelegatedTeams(Game $game): array
    {
        if (!$this->isActiveForGame($game)) {
            return [];
        }

        if (!$this->hasDataSource($game, self::TOP_DIVISION)) {
            return [];
        }

        $relegated = $this->teamsByPositionFromStandings($game->id, self::TOP_DIVISION, self::RELEGATED_POSITIONS);
        if (!empty($relegated)) {
            return $relegated;
        }

        return $this->teamsByPositionFromSimulation($game, self::TOP_DIVISION, self::RELEGATED_POSITIONS);
    }

    public function performSwap(Game $game, array $promoted, array $relegated): void
    {
        $playerTeamId = $game->team_id;

        // 1. Count how many teams left each ESP3 group so we know how many
        //    relegated ESP2 teams to send to each group. Bracket winners keep
        //    their origin tag in the promoted entries.
        $leavingA = count(array_filter($promoted, fn ($p) => ($p['origin'] ?? null) === self::GROUP_A_ID));
        $leavingB = count(array_filter($promoted, fn ($p) => ($p['origin'] ?? null) === self::GROUP_B_ID));

        if ($leavingA + $leavingB !== count($relegated)) {
            throw new \RuntimeException(
                "Primera RFEF swap imbalance: {$leavingA} leaving Group A, {$leavingB} leaving Group B, "
                . count($relegated) . ' relegated from ESP2. Counts must match.'
            );
        }

        // 2. Split relegated ESP2 teams into two buckets, preserving their
        //    natural order (higher-placed relegated teams go to Group A first).
        $relegatedToA = array_slice($relegated, 0, $leavingA);
        $relegatedToB = array_slice($relegated, $leavingA, $leavingB);

        // 3. Promote: move each promoted team from its origin competition to ESP2.
        foreach ($promoted as $entry) {
            $origin = $entry['origin'] ?? self::GROUP_A_ID;
            $this->moveTeam($game->id, $entry['teamId'], $origin, self::TOP_DIVISION, $playerTeamId);
        }

        // 4. Relegate: move each ESP2 team down into its assigned group.
        foreach ($relegatedToA as $entry) {
            $this->moveTeam($game->id, $entry['teamId'], self::TOP_DIVISION, self::GROUP_A_ID, $playerTeamId);
        }
        foreach ($relegatedToB as $entry) {
            $this->moveTeam($game->id, $entry['teamId'], self::TOP_DIVISION, self::GROUP_B_ID, $playerTeamId);
        }

        // 5. Re-sort positions in every affected division.
        foreach ([self::TOP_DIVISION, self::GROUP_A_ID, self::GROUP_B_ID] as $divisionId) {
            $this->resortPositions($game->id, $divisionId);
        }

        // 6. Tear down ESP3PO state so the next season starts clean. The
        //    competition row itself persists — only the per-game artefacts
        //    (entries, standings, matches, ties) are cleared.
        $this->clearPlayoffState($game->id);
    }

    /**
     * @return array{teamId: string, position: int|string, teamName: string, origin: string}|null
     */
    private function directPromotion(Game $game, string $groupId): ?array
    {
        $standing = GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $groupId)
            ->where('position', 1)
            ->first();

        if ($standing) {
            return [
                'teamId' => $standing->team_id,
                'position' => 1,
                'teamName' => $standing->team->name ?? 'Unknown',
                'origin' => $groupId,
            ];
        }

        // Fall back to simulated results.
        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $groupId)
            ->first();

        $winnerId = $simulated?->getWinnerTeamId();
        if (!$winnerId) {
            return null;
        }

        $team = Team::find($winnerId);

        return [
            'teamId' => $winnerId,
            'position' => 1,
            'teamName' => $team->name ?? 'Unknown',
            'origin' => $groupId,
        ];
    }

    /**
     * @return array<array{teamId: string, position: string, teamName: string, origin: string}>
     */
    private function playoffWinners(Game $game): array
    {
        $finals = CupTie::with('winner')
            ->where('game_id', $game->id)
            ->where('competition_id', self::PLAYOFF_ID)
            ->where('round_number', 2)
            ->where('completed', true)
            ->orderBy('bracket_position')
            ->get();

        if ($finals->count() !== 2) {
            return [];
        }

        $winners = [];
        foreach ($finals as $tie) {
            if (!$tie->winner_id) {
                continue;
            }

            $origin = $this->resolveTeamOriginGroup($game, $tie->winner_id);

            $winners[] = [
                'teamId' => $tie->winner_id,
                'position' => 'Playoff',
                'teamName' => $tie->winner->name ?? 'Unknown',
                'origin' => $origin,
            ];
        }

        return count($winners) === 2 ? $winners : [];
    }

    /**
     * Fallback for when the playoff wasn't actually played (e.g. when the
     * player isn't in Primera RFEF and SeasonSimulationProcessor produced
     * SimulatedSeason rows without kicking off the mid-season bracket).
     *
     * We synthesise two promotion spots by taking position 2 from each group,
     * skipping any team that's already in the directly-promoted list to avoid
     * double-counting.
     *
     * @param array<array{teamId: string, position: int|string, teamName: string, origin: string}> $alreadyPromoted
     * @return array<array{teamId: string, position: int|string, teamName: string, origin: string}>
     */
    private function simulatedPlayoffStandIns(Game $game, array $alreadyPromoted): array
    {
        $already = array_column($alreadyPromoted, 'teamId');
        $filter = $this->reserveTeamFilter ?? app(ReserveTeamFilter::class);

        $topDivisionTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', self::TOP_DIVISION)
            ->pluck('team_id');

        $results = [];
        foreach ([self::GROUP_A_ID, self::GROUP_B_ID] as $groupId) {
            $simulated = SimulatedSeason::where('game_id', $game->id)
                ->where('season', $game->season)
                ->where('competition_id', $groupId)
                ->first();

            if (!$simulated) {
                continue;
            }

            $candidates = array_slice($simulated->results ?? [], 1); // skip winner
            $parentMap = $filter->loadParentTeamIds($candidates);

            foreach ($candidates as $teamId) {
                if (in_array($teamId, $already, true)) {
                    continue;
                }
                if ($filter->isBlockedReserveTeam($teamId, $topDivisionTeamIds, $parentMap)) {
                    continue;
                }

                $team = Team::find($teamId);
                $results[] = [
                    'teamId' => $teamId,
                    'position' => 'Playoff',
                    'teamName' => $team->name ?? 'Unknown',
                    'origin' => $groupId,
                ];
                break;
            }
        }

        return $results;
    }

    /**
     * Identify which ESP3 group a bracket winner originally belonged to. We
     * check CompetitionEntry first (most reliable), then fall back to the
     * simulated standings since the rule can fire after SeasonSimulation
     * has produced SimulatedSeason rows for both groups.
     */
    private function resolveTeamOriginGroup(Game $game, string $teamId): string
    {
        $entry = CompetitionEntry::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->whereIn('competition_id', [self::GROUP_A_ID, self::GROUP_B_ID])
            ->first();

        if ($entry) {
            return $entry->competition_id;
        }

        foreach ([self::GROUP_A_ID, self::GROUP_B_ID] as $groupId) {
            $simulated = SimulatedSeason::where('game_id', $game->id)
                ->where('season', $game->season)
                ->where('competition_id', $groupId)
                ->first();

            if ($simulated && in_array($teamId, $simulated->results ?? [], true)) {
                return $groupId;
            }
        }

        // Last resort: default to Group A. This should never happen in practice —
        // a bracket winner must belong to one of the two groups.
        return self::GROUP_A_ID;
    }

    private function moveTeam(
        string $gameId,
        string $teamId,
        string $fromDivision,
        string $toDivision,
        ?string $playerTeamId,
    ): void {
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->delete();

        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $gameId,
                'competition_id' => $toDivision,
                'team_id' => $teamId,
            ],
            ['entry_round' => 1],
        );

        // Remove from source standings (if any — simulated leagues have none).
        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->delete();

        $targetHasStandings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $toDivision)
            ->exists();

        if ($targetHasStandings) {
            GameStanding::firstOrCreate([
                'game_id' => $gameId,
                'competition_id' => $toDivision,
                'team_id' => $teamId,
            ], [
                'position' => 99,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        if ($playerTeamId !== null && $teamId === $playerTeamId) {
            \App\Models\Game::where('id', $gameId)
                ->where('team_id', $teamId)
                ->update(['competition_id' => $toDivision]);
        }
    }

    private function resortPositions(string $gameId, string $competitionId): void
    {
        $standings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->orderBy('position')
            ->get();

        if ($standings->isEmpty()) {
            return;
        }

        foreach ($standings->values() as $index => $standing) {
            $newPosition = $index + 1;
            if ($standing->position !== $newPosition) {
                $standing->update(['position' => $newPosition]);
            }
        }
    }

    /**
     * Clear all per-game state for ESP3PO so the next season starts fresh.
     * The Competition row itself is preserved.
     */
    private function clearPlayoffState(string $gameId): void
    {
        CupTie::where('game_id', $gameId)
            ->where('competition_id', self::PLAYOFF_ID)
            ->delete();

        GameMatch::where('game_id', $gameId)
            ->where('competition_id', self::PLAYOFF_ID)
            ->delete();

        GameStanding::where('game_id', $gameId)
            ->where('competition_id', self::PLAYOFF_ID)
            ->delete();

        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', self::PLAYOFF_ID)
            ->delete();
    }

    /**
     * Legacy games created before Primera RFEF was enabled globally may have
     * no ESP3 CompetitionEntry rows. This rule must no-op for them —
     * otherwise ESP2 relegations would have no matching ESP3 promotions and
     * the processor would error out on the count-imbalance check.
     */
    private function isActiveForGame(Game $game): bool
    {
        return CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', self::GROUP_A_ID)
            ->exists();
    }

    private function hasDataSource(Game $game, string $competitionId): bool
    {
        $hasStandings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->exists();

        if ($hasStandings) {
            return true;
        }

        return SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competitionId)
            ->exists();
    }

    /**
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function teamsByPositionFromStandings(string $gameId, string $competitionId, array $positions): array
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
    private function teamsByPositionFromSimulation(Game $game, string $competitionId, array $positions): array
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
}
