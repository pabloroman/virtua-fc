<?php

namespace App\Modules\Season\Processors;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds domestic cup participants (e.g. Copa del Rey) at the end of each
 * season based on the configured qualification rules.
 *
 * Runs before PromotionRelegationProcessor (priority 85) so that this
 * season's CompetitionEntry still reflects this season's divisions and
 * GameStanding still contains the full final table for every tier.
 *
 * Rules live under each country's `cup_qualification` config:
 *  - auto_qualify_tiers: every team in these tiers qualifies for the next
 *    season's cup.
 *  - top_per_group: the top N teams in each competition at this tier
 *    (including siblings — e.g. ESP3A and ESP3B) qualify.
 *  - target_size (optional): total cup size to maintain. After the rule
 *    pass, the processor pulls additional non-reserves from top_per_group
 *    groups round-robin until qualifiers + untouched regional seed teams
 *    equal this number. Without it the cup permanently shrinks each
 *    season because the rule replaces seeded ESP3 teams with fewer.
 *
 * Reserve teams (Team::parent_team_id is set) never qualify, regardless of
 * finishing position. When a reserve occupies a slot in an auto_qualify
 * tier, its seat cascades to top_per_group: for each ineligible reserve,
 * one additional pick is made from the groups (distributed round-robin),
 * so the cup stays at its expected size even as reserves climb divisions.
 *
 * Teams currently in the cup that are not registered in any playable tier
 * (lower-division seed teams) are left untouched so regional qualifiers
 * keep their cup place.
 */
class CopaQualificationProcessor implements SeasonProcessor
{
    public function __construct(
        private CountryConfig $countryConfig,
    ) {}

    public function priority(): int
    {
        return 82;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        foreach ($this->countryConfig->allCountryCodes() as $countryCode) {
            $reserveTeamIdsForCountry = Team::where('country', $countryCode)
                ->whereNotNull('parent_team_id')
                ->pluck('id')
                ->all();

            foreach ($this->countryConfig->domesticCupIds($countryCode) as $cupId) {
                $rule = $this->countryConfig->cupQualification($countryCode, $cupId);
                if (!$rule) {
                    continue;
                }

                $this->rebuildCupEntries($game, $countryCode, $cupId, $rule, $reserveTeamIdsForCountry);
            }
        }

        return $data;
    }

    /**
     * @param  array{auto_qualify_tiers?: int[], top_per_group?: array<int, int>, target_size?: int}  $rule
     * @param  string[]  $reserveTeamIdsForCountry
     */
    private function rebuildCupEntries(
        Game $game,
        string $countryCode,
        string $cupId,
        array $rule,
        array $reserveTeamIdsForCountry,
    ): void {
        $playableTierCompetitions = $this->playableTierCompetitions($countryCode);
        if (empty($playableTierCompetitions)) {
            return;
        }

        $reserveLookup = array_flip($reserveTeamIdsForCountry);
        $qualifiers = [];
        $shortage = 0;

        foreach ($rule['auto_qualify_tiers'] ?? [] as $tier) {
            foreach ($this->countryConfig->tierCompetitionIds($countryCode, $tier) as $competitionId) {
                foreach ($this->teamsInCompetition($game->id, $competitionId) as $teamId) {
                    if (isset($reserveLookup[$teamId])) {
                        // Reserve team occupies a league slot but can't play
                        // in the cup — its seat cascades down to the next
                        // top_per_group pick so the bracket stays full.
                        $shortage++;
                        continue;
                    }
                    $qualifiers[$teamId] = true;
                }
            }
        }

        // Gather every group (primary + siblings) with its base quota so we
        // can distribute any cascaded seats round-robin across groups.
        $groups = [];
        foreach ($rule['top_per_group'] ?? [] as $tier => $topN) {
            foreach ($this->countryConfig->tierCompetitionIds($countryCode, $tier) as $competitionId) {
                $groups[] = ['competition' => $competitionId, 'topN' => $topN];
            }
        }

        if (!empty($groups) && $shortage > 0) {
            for ($i = 0; $i < $shortage; $i++) {
                $groups[$i % count($groups)]['topN']++;
            }
        }

        // Pre-load each group's ranked team list once, with a cursor so we
        // can resume picking past the base topN during the target-size
        // top-up phase below.
        $groupRanked = array_map(
            fn (array $g) => $this->rankedTeams($game, $g['competition']),
            $groups,
        );
        $groupCursors = array_fill(0, count($groups), 0);

        // Phase 1: respect each group's base topN (the long-standing
        // semantics — exactly N non-reserve picks per group when possible).
        foreach ($groups as $idx => $group) {
            $picked = 0;
            while (
                $picked < $group['topN']
                && $groupCursors[$idx] < count($groupRanked[$idx])
            ) {
                $teamId = $groupRanked[$idx][$groupCursors[$idx]++];
                if (isset($reserveLookup[$teamId])) {
                    continue;
                }
                if (isset($qualifiers[$teamId])) {
                    continue;
                }
                $qualifiers[$teamId] = true;
                $picked++;
            }
        }

        // Lower-tier seed teams (in the cup but not registered in any
        // playable tier and not reserves) survive the rebuild, so they
        // count toward the target_size invariant alongside qualifiers.
        $playableTierTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->whereIn('competition_id', $playableTierCompetitions)
            ->pluck('team_id')
            ->unique()
            ->all();

        $untouchedCount = $this->countUntouchedCupTeams(
            $game->id,
            $cupId,
            $playableTierTeamIds,
            $reserveTeamIdsForCountry,
        );

        // Phase 2: top up to target_size by pulling next-ranked non-reserves
        // round-robin across groups. Skips exhausted groups; stops when
        // either the target is reached or every group is exhausted.
        $targetSize = $rule['target_size'] ?? null;
        if ($targetSize !== null && !empty($groups)) {
            $deficit = $targetSize - count($qualifiers) - $untouchedCount;
            $idx = 0;
            $consecutiveExhausted = 0;
            $groupCount = count($groups);

            while ($deficit > 0 && $consecutiveExhausted < $groupCount) {
                $teamId = $this->advanceCursor($groupRanked[$idx], $groupCursors, $idx, $reserveLookup, $qualifiers);

                if ($teamId === null) {
                    $consecutiveExhausted++;
                } else {
                    $qualifiers[$teamId] = true;
                    $deficit--;
                    $consecutiveExhausted = 0;
                }

                $idx = ($idx + 1) % $groupCount;
            }

            if ($deficit > 0) {
                // Target size is the cup's parity invariant — odd
                // shortfalls are exactly what produced the 93 broken
                // Copa del Rey draws in production. Throw rather than
                // log: silent shortfalls hide the bug we just fixed.
                throw new \RuntimeException(sprintf(
                    '[CopaQualification] %s: target_size %d unreachable for game %s '
                    . '(short by %d, qualifier_pool exhausted across all top_per_group groups). '
                    . 'Check cup_qualification rule, reserve filtering, or league sizes.',
                    $cupId,
                    $targetSize,
                    $game->id,
                    $deficit,
                ));
            }
        }

        // Remove existing cup entries for teams that are part of the playable
        // tier system — we're redeciding qualification for those. Also drop
        // any reserve teams that may have slipped in previously. Lower-tier
        // seed teams (not registered in any playable tier) are left alone.
        $teamIdsToClear = array_unique(array_merge($playableTierTeamIds, $reserveTeamIdsForCountry));

        if (!empty($teamIdsToClear)) {
            CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $cupId)
                ->whereIn('team_id', $teamIdsToClear)
                ->delete();
        }

        if (empty($qualifiers)) {
            Log::info("[CopaQualification] {$cupId}: no qualifiers after filtering reserves");

            return;
        }

        $rows = array_map(fn (string $teamId) => [
            'game_id' => $game->id,
            'competition_id' => $cupId,
            'team_id' => $teamId,
            'entry_round' => 1,
        ], array_keys($qualifiers));

        CompetitionEntry::upsert(
            $rows,
            ['game_id', 'competition_id', 'team_id'],
            ['entry_round']
        );

        Log::info("[CopaQualification] {$cupId}: " . count($rows) . ' qualifiers from playable tiers');
    }

    /**
     * Advance the cursor for one group past reserves and already-qualified
     * teams, returning the next eligible team_id or null if exhausted.
     *
     * @param  array<int, string>  $ranked
     * @param  array<int, int>  $cursors
     * @param  array<string, int>  $reserveLookup
     * @param  array<string, true>  $qualifiers
     */
    private function advanceCursor(array $ranked, array &$cursors, int $idx, array $reserveLookup, array $qualifiers): ?string
    {
        while ($cursors[$idx] < count($ranked)) {
            $teamId = $ranked[$cursors[$idx]++];

            if (isset($reserveLookup[$teamId])) {
                continue;
            }
            if (isset($qualifiers[$teamId])) {
                continue;
            }

            return $teamId;
        }

        return null;
    }

    /**
     * Count cup entries that survive the rebuild — i.e. teams in the cup
     * but not registered in any playable tier and not reserves.
     *
     * @param  string[]  $playableTierTeamIds
     * @param  string[]  $reserveTeamIdsForCountry
     */
    private function countUntouchedCupTeams(
        string $gameId,
        string $cupId,
        array $playableTierTeamIds,
        array $reserveTeamIdsForCountry,
    ): int {
        $excluded = array_unique(array_merge($playableTierTeamIds, $reserveTeamIdsForCountry));

        $query = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $cupId);

        if (!empty($excluded)) {
            $query->whereNotIn('team_id', $excluded);
        }

        return $query->count();
    }

    /**
     * @return string[]
     */
    private function playableTierCompetitions(string $countryCode): array
    {
        $ids = [];
        foreach (array_keys($this->countryConfig->tiers($countryCode)) as $tier) {
            foreach ($this->countryConfig->tierCompetitionIds($countryCode, $tier) as $competitionId) {
                $ids[] = $competitionId;
            }
        }

        return $ids;
    }

    /**
     * @return string[]
     */
    private function teamsInCompetition(string $gameId, string $competitionId): array
    {
        return CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();
    }

    /**
     * Full ranked team list for a competition — real standings first, then
     * simulated season results as fallback. Ordered from 1st position down.
     *
     * @return string[]
     */
    private function rankedTeams(Game $game, string $competitionId): array
    {
        $teams = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('played', '>', 0)
            ->orderBy('position')
            ->pluck('team_id')
            ->all();

        if (!empty($teams)) {
            return $teams;
        }

        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competitionId)
            ->first();

        if (!$simulated || empty($simulated->results)) {
            return [];
        }

        return $simulated->results;
    }
}
