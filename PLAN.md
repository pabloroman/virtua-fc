# Plan: In-Match Substitutions with Live Re-Simulation

## Approach

The user's preferred approach: simulate the full match upfront (pipeline unchanged), but when the user makes substitutions during the live match, send an AJAX call to the server that **re-simulates the remainder** of the match from the substitution minute with the updated lineup, updates the database, and returns the new events + score to the frontend.

This trades off event store immutability for a more realistic experience where substitutions actually affect the outcome.

---

## Feasibility Assessment: Fully Feasible

After reviewing every component in the pipeline, this approach is clean and contained. Here's why:

### The MatchSimulator already supports the core pattern

The existing `simulateExtraTime()` method (line 746) proves the simulator can generate events for a specific minute range. The main `simulate()` method uses Poisson-based goal generation + weighted player selection — both trivially scale to a partial match by reducing the expected goals proportionally and constraining event minutes to `[subMinute+1, 93]`.

### The read model mutation is safe

The `GameProjector` writes to read models (`MatchEvent`, `GamePlayer` stats, `GameStanding`, `GameMatch`). These are standard Eloquent models — fully mutable. The event store (`stored_events`) records the original simulation and is never touched. If event replay is ever needed, it reproduces the pre-substitution state, which is acceptable.

### Standings correction is straightforward

`StandingsCalculator::updateAfterMatch()` does incremental W/D/L/GF/GA updates. Reversing a result and applying a new one is the same logic inverted: decrement old, increment new, recalculate positions.

### No changes to AdvanceMatchday or GameProjector

The entire substitution flow happens after the projector has finished. It's a separate HTTP action that corrects read model data.

---

## Detailed Flow

### Step 1: AdvanceMatchday (unchanged)

```
User clicks "Continue" → AdvanceMatchday runs as today:
  → Simulates all matches fully
  → Records events via event sourcing
  → Projector writes MatchEvent rows, updates player stats, standings
  → Redirects to live match page
```

### Step 2: Live Match Page (enhanced)

```
ShowLiveMatch prepares data:
  → Existing: events, scores, other matches
  → NEW: bench players for user's team (name, id, position, overall_score)
  → NEW: starting lineup players for user's team (for the sub-out picker)

Alpine.js animates the match as today, PLUS:
  → "Substitutions" panel visible when match is running
  → User can pause the clock, pick player out + player in
  → Sub is recorded locally, animation continues
  → Up to 5 subs allowed (modern football rules)
```

### Step 3: AJAX Call on Substitution

```
When user confirms a substitution at minute M:
  → Frontend pauses the clock
  → POST /game/{gameId}/match/{matchId}/substitute
    Body: {
      playerOutId: "uuid",
      playerInId: "uuid",
      minute: 62,
      currentSubstitutions: [{playerOutId, playerInId, minute}, ...] // previous subs this match
    }
  → Server processes the substitution (see Step 4)
  → Returns: { newScore: {home: 2, away: 1}, newEvents: [...], substitution: {...} }
  → Frontend:
    - Replaces all unrevealed events (minute > M) with newEvents
    - Updates finalHomeScore / finalAwayScore
    - Injects substitution event into the feed
    - Resumes clock
```

### Step 4: Server-Side Processing (SubstitutionService)

```php
class SubstitutionService
{
    public function processSubstitution(
        GameMatch $match,
        string $playerOutId,
        string $playerInId,
        int $minute,
        array $currentSubstitutions, // previous subs in this match
    ): array {
        // 1. REVERT: Undo post-sub events and stats from the original simulation
        $this->revertEventsAfterMinute($match, $minute);

        // 2. BUILD NEW LINEUP: Start with current lineup, apply all subs
        $newLineup = $this->buildActiveLineup($match, $currentSubstitutions, $playerOutId, $playerInId);

        // 3. RE-SIMULATE: Generate new events from subMinute to 93
        $remainderResult = $this->simulateRemainder($match, $newLineup, $minute);

        // 4. APPLY: Write new events, update stats, update score, fix standings
        $this->applyNewResult($match, $remainderResult, $minute);

        // 5. RECORD: Store substitution on the match
        $this->recordSubstitution($match, $playerOutId, $playerInId, $minute);

        // 6. RETURN: New events + score for frontend
        return [
            'newScore' => ['home' => $match->home_score, 'away' => $match->away_score],
            'newEvents' => $this->formatEventsForFrontend($match, $minute),
            'substitution' => ['playerOutId' => $playerOutId, 'playerInId' => $playerInId, 'minute' => $minute],
        ];
    }
}
```

#### 4a. Revert Events After Minute

```
1. Load all MatchEvent rows where game_match_id = X AND minute > subMinute
2. For each event, reverse its stat impact:
   - goal      → player.goals--
   - own_goal  → player.own_goals--
   - assist    → player.assists--
   - yellow_card → player.yellow_cards-- (+ undo suspension if this yellow triggered one)
   - red_card  → player.red_cards-- (+ undo suspension)
   - injury    → clear player.injury_until if it was set by this event
3. Delete these MatchEvent rows
4. Revert the match score to what it was at minute M:
   - Count remaining goals/own_goals (minute <= M) to get score-at-M
   - Update GameMatch.home_score and away_score to score-at-M (temporarily)
5. If this is a league match, reverse the standings impact:
   - Call reverseStandingsUpdate(oldHomeScore, oldAwayScore)
```

#### 4b. Build Active Lineup

```
1. Start with the original starting XI (match.home_lineup or away_lineup)
2. Apply all previous substitutions: for each prior sub, remove playerOut, add playerIn
3. Apply the current substitution: remove playerOutId, add playerInId
4. Return the Collection of GamePlayer models for the active lineup
```

#### 4c. Re-Simulate Remainder

```
1. Call MatchSimulator::simulateRemainder() with:
   - Home team + away team (unchanged)
   - New lineup for user's team, original lineup for opponent
   - Formations + mentalities (unchanged)
   - fromMinute = subMinute
2. This returns a MatchResult with:
   - Remainder goals (home and away)
   - Events with minutes in [subMinute+1, 93]
3. Final score = score-at-M + remainder goals
```

#### 4d. Apply New Result

```
1. Insert new MatchEvent rows for the re-simulated remainder
2. Update player stats for new events (goals, assists, cards, injuries)
3. Process special events (yellow accumulation → suspension, red → suspension, injury → injury_until)
4. Update GameMatch score to final score
5. Update GameMatch lineup to include sub player
6. Increment sub player's appearances
7. If league match: apply new standings update + recalculate positions
8. Update goalkeeper stats if score changed
```

---

## MatchSimulator Changes

Add one new method — `simulateRemainder()`. This is a focused variant of `simulate()`:

```php
public function simulateRemainder(
    Team $homeTeam,
    Team $awayTeam,
    Collection $homePlayers,
    Collection $awayPlayers,
    Formation $homeFormation,
    Formation $awayFormation,
    Mentality $homeMentality,
    Mentality $awayMentality,
    int $fromMinute,
    ?Game $game = null,
): MatchResult {
    $this->resetMatchPerformance();
    $matchFraction = (93 - $fromMinute) / 93;

    // Scale expected goals by remaining match fraction
    // (same formula as simulate(), multiplied by matchFraction)
    $homeExpectedGoals = $fullMatchExpectedGoals * $matchFraction;
    $awayExpectedGoals = $fullMatchExpectedGoals * $matchFraction;

    // Generate scores via Poisson
    $homeScore = $this->poissonRandom($homeExpectedGoals);
    $awayScore = $this->poissonRandom($awayExpectedGoals);

    // Generate events with minutes constrained to [fromMinute+1, 93]
    // (reuse existing generateGoalEvents, generateCardEvents, generateInjuryEvents
    //  but with modified generateUniqueMinute that uses fromMinute as floor)

    return new MatchResult($homeScore, $awayScore, $events);
}
```

The key insight: this is structurally identical to `simulateExtraTime()` (which already exists at line 746) but for a custom minute range. The pattern is proven.

---

## What Changes (Complete Inventory)

| Component | Change | Scope |
|-----------|--------|-------|
| `MatchSimulator` | Add `simulateRemainder()` method | ~80 lines (mirrors existing `simulateExtraTime`) |
| New service: `SubstitutionService` | Revert/re-simulate/apply logic | ~200 lines |
| New action: `ProcessSubstitution` | HTTP handler for AJAX call | ~50 lines |
| `routes/web.php` | Add POST route | 1 line |
| Migration | Add `substitutions` JSON column to `game_matches` | Small |
| `GameMatch` model | Cast `substitutions` to array | 1 line |
| `MatchEvent` model | Add `TYPE_SUBSTITUTION` constant | 1 line |
| `MatchEventData` DTO | Add `substitution()` factory method | ~8 lines |
| `StandingsCalculator` | Add `reverseMatchResult()` method | ~20 lines |
| `ShowLiveMatch.php` | Pass bench players + lineup to view | ~15 lines |
| `live-match.blade.php` | Substitution panel UI | ~80 lines |
| `live-match.js` | Sub state, AJAX call, event replacement | ~100 lines |
| Translation files | Spanish strings for sub UI | ~15 keys |
| **AdvanceMatchday** | **No changes** | — |
| **GameProjector** | **No changes** | — |
| **Event store** | **No changes** | — |

---

## Edge Cases & How They're Handled

### Multiple substitutions
Each sub triggers a separate AJAX call. The `currentSubstitutions` array is sent with each call so the server knows the full lineup state. Each call reverts everything after the new sub minute and re-simulates. This means a sub at minute 75 will re-simulate a shorter remainder, which is correct.

### Sub at half-time
The user makes a sub during the half-time pause (minute 45). The server reverts all events after minute 45, re-simulates the second half with the new lineup. The frontend replaces all second-half events.

### Subbed-in player gets injured/carded in re-simulation
This is handled naturally — the re-simulation generates new events with the new lineup, so the sub player is a valid target for cards/injuries. The projector-equivalent logic in `SubstitutionService.applyNewResult()` processes suspensions and injuries for whoever is in the event.

### Score change affects standings
Handled by `reverseMatchResult()` + `updateAfterMatch()` + `recalculatePositions()`. The standing row is corrected before the new result is applied.

### User doesn't make any subs
Nothing changes. The match plays out exactly as today.

### Sub player was already involved in an event before the sub minute
Impossible — bench players aren't in the simulation. Only starting XI players generate events. The sub player has no events before they come on.

### Goalkeeper substitution
If the user subs their goalkeeper, goalkeeper stats (goals_conceded, clean_sheets) need recalculation. The `applyNewResult()` step handles this by recalculating GK stats based on the final score and who was in goal.

### What if the user refreshes the page mid-match?
The database already has the fully-simulated result. The live match page reloads with those events. Any substitutions made before the refresh are lost (they were only in Alpine.js state). The user sees the original match result. This is acceptable — it's the same behavior as closing the page today.

### What about subs on already-revealed events?
The user can only sub from the current match minute forward. Events already revealed can't be changed. The sub takes effect "now" in the animation timeline.

---

## UX Design Sketch

### Substitution Panel (below speed controls)

```
┌──────────────────────────────────────────┐
│  🔄 Substituciones (2/5)                 │
│                                          │
│  [Pause & Substitute]                    │
│                                          │
│  55' ↩ Pedri → ↪ Gavi                   │
│  68' ↩ Lewandowski → ↪ Ferran Torres    │
└──────────────────────────────────────────┘
```

When "Pause & Substitute" is clicked:

```
┌──────────────────────────────────────────┐
│  Sale:                                   │
│  ○ Pedri (MC) 87         ← radio select │
│  ○ Gavi (MC) 83                          │
│  ○ Raphinha (ED) 86                      │
│  ...                                     │
│                                          │
│  Entra:                                  │
│  ○ Ferran Torres (EI) 81  ← radio select│
│  ○ Ansu Fati (DC) 79                     │
│  ○ Eric García (DFC) 78                  │
│  ...                                     │
│                                          │
│  [Confirmar]  [Cancelar]                 │
└──────────────────────────────────────────┘
```

### In the event feed

```
62'  🔄  ↩ Lewandowski  ↪ Ferran Torres
```

---

## Implementation Order

1. **Migration**: Add `substitutions` JSON column to `game_matches`
2. **MatchSimulator**: Add `simulateRemainder()` method
3. **StandingsCalculator**: Add `reverseMatchResult()` method
4. **SubstitutionService**: Core revert/re-simulate/apply logic
5. **ProcessSubstitution action + route**: HTTP endpoint returning JSON
6. **ShowLiveMatch.php**: Pass bench players + starting lineup data
7. **live-match.blade.php**: Substitution panel UI
8. **live-match.js**: Sub state, pause/resume, AJAX, event replacement
9. **Translations**: Spanish strings for all sub UI elements
10. **Tests**: Unit test for SubstitutionService, feature test for endpoint

---

## What This Approach Preserves

- **AdvanceMatchday**: Zero changes. Simulates and records as today.
- **GameProjector**: Zero changes. Projects events as today.
- **Event store**: Zero changes. Original simulation is immutable.
- **Queue worker**: Zero changes. No timing/async concerns.
- **Other matches**: Zero changes. AI matches complete normally.
- **Match simulation for AI teams**: Unchanged. Only user's team can sub.

## What This Approach Trades Off

- **Event store ≠ read model truth**: After a substitution, the `stored_events` table has the original result, but `MatchEvent` rows and player stats reflect the post-sub reality. If events are replayed, subs would be lost. This is an acceptable tradeoff for this game.
- **Multiple AJAX calls**: Each sub is a round-trip. On slow connections, there's a brief pause (~200-500ms). This actually works well — it feels like the match is "processing" the sub.
- **Standings temporarily incorrect**: Between the projector writing the original result and the user making a sub, standings reflect the pre-sub score. After the sub, they're corrected. Since this all happens during the live match page (user isn't looking at standings), this is invisible.
