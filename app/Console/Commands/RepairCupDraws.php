<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repair existing cup brackets that were silently broken by the pre-fix
 * CrossCategoryPairing / CupDrawService truncation. For each affected
 * (game, competition), find teams that should be in the current bracket
 * but aren't represented anywhere in cup_ties (because they were dropped
 * during a past draw) and re-enter them at the next undrawn round.
 *
 * Walks every remaining round from the next undrawn one through the
 * cup final, projecting pool sizes by halving. Whenever a round would
 * be odd, re-enter one lost team there. If we run out of lost teams,
 * the game is reported as partially recoverable — earlier rounds will
 * draw cleanly but at some later round the bracket still throws.
 *
 * Defaults to dry-run. Pass --apply to commit changes.
 */
class RepairCupDraws extends Command
{
    protected $signature = 'app:repair-cup-draws
                            {--apply : Persist the repair (default is dry-run)}
                            {--game= : Restrict to a single game UUID}
                            {--competition= : Restrict to a single competition id (e.g. ESPCUP)}';

    protected $description = 'Re-enter teams dropped by the pre-fix odd-pool truncation, restoring broken cup brackets';

    public function handle(CupDrawService $cupDrawService): int
    {
        $apply = (bool) $this->option('apply');
        $gameFilter = $this->option('game');
        $competitionFilter = $this->option('competition');

        $candidates = $this->findCandidateGames($gameFilter, $competitionFilter);
        if ($candidates->isEmpty()) {
            $this->info('No domestic-cup games found matching the filters.');
            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%s mode. Inspecting %d (game, competition) pairs.',
            $apply ? 'APPLY' : 'DRY-RUN',
            $candidates->count(),
        ));

        $summary = ['clean' => 0, 'fully_repaired' => 0, 'partially_repaired' => 0, 'unrecoverable' => 0];

        foreach ($candidates as $candidate) {
            $report = $this->repairGame($candidate->game_id, $candidate->competition_id, $cupDrawService, $apply);
            $summary[$report['status']]++;

            $this->line(sprintf(
                '  %s  %s/%s  next_round=%s  pool=%d  added=%d  status=%s%s',
                $apply ? '[applied]' : '[dry]',
                substr($candidate->game_id, 0, 8),
                $candidate->competition_id,
                $report['next_round'] ?? '–',
                $report['initial_pool'],
                $report['teams_added'],
                $report['status'],
                $report['note'] ? "  ({$report['note']})" : '',
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary — clean: %d, fully repaired: %d, partially repaired: %d, unrecoverable: %d',
            $summary['clean'],
            $summary['fully_repaired'],
            $summary['partially_repaired'],
            $summary['unrecoverable'],
        ));

        if (!$apply && ($summary['fully_repaired'] + $summary['partially_repaired']) > 0) {
            $this->line('Re-run with --apply to commit the changes.');
        }

        return self::SUCCESS;
    }

    /**
     * Narrow at the SQL level to (game, competition) pairs whose next draw
     * would have an odd team pool — i.e. the only games the prevention
     * throw is about to bite. Excludes:
     *  - cups whose latest round still has unresolved ties (no draw is
     *    imminent anyway)
     *  - cups whose next-round pool is even (no breakage to repair)
     *
     * The PHP layer filters out games whose next round doesn't exist in
     * schedule.json (cup finished) — that case is rare and can't be
     * computed in SQL without parsing the JSON files.
     *
     * @return Collection<int, object{game_id: string, competition_id: string}>
     */
    private function findCandidateGames(?string $gameFilter, ?string $competitionFilter): Collection
    {
        $bindings = [];
        $filter = '';

        if ($gameFilter) {
            $filter .= ' AND ct.game_id = ?';
            $bindings[] = $gameFilter;
        }
        if ($competitionFilter) {
            $filter .= ' AND ct.competition_id = ?';
            $bindings[] = $competitionFilter;
        }

        $sql = <<<SQL
            WITH latest_round AS (
                SELECT
                    ct.game_id,
                    ct.competition_id,
                    MAX(ct.round_number) AS round_number
                FROM cup_ties ct
                JOIN competitions c ON c.id = ct.competition_id
                WHERE c.role = 'domestic_cup'
                {$filter}
                GROUP BY ct.game_id, ct.competition_id
            ),
            ready_to_draw AS (
                -- Latest round must have all ties resolved with a winner
                -- before the next-round draw can fire.
                SELECT
                    lr.game_id,
                    lr.competition_id,
                    lr.round_number,
                    COUNT(*) AS winners_count
                FROM latest_round lr
                JOIN cup_ties ct
                    ON ct.game_id = lr.game_id
                   AND ct.competition_id = lr.competition_id
                   AND ct.round_number = lr.round_number
                GROUP BY lr.game_id, lr.competition_id, lr.round_number
                HAVING COUNT(*) FILTER (WHERE NOT ct.completed OR ct.winner_id IS NULL) = 0
            )
            SELECT
                rtd.game_id,
                rtd.competition_id
            FROM ready_to_draw rtd
            LEFT JOIN competition_entries ce
                ON ce.game_id = rtd.game_id
               AND ce.competition_id = rtd.competition_id
               AND ce.entry_round = rtd.round_number + 1
            GROUP BY rtd.game_id, rtd.competition_id, rtd.winners_count
            HAVING (rtd.winners_count + COUNT(ce.team_id)) % 2 = 1
        SQL;

        return collect(DB::select($sql, $bindings));
    }

    /**
     * @return array{
     *     status: 'clean'|'fully_repaired'|'partially_repaired'|'unrecoverable',
     *     next_round: int|null,
     *     initial_pool: int,
     *     teams_added: int,
     *     note: string,
     * }
     */
    private function repairGame(
        string $gameId,
        string $competitionId,
        CupDrawService $cupDrawService,
        bool $apply,
    ): array {
        $nextRound = $cupDrawService->getNextRoundNeedingDraw($gameId, $competitionId);
        if ($nextRound === null) {
            return $this->result('clean', null, 0, 0, 'cup is finished or not yet ready to draw');
        }

        $initialPoolSize = $this->poolForRound($gameId, $competitionId, $nextRound)->count();
        if ($initialPoolSize % 2 === 0) {
            return $this->result('clean', $nextRound, $initialPoolSize, 0, 'pool already even at next round');
        }

        $competition = Competition::find($competitionId);
        $gameSeason = Game::where('id', $gameId)->value('season');
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds(
            $competitionId,
            $competition?->season ?? $gameSeason,
            $gameSeason,
        );
        $remainingRounds = array_values(array_filter(
            $rounds,
            fn ($r) => $r->round >= $nextRound,
        ));

        $lostTeams = $this->findLostTeams($gameId, $competitionId);

        // Project pool sizes through each remaining round, fixing odd
        // pools by re-entering one lost team. Tracks which round each
        // team should be moved into.
        $plannedAdds = []; // round => [team_id, ...]
        $previousPoolSize = null;

        foreach ($remainingRounds as $roundConfig) {
            $round = $roundConfig->round;
            $entriesAtThisRound = CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->where('entry_round', $round)
                ->count();

            // Pool size at this round
            if ($round === $nextRound) {
                $poolSize = $initialPoolSize;
            } else {
                $poolSize = intdiv($previousPoolSize, 2) + $entriesAtThisRound;
            }

            if ($poolSize % 2 !== 0) {
                if ($lostTeams->isEmpty()) {
                    return $this->maybeApplyAndReturn(
                        $gameId,
                        $competitionId,
                        $plannedAdds,
                        $apply,
                        'partially_repaired',
                        $nextRound,
                        $initialPoolSize,
                        sprintf('ran out of lost teams at round %d (pool would be %d)', $round, $poolSize),
                    );
                }

                $plannedAdds[$round] = [$lostTeams->shift()];
                $poolSize++;
            }

            $previousPoolSize = $poolSize;
        }

        if (empty($plannedAdds)) {
            // Shouldn't happen — we already returned 'clean' if pool was even.
            return $this->result('clean', $nextRound, $initialPoolSize, 0, 'no repairs needed');
        }

        return $this->maybeApplyAndReturn(
            $gameId,
            $competitionId,
            $plannedAdds,
            $apply,
            'fully_repaired',
            $nextRound,
            $initialPoolSize,
            sprintf('moved %d team(s) across %d round(s)', $this->totalAdds($plannedAdds), count($plannedAdds)),
        );
    }

    /**
     * Move planned lost teams into their target entry_rounds and return a
     * report. Skips persistence in dry-run mode.
     *
     * @param  array<int, array<int, string>>  $plannedAdds  round => [team_id, ...]
     */
    private function maybeApplyAndReturn(
        string $gameId,
        string $competitionId,
        array $plannedAdds,
        bool $apply,
        string $status,
        int $nextRound,
        int $initialPoolSize,
        string $note,
    ): array {
        if ($apply && !empty($plannedAdds)) {
            DB::transaction(function () use ($plannedAdds, $gameId, $competitionId) {
                foreach ($plannedAdds as $round => $teamIds) {
                    foreach ($teamIds as $teamId) {
                        // Lost teams already have a competition_entries row —
                        // their original entry_round (typically 1). Move that
                        // row to the recovery round.
                        CompetitionEntry::where('game_id', $gameId)
                            ->where('competition_id', $competitionId)
                            ->where('team_id', $teamId)
                            ->update(['entry_round' => $round]);
                    }
                }
            });
        }

        return $this->result($status, $nextRound, $initialPoolSize, $this->totalAdds($plannedAdds), $note);
    }

    /**
     * Teams in competition_entries that should still be alive in the
     * bracket but aren't represented anywhere — i.e. teams the pre-fix
     * truncation silently dropped.
     */
    private function findLostTeams(string $gameId, string $competitionId): Collection
    {
        $allEntries = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id');

        $eliminated = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('completed', true)
            ->whereNotNull('winner_id')
            ->get(['home_team_id', 'away_team_id', 'winner_id'])
            ->map(fn ($t) => $t->winner_id === $t->home_team_id ? $t->away_team_id : $t->home_team_id)
            ->unique();

        // Any team currently sitting in the latest-round ties (whether
        // those ties are completed or not) is still in the bracket.
        $latestRound = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->max('round_number');

        $aliveInBracket = $latestRound === null ? collect() : CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $latestRound)
            ->get(['home_team_id', 'away_team_id', 'completed', 'winner_id'])
            ->flatMap(fn ($tie) => $tie->completed
                ? ($tie->winner_id ? [$tie->winner_id] : [])
                : [$tie->home_team_id, $tie->away_team_id])
            ->unique();

        return $allEntries
            ->diff($eliminated)
            ->diff($aliveInBracket)
            ->values();
    }

    /**
     * Build the pool for a round about to be drawn: previous-round
     * winners plus this round's entries.
     */
    private function poolForRound(string $gameId, string $competitionId, int $round): Collection
    {
        $entering = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('entry_round', $round)
            ->pluck('team_id');

        if ($round === 1) {
            return $entering;
        }

        $winners = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $round - 1)
            ->where('completed', true)
            ->whereNotNull('winner_id')
            ->pluck('winner_id');

        return $winners->merge($entering);
    }

    /**
     * @param  array<int, array<int, string>>  $plannedAdds
     */
    private function totalAdds(array $plannedAdds): int
    {
        return array_sum(array_map('count', $plannedAdds));
    }

    /**
     * @return array{
     *     status: 'clean'|'fully_repaired'|'partially_repaired'|'unrecoverable',
     *     next_round: int|null,
     *     initial_pool: int,
     *     teams_added: int,
     *     note: string,
     * }
     */
    private function result(string $status, ?int $nextRound, int $initialPool, int $teamsAdded, string $note): array
    {
        return [
            'status' => $status,
            'next_round' => $nextRound,
            'initial_pool' => $initialPool,
            'teams_added' => $teamsAdded,
            'note' => $note,
        ];
    }
}
