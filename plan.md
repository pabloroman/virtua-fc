# Plan: Flexible Player Position Assignment

## Problem

The current lineup system frustrates users in two ways:

1. **No manual control over pitch positions.** The `slotAssignments` algorithm (`lineup.blade.php:35-104`) auto-assigns players to slots. Users can't swap two centre-backs, put a specific winger on the left vs right, etc.

2. **Strict position-group enforcement blocks valid tactical choices.** Validation (`LineupService.php:139-158`) demands exact group counts (e.g., 4-4-2 = 4 DEF, 4 MID, 2 FWD). The UI also hard-filters `positionGroup !== slot.role` (`lineup.blade.php:67`). This prevents playing a DM at CB even though `PositionSlotMapper` scores DM→CB at 50 (acceptable).

## Solution

Four parts: extract JS to a dedicated file, manual slot assignment with persistence, cross-group flexibility, and no match simulation changes.

### Part 0: Extract lineup JS to a dedicated file (preliminary refactor)

Follow the existing `live-match.js` pattern: export an Alpine data function, register it in `app.js`.

1. Create `resources/js/lineup.js` — extract all Alpine.js logic from `lineup.blade.php`'s `x-data` block into an exported function `lineupManager(config)` that receives server data as a config object
2. Register in `resources/js/app.js` — `Alpine.data('lineupManager', lineupManager)`
3. Update `lineup.blade.php` — replace inline `x-data="{...}"` with `x-data="lineupManager({...})"`, passing server data (playersData, formationSlots, slotCompatibility, currentLineup, etc.) as the config object
4. Translation strings that are currently inlined in JS (e.g., `{{ __('squad.natural') }}`) get passed via the config object as a `translations` map

This is a pure refactor — no behavior changes. Keeps the Blade template focused on markup and the JS logic testable and manageable.

### Part 1: Manual slot assignment with click-to-swap

**`resources/js/lineup.js`:**

5. Add `manualAssignments` state (`{slotId: playerId}`) that overrides the auto-algorithm
6. When a user clicks a player in the squad list, enter "placement mode" — highlight compatible pitch slots (compatibility > 0) the player can go into
7. Clicking a highlighted empty slot assigns the player there
8. Clicking a filled slot swaps the two players
9. Clicking an assigned player on the pitch without a pending placement removes them
10. The `slotAssignments` getter respects manual overrides first, then auto-fills remaining slots
11. "Reset positions" button clears all manual assignments and reverts to auto

### Part 2: Persist slot assignments

Slot assignments don't affect match simulation, so we only need to store the user's preference as a default on the `games` table (same as `default_formation`, `default_lineup`, `default_mentality`). No per-match columns on `game_matches` — the simulator doesn't use them.

**Migration:**

12. Add `default_slot_assignments` (JSON, nullable) to `games` table

**Model changes:**

13. `Game` — add `default_slot_assignments` to `$fillable` and `$casts` (as `array`)

**Backend (`SaveLineup.php`):**

14. Accept `slot_assignments` from the form (JSON map of slot `id` → player UUID)
15. Validate: every player in slot_assignments must have compatibility > 0 for their assigned slot (via `PositionSlotMapper`)
16. Save to `game.default_slot_assignments` as the new default

**Backend (`ShowLineup.php`):**

17. Load saved slot assignments from game defaults
18. Pass to the template as `savedSlotAssignments` for Alpine.js to initialize `manualAssignments`

### Part 3: Cross-group flexibility

The `PositionSlotMapper::SLOT_COMPATIBILITY` matrix already defines cross-group scores (DM→CB=50, LW→LB=20, etc.). We leverage this as the single source of truth.

**`resources/js/lineup.js`:**

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
| `resources/js/lineup.js` | **New file** — extracted Alpine.js logic + new manual assignment/swap/placement features |
| `resources/js/app.js` | Register `lineupManager` Alpine data component |
| `resources/views/lineup.blade.php` | Replace inline JS with `x-data="lineupManager({...})"`, markup-only |
| `database/migrations/new` | Add `default_slot_assignments` to `games` |
| `app/Models/Game.php` | Add to `$fillable` and `$casts` |
| `app/Game/Services/LineupService.php` | Update `validateLineup()` for slot-based validation |
| `app/Http/Actions/SaveLineup.php` | Accept, validate, and persist `slot_assignments` |
| `app/Http/Views/ShowLineup.php` | Load and pass saved slot assignments |
| `lang/es/squad.php` | New translation keys (placement mode hints, reset button) |

## Data Format

Slot assignments stored as JSON map of slot `id` (0-10) → player UUID, keyed by slot id to guarantee uniqueness (formations can have duplicate labels like two CBs):

```json
{
  "0": "uuid-keeper",
  "1": "uuid-leftback",
  "2": "uuid-centreback-1",
  "3": "uuid-centreback-2",
  "4": "uuid-rightback",
  "5": "uuid-midfielder-1",
  "6": "uuid-midfielder-2",
  "7": "uuid-leftwinger",
  "8": "uuid-attackingmid",
  "9": "uuid-rightwinger",
  "10": "uuid-striker"
}
```
