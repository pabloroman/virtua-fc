# Plan: Flexible Player Position Assignment

## Problem

The current lineup system frustrates users in two ways:

1. **No manual control over pitch positions.** The `slotAssignments` algorithm (`lineup.blade.php:35-104`) auto-assigns players to slots. Users can't swap two centre-backs, put a specific winger on the left vs right, etc.

2. **Strict position-group enforcement blocks valid tactical choices.** Validation (`LineupService.php:139-158`) demands exact group counts (e.g., 4-4-2 = 4 DEF, 4 MID, 2 FWD). The UI also hard-filters `positionGroup !== slot.role` (`lineup.blade.php:67`). This prevents playing a DM at CB even though `PositionSlotMapper` scores DM→CB at 50 (acceptable).

## Solution

Three changes: manual slot assignment with persistence, cross-group flexibility in the frontend/validation, and no match simulation changes.

### Part 1: Manual slot assignment with click-to-swap

**Frontend (`lineup.blade.php`):**

1. Add `manualAssignments` state (`{slotId: playerId}`) that overrides the auto-algorithm
2. When a user clicks a player in the squad list, enter "placement mode" — highlight compatible pitch slots (compatibility > 0) the player can go into
3. Clicking a highlighted empty slot assigns the player there
4. Clicking a filled slot swaps the two players
5. Clicking an assigned player on the pitch without a pending placement removes them
6. The `slotAssignments` getter respects manual overrides first, then auto-fills remaining slots
7. "Reset positions" button clears all manual assignments and reverts to auto

### Part 2: Persist slot assignments

Follow the existing pattern: defaults on `games`, per-match on `game_matches`.

**Migration:**

8. Add `default_slot_assignments` (JSON, nullable) to `games` table
9. Add `home_slot_assignments` and `away_slot_assignments` (JSON, nullable) to `game_matches` table

**Model changes:**

10. `Game` — add `default_slot_assignments` to `$fillable` and `$casts` (as `array`)
11. `GameMatch` — add `home_slot_assignments`, `away_slot_assignments` to `$fillable` and `$casts` (as `array`)

**Backend (`SaveLineup.php`):**

12. Accept `slot_assignments` from the form (JSON map `{slotLabel: playerId}`, e.g. `{"CB": "uuid-1", "LB": "uuid-2", ...}`)
13. Validate: every player in slot_assignments must have compatibility > 0 for their assigned slot (via `PositionSlotMapper`)
14. Save to `game_matches` (home or away based on team side)
15. Save to `game.default_slot_assignments` as the new default

**Backend (`ShowLineup.php`):**

16. Load saved slot assignments (from match first, fall back to game defaults)
17. Pass to the template as `savedSlotAssignments` for Alpine.js to initialize `manualAssignments`

**Backend (`LineupService.php`):**

18. Add `saveSlotAssignments($match, $teamId, $slotAssignments)` method following the same pattern as `saveLineup`/`saveFormation`/`saveMentality`

### Part 3: Cross-group flexibility

The `PositionSlotMapper::SLOT_COMPATIBILITY` matrix already defines cross-group scores (DM→CB=50, LW→LB=20, etc.). We leverage this as the single source of truth.

**Frontend (`lineup.blade.php`):**

19. In the auto-assignment algorithm, keep the position-group preference as a tiebreaker but don't hard-filter on it — any player with compatibility > 0 for a slot can be auto-assigned there
20. In "placement mode" (click-to-assign), show ALL slots where the player has compatibility > 0, regardless of position group, with color-coded indicators (already exists via `getCompatibilityDisplay`)

**Backend validation (`LineupService.php`):**

21. When `slot_assignments` are provided: validate each player has compatibility > 0 for their assigned slot (via `PositionSlotMapper::getCompatibilityScore`). This replaces the strict position-group count check.
22. When NO slot assignments are provided (legacy/auto path): keep existing position-group validation unchanged for backwards compatibility
23. Always require exactly 1 Goalkeeper (non-negotiable safety check)

### Not in scope: Match simulation

The match simulator (`MatchSimulator.php`) continues to use `$player->position` (natural position) for all event weights, strength calculations, and striker bonuses. No changes needed. This means a DM manually placed at CB will simulate as a DM — the flexibility is a user-facing tactical preference, not a simulation mechanic (yet). This can be wired in as a future enhancement.

## Files to Modify

| File | Changes |
|------|---------|
| `database/migrations/new` | Add `default_slot_assignments` to `games`, `home/away_slot_assignments` to `game_matches` |
| `app/Models/Game.php` | Add to `$fillable` and `$casts` |
| `app/Models/GameMatch.php` | Add to `$fillable` and `$casts` |
| `app/Game/Services/LineupService.php` | Add `saveSlotAssignments()`, update `validateLineup()` for slot-based validation |
| `app/Http/Actions/SaveLineup.php` | Accept, validate, and persist `slot_assignments` |
| `app/Http/Views/ShowLineup.php` | Load and pass saved slot assignments |
| `resources/views/lineup.blade.php` | Manual assignment state, click-to-assign/swap UI, cross-group slot compatibility |
| `lang/es/squad.php` | New translation keys (placement mode hints, reset button) |

## Data Format

Slot assignments stored as JSON map of slot label → player UUID:

```json
{
  "GK": "uuid-keeper",
  "LB": "uuid-leftback",
  "CB": "uuid-centreback-1",
  "CB": "uuid-centreback-2",
  "RB": "uuid-rightback",
  "CM": "uuid-midfielder-1",
  "CM": "uuid-midfielder-2",
  "LW": "uuid-leftwinger",
  "AM": "uuid-attackingmid",
  "RW": "uuid-rightwinger",
  "CF": "uuid-striker"
}
```

Note: Formations can have duplicate slot labels (e.g., two CBs). The key should use the slot `id` (0-10) instead to guarantee uniqueness:

```json
{
  "0": "uuid-keeper",
  "1": "uuid-leftback",
  "2": "uuid-centreback-1",
  "3": "uuid-centreback-2",
  ...
}
```
