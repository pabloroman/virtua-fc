<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\Contracts\PromotionRelegationRule;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;

/**
 * A config-driven promotion/relegation rule.
 *
 * Takes its parameters (divisions, slot counts, playoff generator) from
 * config/countries.php rather than hardcoding them. The actual promotion
 * and relegation logic is generic and works for any two-division pair.
 *
 * Slot allocation (which teams are direct promotions vs playoff seeds)
 * is delegated to PromotionSlotAllocator so the bracket generator can
 * consult the same allocation. This is what prevents a team from appearing
 * in both lists when reserve filtering shifts the direct-promotion range
 * down into the bracket's range.
 *
 * This rule branches on PlayoffState to avoid the historical "playoff loser
 * promoted" class of bug — the old implementation conflated "no playoff
 * played" with "playoff still in progress" and silently promoted the next
 * league position when no winner could be resolved.
 */
class ConfigDrivenPromotionRule implements PromotionRelegationRule
{
    public function __construct(
        private string $topDivision,
        private string $bottomDivision,
        private array $relegatedPositions,
        private int $directCount,
        private int $playoffCount = 0,
        private ?PlayoffGenerator $playoffGenerator = null,
        private ?ReserveTeamFilter $reserveTeamFilter = null,
        private ?PromotionSlotAllocator $slotAllocator = null,
    ) {}

    public function getTopDivision(): string
    {
        return $this->topDivision;
    }

    public function getBottomDivision(): string
    {
        return $this->bottomDivision;
    }

    public function getRelegatedPositions(): array
    {
        return $this->relegatedPositions;
    }

    /**
     * Notional direct-promotion positions, derived from the slot count.
     * Used by in-season UI/notification code (LeaguePlayoffProgressResolver,
     * standings-zone coloring) to colour the table by zone. The actual
     * end-of-season allocation can drift from these positions when reserve
     * teams hold top-of-table slots — that drift is handled by the slot
     * allocator, not here.
     */
    public function getDirectPromotionPositions(): array
    {
        return $this->directCount > 0 ? range(1, $this->directCount) : [];
    }

    public function getPlayoffGenerator(): ?PlayoffGenerator
    {
        return $this->playoffGenerator;
    }

    public function getPromotedTeams(Game $game, array $incomingByDivision = []): array
    {
        if (!$this->hasDataSource($game, $this->bottomDivision)) {
            return [];
        }

        // Reserve-team filtering needs to know who's in the top division. An
        // unpopulated top division silently disables the filter, which can
        // promote a reserve team into the same division as its parent. Fail
        // loudly upfront rather than producing a corrupt swap.
        $this->assertTopDivisionPopulated($game);

        $expectedCount = count($this->relegatedPositions);
        $allocation = $this->getAllocator()->allocate(
            $game,
            $this->bottomDivision,
            $this->directCount,
            $this->playoffCount,
            $incomingByDivision[$this->topDivision] ?? [],
        );

        $promoted = $allocation->directPromotions;

        // With a playoff generator, branch on the playoff's lifecycle state.
        // Without one, direct promotions are all there is.
        if ($this->playoffGenerator) {
            $state = $this->playoffGenerator->state($game);

            $promoted = match ($state) {
                PlayoffState::Completed => array_merge($promoted, [$this->requirePlayoffWinner($game)]),
                PlayoffState::InProgress => throw PlayoffInProgressException::forCompetition($this->bottomDivision),
                PlayoffState::NotStarted => array_merge($promoted, $this->playoffStandIn($allocation)),
            };
        }

        $this->validateTeamCount($promoted, $expectedCount, 'promoted', $this->bottomDivision, $game);

        return $promoted;
    }

    public function getRelegationDestinations(): array
    {
        return [$this->bottomDivision];
    }

    public function getRelegatedTeams(Game $game): array
    {
        if (!$this->hasDataSource($game, $this->topDivision)) {
            return [];
        }

        $expectedCount = count($this->relegatedPositions);

        // Try real standings first
        $relegated = $this->getTeamsByPosition(
            $game->id,
            $this->topDivision,
            $this->relegatedPositions
        );

        if (!empty($relegated)) {
            $this->validateTeamCount($relegated, $expectedCount, 'relegated', $this->topDivision, $game);

            return $relegated;
        }

        // Fall back to simulated results
        $relegated = $this->getSimulatedTeamsByPosition($game, $this->topDivision, $this->relegatedPositions);

        $this->validateTeamCount($relegated, $expectedCount, 'relegated', $this->topDivision, $game);

        return $relegated;
    }

    /**
     * Check whether any data source (real standings or simulated season) exists
     * for the given competition. Returns false when neither exists, which happens
     * when the season-end view is rendered before the closing pipeline has run.
     */
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
     * Reserve filtering needs the top division's roster as a reference set —
     * a team is "blocked" only if its parent club is currently in the top
     * division. With an empty top-division roster, every reserve team would
     * pass the filter, and one could end up promoted into its parent's
     * division. Throwing here catches setup/config problems (missing
     * CompetitionEntry rows for the top division) before they corrupt swaps.
     */
    private function assertTopDivisionPopulated(Game $game): void
    {
        $populated = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $this->topDivision)
            ->exists();

        if ($populated) {
            return;
        }

        throw new \RuntimeException(
            "Top division {$this->topDivision} has no CompetitionEntry rows for this game. "
            . "Cannot resolve promotion from {$this->bottomDivision}: reserve-team filtering "
            . 'requires the top division roster to be populated. This indicates a setup/config problem.'
        );
    }

    /**
     * Validate that the expected number of teams were found for promotion/relegation.
     */
    private function validateTeamCount(array $teams, int $expectedCount, string $type, string $competitionId, Game $game): void
    {
        if (count($teams) !== $expectedCount) {
            $teamIds = array_column($teams, 'teamId');

            $standingsCount = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)->count();

            $simulatedExists = SimulatedSeason::where('game_id', $game->id)
                ->where('season', $game->season)
                ->where('competition_id', $competitionId)
                ->exists();

            throw new \RuntimeException(
                "Promotion/relegation imbalance: expected {$expectedCount} {$type} teams " .
                "from {$competitionId}, got " . count($teams) . ". " .
                "Team IDs: " . json_encode($teamIds) . ". " .
                "Divisions: {$this->topDivision} <-> {$this->bottomDivision}. " .
                "Season: {$game->season}. Standings rows: {$standingsCount}. " .
                "Simulated data exists: " . ($simulatedExists ? 'yes' : 'no') . "."
            );
        }
    }

    /**
     * Playoff is NotStarted — e.g. simulated non-player league that never ran
     * mid-season brackets. Stand in one team to keep the promoted count balanced:
     * the highest-seeded entry from the allocator's playoff slots, which is
     * guaranteed disjoint from the direct promotions.
     *
     * @return array<array{teamId: string, position: int|string, teamName: string}>
     */
    private function playoffStandIn(\App\Modules\Competition\DTOs\PromotionSlotAllocation $allocation): array
    {
        $first = $allocation->playoffQualifiers[0] ?? null;

        return $first ? [$first] : [];
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
     * When PlayoffState::Completed reports true, a winner MUST exist. If it
     * doesn't, we have a data invariant violation (e.g., completed flag set
     * without a winner_id). Throwing surfaces the corruption instead of
     * silently falling back to the next league position.
     *
     * @return array{teamId: string, position: string, teamName: string}
     */
    private function requirePlayoffWinner(Game $game): array
    {
        $finalRound = $this->playoffGenerator->getTotalRounds();

        $finalTie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->bottomDivision)
            ->where('round_number', $finalRound)
            ->where('completed', true)
            ->with('winner')
            ->first();

        if (!$finalTie?->winner) {
            throw new \RuntimeException(
                "Playoff for {$this->bottomDivision} reports state=Completed, but no completed final "
                . 'CupTie with a winner was found. Data invariant violated — refusing to guess a winner.'
            );
        }

        return [
            'teamId' => $finalTie->winner_id,
            'position' => 'Playoff',
            'teamName' => $finalTie->winner->name,
        ];
    }

    private function getAllocator(): PromotionSlotAllocator
    {
        return $this->slotAllocator ??= new PromotionSlotAllocator(
            $this->reserveTeamFilter ?? app(ReserveTeamFilter::class),
        );
    }
}
