<?php

namespace App\Modules\Season\Processors;

use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Exceptions\ReserveParentCoexistenceException;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Competition\Promotions\CountryPromotionRelegationPlanner;
use App\Modules\Competition\Promotions\CountrySeasonSnapshotBuilder;
use App\Modules\Competition\Promotions\PromotionMove;
use App\Modules\Competition\Promotions\PromotionRelegationExecutor;
use App\Modules\Competition\Promotions\PromotionRelegationPlan;
use App\Modules\Competition\Promotions\RepairOutcome;
use App\Modules\Competition\Promotions\ReserveParentCoexistenceRepairer;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Alerts\ReserveCoexistenceHealAlert;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\DB;

/**
 * Closing-pipeline processor for promotion/relegation.
 *
 * Thin orchestrator over three components:
 *   1. {@see CountrySeasonSnapshotBuilder} reads the country's pre-swap state
 *      from the DB into an in-memory snapshot.
 *   2. {@see CountryPromotionRelegationPlanner} converts the snapshot into a
 *      {@see PromotionRelegationPlan} — a pure function of the snapshot, with
 *      no DB writes.
 *   3. {@see PromotionRelegationExecutor} applies the plan in a transaction.
 *
 * Pre-flight: refuse to run if any playoff is still in progress. The planner
 * would also throw, but doing it up front avoids opening a transaction we'd
 * have to roll back.
 *
 * Post-execution: clear out per-game artefacts of any playoff competitions
 * (CupTie / GameMatch / GameStanding / CompetitionEntry rows for ESP3PO etc.)
 * so the next season starts fresh, and re-assert no reserve/parent
 * coexistence. The assertion is belt-and-suspenders: the planner's own
 * validation should make it unreachable, but a violation here means data
 * drift the planner didn't see — fail loudly.
 *
 * Priority: 85 (unchanged from the old processor; same place in pipeline).
 */
class PromotionRelegationProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly CountrySeasonSnapshotBuilder $snapshotBuilder,
        private readonly CountryPromotionRelegationPlanner $planner,
        private readonly PromotionRelegationExecutor $executor,
        private readonly PlayoffGeneratorFactory $playoffFactory,
        private readonly CountryConfig $countryConfig,
        private readonly ReserveParentCoexistenceRepairer $coexistenceRepairer,
        private readonly ReserveCoexistenceHealAlert $healAlert,
    ) {}

    public function priority(): int
    {
        return 85;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $this->assertNoPlayoffInProgress($game);

        if ($game->country === null) {
            // Test-only path: factories that build a Game without seeding the
            // country column. Production games always have a country (DB
            // default 'ES' on the games table); the closing pipeline runs
            // here against a partial fixture with nothing to relegate.
            return $data;
        }

        $config = $this->countryConfig->get($game->country) ?? [];
        if (empty($config['promotions'] ?? null)) {
            // Country with no promotion rules (e.g. England in current data).
            return $data;
        }

        $snapshot = $this->snapshotBuilder->build($game);

        try {
            $plan = $this->planner->planFromSnapshot($snapshot, $config);
        } catch (ReserveParentCoexistenceException $e) {
            // In-band self-heal: a prior incomplete transition can leave a
            // reserve coexisting with (or inverted above) its parent in the
            // snapshot, which the planner refuses to plan around. Attempt a
            // single deterministic repair, then replan exactly once. Everything
            // here runs inside the pipeline's per-processor DB::transaction, so
            // the repair is atomic with the rest of the processor.
            $result = $this->coexistenceRepairer->repair($game);

            if ($result->outcome !== RepairOutcome::Repaired) {
                // Nothing safe to do (ambiguous corruption, or no fixable issue
                // detected). Alert and rethrow the original so the job fails and
                // the operator's manual repair path takes over.
                $this->healAlert->unhealable($game, $e, $result);
                throw $e;
            }

            // Replan against the repaired DB state. Deliberately NOT wrapped:
            // if the swap did not actually resolve the violation, this throws
            // again and propagates — single-shot, never a retry loop.
            $snapshot = $this->snapshotBuilder->build($game);
            $plan = $this->planner->planFromSnapshot($snapshot, $config);

            // Fire the success alert only once the transaction commits, so a
            // later rollback (e.g. the post-execution invariant below) doesn't
            // leave us claiming a heal that was undone.
            DB::afterCommit(fn () => $this->healAlert->healed($game, $result));
        }

        $this->executor->apply($plan, $game);

        $this->clearPlayoffArtefacts($game, $config);

        // Refresh in case the executor updated competition_id.
        $game->refresh();
        if ($game->competition_id !== $data->competitionId) {
            $data->competitionId = $game->competition_id;
        }

        $this->recordTransitionMetadata($data, $plan, $game);

        $this->assertNoReserveCoexistence($game, $plan);

        return $data;
    }

    /**
     * Refuse to start if any configured playoff is still in progress for
     * this game. Distinct exception so ProcessSeasonTransition can present
     * the right message to the player.
     */
    private function assertNoPlayoffInProgress(Game $game): void
    {
        foreach ($this->playoffFactory->all() as $generator) {
            if ($generator->state($game) === PlayoffState::InProgress) {
                throw PlayoffInProgressException::forCompetition($generator->getCompetitionId());
            }
        }
    }

    /**
     * Tear down per-game state for the country's playoff competitions so the
     * next season's bracket starts from a clean slate. The Competition row
     * itself is preserved; only the artefacts the playoff produced during
     * this season are deleted.
     *
     * Scoped to playoff competitions that are distinct from the bottom
     * division (e.g. ESP3PO) — for in-league playoffs (ESP2's bracket) the
     * cleanup runs against ESP2 itself, which we don't want to wipe.
     */
    private function clearPlayoffArtefacts(Game $game, array $config): void
    {
        foreach ($config['promotions'] ?? [] as $rule) {
            $playoffComp = $rule['playoff_competition'] ?? null;
            if ($playoffComp === null) {
                continue;
            }
            // Only wipe when the playoff has its own competition_id distinct
            // from any feeder league.
            $bottom = $rule['bottom_division'];
            $sources = $rule['playoff_source_divisions'] ?? [$bottom];
            if ($playoffComp === $bottom || in_array($playoffComp, $sources, true)) {
                continue;
            }

            CupTie::where('game_id', $game->id)
                ->where('competition_id', $playoffComp)
                ->delete();
            GameMatch::where('game_id', $game->id)
                ->where('competition_id', $playoffComp)
                ->delete();
            GameStanding::where('game_id', $game->id)
                ->where('competition_id', $playoffComp)
                ->delete();
            CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $playoffComp)
                ->delete();
        }
    }

    /**
     * Surface the user's own promotion/relegation in the season transition
     * data so end-of-season views can render it. Mirrors the previous
     * processor's `promotedTeams` / `relegatedTeams` metadata contract.
     */
    private function recordTransitionMetadata(SeasonTransitionData $data, PromotionRelegationPlan $plan, Game $game): void
    {
        $userRule = $this->ruleInvolvingCompetition($game->country, $data->competitionId);

        if ($userRule === null) {
            $data->setMetadata('promotedTeams', []);
            $data->setMetadata('relegatedTeams', []);
            return;
        }

        $top = $userRule['top_division'];
        $sources = $userRule['playoff_source_divisions'] ?? [$userRule['bottom_division']];

        $promoted = [];
        $relegated = [];
        foreach ($plan->moves as $move) {
            if ($move->isPromotion()
                && $move->toCompetitionId === $top
                && in_array($move->fromCompetitionId, $sources, true)
            ) {
                $promoted[] = [
                    'teamId' => $move->teamId,
                    'position' => $move->reason === PromotionMove::REASON_PROMOTION_PLAYOFF ? 'Playoff' : null,
                    'teamName' => $move->teamName,
                ];
            }
            if ($move->isRelegation()
                && $move->fromCompetitionId === $top
                && in_array($move->toCompetitionId, $sources, true)
            ) {
                $relegated[] = [
                    'teamId' => $move->teamId,
                    'position' => null,
                    'teamName' => $move->teamName,
                ];
            }
        }

        $data->setMetadata('promotedTeams', $promoted);
        $data->setMetadata('relegatedTeams', $relegated);
    }

    private function ruleInvolvingCompetition(string $country, string $competitionId): ?array
    {
        foreach ($this->countryConfig->promotions($country) as $rule) {
            $top = $rule['top_division'];
            $bottom = $rule['bottom_division'];
            $sources = $rule['playoff_source_divisions'] ?? [$bottom];
            if ($top === $competitionId || in_array($competitionId, $sources, true)) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * Post-execution invariant check: no reserve team should share a
     * competition with its parent. The planner's own validatePlan should make
     * this unreachable — but if data drifted between snapshot read and
     * execution (a concurrent process inserting a CompetitionEntry, say), we
     * want to fail loudly rather than ship corruption to next season.
     */
    private function assertNoReserveCoexistence(Game $game, PromotionRelegationPlan $plan): void
    {
        if (empty($plan->touchedCompetitionIds)) {
            return;
        }

        $rows = CompetitionEntry::where('game_id', $game->id)
            ->whereIn('competition_id', $plan->touchedCompetitionIds)
            ->join('teams', 'teams.id', '=', 'competition_entries.team_id')
            ->whereNotNull('teams.parent_team_id')
            ->select([
                'competition_entries.competition_id as competition_id',
                'competition_entries.team_id as reserve_id',
                'teams.parent_team_id as parent_id',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $parentIds = $rows->pluck('parent_id')->unique()->all();
        $parentCompetitions = CompetitionEntry::where('game_id', $game->id)
            ->whereIn('team_id', $parentIds)
            ->whereIn('competition_id', $plan->touchedCompetitionIds)
            ->get(['competition_id', 'team_id'])
            ->groupBy('team_id')
            ->map(fn ($entries) => $entries->pluck('competition_id')->all());

        $violations = [];
        foreach ($rows as $row) {
            $parentDivisions = $parentCompetitions->get($row->parent_id, []);
            if (in_array($row->competition_id, $parentDivisions, true)) {
                $violations[] = sprintf(
                    'reserve=%s parent=%s competition=%s',
                    $row->reserve_id,
                    $row->parent_id,
                    $row->competition_id,
                );
            }
        }

        if (empty($violations)) {
            return;
        }

        throw new \RuntimeException(
            'Reserve/parent coexistence invariant violated after promotion/relegation: '
            . implode('; ', $violations)
            . '. This indicates data drift between planner snapshot and execution — '
            . 'the planner produces invariant-satisfying plans by construction.',
        );
    }
}
