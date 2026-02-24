# Game Save & Onboarding UX Plan

## Problem Statement

Three interrelated issues:
1. **Manager name input is friction with no payoff** — users focus on picking a team, the name field is easily missed, and it only appears in 2 greeting lines that nobody reads twice
2. **Users have no idea their progress auto-saves** — there's no messaging about persistence, game slots, or the 3-game limit
3. **Abandoned games clog the database** — current cleanup (matchday 0, 2+ days old) works but could be smarter

## Core Design Principle

**No explicit "save" mechanic.** Web games should auto-save — adding a save button would create confusion ("if I don't save, do I lose progress?"). Instead, we communicate clearly that progress persists automatically.

---

## Part 1: Remove Manager Name Input

**Rationale:** `player_name` is used in exactly 2 places — a greeting on `welcome-tutorial.blade.php:13` and `squad-selection.blade.php:55`. Neither adds value. Removing it eliminates a friction point in game creation.

### Changes

1. **`select-team.blade.php`** — Remove the `<label>` block with the name text input (lines 28-36) and its error display
2. **`InitGame.php`** — Remove `'name'` from validation rules, stop passing `playerName` to creation services
3. **`GameCreationService.php`** — Remove `playerName` parameter, stop setting `player_name` on the Game model
4. **`TournamentCreationService.php`** — Same treatment
5. **`welcome-tutorial.blade.php`** — Replace `__('game.welcome_name', ['name' => $game->player_name])` with a team-based greeting (e.g., "Welcome to [Team Name]" / "Bienvenido al [Team Name]")
6. **`squad-selection.blade.php`** — Same replacement
7. **Translation files** — Update `game.welcome_name` key in both `lang/es/game.php` and `lang/en/game.php` to remove the `:name` parameter, or create a new key
8. **Database** — Make `player_name` nullable via migration (keep the column for now — existing games have data in it, and removing a column is a one-way door we don't need to walk through yet)

**Decision:** Keep the column nullable. Existing games have data, and it costs nothing to keep.

---

## Part 2: Communicate Auto-Save & Game Management

### 2A: Welcome Tutorial Enhancement

On `welcome-tutorial.blade.php`, add a 5th card to the "How it works" section:

> **Your progress is saved automatically**
> Every action you take is saved instantly. You can close the browser and come back anytime — your game will be right where you left it.

This slots naturally after the existing 4 cards (Matches, Squad, Transfers, Finances).

### 2B: Dashboard Game Slot Indicator

On `dashboard.blade.php`, add a subtle game slot counter near the "Your Games" header:

```
Your Games                                    2 of 3 slots used  |  + New Game
```

This replaces the current `+ New Game` link with a more informative layout that proactively communicates the 3-game limit before users hit the error.

When all 3 slots are used, show "3 of 3 slots used" with no "New Game" link (currently the link just disappears with no explanation).

### 2C: First-Time Dashboard Experience

Currently, when a user has 0 games, `Dashboard.php` immediately redirects to `select-team`. This means first-time users never see the dashboard at all.

**Recommendation:** Keep this redirect (it's good UX — no reason to show an empty dashboard). But when a user returns and has 1+ games, the dashboard is already clear enough. No changes needed here.

---

## Part 3: Smarter Cleanup Strategy

### Current State
- `CleanupUnplayedGames` runs daily at 04:00
- Deletes games where `current_matchday = 0` AND `created_at < 2 days ago`
- This catches games where setup completed but the user never played

### Decision: Keep Current Cleanup Rule, Add Transparency

No second cleanup tier. The existing matchday-0 rule is sufficient because:
- A user who played even 1 match made an active decision to engage — deleting their game risks destroying progress they intended to return to
- The 3-game-per-user limit already caps database impact per user
- Better to nudge users to self-clean via improved UI than to silently delete their data

**Changes:**

1. **Add a "last played" indicator on game cards** — On `dashboard.blade.php`, show "Last played: 3 days ago" (using `updated_at`) under the matchday badge. This gives users awareness of their own game freshness and encourages them to delete stale games themselves.

2. **No changes to `CleanupUnplayedGames.php`** — Keep the current 2-day threshold and matchday-0 rule as-is.

---

## Summary of Changes

| File | Change |
|------|--------|
| `select-team.blade.php` | Remove name input |
| `InitGame.php` | Remove name validation, stop passing to services |
| `GameCreationService.php` | Remove `playerName` param |
| `TournamentCreationService.php` | Remove `playerName` param |
| `welcome-tutorial.blade.php` | Replace name greeting with team greeting, add auto-save card |
| `squad-selection.blade.php` | Replace name greeting with team greeting |
| `dashboard.blade.php` | Add game slot counter, add "last played" indicator |
| `Dashboard.php` (View) | Pass slot count data |
| `lang/es/game.php` | Update/add translation keys |
| `lang/en/game.php` | Update/add translation keys |
| Migration | Make `player_name` nullable |

### Files NOT changed
- `Game.php` model — `player_name` stays in fillable (nullable), no functional changes
- `CleanupUnplayedGames.php` — Keep current 2-day / matchday-0 rule
- `routes/console.php` — Schedule stays the same
- `GameDeletionService.php` — No changes to deletion logic

### Design Decisions (Confirmed)
- **Name column:** Keep nullable, don't drop
- **Cleanup:** Keep current matchday-0 rule only, no second tier
- **"Last played" indicator:** Use `updated_at` (no new column)
