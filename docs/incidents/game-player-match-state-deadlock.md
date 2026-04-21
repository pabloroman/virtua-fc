# `game_player_match_state` Deadlock Investigation

**Status:** partially resolved — a new deadlock variant emerged after Phase C, currently open
**Period:** 2026-04-20 to 2026-04-22
**Primary artefacts:** branch `refactor/delete-ensure-exist-path` (Phase C, merged)

## 1. Executive Summary

A recurring PostgreSQL `40P01` deadlock surfaced on the `game_player_match_state`
satellite table at ~130 occurrences/day, affecting 237 users across 7 days. The
root cause was the lazy-materialization path `ensureExistForGamePlayers`, whose
`INSERT ... ON CONFLICT DO NOTHING` statement raced with itself under concurrent
`ProcessRemainingBatches` execution.

We executed a three-phase refactor:

- **Phase A** — every `GamePlayer` insert now atomically creates a matching
  satellite row; a one-shot backfill brought existing games up to the invariant.
- **Phase B** — added `RETURNING` + warning log to the lazy path to detect any
  missed creation paths during a soak period.
- **Phase C** — deleted the lazy path outright once the invariant was confirmed.

Phase C eliminated the original deadlock class. However, a **second deadlock**
immediately surfaced on the `bulkIncrementStats` UPDATE path — a different query
in the same table, hit at ~25 events/day, involving the same structural issue
(multiple uncoordinated writers to this satellite table). That work is still
open; this document captures the reasoning so far.

## 2. Timeline

| Date (UTC) | Event |
|---|---|
| 2026-04-14 14:21 | Previous "ORDER BY gp.id" fix deployed (58b6cdda) — starts the current issue window |
| 2026-04-14 14:31 | First seen of the INSERT-variant deadlock (post-fix) |
| 2026-04-20 morning | Investigation begins, root-cause hypothesis formed |
| 2026-04-20 afternoon | Phase A shipped (eager satellite creation + backfill migration) |
| 2026-04-20 ~16:00 | Phase A's in-migration backfill replaced with chunked artisan command `app:backfill-match-states` due to deploy timeout |
| 2026-04-20 17:28 | Phase B shipped (RETURNING + warning log) |
| 2026-04-20 ~21:00 | Phase C shipped (lazy path deleted) |
| 2026-04-20 21:36 | First seen of the UPDATE-variant deadlock (post-Phase-C) |
| 2026-04-21 19:40 | User flags that UPDATE deadlocks have continued at ~25/day |
| 2026-04-22 | Discussion of mitigation options and a proposed sync-PRB architectural change — still open |

## 3. Original Problem

### 3.1 Error

```
SQLSTATE[40P01]: Deadlock detected
CONTEXT: while inserting index tuple (37086,10) in relation "game_player_match_state"

INSERT INTO game_player_match_state (...)
SELECT gp.id, gp.game_id, 80, 80, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0
FROM game_players gp
WHERE gp.game_id = ?
  AND gp.team_id IN (SELECT unnest(?::uuid[]))
ORDER BY gp.id
ON CONFLICT (game_player_id) DO NOTHING
```

Thrown from `GamePlayerMatchState::ensureExistForGamePlayers`, called per-batch
from `MatchdayOrchestrator::processBatch` inside `ProcessRemainingBatches` job
transactions.

### 3.2 Architecture context

`game_player_match_state` is a satellite of `GamePlayer` holding the 13 hot-write
columns that get updated on every matchday (fitness, morale, injuries,
appearances, goals, assists, cards, GK stats). It exists because these columns
are the only per-matchday volatile state, and separating them from the stable
`game_players` row reduces write amplification.

Historically the table was **sparse**: only "active scope" players (teams in the
user's domestic competitions, as defined by `GamePlayerScopeResolver`) had rows.
Foreign transfer-pool players (ENG1/DEU1/FRA1/ITA1/EUR) had none. Read paths
tolerated the absence via accessor fallbacks in `GamePlayer::matchStateValue`.

Rows were materialized lazily by `ensureExistForGamePlayers`, called at the
start of `processBatch` for any team whose matchState might not yet exist.

### 3.3 Prior mitigation history

Commit `58b6cdda` (2026-04-14) tried to fix an earlier deadlock by:
- Adding `ORDER BY gp.id` to the INSERT
- Wrapping `ProcessCareerActions` ticks in `lockForUpdate` on the game row
- Sorting-by-id in `AITransferMarketService::flushBatchedOperations` and
  `TransferService::completeAgreedTransfers`

These mitigations closed the specific cycle they were written for but did not
address the broader issue: multiple concurrent writers to `game_player_match_state`
for the same game under `ProcessRemainingBatches` duplicate-job conditions.

## 4. Root Cause Analysis

### 4.1 The duplicate-job race

`ProcessRemainingBatches` implements `ShouldBeUnique` with `uniqueFor = 180`
seconds (Redis lock). Separately, the frontend view `ShowGame` calls
`clearStuckRemainingBatches()` which clears `remaining_batches_processing_at`
after only **120 seconds**.

The 60-second mismatch created a window where:

1. PRB job starts for game G. Redis unique lock held. DB flag set.
2. At T+120s the flag is cleared by a UI refresh (`clearStuckFlag` threshold).
3. If the user advances another matchday, `deferRemainingBatches` sees
   `whereNull('remaining_batches_processing_at')` passes and dispatches a
   second PRB job.
4. The Redis unique lock is still held, so the second dispatch is silently
   dropped — usually. But if the first job has taken >180s (possible on heavy
   matchdays), its lock has expired too, and the second dispatch fires.
5. Two PRB jobs for the same game now run concurrently.

### 4.2 Why `ORDER BY gp.id` didn't save us

Both jobs acquire `Game::lockForUpdate()` per-batch, which serialises their
*batch transactions*. But each job keeps `$ensuredTeamIds` as orchestrator
instance state — not shared across jobs. So:

- Job A starts with empty `$ensuredTeamIds`, inserts for Batch 1's team set `S1`.
- Job B starts with empty `$ensuredTeamIds`, inserts for Batch 1's team set `S1`
  (same teams, same game).

Between batches, the game row lock is released (each batch is its own
transaction). The two jobs leapfrog each other on different batch transactions.
`ORDER BY gp.id` makes the INSERT's lock acquisition order deterministic *within
a statement*, but does not help when Job A's `ensureExistForGamePlayers` for
`S_a` races Job B's `ensureExistForGamePlayers` for `S_b` where the sets overlap
partially but not identically (because each job's `$ensuredTeamIds` differs by
the teams already processed in its own run).

The result: two `INSERT ... ON CONFLICT DO NOTHING` statements with
overlapping-but-non-identical key sets, each holding speculative-insert
xid-level locks on rows the other wants. Deadlock.

### 4.3 The meta-shape

Even without duplicate jobs, the INSERT's speculative-insertion protocol
executes *per row* regardless of whether any insert actually happens:

1. Write a heap tuple (speculative).
2. Probe the unique index.
3. On conflict, release the speculative token and move on.

Each of these (1–3) briefly holds a transaction-level xid lock on the key.
Under heavy concurrency (15 gameplay workers × many games), that narrow window
is enough to produce occasional cycles even when the INSERT's net effect is
zero rows changed.

## 5. Redesign Decision

Two redesign directions were considered:

### 5.1 Option A — Replace the coordination soup with a single advisory lock

Replace `ShouldBeUnique` / `remaining_batches_processing_at` / per-batch
`lockForUpdate` — three overlapping primitives — with one `pg_try_advisory_lock`
per game. Binary, session-scoped, auto-released on worker death. Makes two PRBs
for the same game definitionally impossible.

### 5.2 Option B — Delete lazy materialization entirely

Every `GamePlayer` gets a satellite row at creation time. `ensureExistForGamePlayers`
disappears. The entire class of "lazy ensure" deadlocks is eliminated by
construction.

### 5.3 Choice

After discussion, **Option B** was chosen. The key insight: the sparseness
invariant costs much less than we thought.

- Storage delta: ~4× rows in one small table (60–80 bytes/row × ~6k foreign
  pool players/game). Total across production: low single-digit GB, not TB.
- Creation-time cost: one-off seconds added to new-game setup.
- Runtime cost at matchday: zero (simulation still filters by team).

An audit for code that treats `matchState === null` as a scope signal came back
clean — only 3 touchpoints, all benign accessor fallbacks. The scope concept
lives in `GamePlayerScopeResolver` (used by `ScoutSearchQueryBuilder`), not in
`matchState` nullability.

## 6. Phase A — Eager Materialization

### 6.1 Creation-path audit

Every path that inserts `GamePlayer` rows:

| Path | Creates matchState? |
|---|---|
| `PlayerGeneratorService:124` (single `create`) | ✓ via `createWithDefaults` |
| `PlayerGeneratorService:289` (bulk `insert`) | ✓ via `createForPlayers:293` |
| `SetupTournamentGame:240` | ✓ via `createForPlayers:244` |
| `SaveSquadSelection:98` | ✓ via `createForPlayers:102` |
| `SetupNewGame:372` | **Gated on in-scope teams — the only path needing fix** |
| `AITransferMarketService:1453` upsert | N/A (update-only, no new rows) |
| `PlayerDevelopmentProcessor:85` upsert | N/A (update-only) |

### 6.2 The `SetupNewGame` fix

Dropped the scope-gate at the template-chunk loop — every game player now gets
a satellite row seeded with `fitness` and `morale` from the template (which has
those columns with default 80, so foreign-pool players inherit the sane
defaults).

### 6.3 Backfill

Two migration iterations:

1. Initial: `2026_04_20_000001_backfill_missing_game_player_match_state_rows.php`
   ran a single `INSERT ... SELECT ... LEFT JOIN ... WHERE NULL`.
2. Revised: on large databases the single statement exceeded the deploy
   timeout. The migration was changed to a no-op, and the work moved to an
   out-of-band artisan command `app:backfill-match-states` that iterates games
   one at a time with progress output. `NOT EXISTS` filter keeps it idempotent.

### 6.4 Result

Phase A shipped as commit `60a0e138`. After backfill, every `GamePlayer` in
every game carries a satellite row. The `ensureExistForGamePlayers` INSERT
becomes a structural no-op (all conflicts, nothing inserted).

## 7. Phase B — Observability

### 7.1 Change

`ensureExistForGamePlayers` converted from `DB::statement` to `DB::select` with
`RETURNING game_player_id`. Post-Phase-A, the RETURNING set should always be
empty. If it isn't, that row represents a missed creation path — the method
logs a `Log::warning` with game_id, team_id, game_player_id, capped at 50
entries per call.

```php
Log::warning('GamePlayerMatchState: satellite row was missing and backfilled inline', [
    'game_id' => $gameId,
    'count' => count($missingIds),
    'truncated' => count($missingIds) > $sampleLimit,
    'missing' => array_map(fn ($id) => [...], $sampled),
]);
```

### 7.2 Purpose

A binary signal for "are we safe to delete the lazy path in Phase C?" Silent
logs for a release cycle = invariant holds in the wild = safe to cut.

### 7.3 Important limitation

The warning fires **after** the INSERT succeeds. If the INSERT itself
deadlocks, PHP throws before the log line runs. So the warning doesn't cover
deadlocked cases — but those are independently visible via Flare/Sentry. The
two signals together (warning count + deadlock count) give full visibility.

## 8. Phase C — Deletion

### 8.1 Verification before cutover

To confirm the eager-materialization invariant held in production, ran a scoped
query for the specific failing game from a recent deadlock trace:

```sql
SELECT COUNT(*) FROM game_players gp
WHERE gp.game_id = '<failing-game-uuid>'
  AND NOT EXISTS (
      SELECT 1 FROM game_player_match_state gpms
      WHERE gpms.game_player_id = gp.id
  );
```

Returned `0`. Confirmed with a random-20-game sample — also `0`. Invariant
held. (Scoped queries were used because the tables hold ~17M rows each and
full-table LEFT JOINs risk table scans under concurrent load.)

### 8.2 The cut

Commit `146e83ba` deleted:

- `GamePlayerMatchState::ensureExistForGamePlayers` (the whole method)
- The call at `MatchdayOrchestrator.php:222` and the `$ensuredTeamIds`
  instance state
- The "re-load missing matchStates" fallback block (`MatchdayOrchestrator:229–244`)
- The `Log` import (no longer needed)

Updated four stale comments across `SetupNewGame`, `TransferService`,
`AITransferMarketService`, `ProcessCareerActions` to drop references to the
deleted method while preserving the underlying lock-ordering rationale.

Kept intentionally: `GamePlayer::matchStateValue`'s null-safe fallback. It
costs nothing and acts as a soft safety net if any game somehow has
stragglers the backfill command missed. Degrades to defaults rather than
crashing.

Phase C was merged 2026-04-20 ~21:00 via branch `refactor/delete-ensure-exist-path`.

### 8.3 Result

The original INSERT-variant deadlock query **no longer exists in the codebase**.
Zero further occurrences of that specific error since the cutover.

## 9. Second Deadlock (Post-Phase-C)

### 9.1 What emerged

First seen 2026-04-20T21:36 — almost immediately after Phase C. Different
query, same table:

```
CONTEXT: while updating tuple (161716,86) in relation "game_player_match_state"

UPDATE game_player_match_state SET
    goals = CASE WHEN game_player_id = '...' THEN goals + 2 WHEN ... END,
    assists = CASE WHEN ... END,
    yellow_cards = CASE WHEN ... END
WHERE game_player_id IN ('...', '...', ...)
```

Thrown from `GamePlayerMatchState::bulkIncrementStats`, called by
`MatchResultProcessor::batchProcessPlayerStats` → `processAll`.

Rate: **~25 occurrences/day**, 18 users affected in the first ~22 hours.

### 9.2 Why this class of bug persists

The INSERT and the UPDATE are both expressions of the same underlying
structural issue: **multiple uncoordinated writers to `game_player_match_state`
for the same game can still race**.

`ensureExistForGamePlayers` was one such writer. Deleting it closed one door.
The bulk UPDATEs remain. They write with `WHERE game_player_id IN (...)` —
unsorted lists — so row-lock acquisition order follows the planner's choice
(often bitmap heap scan = physical block order), which is not stable across
sessions with different input sets.

Concurrent writers to `game_player_match_state` *for the same game* can come
from several paths:

- `MatchResultProcessor` inside `processBatch` (has game lock ✓)
- `PlayerConditionService::bulkSetValues` inside `processBatch` (has game lock ✓)
- `MatchResimulationService` via `TacticalChangeService` — **no game lock**
- `MatchResimulationService` via `ProcessExtraTime` (HTTP action) — **no game lock**
- Listeners: `UpdateGoalkeeperStats`, `CheckRecoveredPlayers` — **lock context varies**
- `EligibilityService::setInjury/bulkSetInjuries/clearInjury` — varies by caller

During a live match, the user's frontend calls `TacticalChangeService` (for
subs) concurrently with `ProcessRemainingBatches` in the background. Both
write `game_player_match_state`. Though they typically touch disjoint player
sets (user's team vs AI-match teams), the concurrency window creates the
opportunity for the cycle.

### 9.3 The meta-lesson

We've been fixing this at the query level. Each fix closes one door; the next
query opens another. The table structurally has many writers and weak
coordination — any row-lock UPDATE under concurrency is a candidate for
row-lock cycles.

## 10. Mitigation Options Discussed

### Option 1 — Retry wrapper on `40P01` (tactical)

Wrap every write in `GamePlayerMatchState` in a small retry helper: 2–3
attempts with small random backoff. Postgres cancels one transaction on
deadlock; the retry succeeds on the cleaned-up state. **Does not eliminate
deadlocks — absorbs them transparently.**

```php
private static function retryOnDeadlock(callable $op): void
{
    $attempts = 0;
    while (true) {
        try { $op(); return; }
        catch (QueryException $e) {
            if ($e->getCode() !== '40P01' || ++$attempts >= 3) throw $e;
            usleep(random_int(10_000, 50_000));
        }
    }
}
```

**Pros:** 10 lines, low risk, user-visible error rate goes to zero.
**Cons:** Deadlocks still happen under the hood; doesn't scale if concurrency
grows significantly.

### Option 2 — Game-lock audit (structural)

Audit every writer to `game_player_match_state` and ensure all hold
`Game::lockForUpdate()` for the duration of their write:

- `TacticalChangeService` (currently no lock)
- `ProcessExtraTime` (currently no lock)
- `UpdateGoalkeeperStats` listener (context varies)
- `CheckRecoveredPlayers` listener (context varies)

**Pros:** Eliminates the source rather than recovering from symptoms.
**Cons:**

1. **Live-match UX regression.** `TacticalChangeService` runs when the user
   clicks "make substitution". Blocking on `PRB`'s per-batch transaction (up
   to ~6s) would freeze the substitution UI.
2. **Lock duration stacks.** `ProcessCareerActions` already takes the game
   lock per tick. More contenders = more waiting.
3. **Cross-resource deadlocks.** New lock sites create new ordering rules;
   each needs consistent ordering.
4. **Queued listener context.** Sync-in-tx vs queued-in-worker differ — audit
   every listener.
5. **Coverage is brittle.** One missed writer silently reopens the bug. No
   compile-time enforcement.
6. **Testing.** Deadlocks reproduce non-deterministically; verification is
   mostly observational.

## 11. Proposed Architectural Change — Make PRB Sync

Active proposal at end of session. Key insight: the entire class of "two
transactions touching the same game's match state concurrently" goes away if
we remove the async `ProcessRemainingBatches` job entirely.

### 11.1 Current architecture

- User clicks "Advance matchday" → `ProcessMatchdayAdvance` job runs `advance()`
- `advance()` processes batches up to and including the user's match
- If `advance()` returns `live_match`, it dispatches `ProcessRemainingBatches`
  to process sibling AI batches *in parallel* with the user playing their
  live match in the browser
- The user takes minutes to play the live match; PRB takes ~2–6 seconds

The "parallel" optimisation is mostly fake value: by the time the user
finishes their live match and clicks Continue, PRB is long done anyway.

### 11.2 Two concrete sync options

**Option A — sync in `advance()`.** Process every batch inside `advance()`.
User's "Advance matchday" request takes longer (~+2–6s); no separate PRB
job.

**Option B — sync in `FinalizeMatch`.** Leave `advance()` unchanged; when the
user clicks Continue after their live match, `FinalizeMatch` also processes
the remaining batches before redirecting. Latency lands on the Continue
click, which is already a page-transition the user expects.

Option B is probably better — the added latency lands where the user is
already mentally prepared for a page load, rather than making the Advance
click feel sluggish.

### 11.3 What this deletes

- `ProcessRemainingBatches` job
- `remaining_batches_processing_at` flag + `clearStuckRemainingBatches`
- The "remaining batches" game-loading screen + UI polling
- `deferRemainingBatches` in the orchestrator
- `ShouldBeUnique` / `uniqueFor` timing for PRB

And structurally eliminates the deadlock class: only one transaction per game
at a time, serialized by `Game::lockForUpdate()` in `advance()` / `FinalizeMatch`
/ `ProcessCareerActions`. The bulk `UPDATE` just runs, alone. No retry needed.

### 11.4 Pitfalls

1. **Request/worker timeout.** Synchronous processing pushes a single
   request/job duration higher. Horizon workers handle long jobs fine; HTTP
   request timeouts are usually 30–60s so still within margin, but worth
   confirming.
2. **Long Postgres transactions.** Holding locks longer bloats WAL and defers
   vacuum. Mitigated by keeping per-batch transactions (just run them
   sequentially inside `FinalizeMatch` rather than in a separate job).
3. **All-or-nothing failure.** Today PRB can fail independently; tomorrow
   `FinalizeMatch` couples success. Probably a net improvement — failures
   become loud instead of quiet.
4. **Loss of perceived speed in rapid-advance flows.** If `fast mode` exists
   and uses a different code path, impact is limited to normal play.

### 11.5 Recommendation at session end

Sequence:

1. **Ship Option 1 (retry wrapper) as an immediate hotfix.** Cheap, low-risk,
   takes the user-visible error rate to ~zero while we think.
2. **Prototype Option B (sync in `FinalizeMatch`)** on a branch. Measure the
   added latency on a realistic matchday in staging.
3. If latency is acceptable (<5s typical, <10s p99), ship it and delete the
   PRB coordination code.
4. Once Option B is in production, the retry wrapper becomes decorative —
   can stay as cheap defense-in-depth, or be removed.

Option 2 (the game-lock audit) remains available but is strictly dominated by
Option B: sync-in-`FinalizeMatch` achieves the same structural guarantee
(one tx per game at a time) with a cleaner mental model and no live-match
latency penalty.

## 12. Outstanding Work

- [ ] **Short term:** ship Option 1 (retry-on-40P01 wrapper in
  `GamePlayerMatchState`) as a hotfix.
- [ ] **Medium term:** prototype Option B (sync in `FinalizeMatch`) on a
  branch, measure latency on realistic matchday data.
- [ ] **Medium term:** decide Option B go/no-go based on measurement.
- [ ] **Cleanup:** once Option B is stable, remove the retry wrapper if
  desired, plus `ProcessRemainingBatches` and its coordination machinery.
- [ ] **Optional future cleanup:** simplify `GamePlayer::matchStateValue` to
  drop the null-fallback, since the eager-materialization invariant now
  guarantees presence.

## 13. Key Learnings

1. **Occurrence counts on freshly-fingerprinted errors are misleading.** Early
   in the Phase B soak I misread a new error record's "2 occurrences in 24h"
   as a 98.5% rate drop — it was actually a 45-minute-old record that had only
   just started accumulating. Always compare first-seen timestamps to know if
   you're looking at a trend or a new error's birth cadence.

2. **Fixing query-by-query is a losing strategy when the real issue is
   structural.** Three phases of patches (`ORDER BY`, `ensureExistForGamePlayers`,
   eager materialization) each closed one specific query. The underlying
   "multiple uncoordinated writers" problem kept reopening itself in a
   different query. The right fix was architectural (sync PRB) not tactical.

3. **"Fix it" vs "define it out of existence" are very different work.** The
   eager-materialization redesign (define-out) turned a recurring bug into
   impossible-by-construction. That was a structurally better outcome than
   the smaller patches would have been, even though the diff was larger.

4. **Lazy-materialization patterns carry hidden coordination costs.** The
   sparse satellite table looked cheap (less storage, less write volume), but
   the "ensure at hot-path time" pattern was the root of a persistent bug
   class. Eager materialization cost measurably more (~4× rows) but
   eliminated the bug class at its source. Worth the trade.

5. **Retries are a legitimate mitigation for `40P01`.** Postgres' own
   documentation recommends it. It absorbs the symptom while architectural
   fixes ship. Refusing to retry "because we should fix the root cause" is
   false purity when the root cause is a months-long refactor.

6. **`ShouldBeUnique` + flag-based stuck detection can drift.** The 180s
   Redis lock vs 120s DB flag mismatch was the original duplicate-job vector.
   If you use both, set the stuck-flag threshold to be larger than the unique
   lock duration, or — better — replace the pair with one primitive.

## Appendix A — File-level change index

| Commit | Branch | Summary |
|---|---|---|
| `60a0e138` | `main` | Always seed `game_player_match_state` rows for every `game_player` (Phase A) |
| `9979feaf` | `main` | Chunk match-state backfill to fit inside deploy window |
| `9340a4f5` | `main` | Move match-state backfill off deploy path into per-game artisan command |
| `e1bd01dd` | `main` | Detect missed satellite-row creation paths via RETURNING + warning log (Phase B) |
| `146e83ba` | `refactor/delete-ensure-exist-path` → `main` | Delete `ensureExistForGamePlayers` lazy path and its per-batch call (Phase C) |

## Appendix B — Queries used for invariant verification

Scoped to one game (fastest, preferred):

```sql
SELECT COUNT(*) AS missing
FROM game_players gp
WHERE gp.game_id = '<uuid>'
  AND NOT EXISTS (
      SELECT 1 FROM game_player_match_state gpms
      WHERE gpms.game_player_id = gp.id
  );
```

Random sample across games:

```sql
SELECT sg.id AS game_id, COUNT(gp.id) AS missing
FROM (SELECT id FROM games ORDER BY RANDOM() LIMIT 20) sg
JOIN game_players gp ON gp.game_id = sg.id
LEFT JOIN game_player_match_state gpms ON gpms.game_player_id = gp.id
WHERE gpms.game_player_id IS NULL
GROUP BY sg.id;
```

Full-table scans were deliberately avoided — both tables hold ~17M rows and
full LEFT JOINs could interact badly with concurrent production traffic
during investigation.
