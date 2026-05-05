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
 * Algorithm (in order):
 *  1. Add every non-reserve team from each tier in `auto_qualify_tiers`
 *     (e.g. ESP1, ESP2 → all teams in those leagues).
 *  2. Add the top N non-reserves from each competition in `top_per_group`
 *     (e.g. ESP3A and ESP3B → top 5 each).
 *  3. Preserve any regional teams already in the cup that aren't in any
 *     playable tier (lower-division seed teams from data/<year>/ESPCUP).
 *  3b. Force-include any team in this country's supercup field. The
 *      downstream setup pipeline bumps these teams to a later entry_round
 *      via an UPDATE — if a supercup-qualifying cup finalist (e.g. a
 *      tier-3 cup winner outside its group's top 5) isn't in this rebuilt
 *      cup field, the bump silently misses it and round 1 ends up odd.
 *  4. If `target_size` is set, fill to that size by walking the next
 *     positions in the `top_per_group` competitions, skipping reserves
 *     and teams already qualified.
 *  5. If after step 4 the field is still smaller than `target_size`,
 *     throw — the cup is the parity invariant (an even round-1 pool
 *     after the supercup bump), and silent shortfalls are what produced
 *     the 93 broken Copa del Rey draws in production.
 *
 * Reserve teams (Team::parent_team_id is set) never qualify, regardless
 * of finishing position. They're skipped at every step.
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
     * Build the cup field for a country in the order that mirrors the rule:
     *
     *   1. All teams from auto_qualify_tiers (ESP1, ESP2 — minus reserves).
     *   2. Top N from each top_per_group competition (ESP3A/B top 5 — minus reserves).
     *   3. Pre-existing regional teams already in the cup (lower-division
     *      seed teams not registered in any playable tier).
     *   3b. Force-include the country's supercup-qualifying teams (so the
     *       downstream entry_round bump always finds them and round 1 stays even).
     *   4. Fill any remaining slots up to target_size from later positions
     *      in the top_per_group competitions.
     *
     * If after step 4 the field still has fewer than target_size teams,
     * throw — the cup is the parity invariant and silent shortfalls are
     * what produced the 93 broken Copa del Rey draws in production.
     *
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

        // Skip when this country isn't part of the game (no entries in any
        // playable tier). The target_size invariant below catches partial-
        // data shortfalls — wholly absent data is a different signal.
        $hasAnyTierEntries = CompetitionEntry::where('game_id', $game->id)
            ->whereIn('competition_id', $playableTierCompetitions)
            ->exists();
        if (!$hasAnyTierEntries) {
            return;
        }

        $reserveLookup = array_flip($reserveTeamIdsForCountry);
        $qualifiers = [];

        // 1. Auto-qualify tiers.
        foreach ($rule['auto_qualify_tiers'] ?? [] as $tier) {
            foreach ($this->countryConfig->tierCompetitionIds($countryCode, $tier) as $competitionId) {
                foreach ($this->teamsInCompetition($game->id, $competitionId) as $teamId) {
                    if (!isset($reserveLookup[$teamId])) {
                        $qualifiers[$teamId] = true;
                    }
                }
            }
        }

        // 2. Top N per group. Collect the group competition IDs in order so
        //    step 4 can revisit them to backfill any remaining slots.
        $groupCompetitionIds = [];
        foreach ($rule['top_per_group'] ?? [] as $tier => $topN) {
            foreach ($this->countryConfig->tierCompetitionIds($countryCode, $tier) as $competitionId) {
                $groupCompetitionIds[] = $competitionId;
                $picked = 0;
                foreach ($this->rankedTeams($game, $competitionId) as $teamId) {
                    if (isset($reserveLookup[$teamId])) {
                        continue;
                    }
                    if (isset($qualifiers[$teamId])) {
                        continue;
                    }
                    $qualifiers[$teamId] = true;
                    if (++$picked >= $topN) {
                        break;
                    }
                }
            }
        }

        // 3. Preserve regional teams currently in the cup (in cup entries
        //    but not registered in any playable tier and not reserves).
        $playableTierTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->whereIn('competition_id', $playableTierCompetitions)
            ->pluck('team_id')
            ->unique()
            ->all();

        $excludeFromRegional = array_unique(array_merge($playableTierTeamIds, $reserveTeamIdsForCountry));
        $regionalQuery = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $cupId);
        if (!empty($excludeFromRegional)) {
            $regionalQuery->whereNotIn('team_id', $excludeFromRegional);
        }
        foreach ($regionalQuery->pluck('team_id') as $teamId) {
            $qualifiers[$teamId] = true;
        }

        // 3b. Force-include the supercup-qualifying teams. The supercup
        //     processor (priority 80) ran before us and persisted the new
        //     season's supercup field; downstream the setup pipeline bumps
        //     these teams from cup round 1 to a later entry_round so the
        //     supercup field skips the early rounds. That bump is an
        //     UPDATE on existing rows — if a supercup team isn't in this
        //     cup field, it silently isn't bumped, and round 1 ends up
        //     odd (the OddCupDrawPoolException case). League top 1–2 are
        //     always ESP1/auto-qualify, but cup finalists can be anyone:
        //     a tier-3 cup winner outside its group's top 5 falls through
        //     steps 1-3 and may not be reached by step 4. Pull them in
        //     explicitly so the parity invariant holds.
        $supercupConfig = $this->countryConfig->supercup($countryCode);
        if ($supercupConfig && ($supercupConfig['cup'] ?? null) === $cupId) {
            $supercupTeamIds = CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $supercupConfig['competition'])
                ->pluck('team_id');
            foreach ($supercupTeamIds as $teamId) {
                if (isset($reserveLookup[$teamId])) {
                    continue;
                }
                $qualifiers[$teamId] = true;
            }
        }

        // 4. Fill to target_size from remaining top_per_group competitions
        //    (positions beyond the top-N already taken).
        $targetSize = $rule['target_size'] ?? null;
        if ($targetSize !== null) {
            foreach ($groupCompetitionIds as $competitionId) {
                if (count($qualifiers) >= $targetSize) {
                    break;
                }
                foreach ($this->rankedTeams($game, $competitionId) as $teamId) {
                    if (count($qualifiers) >= $targetSize) {
                        break;
                    }
                    if (isset($reserveLookup[$teamId])) {
                        continue;
                    }
                    if (isset($qualifiers[$teamId])) {
                        continue;
                    }
                    $qualifiers[$teamId] = true;
                }
            }

            if (count($qualifiers) < $targetSize) {
                throw new \RuntimeException(sprintf(
                    '[CopaQualification] %s for game %s: target_size %d unreachable, got %d. '
                    . 'Check cup_qualification rule, reserve filtering, or league sizes.',
                    $cupId,
                    $game->id,
                    $targetSize,
                    count($qualifiers),
                ));
            }
        }

        // Replace cup entries: clear playable-tier + reserves, upsert the
        // new field. Regional teams added in step 3 are preserved by the
        // upsert (no rows for them are deleted).
        $teamIdsToClear = array_unique(array_merge($playableTierTeamIds, $reserveTeamIdsForCountry));
        if (!empty($teamIdsToClear)) {
            CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $cupId)
                ->whereIn('team_id', $teamIdsToClear)
                ->delete();
        }

        if (empty($qualifiers)) {
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

        Log::info("[CopaQualification] {$cupId}: " . count($rows) . ' qualifiers');
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
