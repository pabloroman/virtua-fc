# VirtuaFC Beta Launch Analysis — Pre-Public Release Audit

**Date:** 2026-02-22
**Scope:** Full codebase analysis across security, scalability, performance, data integrity, and operational readiness.

---

## Executive Summary

VirtuaFC has a **solid architectural foundation** — modular monolith design, proper auth middleware, safe Blade templating, and good eager-loading practices. However, the codebase was built with a single-tenant beta mindset and has **critical gaps that must be addressed before opening signups publicly**:

- **4 Critical issues** (security vulnerabilities, data corruption risks)
- **9 High-priority issues** (race conditions, missing protections, operational risks)
- **10+ Medium-priority issues** (performance, hardening, observability)

Estimated effort for critical + high fixes: **3–5 days of focused work**.

---

## Table of Contents

1. [Critical Issues (Fix Before Launch)](#1-critical-issues)
2. [High Priority (Fix Before or Immediately After Launch)](#2-high-priority-issues)
3. [Medium Priority (Address Within First Month)](#3-medium-priority-issues)
4. [Low Priority (Backlog)](#4-low-priority-issues)
5. [What's Already Solid](#5-whats-already-solid)
6. [Scalability Assessment](#6-scalability-assessment)
7. [Deployment Checklist](#7-deployment-checklist)

---

## 1. Critical Issues

### C1. `is_admin` in User `$fillable` — Privilege Escalation Risk

**File:** `app/Models/User.php:53-60`

```php
protected $fillable = [
    'name', 'email', 'password',
    'feedback_requested_at',
    'is_admin',  // ← DANGEROUS
    'locale',
];
```

**Current exposure:** Registration explicitly passes only `name`, `email`, `password`, and profile update uses `$request->validated()` (only `name`, `email`, `locale`). So this isn't currently exploitable.

**Risk:** Any future code that does `$user->update($request->all())` or `User::create($request->all())` would let a user set themselves as admin. This is a latent vulnerability — one careless code change away from full privilege escalation.

**Fix:** Remove `is_admin` from `$fillable`. Set it explicitly when needed:
```php
$user->is_admin = true;
$user->save();
```

---

### C2. Race Condition on Season Transition — Data Corruption

**File:** `app/Http/Actions/StartNewSeason.php`

```php
if ($game->isTransitioningSeason()) {
    return redirect()->route('show-game', $gameId);
}
$game->update(['season_transitioning_at' => now()]);  // Non-atomic!
ProcessSeasonTransition::dispatch($game->id);
```

Between the check and the update, two simultaneous requests can both pass validation and both dispatch `ProcessSeasonTransition`. The season-end pipeline runs 21 processors that expire contracts, retire players, settle finances, and generate fixtures. Running it twice would:
- Double financial settlements
- Duplicate fixture generation
- Corrupt standings and season archives

**Fix:** Use an atomic lock:
```php
$updated = Game::where('id', $gameId)
    ->whereNull('season_transitioning_at')
    ->update(['season_transitioning_at' => now()]);

if (!$updated) {
    return redirect()->route('show-game', $gameId);
}
ProcessSeasonTransition::dispatch($gameId);
```

---

### C3. Race Condition on Matchday Advancement — No Concurrency Control

**File:** `app/Modules/Match/Services/MatchdayOrchestrator.php:50`

The `advance()` method has **zero locking**. If a user double-clicks "Play Matchday" or has a flaky connection that retries, two requests could simultaneously:
- Fetch the same next match batch
- Simulate the same matches independently
- Both write results to the database
- Both update standings (potentially with different random outcomes)

**Impact:** Duplicate match events, corrupted standings, inconsistent game state.

**Fix:** Add a pessimistic lock at the entry point:
```php
public function advance(Game $game): MatchdayAdvanceResult
{
    return DB::transaction(function () use ($game) {
        $game = Game::where('id', $game->id)->lockForUpdate()->first();
        // ... existing logic
    });
}
```

---

### C4. Missing Security Headers — XSS/Clickjacking Exposure

**File:** `bootstrap/app.php` (missing entirely)

The application ships with **no security headers**:

| Header | Status | Risk |
|--------|--------|------|
| `X-Frame-Options` | Missing | Clickjacking attacks |
| `Content-Security-Policy` | Missing | XSS via injected scripts |
| `X-Content-Type-Options` | Missing | MIME-sniffing attacks |
| `Strict-Transport-Security` | Missing | Downgrade attacks |
| `Referrer-Policy` | Missing | Referrer leakage |

**Fix:** Add a security headers middleware:
```php
// In bootstrap/app.php or a dedicated middleware
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
})
```

---

## 2. High Priority Issues

### H1. Season End Pipeline Not Transaction-Wrapped

**File:** `app/Modules/Season/Services/SeasonEndPipeline.php`

21 processors run sequentially. If processor #15 fails, processors #1–14 have already committed. The game is left in a half-transitioned state with:
- Contracts expired but no new fixtures
- Players retired but standings not reset
- Finances settled but budgets not projected

**Fix:** Wrap the entire pipeline in a transaction, or implement a checkpoint/resumption mechanism.

---

### H2. Queue Jobs Have No Retry Strategy

**File:** `config/horizon.php:192` — `'tries' => 1`

All jobs (`SetupNewGame`, `ProcessSeasonTransition`, `SetupTournamentGame`) attempt once and permanently fail. A transient database lock, Redis timeout, or memory spike will:
- Leave new games in an unplayable state (missing players/fixtures)
- Leave season transitions half-complete
- Require manual database intervention

**Fix:**
```php
// On each job class
public int $tries = 3;
public array $backoff = [30, 120, 300];
```
Combine with wrapping job logic in `DB::transaction()` so partial work is rolled back on failure.

---

### H3. No Rate Limiting on Most Endpoints

Only 3 of ~40 action endpoints have rate limiting:

| Protected | Unprotected |
|-----------|-------------|
| Login (5/min) | Registration |
| Email verification (6/min) | Game creation |
| Tactical changes (10/min) | Matchday advancement |
| | Transfer bids |
| | Scout searches |
| | All other game actions |

A malicious user or bot could spam transfer bids, scout searches, or matchday advancement to degrade performance for all users.

**Fix:** Add rate limiting to sensitive routes:
```php
Route::post('/game/{gameId}/advance', AdvanceMatchday::class)
    ->middleware('throttle:5,1');

Route::post('/game/{gameId}/scouting/search', SubmitScoutSearch::class)
    ->middleware('throttle:5,1');

Route::post('/game/{gameId}/scouting/{playerId}/bid', SubmitTransferBid::class)
    ->middleware('throttle:10,1');

Route::post('register', [RegisteredUserController::class, 'store'])
    ->middleware('throttle:3,1');
```

---

### H4. Cross-Game Notification Access

**File:** `app/Http/Actions/MarkNotificationRead.php`

```php
$notification = $this->notificationService->markAsRead($notificationId);
// ⚠️ No check that $notification->game_id === $gameId
```

The `game.owner` middleware validates the route's `{gameId}`, but the action doesn't scope the notification query to that game. User A could mark User B's notifications as read by guessing notification UUIDs.

**Fix:**
```php
$notification = GameNotification::where('game_id', $gameId)
    ->findOrFail($notificationId);
$notification->markAsRead();
```

---

### H5. Duplicate Transfer Bids Allowed

**File:** `app/Http/Actions/SubmitTransferBid.php`

No check prevents submitting multiple pending bids for the same player from the same team. Each creates a new `TransferOffer` row, leading to database bloat and ambiguous game state.

**Fix:** Add a uniqueness check before creation:
```php
$existingBid = TransferOffer::where('game_id', $gameId)
    ->where('game_player_id', $playerId)
    ->where('offering_team_id', $game->team_id)
    ->where('status', TransferOffer::STATUS_PENDING)
    ->exists();

if ($existingBid) {
    return back()->with('error', __('messages.bid_already_exists'));
}
```

---

### H6. User Account Deletion Crashes with Foreign Key Error

**File:** `app/Http/Controllers/ProfileController.php:49`

The `games` table has `foreignId('user_id')->constrained()` with **no cascade delete**. When a user with games tries to delete their account:

```php
$user->delete();  // ← throws integrity constraint violation
```

This results in a 500 error page.

**Fix:** Either:
- Add `->cascadeOnDelete()` to the migration, or
- Delete user's games before deleting the user in `ProfileController::destroy()`

---

### H7. Transfer Window Not Validated in Action Layer

**Files:** `ListPlayerForTransfer.php`, `SubmitTransferBid.php`, `RequestLoan.php`, `SubmitPreContractOffer.php`

None of these actions validate that the current game date falls within the transfer window. While backend simulation logic may reject out-of-window offers, the action layer should fail fast.

**Fix:** Add a check:
```php
if (!$game->isTransferWindowOpen()) {
    return back()->with('error', __('messages.transfer_window_closed'));
}
```

---

### H8. Session Security Defaults Too Permissive

**File:** `.env.example`

| Setting | Current | Should Be |
|---------|---------|-----------|
| `SESSION_ENCRYPT` | `false` | `true` |
| `SESSION_SECURE_COOKIE` | not set | `true` |
| `SESSION_SAME_SITE` | `lax` | `strict` |
| `LOG_LEVEL` | `debug` | `warning` |

These need to be verified in the production `.env`.

---

### H9. Password Reset Disabled

**File:** `routes/auth.php:27-37` — Routes commented out.

Users who forget their password are permanently locked out. Before public launch, re-enable with proper rate limiting:
```php
Route::post('/forgot-password', ...)
    ->middleware('throttle:3,1');  // 3 attempts per minute
```

---

## 3. Medium Priority Issues

### M1. Missing Database Indices

Several frequently-queried columns lack indices:

| Table | Column | Used In |
|-------|--------|---------|
| `games` | `user_id` | Dashboard, all game lookups |
| `game_players` | `contract_until` | Season-end contract expiration |
| `game_players` | `injury_until` | Lineup availability checks |
| `game_matches` | `scheduled_date` | Calendar, countdown queries |
| `loans` | `parent_team_id` | Loaned-out player queries |
| `loans` | `loan_team_id` | Loaned-in player queries |

**Impact:** Queries become full table scans as data grows. Add indices via migration.

---

### M2. Zero Caching Strategy

No `Cache::` calls exist anywhere. Every page load executes fresh database queries. Key opportunities:

- **Game finances** (changes once per season): Cache with `game:{id}:finances` key
- **League strengths** (BudgetProjectionService): Expensive GROUP BY across all teams
- **Competition standings** (StandingsCalculator): Recalculated on every view

At 500+ concurrent users, this creates ~5-10K QPS to the database.

**Fix:** Add Redis cache layer with smart invalidation for read-heavy data.

---

### M3. Raw SQL String Interpolation

**File:** `app/Modules/Match/Services/MatchResultProcessor.php:116-127`

```php
$homeCases[] = "WHEN id = '{$id}' THEN {$result['homeScore']}";
// ... later
DB::statement("UPDATE game_matches SET home_score = CASE " . implode(' ', $homeCases) . " END ...");
```

While `$id` and `$result['homeScore']` come from internal simulation (not user input), this pattern bypasses prepared statements. A similar pattern exists in `PlayerConditionService.php`.

**Fix:** Use query builder with parameter binding instead of string concatenation.

---

### M4. StandingsCalculator Recalculation Loop

**File:** `app/Modules/Competition/Services/StandingsCalculator.php:163-199`

```php
foreach ($standings as $standing) {
    $standing->update([...]);  // N individual UPDATE queries
}
```

For a 20-team league, this executes 20 separate UPDATE queries. At season-end with multiple competitions, this compounds.

**Fix:** Use a bulk UPDATE with CASE WHEN or `upsert()`.

---

### M5. Horizon / Telescope Production Access

**Files:** `app/Providers/HorizonServiceProvider.php:31`, `app/Providers/TelescopeServiceProvider.php:59`

Both have empty email whitelists:
```php
Gate::define('viewHorizon', function ($user) {
    return in_array($user->email, [
        //  ← empty
    ]);
});
```

This is safe (nobody can access), but means **you have no production monitoring dashboard**. Populate with admin emails.

---

### M6. PostgreSQL SSL Mode Too Permissive

**File:** `config/database.php`

```php
'sslmode' => 'prefer',  // Allows fallback to unencrypted
```

Neon uses TLS by default, but `prefer` mode doesn't enforce it.

**Fix:** Change to `'sslmode' => 'require'`.

---

### M7. Database Seeder Creates Backdoor Account

**File:** `database/seeders/DatabaseSeeder.php:20`

```php
User::create([
    'email' => 'info@example.net',
    'password' => Hash::make('1234'),
]);
```

If `php artisan db:seed` is ever accidentally run in production, this creates a known-credentials account.

**Fix:** Guard with environment check:
```php
if (app()->environment('production')) {
    $this->command->error('Cannot seed in production!');
    return;
}
```

---

### M8. `SaveSquadSelection` — Unhandled File Exception

**File:** `app/Http/Actions/SaveSquadSelection.php:37`

```php
$data = json_decode(file_get_contents($jsonPath), true);  // No try-catch
```

If the JSON file doesn't exist, this throws a PHP warning and could expose the file path in error output.

---

### M9. Admin Impersonation Audit Trail

**Files:** `app/Http/Actions/StartImpersonation.php`, `StopImpersonation.php`

No logging of who impersonated whom and when. For a public service, admin actions should be auditable.

**Fix:** `Log::info("Admin {$admin->email} started impersonating user {$target->email}")`.

---

### M10. API Waitlist Endpoint Unthrottled

**File:** `routes/api.php`

```php
Route::post('/waitlist', JoinWaitlist::class);  // No rate limiting
```

Can be spammed to fill the database with junk entries.

---

## 4. Low Priority Issues

| # | Issue | Location |
|---|-------|----------|
| L1 | Offer expiration not checked on accept | `AcceptTransferOffer.php` |
| L2 | Design system route publicly accessible | `routes/web.php:77-79` |
| L3 | `axios` package outdated (1.7.4 → 1.7.7) | `package.json` |
| L4 | No pagination on academy players view | `ShowAcademy.php` |
| L5 | `BudgetProjectionService::calculateLeagueStrengths()` loads all players into memory and filters in PHP | Should use GROUP BY |
| L6 | Form calculation loads 380+ matches into memory | `ShowCompetition::getTeamForms()` |
| L7 | `RequestLoan::handleLoanOut` has check-then-act race | Should use atomic check |

---

## 5. What's Already Solid

The analysis revealed many well-implemented patterns:

| Area | Assessment |
|------|-----------|
| **Game ownership middleware** | All game routes properly protected via `EnsureGameOwnership` |
| **Blade XSS protection** | No unescaped `{!! !!}` with user data; all output properly escaped |
| **CSRF protection** | `@csrf` in all forms; AJAX uses `X-CSRF-TOKEN` header |
| **Mass assignment** | All models use explicit `$fillable` arrays |
| **Auth flow** | Proper password hashing, session regeneration on login/logout |
| **Beta invite system** | Solid implementation with usage limits, expiration, email matching |
| **Modular architecture** | Clean module boundaries with proper dependency direction |
| **Eager loading** | Good use of `with()` to prevent N+1 in views/actions |
| **Batch operations** | MatchResultProcessor uses `keyBy()` and bulk loads |
| **Match simulation** | Zero DB queries during simulation; all data pre-loaded |
| **Input validation** | Strong validation in most actions (formations, budgets, substitutions) |
| **Game limits** | 3 games per user hard cap |
| **Mobile responsiveness** | Consistent responsive patterns throughout |
| **Onboarding UX** | Clear progression flow with helpful guidance |
| **Secret management** | No hardcoded secrets; proper `.gitignore` |

---

## 6. Scalability Assessment

### Current Capacity

| Concurrent Users | Status | Bottleneck |
|-------------------|--------|-----------|
| **100–200** | Comfortable | None |
| **500** | Risky | Season transitions may timeout; no query caching |
| **1,000** | Fragile | Database becomes bottleneck (~10K QPS); job failures corrupt games |
| **5,000+** | Breaks | Needs read replicas, distributed job processing, caching |

### Key Scalability Risks

1. **Season transitions** — 21 sequential processors with 60s job timeout. Large leagues (Segunda with playoffs) risk timeout and corruption.

2. **No caching** — Every page load hits the database. With 500+ users browsing their squads/standings, the database saturates.

3. **Single queue** — All jobs share one queue. A spike of `SetupNewGame` jobs during a marketing event could delay `ProcessSeasonTransition` jobs.

4. **Horizon pool** — 10 max processes in production. 100 concurrent game setups would take ~60-100 seconds to clear.

5. **Memory** — `SetupNewGame` loads all reference players via `Player::all()`. With 10K+ reference players, each worker uses ~200MB.

### Scaling Roadmap

**Phase 1 (Launch):** Fix critical issues, add rate limiting, add basic caching.
**Phase 2 (500 users):** Redis cache layer, separate queues for game setup vs season transition, increase Horizon workers.
**Phase 3 (1000+ users):** Read replicas, async season simulation, connection pooling (PgBouncer).

---

## 7. Deployment Checklist

### Must Verify in Production `.env`

```bash
APP_ENV=production
APP_DEBUG=false
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
```

### Must Do Before Launch

- [ ] Remove `is_admin` from User `$fillable`
- [ ] Add atomic lock to `StartNewSeason`
- [ ] Add pessimistic lock to `MatchdayOrchestrator::advance()`
- [ ] Add security headers middleware (CSP, X-Frame-Options, HSTS, etc.)
- [ ] Scope `MarkNotificationRead` to game_id
- [ ] Add duplicate bid prevention to `SubmitTransferBid`
- [ ] Fix user account deletion (cascade or pre-delete games)
- [ ] Add rate limiting to registration and key game actions
- [ ] Re-enable password reset with rate limiting
- [ ] Change PostgreSQL `sslmode` to `require`

### Should Do Before Launch

- [ ] Add retry strategy to queue jobs (tries=3, backoff)
- [ ] Wrap season-end pipeline in transaction
- [ ] Add missing database indices (user_id, contract_until, etc.)
- [ ] Populate Horizon/Telescope email whitelists
- [ ] Guard database seeder against production execution
- [ ] Add transfer window validation to transfer action classes
- [ ] Run `composer audit` and `npm audit` for known vulnerabilities

### Should Do Within First Month

- [ ] Add Redis caching for finances, standings, league strengths
- [ ] Refactor raw SQL interpolation to use parameter binding
- [ ] Bulk-update StandingsCalculator recalculation
- [ ] Add admin impersonation audit logging
- [ ] Rate limit the waitlist API endpoint
- [ ] Add pagination to academy players view
