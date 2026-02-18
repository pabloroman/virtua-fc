# Plan: Flexible Player Position Assignment

## Problem Analysis

The current lineup system has two rigidity issues that frustrate players:

### Issue 1: Automated slot assignment with no manual override
When users select 11 players, the `slotAssignments` algorithm (in `lineup.blade.php:35-104`) automatically decides which player goes in which pitch slot. There is **no way for the user to manually drag/swap players between slots**. The greedy algorithm sorts slots by priority, then assigns the "best" player per slot using a 70/30 weighted score. If the user disagrees with the assignment (e.g., wants a specific CB on the left side rather than the right), they have no recourse.

### Issue 2: Strict position-group enforcement prevents cross-group flexibility
The validation layer (`LineupService.php:139-158`) enforces that the selected players' position groups **exactly match** formation requirements. A 4-4-2 demands exactly 4 Defenders, 4 Midfielders, 2 Forwards, 1 Goalkeeper. The UI slot assignment algorithm also hard-filters: `if (player.positionGroup !== slot.role) return;` (`lineup.blade.php:67`).

This means a user **cannot** play a Defensive Midfielder (position_group=Midfielder) at Centre-Back, even though `PositionSlotMapper` gives DM→CB a compatibility score of 50 (acceptable). The compatibility matrix acknowledges cross-group versatility, but the validation and UI ignore it.

## Proposed Solution: Manual Slot Assignment with Cross-Group Flexibility

### Part 1: Allow manual player-to-slot assignment on the pitch (drag & swap)

**Frontend changes (`lineup.blade.php`):**

1. Add a `manualAssignments` state object (`{slotId: playerId}`) that overrides the automatic algorithm
2. When a user clicks a player in the squad list, highlight compatible pitch slots they can be placed into
3. When a user clicks a highlighted slot, assign the player to that slot (storing in `manualAssignments`)
4. Allow clicking a filled slot to swap/remove its player
5. Modify `slotAssignments` getter: if a manual assignment exists for a slot, use it; otherwise fall back to the automatic algorithm
6. Add a "Reset positions" button that clears `manualAssignments` and reverts to auto

**Backend changes:**

7. Add `slot_assignments` field to the form submission (JSON map of `{slotLabel: playerId}`)
8. Store slot assignments on `GameMatch` (new JSON column `slot_assignments`) alongside the existing lineup
9. Update `SaveLineup` action to accept and persist slot assignments
10. Update `ShowLineup` view to load saved slot assignments and pass them as `manualAssignments` initial state

### Part 2: Relax position-group validation to allow cross-group placement

**Backend changes (`LineupService.php`):**

11. Replace strict position-group count validation with a softer check: each player must have a non-zero compatibility score for *some* slot in the formation, rather than requiring exact group counts
12. If slot assignments are provided, validate that each player has compatibility > 0 for their assigned slot

**Frontend changes (`lineup.blade.php`):**

13. Remove the hard `positionGroup !== slot.role` filter in the slot assignment algorithm
14. Instead, use the full `PositionSlotMapper` compatibility matrix — any player with compatibility > 0 for a slot can be placed there
15. Color-code slots by compatibility when placing a player (green = natural, yellow = acceptable, red = poor) — this already exists via `getCompatibilityDisplay`

### Part 3: Wire slot assignments into match simulation

**Backend changes (`MatchSimulator.php`):**

16. When slot assignments are saved, use the **assigned slot's position** (not the player's natural position) for goal scoring weights, assist weights, card weights, and striker bonus calculations
17. Apply the `PositionSlotMapper::getEffectiveRating()` penalty to the player's contribution when they're playing out of position — this already exists but isn't currently used in match simulation

This means playing a Defensive Midfielder at CB will work but they'll be slightly less effective (50 compatibility = ~25% penalty), and they won't get forward goal-scoring weights because they're assigned to a CB slot.

## Files to Modify

| File | Changes |
|------|---------|
| `resources/views/lineup.blade.php` | Add manual assignment state, click-to-assign UI, relax group filter |
| `app/Http/Actions/SaveLineup.php` | Accept & persist `slot_assignments` |
| `app/Http/Views/ShowLineup.php` | Load & pass saved slot assignments |
| `app/Game/Services/LineupService.php` | Relax position-group validation, add slot-based validation |
| `app/Game/Services/MatchSimulator.php` | Use assigned slot positions for event weights |
| `app/Models/GameMatch.php` | Add `slot_assignments` to casts/fillable |
| Migration | Add `slot_assignments` JSON column to `game_matches` |
| `lang/es/*.php` | New translation keys for UI labels |

## Scope & Complexity

This is a significant feature touching frontend (Alpine.js interactivity), backend validation, data persistence, and match simulation. The riskiest parts are:

- **Match simulation changes** — must ensure out-of-position penalties are balanced
- **Validation relaxation** — must prevent nonsensical lineups (e.g., 11 goalkeepers) while allowing reasonable flexibility
- **UI interaction model** — click-to-assign needs to be intuitive on both desktop and mobile
