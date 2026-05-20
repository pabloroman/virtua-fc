<?php

namespace App\Modules\Competition\Promotions;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\TierStandingsMissingException;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Competition\Services\CountryConfig;

/**
 * Reads everything {@see CountryPromotionRelegationPlanner} needs to plan a
 * country's end-of-season promotion/relegation from the database, returning
 * an in-memory {@see CountrySeasonSnapshot}.
 *
 * Standings come from {@see StandingsReader} (which reconciles stale
 * SimulatedSeason rows in-place). Playoff state and winners come from
 * {@see PlayoffGeneratorFactory} plus a direct CupTie lookup keyed on the
 * final round.
 */
class CountrySeasonSnapshotBuilder
{
    public function __construct(
        private readonly CountryConfig $countryConfig,
        private readonly StandingsReader $standingsReader,
        private readonly PlayoffGeneratorFactory $playoffFactory,
    ) {}

    public function build(Game $game): CountrySeasonSnapshot
    {
        $country = $game->country;
        $config = $this->countryConfig->get($country) ?? [];

        $standingsByCompetition = [];
        foreach ($this->tierCompetitionIds($config) as $competitionId) {
            $entries = $this->standingsReader->read($game, $competitionId);
            $standingsByCompetition[$competitionId] = array_column($entries, 'teamId');
            if (empty($standingsByCompetition[$competitionId])) {
                throw TierStandingsMissingException::forCompetition($competitionId);
            }
        }

        $this->assertTierStandingsMatchConfig($config, $standingsByCompetition);

        $reserveToParent = $this->buildReserveMap($country, $standingsByCompetition);

        [$playoffStates, $playoffWinners] = $this->buildPlayoffData($game, $config);

        return new CountrySeasonSnapshot(
            countryCode: $country,
            standingsByCompetition: $standingsByCompetition,
            reserveToParent: $reserveToParent,
            playoffStates: $playoffStates,
            playoffWinners: $playoffWinners,
            userTeamId: $game->team_id,
        );
    }

    /**
     * @return list<string>
     */
    private function tierCompetitionIds(array $config): array
    {
        $ids = [];
        foreach ($config['tiers'] ?? [] as $tier) {
            $ids[] = $tier['competition'];
            foreach ($tier['siblings'] ?? [] as $sibling) {
                if (!empty($sibling['competition'])) {
                    $ids[] = $sibling['competition'];
                }
            }
        }
        return $ids;
    }

    /**
     * Belt-and-braces check that every tier's standings match the team count
     * the country config declares. If they don't, the planner is being handed
     * partial data and will silently produce an unbalanced plan — the failure
     * eventually surfaces as a confusing mid-validation "ends with N teams"
     * error well after the snapshot was built. Throwing at the boundary keeps
     * the operator-facing message pointed at the real precondition violation.
     *
     * @param  array<string, list<string>>  $standingsByCompetition
     */
    private function assertTierStandingsMatchConfig(array $config, array $standingsByCompetition): void
    {
        foreach ($config['tiers'] ?? [] as $tier) {
            $this->assertCompetitionMatchesTierSize(
                $tier['competition'],
                $tier['teams'] ?? null,
                $standingsByCompetition,
            );
            foreach ($tier['siblings'] ?? [] as $sibling) {
                $this->assertCompetitionMatchesTierSize(
                    $sibling['competition'],
                    $sibling['teams'] ?? null,
                    $standingsByCompetition,
                );
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $standingsByCompetition
     */
    private function assertCompetitionMatchesTierSize(
        string $competitionId,
        ?int $expectedSize,
        array $standingsByCompetition,
    ): void {
        if ($expectedSize === null) {
            return;
        }
        $actual = count($standingsByCompetition[$competitionId] ?? []);
        if ($actual !== $expectedSize) {
            throw TierStandingsMissingException::sizeMismatch($competitionId, $actual, $expectedSize);
        }
    }

    /**
     * Build the reserve→parent map for teams currently in the country's
     * playable tiers. We scope to teams the snapshot will reference because
     * an unbounded country-wide query would needlessly load retired/unused
     * reserve records.
     *
     * @param  array<string, list<string>>  $standingsByCompetition
     * @return array<string, string>
     */
    private function buildReserveMap(string $country, array $standingsByCompetition): array
    {
        $allTeamIds = [];
        foreach ($standingsByCompetition as $teams) {
            foreach ($teams as $teamId) {
                $allTeamIds[$teamId] = true;
            }
        }

        if (empty($allTeamIds)) {
            return [];
        }

        $rows = Team::where('country', $country)
            ->whereNotNull('parent_team_id')
            ->whereIn('id', array_keys($allTeamIds))
            ->get(['id', 'parent_team_id']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->id] = $row->parent_team_id;
        }
        return $map;
    }

    /**
     * Read the lifecycle state and (if Completed) winners for every playoff
     * competition this country has.
     *
     * @return array{0: array<string, PlayoffState>, 1: array<string, list<string>>}
     */
    private function buildPlayoffData(Game $game, array $config): array
    {
        $states = [];
        $winners = [];

        $playoffComps = $this->playoffCompetitionIds($config);
        foreach ($playoffComps as $playoffComp) {
            $generator = $this->playoffFactory->forCompetition($playoffComp);
            if ($generator !== null) {
                $states[$playoffComp] = $generator->state($game);
            } else {
                // No generator registered means the playoff hasn't been wired
                // (e.g. a rule without playoff_generator) — treat as "no playoff".
                $states[$playoffComp] = PlayoffState::NotStarted;
            }

            if ($states[$playoffComp] === PlayoffState::Completed) {
                $winners[$playoffComp] = $this->readFinalWinners($game, $playoffComp, $generator?->getTotalRounds() ?? 2);
            }
        }

        return [$states, $winners];
    }

    /**
     * @return list<string>
     */
    private function playoffCompetitionIds(array $config): array
    {
        $ids = [];
        foreach ($config['promotions'] ?? [] as $rule) {
            $playoffComp = $rule['playoff_competition'] ?? $rule['bottom_division'] ?? null;
            if ($playoffComp !== null && !in_array($playoffComp, $ids, true)) {
                $ids[] = $playoffComp;
            }
        }
        return $ids;
    }

    /**
     * Read the bracket-final winner team IDs for a playoff competition. Order
     * by bracket_position so multi-bracket formats (Primera RFEF) hand back
     * winners in a stable order.
     *
     * @return list<string>
     */
    private function readFinalWinners(Game $game, string $playoffComp, int $totalRounds): array
    {
        $finals = CupTie::where('game_id', $game->id)
            ->where('competition_id', $playoffComp)
            ->where('round_number', $totalRounds)
            ->where('completed', true)
            ->whereNotNull('winner_id')
            ->orderBy('bracket_position')
            ->pluck('winner_id')
            ->all();

        return array_values($finals);
    }
}
