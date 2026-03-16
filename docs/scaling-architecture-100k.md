# Scaling VirtuaFC to 100k Users: Architecture Assessment

## Context

VirtuaFC needs to scale from its current state to 100k users. Low-hanging fruit (Redis for cache/sessions/queue, indexing, eager loading) is already covered. The question: are there architectural changes needed, or can we throw hardware at it?

## The Honest Answer

**Mostly yes, hardware works — with three critical exceptions.** Your game-isolation-by-`game_id` design is your greatest asset. Each game is an independent state machine with zero cross-game data dependencies. This is embarrassingly parallel at the infrastructure level. But three bottlenecks are structural, not resource-limited.

---

## Bottleneck 1: Synchronous Match Simulation (CRITICAL)

**The problem.** Every "advance matchday" click blocks a PHP worker for 2-10 seconds while `MatchdayOrchestrator::advance()` simulates 8+ matches inside a DB transaction. At 10k concurrent users, this pins thousands of workers on CPU-bound work. Octane doesn't help — it's CPU-bound, not boot-time.

**The fix: Async matchday advancement.**
- `AdvanceMatchday` dispatches a `ProcessMatchday` job → redirects to "matchday in progress" view
- Job runs existing orchestrator logic, marks game ready when user's match completes
- Frontend notified via Laravel Broadcasting (Reverb/SSE) or extended polling (5-10s interval)
- The orchestrator logic itself doesn't change — just the call site moves from HTTP to queue

**Files:** `app/Modules/Match/Services/MatchdayOrchestrator.php`, `app/Http/Actions/AdvanceMatchday.php`, `resources/js/live-match.js`

**When:** Before 10k users. This is the only bottleneck that impacts every user's core game loop.

---

## Bottleneck 2: Season Transition Thundering Herd (HIGH)

**The problem.** `ProcessSeasonTransition` runs 25 processors sequentially in one job (300s timeout). The expensive ones: `SeasonSimulationProcessor` simulates 380 matches per non-played league (pure CPU), `PlayerDevelopmentProcessor` recalculates 500+ players, `SquadReplenishmentProcessor` generates 40-60 new players. If 5% of games transition the same day = 5,000 jobs × 60-120s each.

**The fix: Split into chained job batches via `Bus::chain()`.**
- **Phase 1** (I/O, fast): Loan returns, contract expirations, archives, stats reset — bulk SQL ops, ~5-10s
- **Phase 2** (CPU, parallelizable): Each non-played league simulation as a separate sub-job via `Bus::batch()` — run across workers in parallel
- **Phase 3** (CPU, sequential): Player development, squad replenishment, youth academy — must be sequential but separate job from Phase 1
- **Phase 4** (depends on 2+3): Promotion/relegation, UEFA qualification, fixtures, budgets

The existing `SeasonProcessor` priority system already defines ordering. The change is grouping into dependency-safe batches.

**Files:** `app/Modules/Season/Jobs/ProcessSeasonTransition.php`, `app/Modules/Season/Services/SeasonClosingPipeline.php`, `app/Modules/Finance/Services/SeasonSimulationService.php`

**When:** Before 25k users.

---

## Bottleneck 3: View-Level Query Load (MEDIUM)

**The problem.** `ShowSquad` (7-8 queries + 15 aggregations/player), `ShowLineup` (12-15 queries + AI formation), `ShowCompetition` (loads ALL matches, filters in PHP) — all executed on every page load, zero caching. At 30% DAU with 5 page loads/session = 150k view renders/day.

**The fix: Lightweight denormalization (not full CQRS).**
- **Team form strings** ("WWDLW"): Store on `GameStanding`, update after each match. Eliminates loading all matches in `ShowCompetition::getTeamForms()`
- **Top scorers**: Materialize on `GameStanding` or cache table, update after match batch. Eliminates JOIN + GROUP BY on `match_events`
- **Opponent scouting data**: Cache per matchday on lineup view — doesn't change between visits within same matchday
- **Squad summary KPIs**: Compute after matchday advance, store as JSON. Invalidate on transfer/contract changes

Why database denormalization over Redis caching: data changes at well-defined write points (matchday advance, transfer), invalidation is trivial (same transaction), no cache stampede on cold start.

**Files:** `app/Http/Views/ShowCompetition.php`, `app/Http/Views/ShowSquad.php`, `app/Http/Views/ShowLineup.php`, `app/Models/GameStanding.php`

**When:** Incremental, start before 10k users with team forms.

---

## Bottleneck 4: Horizon Queue Configuration (QUICK WIN)

**Current:** Single supervisor, 10 workers, one queue, 60s timeout.

**Fix:**
- **Priority queues:** `matchday` (high — users waiting), `default` (game setup, transitions), `low` (analytics, cleanup)
- **Dedicated supervisors:** matchday: 20 workers/120s timeout, default: 15 workers/300s timeout, low: 5 workers/600s timeout
- **Scale to 50-100 workers** total (auto-balanced by Horizon)

**File:** `config/horizon.php`

**When:** Now. Config-only change.

---

## What You Can Safely Ignore

| Idea | Why Skip It |
|------|-------------|
| **Microservices** | Modular monolith with game isolation handles 100k. Extracting a "match simulation service" adds network latency for zero benefit — simulation is CPU-bound, not I/O-bound |
| **Full CQRS / event sourcing** | Lightweight denormalization gets 90% of benefit |
| **Database sharding** | PostgreSQL handles 50M+ rows with proper indexes and game-scoped queries. Neon scales vertically well |
| **Database partitioning** | Defer until VACUUM or index sizes become problematic (100M+ rows in `match_events`). If needed: hash partition on `game_id`, 16-32 partitions |
| **Read replicas** | Defer until metrics prove read load is the bottleneck (unlikely given denormalization) |
| **GraphQL / API gateway** | App serves Blade templates, no API consumption pattern |

---

## Priority Order

| # | Change | Impact | Effort | When |
|---|--------|--------|--------|------|
| 1 | Horizon queue splitting + more workers | High | Very Low | Now |
| 2 | Async matchday advancement | Critical | Medium | Before 10k |
| 3 | Team form / top scorer denormalization | Medium | Low | Before 10k |
| 4 | Season transition job batching | High | Medium | Before 25k |
| 5 | Enable Octane (FrankenPHP) | Medium | Low | Before 25k |
| 6 | Squad summary caching | Medium | Medium | Before 50k |
| 7 | Polling → push (Broadcasting/SSE) | Low-Med | Medium | Before 50k |
| 8 | Database partitioning | Low | High | Only if metrics demand |

---

## TL;DR

Your architecture is fundamentally sound for 100k. The game-per-user isolation means horizontal scaling works for most things. The three real changes are: **(1)** get match simulation off the HTTP request, **(2)** break season transitions into parallel job batches, **(3)** denormalize the heaviest view queries. Everything else is configuration tuning or deferred until data proves the need. You don't need microservices, sharding, or a rewrite — but you can't purely throw hardware at synchronous CPU-bound work in HTTP requests.
