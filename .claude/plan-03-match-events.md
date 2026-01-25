# Match Events & Game Stats Plan

## Goal
Track individual player performance during matches (goals, assists, cards, injuries) and use these events to update player stats, eligibility, and eventually morale/fitness.

## Current State
- `GamePlayer` already has season stats columns: `appearances`, `goals`, `assists`, `yellow_cards`, `red_cards`
- Match simulation exists but doesn't track who scored or what happened
- No suspension/eligibility system yet

---

## Architecture

### Match Events (new table)
Store granular events per match per player for history and replay.

```sql
match_events
  - id UUID PRIMARY KEY
  - game_id UUID REFERENCES games
  - game_match_id UUID REFERENCES game_matches
  - game_player_id UUID REFERENCES game_players
  - minute TINYINT (1-90+)
  - event_type ENUM('goal', 'own_goal', 'assist', 'yellow_card', 'red_card', 'injury', 'substitution')
  - metadata JSON NULL (injury details, goal type, etc.)
```

### Event Types

| Type | Description | Updates Stats? |
|------|-------------|----------------|
| `goal` | Player scored for their team | Yes (+goals) |
| `own_goal` | Player scored into their own net | Yes (+own_goals) |
| `assist` | Player assisted a goal | Yes (+assists) |
| `yellow_card` | Yellow card received | Yes (+yellow_cards) |
| `red_card` | Red card (direct or second yellow) | Yes (+red_cards) |
| `injury` | Player injured during match | No (but sets injury_until) |
| `substitution` | Player subbed in/out | No |

### Score Calculation
```php
$homeGoals = $events->where('team_id', $homeTeamId)->where('event_type', 'goal')->count()
           + $events->where('team_id', $awayTeamId)->where('event_type', 'own_goal')->count();

$awayGoals = $events->where('team_id', $awayTeamId)->where('event_type', 'goal')->count()
           + $events->where('team_id', $homeTeamId)->where('event_type', 'own_goal')->count();
```

### Flow
1. **Match Simulator** generates events during simulation
2. **Events stored** in `match_events` table
3. **Projector** updates `game_players` season stats
4. **Eligibility check** runs before each match

---

## Eligibility Rules (La Liga)

| Infraction | Consequence |
|------------|-------------|
| 5 yellow cards (accumulated) | 1 match suspension |
| 10 yellow cards (accumulated) | 2 match suspension |
| 15 yellow cards (accumulated) | 3 match suspension |
| Red card (direct) | 1-3 match suspension (default: 1) |
| Red card (2 yellows) | 1 match suspension |

**Implementation:**
- Add `suspended_until_matchday` column to `game_players`
- After each match, check yellow card count and set suspension if threshold crossed
- Red cards immediately set suspension
- Clear suspension after serving

---

## Database Changes

### New Migration: `match_events` table
```sql
match_events
  - id UUID PRIMARY KEY
  - game_id UUID
  - game_match_id UUID
  - game_player_id UUID
  - team_id UUID (for quick filtering)
  - minute TINYINT UNSIGNED
  - event_type VARCHAR(20)
  - metadata JSON NULL
  - created_at TIMESTAMP

  INDEX (game_match_id)
  INDEX (game_player_id)
```

### Modify `game_players` table
```sql
ALTER game_players ADD:
  - own_goals SMALLINT UNSIGNED DEFAULT 0
  - suspended_until_matchday INT UNSIGNED NULL
```

Note: `own_goals` is tracked separately from `goals` for statistical accuracy.

---

## Implementation Order

### 1. Migration
- Create `match_events` table
- Add `suspended_until_matchday` to `game_players`

### 2. MatchEvent Model
- `app/Models/MatchEvent.php`
- Relationships to GameMatch, GamePlayer, Team

### 3. Update MatchSimulator
- Generate goal events (who scored, minute, assisted by)
- Generate card events (yellow/red, minute)
- Generate injury events (rare, type, duration)
- Return events alongside score

### 4. Update GameProjector
- Store match events after match simulation
- Update `game_players` season stats (appearances, goals, etc.)
- Check and apply suspensions

### 5. Eligibility Service
- `app/Game/Services/EligibilityService.php`
- `isEligible(GamePlayer, matchday): bool`
- `applySuspension(GamePlayer, matches): void`
- `checkYellowCardAccumulation(GamePlayer): ?int` (returns suspension length)

### 6. Update Views
- Match results page: show scorers, cards
- Squad page: show suspension status
- Player can't be selected if suspended

---

## Match Simulator Changes

Current simulator only returns `{ homeScore, awayScore }`.

New output structure:
```php
MatchResult {
    int $homeScore;
    int $awayScore;
    Collection $events; // MatchEventData[]
}

MatchEventData {
    string $teamId;
    string $playerId;
    int $minute;
    string $type; // goal, own_goal, assist, yellow_card, red_card, injury
    ?array $metadata;
}
```

### Event Generation Logic

**Goals:**
- For each goal, pick a scorer based on position weights (forwards > midfielders > defenders)
- 60% chance of having an assist
- Pick assister from same team (midfielders > forwards > defenders)

**Own Goals:**
- ~2% of all goals are own goals (roughly 1 in 50)
- When own goal occurs: pick a defender from the conceding team
- Own goal counts toward opponent's score but doesn't affect player's `goals` stat
- Recorded in `match_events` for historical accuracy

**Yellow Cards:**
- Average ~3-4 per match total
- Defenders and defensive midfielders more likely
- Distribution: Poisson with lambda ~1.7 per team

**Red Cards:**
- Rare: ~3% chance per match of any red
- If red: 70% second yellow, 30% direct

**Injuries:**
- ~5% chance per match of injury
- Duration: 1-8 weeks based on type
- Store injury type and return date

---

## Files to Create

```
database/migrations/2025_01_25_000001_create_match_events_table.php
app/Models/MatchEvent.php
app/Game/Services/EligibilityService.php
app/Game/DTO/MatchResult.php
app/Game/DTO/MatchEventData.php
```

## Files to Modify

```
database/migrations/2025_01_24_000002_create_players_tables.php  # Add suspended_until_matchday
app/Game/Services/MatchSimulator.php      # Generate events
app/Game/GameProjector.php                # Store events, update stats, apply suspensions
app/Models/GamePlayer.php                 # isEligible() method
resources/views/squad.blade.php           # Show suspension status
resources/views/results.blade.php         # Show scorers, cards
```

---

## Future Enhancements (not in this plan)

- Morale changes based on:
  - Scoring a goal (+morale)
  - Getting a red card (-morale)
  - Team winning/losing (+/- morale)
  - Not playing regularly (-morale)

- Fitness changes based on:
  - Playing full match (-fitness)
  - Rest days (+fitness recovery)
  - Injuries (fitness drops, recovers over time)

---

## Verification

1. Run migrations
2. Simulate a matchday
3. Check `match_events` table has goal/card events
4. Verify `game_players` stats updated (goals, yellow_cards, etc.)
5. Accumulate 5 yellow cards for a player
6. Verify player is suspended for next match
7. Verify suspended player shows in squad view
8. Advance matchday, verify suspension clears after serving
