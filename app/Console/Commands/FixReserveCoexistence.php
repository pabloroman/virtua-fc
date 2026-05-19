<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recovery command for games whose reserve-team invariant has drifted:
 * a reserve club (Team::parent_team_id NOT NULL) sharing a competition
 * with its parent. The upstream root cause — the cross-rule parent-
 * relegation/reserve-promotion collision in PromotionRelegationProcessor —
 * is fixed going forward, but games that accumulated the violation over
 * many seasons need a one-off correction.
 *
 * Per affected game: for each (reserve, parent, competition) triplet,
 * find the next-most-eligible non-reserve team in a lower tier that
 * doesn't include the reserve's parent, and swap places. The vacated
 * top-tier slot is filled with that lower-tier team; the reserve drops
 * down into the slot the swapped team left behind. This preserves
 * division sizes — every league keeps its team count.
 *
 * Side-effects per affected game:
 *   - Clear ESPSUP entries so SupercupQualificationProcessor will re-derive
 *     the field with the corrected ESP1 roster on the next pipeline run.
 *   - Reset `season_transition_step` to NULL if the game is mid-transition
 *     so the pipeline restarts cleanly from the beginning of the closing
 *     phase (idempotent processors will short-circuit the already-done work).
 *
 * Safe to re-run: each step is idempotent. Default mode is dry-run; pass
 * --fix to apply.
 */
class FixReserveCoexistence extends Command
{
    protected $signature = 'app:fix-reserve-coexistence {--game=} {--fix}';

    protected $description = 'Detect and (optionally) repair reserve teams sharing a competition with their parent club.';

    public function handle(CountryConfig $countryConfig): int
    {
        $apply = (bool) $this->option('fix');
        $specificGame = $this->option('game');

        $games = Game::query()
            ->when($specificGame, fn ($q) => $q->where('id', $specificGame))
            ->get(['id', 'country', 'season', 'season_transition_step']);

        if ($games->isEmpty()) {
            $this->info('No games found.');

            return self::SUCCESS;
        }

        $totalViolations = 0;
        $totalRepairs = 0;

        foreach ($games as $game) {
            $country = $game->country ?? 'ES';
            $reserveTeams = Team::where('country', $country)
                ->whereNotNull('parent_team_id')
                ->get(['id', 'name', 'parent_team_id']);

            if ($reserveTeams->isEmpty()) {
                continue;
            }

            $violations = $this->findViolations($game->id, $reserveTeams);

            if ($violations->isEmpty()) {
                continue;
            }

            $this->line("Game {$game->id} (season {$game->season}, country {$country}): " . $violations->count() . ' violation(s)');
            $totalViolations += $violations->count();

            if (!$apply) {
                foreach ($violations as $v) {
                    $this->line("  - reserve {$v['reserve_name']} ({$v['reserve_id']}) and parent {$v['parent_id']} share {$v['competition_id']}");
                }
                continue;
            }

            $repaired = $this->repairGame($game, $country, $violations, $countryConfig);
            $totalRepairs += $repaired;

            $this->info("  → repaired {$repaired} violation(s) and reset pipeline state");
        }

        $this->line('');
        $this->info("Scanned {$games->count()} game(s) — {$totalViolations} violation(s) detected" . ($apply ? ", {$totalRepairs} repaired." : '. Re-run with --fix to apply.'));

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{
     *     reserve_id: string,
     *     reserve_name: string,
     *     parent_id: string,
     *     competition_id: string,
     * }>
     */
    private function findViolations(string $gameId, \Illuminate\Support\Collection $reserveTeams)
    {
        $reservesById = $reserveTeams->keyBy('id');

        $reserveEntries = CompetitionEntry::where('game_id', $gameId)
            ->whereIn('team_id', $reservesById->keys())
            ->get(['competition_id', 'team_id']);

        if ($reserveEntries->isEmpty()) {
            return collect();
        }

        $parentIds = $reservesById->pluck('parent_team_id')->unique()->all();
        $parentEntries = CompetitionEntry::where('game_id', $gameId)
            ->whereIn('team_id', $parentIds)
            ->get(['competition_id', 'team_id'])
            ->groupBy('team_id')
            ->map(fn ($e) => $e->pluck('competition_id')->all());

        return $reserveEntries->map(function ($entry) use ($reservesById, $parentEntries) {
            $reserve = $reservesById->get($entry->team_id);
            $parentDivisions = $parentEntries->get($reserve->parent_team_id, []);

            if (!in_array($entry->competition_id, $parentDivisions, true)) {
                return null;
            }

            return [
                'reserve_id' => $entry->team_id,
                'reserve_name' => $reserve->name,
                'parent_id' => $reserve->parent_team_id,
                'competition_id' => $entry->competition_id,
            ];
        })->filter()->values();
    }

    /**
     * Apply repairs inside a transaction so a partial failure rolls back
     * cleanly. Returns the number of successful swaps.
     *
     * @param  \Illuminate\Support\Collection<int, array{reserve_id: string, reserve_name: string, parent_id: string, competition_id: string}>  $violations
     */
    private function repairGame(Game $game, string $country, $violations, CountryConfig $countryConfig): int
    {
        return DB::transaction(function () use ($game, $country, $violations, $countryConfig) {
            $repaired = 0;

            foreach ($violations as $v) {
                $replacement = $this->findReplacement($game->id, $country, $v, $countryConfig);

                if ($replacement === null) {
                    Log::warning('[ReserveCoexistenceFix] No replacement found — skipping', [
                        'game_id' => $game->id,
                        'violation' => $v,
                    ]);
                    $this->warn("    no replacement found for {$v['reserve_name']} — skipped");
                    continue;
                }

                $this->swapTeams(
                    $game->id,
                    reserveId: $v['reserve_id'],
                    replacementId: $replacement['team_id'],
                    reserveCompetition: $v['competition_id'],
                    replacementCompetition: $replacement['competition_id'],
                );

                Log::info('[ReserveCoexistenceFix] Swapped', [
                    'game_id' => $game->id,
                    'reserve' => $v,
                    'replacement' => $replacement,
                ]);

                $repaired++;
            }

            if ($repaired > 0) {
                // Clear ESPSUP-style supercup entries so the next pipeline run
                // re-derives them against the corrected league rosters. Use
                // the country's supercup config rather than hard-coding ESPSUP.
                $supercupConfig = $countryConfig->supercup($country);
                if ($supercupConfig !== null && !empty($supercupConfig['competition'])) {
                    CompetitionEntry::where('game_id', $game->id)
                        ->where('competition_id', $supercupConfig['competition'])
                        ->delete();
                }

                // If the game is mid-transition, clear the checkpoint so the
                // pipeline restarts from the beginning of the closing phase.
                // Most processors are idempotent — they short-circuit already-
                // completed work. Leaving a stale step pointer would skip
                // SupercupQualificationProcessor, which is exactly the step we
                // want to re-run.
                if ($game->season_transition_step !== null) {
                    Game::where('id', $game->id)->update([
                        'season_transition_step' => null,
                        'season_transition_data' => null,
                    ]);
                }
            }

            return $repaired;
        });
    }

    /**
     * Find a non-reserve team to swap into the violating competition. Looks
     * for the worst-ranked non-reserve in a lower tier than the violation's
     * competition where the reserve's parent is NOT present. Preferring the
     * worst-ranked team minimises competitive disruption — a struggling
     * lower-tier team gets a bump up; the reserve drops into the slot it
     * vacates.
     *
     * @param  array{reserve_id: string, parent_id: string, competition_id: string}  $violation
     * @return array{team_id: string, competition_id: string}|null
     */
    private function findReplacement(string $gameId, string $country, array $violation, CountryConfig $countryConfig): ?array
    {
        $tierMap = $countryConfig->tiers($country);

        $violationTier = $this->resolveTier($violation['competition_id'], $tierMap, $countryConfig, $country);
        if ($violationTier === null) {
            return null;
        }

        $parentCompetitions = CompetitionEntry::where('game_id', $gameId)
            ->where('team_id', $violation['parent_id'])
            ->pluck('competition_id')
            ->all();

        // Walk tiers strictly below the violation downward. Prefer the
        // worst-ranked eligible team in the lowest tier that doesn't host
        // the parent. Reserve teams in those tiers are skipped — replacing
        // a reserve with another reserve solves nothing.
        for ($tier = $violationTier + 1; $tier <= max(array_keys($tierMap)); $tier++) {
            foreach ($countryConfig->tierCompetitionIds($country, $tier) as $candidateCompetition) {
                if (in_array($candidateCompetition, $parentCompetitions, true)) {
                    continue;
                }

                $candidate = $this->worstNonReserveIn($gameId, $candidateCompetition);
                if ($candidate !== null) {
                    return [
                        'team_id' => $candidate,
                        'competition_id' => $candidateCompetition,
                    ];
                }
            }
        }

        return null;
    }

    private function resolveTier(string $competitionId, array $tierMap, CountryConfig $countryConfig, string $country): ?int
    {
        foreach (array_keys($tierMap) as $tier) {
            foreach ($countryConfig->tierCompetitionIds($country, $tier) as $id) {
                if ($id === $competitionId) {
                    return $tier;
                }
            }
        }

        return null;
    }

    /**
     * Worst-ranked non-reserve team in a competition (last position in
     * standings, or last entry if standings are empty / placeholder).
     */
    private function worstNonReserveIn(string $gameId, string $competitionId): ?string
    {
        $candidates = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->join('teams', 'teams.id', '=', 'competition_entries.team_id')
            ->whereNull('teams.parent_team_id')
            ->pluck('competition_entries.team_id')
            ->all();

        if (empty($candidates)) {
            return null;
        }

        $worst = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereIn('team_id', $candidates)
            ->where('played', '>', 0)
            ->orderByDesc('position')
            ->value('team_id');

        return $worst ?? end($candidates) ?: null;
    }

    /**
     * Swap a reserve with its non-reserve replacement: each moves into the
     * other's competition. Mirrors the move semantics in
     * PromotionRelegationProcessor::moveTeam but as a direct swap.
     */
    private function swapTeams(
        string $gameId,
        string $reserveId,
        string $replacementId,
        string $reserveCompetition,
        string $replacementCompetition,
    ): void {
        $this->moveTeam($gameId, $reserveId, $reserveCompetition, $replacementCompetition);
        $this->moveTeam($gameId, $replacementId, $replacementCompetition, $reserveCompetition);
    }

    private function moveTeam(string $gameId, string $teamId, string $fromDivision, string $toDivision): void
    {
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

        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->delete();
    }
}
