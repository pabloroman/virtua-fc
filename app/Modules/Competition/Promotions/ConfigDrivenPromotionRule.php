<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\Contracts\PromotionRelegationRule;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * A config-driven promotion/relegation rule.
 *
 * Takes its parameters (divisions, positions, playoff generator) from
 * config/countries.php rather than hardcoding them. The actual promotion
 * and relegation logic is generic and works for any two-division pair.
 *
 * This rule branches on PlayoffState to avoid the historical "playoff loser
 * promoted" class of bug — the old implementation conflated "no playoff
 * played" with "playoff still in progress" and silently promoted the next
 * league position when no winner could be resolved.
 */
class ConfigDrivenPromotionRule implements PromotionRelegationRule
{
    /**
     * Extra positions to check beyond the required count when skipping
     * blocked reserve teams. Covers the unlikely case of multiple
     * reserve teams clustered at the top of the standings.
     */
    private const RESERVE_TEAM_BUFFER = 3;

    public function __construct(
        private string $topDivision,
        private string $bottomDivision,
        private array $relegatedPositions,
        private array $directPromotionPositions,
        private ?PlayoffGenerator $playoffGenerator = null,
        private ?ReserveTeamFilter $reserveTeamFilter = null,
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

    public function getDirectPromotionPositions(): array
    {
        return $this->directPromotionPositions;
    }

    public function getPlayoffGenerator(): ?PlayoffGenerator
    {
        return $this->playoffGenerator;
    }

    public function getPromotedTeams(Game $game): array
    {
        if (!$this->hasDataSource($game, $this->bottomDivision)) {
            return [];
        }

        $expectedCount = count($this->relegatedPositions);
        $filter = $this->getFilter();
        $topDivisionTeamIds = $filter->getTopDivisionTeamIds($game, $this->bottomDivision);
        $this->assertTopDivisionPopulated($topDivisionTeamIds);

        $promoted = $this->getDirectPromotions($game, $filter, $topDivisionTeamIds);

        // With a playoff generator, branch on the playoff's lifecycle state.
        // Without one, direct promotions are all there is.
        if ($this->playoffGenerator) {
            $state = $this->playoffGenerator->state($game);

            $promoted = match ($state) {
                PlayoffState::Completed => array_merge($promoted, [$this->requirePlayoffWinner($game)]),
                PlayoffState::InProgress => throw PlayoffInProgressException::forCompetition($this->bottomDivision),
                PlayoffState::NotStarted => array_merge(
                    $promoted,
                    $this->playoffStandIn($game, $promoted, $filter, $topDivisionTeamIds),
                ),
            };
        }

        $this->validateTeamCount($promoted, $expectedCount, 'promoted', $this->bottomDivision, $game);

        return $promoted;
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
     * Reserve-team filtering depends on knowing who's currently in the top
     * division. An empty list means either a config error (no mapping for
     * this bottom division) or missing CompetitionEntry rows — in either
     * case, proceeding would silently bypass the filter and could promote
     * a reserve team whose parent is already in the top division. Fail loudly.
     */
    private function assertTopDivisionPopulated(Collection $topDivisionTeamIds): void
    {
        if ($topDivisionTeamIds->isEmpty()) {
            throw new \RuntimeException(
                "Top division {$this->topDivision} has no CompetitionEntry rows for this game. "
                . "Cannot resolve promotion from {$this->bottomDivision}: reserve-team filtering "
                . 'requires the top division roster to be populated. This indicates a setup/config problem.'
            );
        }
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
     * Get direct-promotion teams from the bottom division, preferring real
     * standings and falling back to simulated. Reserve teams whose parent is
     * in the top division are filtered out; the next eligible team slides in.
     *
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function getDirectPromotions(Game $game, ReserveTeamFilter $filter, Collection $topDivisionTeamIds): array
    {
        $requiredCount = count($this->directPromotionPositions);

        // Real standings path.
        $maxPosition = max($this->directPromotionPositions) + $requiredCount + self::RESERVE_TEAM_BUFFER;
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $this->bottomDivision)
            ->whereBetween('position', [min($this->directPromotionPositions), $maxPosition])
            ->with('team')
            ->orderBy('position')
            ->get();

        if ($standings->isNotEmpty()) {
            return $this->filterEligibleFromStandings($standings, $filter, $topDivisionTeamIds, $requiredCount);
        }

        // Simulated path — pull top N + buffer and filter.
        return $this->filterEligibleFromSimulated(
            $game,
            range(1, $requiredCount + self::RESERVE_TEAM_BUFFER),
            $filter,
            $topDivisionTeamIds,
            $requiredCount,
        );
    }

    /**
     * Playoff is NotStarted — e.g. simulated non-player league that never ran
     * mid-season brackets. Stand in one team to keep the promoted count balanced:
     * the next eligible position after the direct promotions.
     *
     * @param array<array{teamId: string, position: int|string, teamName: string}> $alreadyPromoted
     * @return array<array{teamId: string, position: int|string, teamName: string}>
     */
    private function playoffStandIn(Game $game, array $alreadyPromoted, ReserveTeamFilter $filter, Collection $topDivisionTeamIds): array
    {
        $promotedIds = array_column($alreadyPromoted, 'teamId');

        $nextPosition = max($this->directPromotionPositions) + 1;
        $maxPosition = $nextPosition + self::RESERVE_TEAM_BUFFER + 1;

        // Real standings path.
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $this->bottomDivision)
            ->whereBetween('position', [$nextPosition, $maxPosition])
            ->with('team')
            ->orderBy('position')
            ->get();

        if ($standings->isNotEmpty()) {
            $teamIds = $standings->pluck('team_id')->all();
            $parentMap = $filter->loadParentTeamIds($teamIds);

            $eligible = $standings
                ->filter(fn ($s) => !in_array($s->team_id, $promotedIds, true))
                ->filter(fn ($s) => !$filter->isBlockedReserveTeam($s->team_id, $topDivisionTeamIds, $parentMap))
                ->first();

            if (!$eligible) {
                return [];
            }

            return [[
                'teamId' => $eligible->team_id,
                'position' => $eligible->position,
                'teamName' => $eligible->team->name ?? 'Unknown',
            ]];
        }

        // Simulated path — take the next eligible position after the already-promoted set.
        $simulated = $this->getSimulatedTeamsByPosition(
            $game,
            $this->bottomDivision,
            range($nextPosition, $maxPosition),
        );

        $candidateIds = array_column($simulated, 'teamId');
        $parentMap = $filter->loadParentTeamIds($candidateIds);

        foreach ($simulated as $candidate) {
            if (in_array($candidate['teamId'], $promotedIds, true)) {
                continue;
            }
            if ($filter->isBlockedReserveTeam($candidate['teamId'], $topDivisionTeamIds, $parentMap)) {
                continue;
            }
            return [$candidate];
        }

        return [];
    }

    /**
     * Filter a collection of GameStanding rows through the reserve-team
     * filter and return $requiredCount entries in standard shape.
     *
     * @param  \Illuminate\Support\Collection<int, GameStanding>  $standings
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function filterEligibleFromStandings(Collection $standings, ReserveTeamFilter $filter, Collection $topDivisionTeamIds, int $requiredCount): array
    {
        $teamIds = $standings->pluck('team_id')->all();
        $parentMap = $filter->loadParentTeamIds($teamIds);

        $eligible = $standings->filter(
            fn ($s) => !$filter->isBlockedReserveTeam($s->team_id, $topDivisionTeamIds, $parentMap)
        )->take($requiredCount);

        return $eligible->map(fn ($standing) => [
            'teamId' => $standing->team_id,
            'position' => $standing->position,
            'teamName' => $standing->team->name ?? 'Unknown',
        ])->values()->toArray();
    }

    /**
     * Filter simulated standings through the reserve-team filter and return
     * $requiredCount entries in standard shape.
     *
     * @param  int[]  $positions
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function filterEligibleFromSimulated(Game $game, array $positions, ReserveTeamFilter $filter, Collection $topDivisionTeamIds, int $requiredCount): array
    {
        $candidates = $this->getSimulatedTeamsByPosition($game, $this->bottomDivision, $positions);
        if (empty($candidates)) {
            return [];
        }

        $candidateTeamIds = array_column($candidates, 'teamId');
        $parentMap = $filter->loadParentTeamIds($candidateTeamIds);

        $eligible = [];
        foreach ($candidates as $candidate) {
            if ($filter->isBlockedReserveTeam($candidate['teamId'], $topDivisionTeamIds, $parentMap)) {
                continue;
            }
            $eligible[] = $candidate;
            if (count($eligible) >= $requiredCount) {
                break;
            }
        }

        return $eligible;
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

    private function getFilter(): ReserveTeamFilter
    {
        return $this->reserveTeamFilter ?? app(ReserveTeamFilter::class);
    }
}
