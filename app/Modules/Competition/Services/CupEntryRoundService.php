<?php

namespace App\Modules\Competition\Services;

use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\Team;

/**
 * Decides the round every club joins each of a country's domestic cups at,
 * once a season's field is known and before the first draw.
 *
 * The field itself is settled upstream (seed data on the first season,
 * DomesticCupQualificationProcessor afterwards); this service only writes
 * `competition_entries.entry_round`. For each cup, in order:
 *
 *  1. Baseline. Every club joins at the round its
 *     data/<season>/<cup>/teams.json entry declared, carried on the
 *     competition_teams row — round one when it declared none. This is the
 *     single source for a cup's shape: the FA Cup's 80 lower-league clubs
 *     in round one and its 44 league clubs in round three are a property of
 *     the data, not of engine code.
 *  2. Supercup. When the country's supercup declares `cup_entry_round`, its
 *     field skips ahead to that round of the main cup (Spain's four Supercopa
 *     clubs join the Copa at the round of 32). A supercup club missing from
 *     the cup is inserted rather than silently skipped: that silent miss is
 *     what once left round 1 odd in production.
 *
 * Rounds beyond the final are clamped to it; nothing else is adjusted. A
 * real cup's rounds are even by federation design, and the drift Spain can
 * produce (reserves climbing divisions) is already absorbed upstream by
 * `cup_qualification.target_size`.
 *
 * Runs from SeasonInitializationService::conductCupDraws, which then draws
 * round 1 of the cups the user's club is in.
 */
class CupEntryRoundService
{
    public function __construct(
        private readonly CountryConfig $countryConfig,
    ) {}

    /**
     * Assign entry rounds for every domestic cup of a country in a game.
     */
    public function assignEntryRounds(string $gameId, string $countryCode): void
    {
        $game = Game::select(['id', 'season', 'base_season'])->find($gameId)
            ?? throw new \RuntimeException("Cannot assign cup entry rounds: game {$gameId} does not exist");

        foreach ($this->countryConfig->domesticCupIds($countryCode) as $cupId) {
            $this->assignForCup($game, $countryCode, $cupId);
        }
    }

    private function assignForCup(Game $game, string $countryCode, string $cupId): void
    {
        $roundCount = LeagueFixtureGenerator::finalKnockoutRound($cupId, $game->base_season);
        if ($roundCount === null) {
            return;
        }

        $this->insertMissingSupercupClubs($game, $countryCode, $cupId);

        $current = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $cupId)
            ->pluck('entry_round', 'team_id')
            ->map(fn ($round) => (int) $round)
            ->all();

        if ($current === []) {
            return;
        }

        $teamIds = array_keys($current);
        $seededRound = $this->seededEntryRounds($cupId, $teamIds);

        // 1. Baseline: the round the cup's data file gave each club.
        $assigned = [];
        foreach ($teamIds as $teamId) {
            $assigned[$teamId] = min($roundCount, max(1, $seededRound[$teamId] ?? 1));
        }

        // 2. Supercup field skips ahead.
        $supercupConfig = $this->countryConfig->supercup($countryCode);
        $supercupEntryRound = (int) ($supercupConfig['cup_entry_round'] ?? 0);
        if ($supercupConfig && ($supercupConfig['cup'] ?? null) === $cupId && $supercupEntryRound > 0) {
            foreach ($this->supercupTeamIds($game->id, $countryCode, $supercupConfig['competition']) as $teamId) {
                if (isset($assigned[$teamId])) {
                    $assigned[$teamId] = $supercupEntryRound;
                }
            }
        }

        $this->persist($game->id, $cupId, $current, $assigned);
    }

    /**
     * A supercup club that is not in the cup at all is upserted at round 1
     * before the rounds are worked out, so the skip-ahead below always finds
     * it. The upstream qualification path scrubs reserves, but a field
     * written by older code can still carry one — those are never inserted.
     */
    private function insertMissingSupercupClubs(Game $game, string $countryCode, string $cupId): void
    {
        $supercupConfig = $this->countryConfig->supercup($countryCode);
        if (!$supercupConfig || ($supercupConfig['cup'] ?? null) !== $cupId || empty($supercupConfig['cup_entry_round'])) {
            return;
        }

        $supercupTeamIds = $this->supercupTeamIds($game->id, $countryCode, $supercupConfig['competition']);
        if ($supercupTeamIds === []) {
            return;
        }

        $alreadyIn = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $cupId)
            ->whereIn('team_id', $supercupTeamIds)
            ->pluck('team_id')
            ->all();

        $missing = array_values(array_diff($supercupTeamIds, $alreadyIn));
        if ($missing === []) {
            return;
        }

        CompetitionEntry::insert(array_map(fn (string $teamId) => [
            'game_id' => $game->id,
            'competition_id' => $cupId,
            'team_id' => $teamId,
            'entry_round' => 1,
        ], $missing));
    }

    /**
     * This season's supercup field, minus any reserve team.
     *
     * @return string[]
     */
    private function supercupTeamIds(string $gameId, string $countryCode, string $supercupId): array
    {
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $supercupId)
            ->pluck('team_id')
            ->all();

        if ($teamIds === []) {
            return [];
        }

        $reserves = Team::where('country', $countryCode)
            ->whereNotNull('parent_team_id')
            ->whereIn('id', $teamIds)
            ->pluck('id')
            ->all();

        return array_values(array_diff($teamIds, $reserves));
    }


    /**
     * The entry round each club's data file declared, from the most recent
     * season's competition_teams row. Ghost clubs have no other source of
     * truth for where they join.
     *
     * @param  string[]  $teamIds
     * @return array<string, int>
     */
    private function seededEntryRounds(string $cupId, array $teamIds): array
    {
        return CompetitionTeam::where('competition_id', $cupId)
            ->whereIn('team_id', $teamIds)
            ->orderBy('season')
            ->get(['team_id', 'entry_round'])
            ->reduce(function (array $carry, CompetitionTeam $row) {
                $carry[$row->team_id] = (int) ($row->entry_round ?? 1);

                return $carry;
            }, []);
    }


    /**
     * Write only the rows whose round changed, one UPDATE per target round.
     *
     * @param  array<string, int>  $current
     * @param  array<string, int>  $balanced
     */
    private function persist(string $gameId, string $cupId, array $current, array $balanced): void
    {
        $changes = [];
        foreach ($balanced as $teamId => $round) {
            if (($current[$teamId] ?? null) !== $round) {
                $changes[$round][] = $teamId;
            }
        }

        foreach ($changes as $round => $teamIds) {
            CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $cupId)
                ->whereIn('team_id', $teamIds)
                ->update(['entry_round' => $round]);
        }
    }
}
