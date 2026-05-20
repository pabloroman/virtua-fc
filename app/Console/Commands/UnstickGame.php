<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Season\Processors\PromotionRelegationProcessor;
use App\Modules\Season\Services\SeasonClosingPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recovery command for games stuck mid season-transition.
 *
 * Repairs three kinds of corruption that have accumulated over many
 * seasons of cross-rule promotion/relegation collisions:
 *
 *   1. Reserve/parent COEXISTENCE — reserve and parent share a league.
 *      Repaired by moving the reserve to a deeper tier, falling back
 *      to promoting the parent if no deeper tier exists.
 *
 *   2. Reserve/parent INVERSION — reserve sits in a higher tier than
 *      its parent (e.g. RC Celta Fortuna in ESP1 with parent RC Celta
 *      in ESP3B). The pipeline's invariant check only flags same-comp
 *      coexistence and misses this, but the relationship is semantically
 *      broken. Repaired by swapping reserve and parent directly.
 *
 *   3. Missing GameStanding rows for competitions where finalised
 *      matches exist (entries > standings). This is what triggers the
 *      "expected N relegated, got M" crash inside
 *      PromotionRelegationProcessor. Rebuilt from game_matches via
 *      StandingsCalculator.
 *
 * After any team movement, advances season_transition_step to the index
 * of PromotionRelegationProcessor so the resumed pipeline skips it.
 * The placeholder GameStanding rows added during the swap land the
 * moved team at the bottom of the new league; re-running
 * PromotionRelegationProcessor would just relegate the swapped team
 * straight back, undoing the fix. Trade-off: this season has no
 * automatic promotion/relegation. Subsequent closing processors
 * (reputation, fan loyalty, youth, UEFA qualification) still run.
 *
 * Also clears the country's supercup entries — see the comment in
 * FixReserveCoexistence::repairGame() for the rationale.
 *
 * Safe to re-run: each step is idempotent. Default mode is dry-run;
 * pass --fix to apply.
 */
class UnstickGame extends Command
{
    protected $signature = 'app:unstick-game {--game=} {--fix}';

    protected $description = 'Detect and (optionally) repair games stuck mid season-transition.';

    public function handle(
        CountryConfig $countryConfig,
        StandingsCalculator $calculator,
        SeasonClosingPipeline $pipeline,
    ): int {
        $apply = (bool) $this->option('fix');
        $specificGame = $this->option('game');

        $games = Game::query()
            ->when($specificGame, fn ($q) => $q->where('id', $specificGame))
            ->get(['id', 'country', 'season', 'season_transition_step']);

        if ($games->isEmpty()) {
            $this->info('No games found.');
            return self::SUCCESS;
        }

        $promotionRelegationStep = $this->findPromotionRelegationStep($pipeline);
        if ($promotionRelegationStep === null) {
            $this->error('Could not locate PromotionRelegationProcessor in the closing pipeline.');
            return self::FAILURE;
        }

        $totalRepaired = 0;
        $totalRebuilt = 0;

        foreach ($games as $game) {
            $country = $game->country ?? 'ES';
            $stepLabel = $game->season_transition_step ?? 'NULL';
            $this->line("Game {$game->id} (season {$game->season}, country {$country}, step {$stepLabel})");

            $violations = $this->findViolations($game->id, $country, $countryConfig);
            $rebuilds = $this->findStandingsRebuilds($game->id);
            $simFixes = $this->findSimulatedSeasonFixes($game);

            if ($violations->isEmpty() && $rebuilds->isEmpty() && $simFixes->isEmpty()) {
                $this->line('  no violations, no standings to rebuild, no sim mismatches');
                continue;
            }

            foreach ($violations as $v) {
                $this->line("  - [{$v['type']}] reserve {$v['reserve_name']} ({$v['reserve_comp']}) <- parent {$v['parent_name']} ({$v['parent_comp']})");
            }
            foreach ($rebuilds as $r) {
                $this->line("  - [standings] {$r['competition_id']}: entries={$r['entries']} standings={$r['standings']} matches={$r['matches']}");
            }
            foreach ($simFixes as $s) {
                $stale = implode(',', $s['stale_ids']);
                $missing = implode(',', $s['missing_ids']);
                $this->line("  - [sim] {$s['competition_id']}: replace [{$stale}] with [{$missing}]");
            }

            if (!$apply) {
                continue;
            }

            $result = $this->repair(
                $game,
                $country,
                $countryConfig,
                $calculator,
                $violations,
                $rebuilds,
                $simFixes,
                $promotionRelegationStep,
            );
            $totalRepaired += $result['repaired'];
            $totalRebuilt += $result['rebuilt'];

            $stepNote = $result['stepAdvanced'] ? "step advanced to {$promotionRelegationStep}" : 'step unchanged';
            $this->info("  -> repaired={$result['repaired']} rebuilt={$result['rebuilt']} sim_fixed={$result['simFixed']} ({$stepNote})");
        }

        $this->line('');
        $tail = $apply ? '.' : '. Re-run with --fix to apply.';
        $this->info("Scanned {$games->count()} game(s) — repaired={$totalRepaired}, rebuilt={$totalRebuilt}{$tail}");
        return self::SUCCESS;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function findViolations(string $gameId, string $country, CountryConfig $countryConfig): Collection
    {
        $tierMap = $countryConfig->tiers($country);
        $leagueCompIds = $this->allLeagueCompIds($country, $countryConfig);

        $reserves = Team::where('country', $country)
            ->whereNotNull('parent_team_id')
            ->get(['id', 'name', 'parent_team_id']);

        return $reserves->map(function ($reserve) use ($gameId, $leagueCompIds, $tierMap, $countryConfig, $country) {
            $rComp = $this->findTeamLeagueComp($gameId, $reserve->id, $leagueCompIds);
            $pComp = $this->findTeamLeagueComp($gameId, $reserve->parent_team_id, $leagueCompIds);

            if ($rComp === null || $pComp === null) {
                return null;
            }

            $rTier = $this->resolveTier($rComp, $tierMap, $countryConfig, $country);
            $pTier = $this->resolveTier($pComp, $tierMap, $countryConfig, $country);

            $parentName = Team::where('id', $reserve->parent_team_id)->value('name');

            $base = [
                'reserve_id' => $reserve->id,
                'reserve_name' => $reserve->name,
                'reserve_comp' => $rComp,
                'parent_id' => $reserve->parent_team_id,
                'parent_name' => $parentName,
                'parent_comp' => $pComp,
            ];

            if ($rComp === $pComp) {
                return ['type' => 'coexistence'] + $base;
            }

            if ($rTier !== null && $pTier !== null && $rTier < $pTier) {
                return ['type' => 'inverted'] + $base;
            }

            return null;
        })->filter()->values();
    }

    /** @return Collection<int, array{competition_id: string, entries: int, standings: int, matches: int}> */
    private function findStandingsRebuilds(string $gameId): Collection
    {
        $compIds = CompetitionEntry::where('game_id', $gameId)
            ->distinct()
            ->pluck('competition_id');

        return $compIds->map(function ($compId) use ($gameId) {
            $entries = CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $compId)->count();
            $standings = GameStanding::where('game_id', $gameId)
                ->where('competition_id', $compId)->count();
            $matches = GameMatch::where('game_id', $gameId)
                ->where('competition_id', $compId)
                ->whereNotNull('home_score')
                ->count();

            if ($standings >= $entries || $matches === 0) {
                return null;
            }

            return [
                'competition_id' => $compId,
                'entries' => $entries,
                'standings' => $standings,
                'matches' => $matches,
            ];
        })->filter()->values();
    }

    /**
     * @return array{repaired: int, rebuilt: int, simFixed: int, stepAdvanced: bool}
     */
    private function repair(
        Game $game,
        string $country,
        CountryConfig $countryConfig,
        StandingsCalculator $calculator,
        Collection $violations,
        Collection $rebuilds,
        Collection $simFixes,
        int $promotionRelegationStep,
    ): array {
        return DB::transaction(function () use ($game, $country, $countryConfig, $calculator, $violations, $rebuilds, $simFixes, $promotionRelegationStep) {
            $repaired = 0;
            $anySwap = false;

            foreach ($violations as $v) {
                if ($this->repairViolation($game->id, $country, $v, $countryConfig)) {
                    $repaired++;
                    $anySwap = true;
                }
            }

            $rebuilt = 0;
            foreach ($rebuilds as $r) {
                $this->rebuildStandings($game->id, $r['competition_id'], $calculator);
                $rebuilt++;
            }

            // Reconcile SimulatedSeason AFTER team swaps, so we map against the
            // final roster, not the pre-repair state.
            $simFixed = 0;
            foreach ($simFixes as $s) {
                $applied = $this->applySimulatedSeasonFix($game, $s['competition_id']);
                if ($applied) {
                    $simFixed++;
                }
            }

            $stepAdvanced = false;
            if ($anySwap) {
                $game->updateQuietly(['season_transition_step' => $promotionRelegationStep]);
                $stepAdvanced = true;

                $supercupConfig = $countryConfig->supercup($country);
                if ($supercupConfig !== null && !empty($supercupConfig['competition'])) {
                    CompetitionEntry::where('game_id', $game->id)
                        ->where('competition_id', $supercupConfig['competition'])
                        ->delete();
                }
            }

            return compact('repaired', 'rebuilt', 'simFixed', 'stepAdvanced');
        });
    }

    /**
     * Detect SimulatedSeason rows whose `results` roster has drifted from the
     * current CompetitionEntry roster (typically because a team was moved
     * between leagues after the season was simulated, but the SimulatedSeason
     * row was never refreshed). If the in-sim-not-entry and in-entry-not-sim
     * sets are equal in size, they can be paired 1:1 and swapped in place —
     * preserving the simulated finishing order.
     *
     * @return Collection<int, array{competition_id: string, stale_ids: list<string>, missing_ids: list<string>}>
     */
    private function findSimulatedSeasonFixes(Game $game): Collection
    {
        $simRows = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->get(['competition_id', 'results']);

        return $simRows->map(function ($sim) use ($game) {
            $simTeams = is_array($sim->results) ? $sim->results : (array) $sim->results;
            $entryTeams = CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $sim->competition_id)
                ->pluck('team_id')->all();

            $stale = array_values(array_diff($simTeams, $entryTeams));
            $missing = array_values(array_diff($entryTeams, $simTeams));

            if (empty($stale) && empty($missing)) {
                return null;
            }

            if (count($stale) !== count($missing)) {
                Log::warning('[UnstickGame] SimulatedSeason mismatch with unequal sets — skipping', [
                    'game_id' => $game->id,
                    'competition_id' => $sim->competition_id,
                    'stale' => $stale,
                    'missing' => $missing,
                ]);
                return null;
            }

            return [
                'competition_id' => $sim->competition_id,
                'stale_ids' => $stale,
                'missing_ids' => $missing,
            ];
        })->filter()->values();
    }

    /**
     * Replace stale team IDs in SimulatedSeason.results with the current
     * roster's missing teams, position-for-position. Returns true if the row
     * was updated.
     */
    private function applySimulatedSeasonFix(Game $game, string $competitionId): bool
    {
        $sim = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competitionId)
            ->first();

        if (!$sim) {
            return false;
        }

        $simTeams = is_array($sim->results) ? $sim->results : (array) $sim->results;
        $entryTeams = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')->all();

        $stale = array_values(array_diff($simTeams, $entryTeams));
        $missing = array_values(array_diff($entryTeams, $simTeams));

        if (count($stale) !== count($missing) || empty($stale)) {
            return false;
        }

        // Pair them 1:1. The order is arbitrary (we don't have meaningful
        // signal to choose which missing team replaces which stale team), so
        // pair by index — stable and deterministic.
        $replacement = array_combine($stale, $missing);

        $newResults = array_map(
            fn ($teamId) => $replacement[$teamId] ?? $teamId,
            $simTeams,
        );

        $sim->results = $newResults;
        $sim->save();

        Log::info('[UnstickGame] SimulatedSeason results reconciled', [
            'game_id' => $game->id,
            'competition_id' => $competitionId,
            'replacements' => $replacement,
        ]);

        return true;
    }

    private function repairViolation(string $gameId, string $country, array $v, CountryConfig $countryConfig): bool
    {
        if ($v['type'] === 'inverted') {
            $this->swapTeams($gameId, $v['reserve_id'], $v['parent_id'], $v['reserve_comp'], $v['parent_comp']);
            Log::info('[UnstickGame] Inversion swap', ['game_id' => $gameId, 'violation' => $v]);
            return true;
        }

        if ($v['type'] === 'coexistence') {
            $replacement = $this->findReplacementDown($gameId, $country, $v, $countryConfig);
            if ($replacement !== null) {
                $this->swapTeams($gameId, $v['reserve_id'], $replacement['team_id'], $v['reserve_comp'], $replacement['competition_id']);
                Log::info('[UnstickGame] Coexistence swap (reserve-down)', ['game_id' => $gameId, 'violation' => $v, 'replacement' => $replacement]);
                return true;
            }

            $promotion = $this->findParentPromotion($gameId, $country, $v, $countryConfig);
            if ($promotion !== null) {
                $this->swapTeams($gameId, $v['parent_id'], $promotion['team_id'], $promotion['parent_competition_id'], $promotion['competition_id']);
                Log::info('[UnstickGame] Coexistence swap (parent-up)', ['game_id' => $gameId, 'violation' => $v, 'promotion' => $promotion]);
                return true;
            }

            Log::warning('[UnstickGame] No repair path for coexistence', ['game_id' => $gameId, 'violation' => $v]);
            return false;
        }

        return false;
    }

    /**
     * Walk tiers strictly below the violation, deepest first. Swap the
     * reserve with the worst non-reserve in the first suitable candidate
     * tier (skipping any tier hosting the parent).
     *
     * @return array{team_id: string, competition_id: string}|null
     */
    private function findReplacementDown(string $gameId, string $country, array $v, CountryConfig $countryConfig): ?array
    {
        $tierMap = $countryConfig->tiers($country);
        $violationTier = $this->resolveTier($v['reserve_comp'], $tierMap, $countryConfig, $country);
        if ($violationTier === null) {
            return null;
        }

        $parentCompetitions = CompetitionEntry::where('game_id', $gameId)
            ->where('team_id', $v['parent_id'])
            ->pluck('competition_id')->all();

        $tiersBelow = array_values(array_filter(array_keys($tierMap), fn ($t) => $t > $violationTier));
        rsort($tiersBelow);

        foreach ($tiersBelow as $tier) {
            foreach ($countryConfig->tierCompetitionIds($country, $tier) as $candidate) {
                if (in_array($candidate, $parentCompetitions, true)) {
                    continue;
                }
                $worst = $this->worstNonReserveIn($gameId, $candidate);
                if ($worst !== null) {
                    return ['team_id' => $worst, 'competition_id' => $candidate];
                }
            }
        }

        return null;
    }

    /**
     * Walk tiers strictly above the parent's current tier, closest first.
     * Swap the parent with the worst non-reserve there.
     *
     * @return array{team_id: string, competition_id: string, parent_competition_id: string}|null
     */
    private function findParentPromotion(string $gameId, string $country, array $v, CountryConfig $countryConfig): ?array
    {
        $tierMap = $countryConfig->tiers($country);

        $parentCompetitions = CompetitionEntry::where('game_id', $gameId)
            ->where('team_id', $v['parent_id'])
            ->pluck('competition_id')->all();

        if (empty($parentCompetitions)) {
            return null;
        }

        $parentTier = null;
        $parentCompetitionId = null;
        foreach ($parentCompetitions as $comp) {
            $tier = $this->resolveTier($comp, $tierMap, $countryConfig, $country);
            if ($tier !== null && ($parentTier === null || $tier > $parentTier)) {
                $parentTier = $tier;
                $parentCompetitionId = $comp;
            }
        }

        if ($parentTier === null || $parentCompetitionId === null) {
            return null;
        }

        $tiersAbove = array_values(array_filter(array_keys($tierMap), fn ($t) => $t < $parentTier));
        rsort($tiersAbove);

        foreach ($tiersAbove as $tier) {
            foreach ($countryConfig->tierCompetitionIds($country, $tier) as $candidate) {
                $hostingReserve = CompetitionEntry::where('game_id', $gameId)
                    ->where('competition_id', $candidate)
                    ->where('team_id', $v['reserve_id'])
                    ->exists();
                if ($hostingReserve) {
                    continue;
                }
                $worst = $this->worstNonReserveIn($gameId, $candidate);
                if ($worst !== null) {
                    return [
                        'team_id' => $worst,
                        'competition_id' => $candidate,
                        'parent_competition_id' => $parentCompetitionId,
                    ];
                }
            }
        }

        return null;
    }

    private function worstNonReserveIn(string $gameId, string $competitionId): ?string
    {
        $candidates = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->join('teams', 'teams.id', '=', 'competition_entries.team_id')
            ->whereNull('teams.parent_team_id')
            ->pluck('competition_entries.team_id')->all();

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
     * Swap two teams between two competitions. Mirrors
     * PromotionRelegationProcessor::moveTeam: delete source rows, insert
     * target entry + placeholder standing (if target has standings), then
     * resort positions in both competitions.
     */
    private function swapTeams(string $gameId, string $teamAId, string $teamBId, string $teamAComp, string $teamBComp): void
    {
        $this->moveTeam($gameId, $teamAId, $teamAComp, $teamBComp);
        $this->moveTeam($gameId, $teamBId, $teamBComp, $teamAComp);
        $this->resortPositions($gameId, $teamAComp);
        $this->resortPositions($gameId, $teamBComp);
    }

    private function moveTeam(string $gameId, string $teamId, string $fromComp, string $toComp): void
    {
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $fromComp)
            ->where('team_id', $teamId)
            ->delete();

        CompetitionEntry::updateOrCreate(
            ['game_id' => $gameId, 'competition_id' => $toComp, 'team_id' => $teamId],
            ['entry_round' => 1],
        );

        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $fromComp)
            ->where('team_id', $teamId)
            ->delete();

        $targetHasStandings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $toComp)
            ->exists();

        if ($targetHasStandings) {
            GameStanding::firstOrCreate(
                ['game_id' => $gameId, 'competition_id' => $toComp, 'team_id' => $teamId],
                [
                    'position' => 99,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'points' => 0,
                ],
            );
        }
    }

    private function resortPositions(string $gameId, string $competitionId): void
    {
        $standings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->orderBy('position')
            ->get();

        if ($standings->isEmpty()) {
            return;
        }

        foreach ($standings->values() as $index => $s) {
            $newPosition = $index + 1;
            if ($s->position !== $newPosition) {
                $s->update(['position' => $newPosition]);
            }
        }
    }

    /**
     * Rebuild standings for a competition from its finalised matches.
     * Nuke + initialise + bulk-apply preserves true match history and
     * fills in any teams whose standings rows were dropped by prior
     * partial repairs.
     */
    private function rebuildStandings(string $gameId, string $competitionId, StandingsCalculator $calculator): void
    {
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')->all();

        if (empty($teamIds)) {
            return;
        }

        $matches = GameMatch::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        if ($matches->isEmpty()) {
            return;
        }

        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->delete();

        $calculator->initializeStandings($gameId, $competitionId, $teamIds);

        $matchResults = $matches->map(fn ($m) => [
            'homeTeamId' => $m->home_team_id,
            'awayTeamId' => $m->away_team_id,
            'homeScore' => (int) $m->home_score,
            'awayScore' => (int) $m->away_score,
        ])->all();

        $calculator->bulkUpdateAfterMatches($gameId, $competitionId, $matchResults);
        $calculator->recalculatePositions($gameId, $competitionId);
    }

    private function findPromotionRelegationStep(SeasonClosingPipeline $pipeline): ?int
    {
        foreach ($pipeline->getProcessors() as $index => $processor) {
            if ($processor instanceof PromotionRelegationProcessor) {
                return $index;
            }
        }
        return null;
    }

    private function findTeamLeagueComp(string $gameId, string $teamId, array $leagueCompIds): ?string
    {
        return CompetitionEntry::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->whereIn('competition_id', $leagueCompIds)
            ->value('competition_id');
    }

    private function allLeagueCompIds(string $country, CountryConfig $countryConfig): array
    {
        $tierMap = $countryConfig->tiers($country);
        $ids = [];
        foreach (array_keys($tierMap) as $tier) {
            $ids = array_merge($ids, $countryConfig->tierCompetitionIds($country, $tier));
        }
        return $ids;
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
}
