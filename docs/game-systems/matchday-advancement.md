# Matchday Advancement System

How matches are found, simulated, and processed — the core gameplay loop.

## Overview

The matchday advancement system handles: finding the next batch of matches, simulating them, processing results (standings, tie resolution, suspensions), generating the next round of knockout/playoff matches, and determining when a season or tournament is complete.

```
User clicks "Play"
    │
    ├─ Generate pending knockout/playoff rounds
    ├─ Find next batch of matches
    ├─ Simulate batch
    ├─ Process results (standings, suspensions, GK stats)
    ├─ Resolve completed cup ties
    ├─ Generate next knockout/playoff round
    ├─ Award prize money, send notifications
    └─ Return: live match (user plays) | done | season complete
```

## Entry Points

There are three ways matchday advancement starts:

| Entry | Route | When |
|-------|-------|------|
| `AdvanceMatchday` | `POST /game/{id}/advance` | User clicks "Play next match" |
| `FinalizeMatch` | `POST /game/{id}/finalize-match` | User finishes viewing their live match |
| `SimulateTournament` | `GET /game/{id}/simulate-tournament` | Auto-simulate remaining matches after user is eliminated (tournament mode) |

All three ultimately call `MatchdayOrchestrator::advance()`.

## Key Components

### MatchdayOrchestrator

**File:** `app/Modules/Match/Services/MatchdayOrchestrator.php`

The main coordinator. `advance(Game)` runs inside a DB transaction with a row lock to prevent concurrent calls. It:

1. Safety-finalizes any stuck pending match (browser close recovery)
2. Checks for blocking conditions (career actions, pending user actions)
3. Loops through match batches until the user's team plays or season ends
4. For each batch: simulates, processes results, runs post-match actions
5. Returns a `MatchdayAdvanceResult` (live_match, done, blocked, season_complete)

### MatchdayService

**File:** `app/Modules/Match/Services/MatchdayService.php`

Finds and assembles the next batch of matches. `getNextMatchBatch(Game)`:

1. Calls `generatePendingMatches()` — triggers `beforeMatches()` on hybrid handlers (league_with_playoff, swiss_format, group_stage_cup) to create knockout rounds if a phase just completed
2. Calls `KnockoutCupHandler::beforeMatches()` — conducts draws for simple knockout cups
3. Finds the next unplayed match by `scheduled_date`
4. Gathers all matches on that date + expands league matches to include the full round
5. Returns the batch with resolved handlers per competition

### MatchFinalizationService

**File:** `app/Modules/Match/Services/MatchFinalizationService.php`

Handles deferred finalization for the user's match (see Deferred Finalization below). Steps:

1. Serve deferred suspensions
2. Resolve cup tie if applicable → dispatch `CupTieResolved`
3. Dispatch `MatchFinalized` → triggers standings update, GK stats, notifications
4. Clear `pending_finalization_match_id`
5. Call `handler->beforeMatches()` to generate next round (only for group-phase matches)

### Competition Handlers

**Interface:** `app/Modules/Competition/Contracts/CompetitionHandler.php`

Each competition type has a handler that implements four methods:

| Method | Purpose |
|--------|---------|
| `getMatchBatch()` | Which matches play together? League: by round. Cup: by date. |
| `beforeMatches()` | Generate next round if previous completed. No-op for simple types. |
| `afterMatches()` | Resolve cup ties, dispatch events. No-op for leagues. |
| `getRedirectRoute()` | Where to redirect after the batch plays |

## Competition Handlers in Detail

### LeagueHandler (`league`)

Simplest handler. Batches by `round_number`. `beforeMatches()` and `afterMatches()` are no-ops — standings are updated by the `UpdateLeagueStandings` listener on `MatchFinalized`.

### KnockoutCupHandler (`knockout_cup`)

Used for Copa del Rey, Supercopa. Batches by `scheduled_date`. `beforeMatches()` is a no-op. `afterMatches()` resolves cup ties and dispatches `CupTieResolved`. Next round generation happens via the `ConductNextCupRoundDraw` listener, which calls `CupDrawService` for random draw pairing.

### SwissFormatHandler (`swiss_format`)

Used for Champions League, Europa League, Conference League. Hybrid batching: by round for league phase, by date for knockout. `beforeMatches()` checks if the league phase is complete and generates knockout rounds via `SwissKnockoutGenerator`. `afterMatches()` resolves knockout ties.

### GroupStageCupHandler (`group_stage_cup`)

Used for World Cup. Hybrid batching like Swiss. `beforeMatches()` checks if group stage is complete and generates knockout rounds via `WorldCupKnockoutGenerator`. Special logic after semi-finals: generates both 3rd-place match (SF losers) and final (SF winners) together. `afterMatches()` resolves knockout ties.

### LeagueWithPlayoffHandler (`league_with_playoff`)

Used for Segunda Division. Hybrid batching. `beforeMatches()` checks if the regular season is complete and generates playoff rounds via `PlayoffGeneratorFactory`. `afterMatches()` resolves playoff ties.

### PreSeasonHandler (`preseason`)

Batches by date. Everything is a no-op — friendly matches have no consequences.

## Round Generation

This is the most complex part of the system. Different handlers generate knockout/playoff rounds through different mechanisms:

```
                           ┌─────────────────────────────────────┐
                           │ How next round gets generated       │
                           └─────────────────────────────────────┘

knockout_cup:    CupTieResolved event → ConductNextCupRoundDraw listener → CupDrawService
swiss_format:    handler.beforeMatches() → maybeGenerateKnockoutRound() → SwissKnockoutGenerator
group_stage_cup: handler.beforeMatches() → maybeGenerateKnockoutRound() → WorldCupKnockoutGenerator
league_playoff:  handler.beforeMatches() → shouldGeneratePlayoffRound()  → PlayoffGeneratorFactory
```

The `ConductNextCupRoundDraw` listener explicitly skips `swiss_format` and `group_stage_cup` — those handlers own their round generation internally. This split exists because Swiss and World Cup formats need specialized generation (seeded brackets, 3rd-place logic) while Copa del Rey uses simple random draws.

### When round generation is triggered

Round generation happens at multiple points:

1. **`MatchdayService::generatePendingMatches()`** — before finding the next batch. Covers hybrid handlers.
2. **`MatchdayService::getNextMatchBatch()`** — calls `KnockoutCupHandler::beforeMatches()` and per-competition `handler->beforeMatches()`.
3. **`ConductNextCupRoundDraw` listener** — fires synchronously on `CupTieResolved` for knockout cups.
4. **`MatchFinalizationService::finalize()`** — after deferred match finalization, calls `handler->beforeMatches()` for group-phase matches.

Handlers use idempotency guards (`knockoutRoundExists()`) to handle being called multiple times safely.

### Pending finalization guard

Round generation is blocked while a match is pending finalization for the same competition. The deferred match's result might affect standings, which affect seeding for the next round. Handlers check `game->hasPendingFinalizationForCompetition()` before generating.

## Deferred Finalization

When the user's team plays, their match is "deferred" — simulated but not fully processed until the user finishes viewing the live match result.

```
Batch includes user's match
    │
    ├─ All matches simulated (scores determined)
    ├─ AI matches: results processed immediately (standings, ties)
    ├─ User's match: marked as pending_finalization_match_id
    ├─ User redirected to live match view
    │
    ├─ User views match, makes substitutions/tactical changes
    ├─ User clicks "Continue"
    │
    └─ FinalizeMatch action → MatchFinalizationService::finalize()
        ├─ Apply deferred standings, suspensions, GK stats
        ├─ Resolve cup tie if applicable
        ├─ Generate next round (now that standings are final)
        └─ Clear pending flag
```

Why defer? Because all matches in a batch are simulated simultaneously, but the user's match is experienced in real-time. Standings and seedings must wait for all results.

## Event-Driven Side Effects

Two events drive post-match side effects:

### MatchFinalized

Dispatched after a match's results are fully applied (either immediately for AI matches via `MatchResultProcessor`, or deferred for user matches via `MatchFinalizationService`).

| Listener | Purpose |
|----------|---------|
| `UpdateLeagueStandings` | Update standings for league matches (not cup) |
| `UpdateGoalkeeperStats` | Increment goals_conceded, clean_sheets |
| `SendMatchNotifications` | Red cards, injuries, yellow card accumulation |
| `SendCompetitionProgressNotifications` | Advancement/elimination notifications |

### CupTieResolved

Dispatched when a cup tie has a definitive winner (after 90 min, extra time, or penalties).

| Listener | Purpose |
|----------|---------|
| `AwardCupPrizeMoney` | Record prize money for advancing |
| `ConductNextCupRoundDraw` | Generate next round draw (knockout_cup only) |
| `SendCupTieNotifications` | Advancement/elimination notifications |

## Season/Tournament Completion

`ShowGame` determines what to show the user:

```php
$nextMatch = $game->next_match;                                    // User's next unplayed match
$hasRemainingMatches = $game->matches()->where('played', false)->exists(); // Any unplayed matches

if (tournament && !$nextMatch && $hasRemainingMatches)  → simulate remaining (user eliminated)
if (!$nextMatch && !$hasRemainingMatches)                → season-end / tournament-end
else                                                     → show dashboard with next match
```

In tournament mode, if the user is eliminated but matches remain, `SimulateTournament` auto-advances up to 500 batches to finish the tournament.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Match/Services/MatchdayOrchestrator.php` | Main coordinator — advance, simulate, process |
| `app/Modules/Match/Services/MatchdayService.php` | Batch assembly, pending round generation |
| `app/Modules/Match/Services/MatchFinalizationService.php` | Deferred match finalization |
| `app/Modules/Match/Services/MatchSimulator.php` | Match simulation engine |
| `app/Modules/Match/Services/MatchResultProcessor.php` | Batch result processing |
| `app/Modules/Match/Services/CupTieResolver.php` | Cup tie resolution (aggregate, ET, penalties) |
| `app/Modules/Competition/Contracts/CompetitionHandler.php` | Handler interface |
| `app/Modules/Match/Handlers/` | All 6 handler implementations |
| `app/Modules/Match/Events/` | MatchFinalized, CupTieResolved |
| `app/Modules/Match/Listeners/` | Event listeners for side effects |
| `app/Modules/Competition/Services/CupDrawService.php` | Random cup draw mechanics |
| `app/Modules/Competition/Services/WorldCupKnockoutGenerator.php` | World Cup bracket generation |
| `app/Modules/Competition/Services/SwissKnockoutGenerator.php` | Champions League knockout generation |
| `app/Http/Actions/AdvanceMatchday.php` | Entry point: user clicks "Play" |
| `app/Http/Actions/FinalizeMatch.php` | Entry point: user finishes live match |
| `app/Http/Actions/SimulateTournament.php` | Entry point: auto-simulate after elimination |
| `app/Http/Views/ShowGame.php` | Dashboard — determines next action |

## Known Design Debt

The system evolved organically as competition types were added. Key areas of debt:

1. **Inconsistent round generation**: KnockoutCupHandler uses a listener while other handlers generate internally. The listener hardcodes which types to skip.

2. **Redundant `beforeMatches()` calls**: Handlers are called 2-3 times per cycle from different call sites. Relies on idempotency guards.

3. **Cup tie resolution duplication**: Nearly identical `resolveTies()` method copy-pasted across 4 handlers.

4. **`ShowGame` doesn't trigger round generation**: It queries for existing matches directly. If rounds haven't been generated yet, the game can appear "complete" prematurely.

5. **Pending finalization timing**: `CupTieResolved` events fire before `pending_finalization_match_id` is cleared, creating ordering constraints between listeners and handlers.
