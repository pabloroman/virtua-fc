<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\DTOs\PromotionSlotAllocation;
use App\Modules\Competition\Services\ReserveTeamFilter;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;

/**
 * Single source of truth for assigning teams to a competition's
 * direct-promotion and playoff-bracket slots.
 *
 * The historical bug this exists to prevent: ConfigDrivenPromotionRule and
 * ESP2PlayoffGenerator each ran their own reserve-team filter against
 * overlapping standings ranges. When a reserve team held a direct-promotion
 * position, the direct list slid down into the bracket's range and the same
 * team appeared in both — silently corrupting promotion at the array_merge.
 *
 * The allocator walks the standings exactly once in position order, skips
 * blocked reserve teams, and hands out the first $directCount eligible teams
 * to direct promotion and the next $playoffCount to the bracket. By
 * construction, no team can appear in both lists.
 *
 * The order in $playoffQualifiers preserves standings ranking — bracket
 * generators consume it as the seeding order (index 0 is the top seed).
 */
class PromotionSlotAllocator
{
    /**
     * Extra standings rows to fetch beyond the slot total. Absorbs reserve
     * clustering at the top of the table without paginating. Sized for the
     * worst plausible Spanish case (Real Madrid Castilla, Sevilla Atlético,
     * Atlético Madrileño, Bilbao Athletic… all in ESP2 simultaneously).
     */
    private const RESERVE_BUFFER = 10;

    public function __construct(
        private readonly ReserveTeamFilter $reserveFilter,
    ) {}

    /**
     * @param  Game  $game  The game whose standings we're reading.
     * @param  string  $bottomDivision  Competition ID being walked (e.g., 'ESP2').
     * @param  int  $directCount  Number of direct-promotion slots to fill.
     * @param  int  $playoffCount  Number of bracket slots to fill.
     */
    public function allocate(
        Game $game,
        string $bottomDivision,
        int $directCount,
        int $playoffCount,
    ): PromotionSlotAllocation {
        $orderedTeams = $this->loadOrderedTeams($game, $bottomDivision, $directCount + $playoffCount);

        if ($orderedTeams === []) {
            return new PromotionSlotAllocation(directPromotions: [], playoffQualifiers: []);
        }

        $topDivisionTeamIds = $this->reserveFilter->getTopDivisionTeamIds($game, $bottomDivision);
        $parentMap = $this->reserveFilter->loadParentTeamIds(
            array_column($orderedTeams, 'teamId')
        );

        $eligible = [];
        foreach ($orderedTeams as $entry) {
            if ($this->reserveFilter->isBlockedReserveTeam(
                $entry['teamId'], $topDivisionTeamIds, $parentMap
            )) {
                continue;
            }
            $eligible[] = $entry;
        }

        return new PromotionSlotAllocation(
            directPromotions: array_slice($eligible, 0, $directCount),
            playoffQualifiers: array_slice($eligible, $directCount, $playoffCount),
        );
    }

    /**
     * Pull the top-of-table teams in position order, preferring real
     * GameStanding rows and falling back to SimulatedSeason. Returns
     * standardised entries so the caller doesn't care about the source.
     *
     * @return array<int, array{teamId: string, position: int, teamName: string}>
     */
    private function loadOrderedTeams(Game $game, string $competitionId, int $needed): array
    {
        $fetchCount = $needed + self::RESERVE_BUFFER;

        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->with('team')
            ->orderBy('position')
            ->limit($fetchCount)
            ->get();

        if ($standings->isNotEmpty()) {
            return $standings->map(fn ($s) => [
                'teamId' => $s->team_id,
                'position' => $s->position,
                'teamName' => $s->team->name ?? 'Unknown',
            ])->values()->toArray();
        }

        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competitionId)
            ->first();

        if (!$simulated || empty($simulated->results)) {
            return [];
        }

        $results = array_slice($simulated->results, 0, $fetchCount);
        $teams = Team::whereIn('id', $results)->get()->keyBy('id');

        $entries = [];
        foreach ($results as $index => $teamId) {
            if (!isset($teams[$teamId])) {
                continue;
            }
            $entries[] = [
                'teamId' => $teamId,
                'position' => $index + 1,
                'teamName' => $teams[$teamId]->name,
            ];
        }

        return $entries;
    }
}
