<?php

namespace App\Modules\Competition\Promotions;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\ReserveTeamFilter;
use Illuminate\Support\Facades\Log;

/**
 * After promotion/relegation completes, repair any case where a parent team
 * ended up in the same competition as its reserve.
 *
 * The per-rule swap logic only consults ReserveTeamFilter when deciding who
 * gets promoted (a reserve whose parent is in the top division is held back).
 * The symmetric case is uncovered: when the parent gets *relegated* into the
 * reserve's tier, the reserve sits idle in the bottom division while its
 * parent arrives, and the two end up sharing a competition.
 *
 * This cascader walks the country's promotion chain top → bottom. At each
 * tier, it finds parent/reserve same-competition pairs, demotes the reserve
 * one tier further, and backfills the current tier with the highest-eligible
 * team from the destination tier. Conflicts at the bottom of the chain are
 * logged and accepted — there's no lower tier to cascade into.
 *
 * Pre-existing legacy conflicts (e.g. a reserve that drifted into the top
 * division across multiple seasons of the original bug) are also healed,
 * because the cascader doesn't care *how* the conflict arose.
 */
class ReserveCascader
{
    public function __construct(
        private readonly CountryConfig $countryConfig,
        private readonly ReserveTeamFilter $reserveFilter,
    ) {}

    /**
     * Repair all parent/reserve same-competition pairs for the given game.
     *
     * @return string[] Competition IDs that had roster changes — caller
     *                  should re-simulate these.
     */
    public function cascade(Game $game): array
    {
        $affected = [];

        foreach ($this->countryConfig->allCountryCodes() as $countryCode) {
            $chain = $this->buildChain($countryCode);
            if (empty($chain)) {
                continue;
            }

            $bottomLevel = count($chain) - 1;

            foreach ($chain as $level => $competitions) {
                $isBottom = $level === $bottomLevel;

                foreach ($competitions as $competitionId) {
                    $conflicts = $this->findConflicts($game, $competitionId);
                    if (empty($conflicts)) {
                        continue;
                    }

                    if ($isBottom) {
                        Log::warning('Reserve/parent conflict at bottom of chain — accepting', [
                            'game_id' => $game->id,
                            'competition_id' => $competitionId,
                            'pairs' => $conflicts,
                        ]);
                        continue;
                    }

                    $destinations = $chain[$level + 1];

                    foreach ($conflicts as $conflict) {
                        $destination = $this->chooseDestination($game, $destinations);
                        $replacementId = $this->pickReplacement($game, $destination, $competitionId);

                        $this->moveTeam($game, $conflict['reserveId'], $competitionId, $destination);

                        if ($replacementId !== null) {
                            $this->moveTeam($game, $replacementId, $destination, $competitionId);
                        }

                        $affected[$competitionId] = true;
                        $affected[$destination] = true;

                        Log::info('Reserve cascaded down a tier', [
                            'game_id' => $game->id,
                            'from' => $competitionId,
                            'to' => $destination,
                            'reserve_id' => $conflict['reserveId'],
                            'parent_id' => $conflict['parentId'],
                            'replacement_id' => $replacementId,
                        ]);
                    }
                }
            }
        }

        foreach (array_keys($affected) as $competitionId) {
            $this->resortPositions($game, $competitionId);
        }

        return array_keys($affected);
    }

    /**
     * Build the country's promotion chain as a list of tier-levels. Level 0
     * is the topmost league; deeper indices are progressively lower tiers.
     * Sibling competitions at the same tier (e.g. ESP3A + ESP3B) appear in
     * the same level array.
     *
     * @return string[][]
     */
    private function buildChain(string $countryCode): array
    {
        $promotions = $this->countryConfig->promotions($countryCode);
        if (empty($promotions)) {
            return [];
        }

        $chain = [[$promotions[0]['top_division']]];

        foreach ($promotions as $rule) {
            $level = [$rule['bottom_division']];
            if (!empty($rule['playoff_source_divisions'])) {
                $level = array_values(array_unique(array_merge(
                    $level,
                    $rule['playoff_source_divisions'],
                )));
            }
            $chain[] = $level;
        }

        return $chain;
    }

    /**
     * Find parent/reserve pairs that share the given competition.
     *
     * @return array<array{parentId: string, reserveId: string}>
     */
    private function findConflicts(Game $game, string $competitionId): array
    {
        $teamIds = $this->rosterIds($game, $competitionId);
        if (empty($teamIds)) {
            return [];
        }

        $parentMap = $this->reserveFilter->loadParentTeamIds($teamIds);
        $idSet = array_flip($teamIds);

        $conflicts = [];
        foreach ($parentMap as $reserveId => $parentId) {
            if (isset($idSet[$parentId])) {
                $conflicts[] = ['parentId' => $parentId, 'reserveId' => $reserveId];
            }
        }

        return $conflicts;
    }

    /**
     * When the next tier is split (ESP3A/ESP3B), prefer the smaller-roster
     * competition so cascading doesn't push one group over its capacity.
     *
     * @param string[] $candidates
     */
    private function chooseDestination(Game $game, array $candidates): string
    {
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $bestId = $candidates[0];
        $bestCount = PHP_INT_MAX;

        foreach ($candidates as $competitionId) {
            $count = CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->count();

            if ($count < $bestCount) {
                $bestCount = $count;
                $bestId = $competitionId;
            }
        }

        return $bestId;
    }

    /**
     * Pick the highest-eligible team from $source to backfill $destination
     * after we demote a reserve out of it. Skips any candidate whose parent
     * is in the destination — that would just recreate the conflict.
     */
    private function pickReplacement(Game $game, string $source, string $destination): ?string
    {
        $ordered = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $source)
            ->orderBy('position')
            ->pluck('team_id')
            ->all();

        if (empty($ordered)) {
            // Simulated leagues have no standings — fall back to entry order,
            // which at least produces a stable choice.
            $ordered = CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $source)
                ->pluck('team_id')
                ->all();
        }

        if (empty($ordered)) {
            return null;
        }

        $destinationTeamIds = collect($this->rosterIds($game, $destination));
        $parentMap = $this->reserveFilter->loadParentTeamIds($ordered);

        foreach ($ordered as $candidateId) {
            if ($this->reserveFilter->isBlockedReserveTeam($candidateId, $destinationTeamIds, $parentMap)) {
                continue;
            }

            return $candidateId;
        }

        return null;
    }

    /**
     * Prefer standings (real or post-swap); fall back to competition entries
     * for simulated leagues that have no standings rows.
     *
     * @return string[]
     */
    private function rosterIds(Game $game, string $competitionId): array
    {
        $ids = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();

        if (!empty($ids)) {
            return $ids;
        }

        return CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();
    }

    private function moveTeam(Game $game, string $teamId, string $from, string $to): void
    {
        CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $from)
            ->where('team_id', $teamId)
            ->delete();

        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $game->id,
                'competition_id' => $to,
                'team_id' => $teamId,
            ],
            ['entry_round' => 1],
        );

        GameStanding::where('game_id', $game->id)
            ->where('competition_id', $from)
            ->where('team_id', $teamId)
            ->delete();

        $targetHasStandings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $to)
            ->exists();

        if ($targetHasStandings) {
            GameStanding::firstOrCreate([
                'game_id' => $game->id,
                'competition_id' => $to,
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

        if ($teamId === $game->team_id) {
            Game::where('id', $game->id)->update(['competition_id' => $to]);
            $game->competition_id = $to;
        }
    }

    private function resortPositions(Game $game, string $competitionId): void
    {
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->orderBy('position')
            ->get();

        foreach ($standings->values() as $index => $standing) {
            $newPosition = $index + 1;
            if ($standing->position !== $newPosition) {
                $standing->update(['position' => $newPosition]);
            }
        }
    }
}
