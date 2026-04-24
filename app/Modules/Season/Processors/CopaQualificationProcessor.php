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
 *
 * Reserve teams (Team::parent_team_id is set) never qualify, regardless of
 * finishing position. Teams currently in the cup that are not registered in
 * any playable tier (lower-division seed teams) are left untouched so
 * regional qualifiers keep their cup place.
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
     * @param  array{auto_qualify_tiers?: int[], top_per_group?: array<int, int>}  $rule
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

        foreach ($rule['auto_qualify_tiers'] ?? [] as $tier) {
            foreach ($this->countryConfig->tierCompetitionIds($countryCode, $tier) as $competitionId) {
                foreach ($this->teamsInCompetition($game->id, $competitionId) as $teamId) {
                    if (isset($reserveLookup[$teamId])) {
                        continue;
                    }
                    $qualifiers[$teamId] = true;
                }
            }
        }

        // For each group (including siblings), qualify the top N non-reserve
        // teams — skipping any reserves in higher positions so the group
        // always contributes exactly N qualifiers when enough are eligible.
        foreach ($rule['top_per_group'] ?? [] as $tier => $topN) {
            foreach ($this->countryConfig->tierCompetitionIds($countryCode, $tier) as $competitionId) {
                $picked = 0;
                foreach ($this->rankedTeams($game, $competitionId) as $teamId) {
                    if (isset($reserveLookup[$teamId])) {
                        continue;
                    }
                    $qualifiers[$teamId] = true;
                    $picked++;
                    if ($picked >= $topN) {
                        break;
                    }
                }
            }
        }

        // Remove existing cup entries for teams that are part of the playable
        // tier system — we're redeciding qualification for those. Also drop
        // any reserve teams that may have slipped in previously. Lower-tier
        // seed teams (not registered in any playable tier) are left alone.
        $playableTierTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->whereIn('competition_id', $playableTierCompetitions)
            ->pluck('team_id')
            ->unique()
            ->all();

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
