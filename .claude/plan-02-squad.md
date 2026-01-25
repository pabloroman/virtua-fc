
# Squad & Player Management Plan

## Goal
Add squad viewing and basic player management to VirtuaFC, following existing patterns.

## Architecture Decision: Hybrid Approach

**Reference Data (static, seeded from JSON):**
- `Player` model - biographical data + base abilities (Technical, Physical)

**Game-Scoped (per-game instance):**
- `GamePlayer` model - dynamic state (Fitness, Morale) + contract/transfer data + season stats

This mirrors the existing Team (reference) → GameStanding (game-scoped) pattern.

---

## Player Attribute Model

Players have 4 core attributes that combine into an overall score:

| Attribute | Stored In | Change Rate | What Affects It |
|-----------|-----------|-------------|-----------------|
| **Technical Ability** | Player | Slow (career) | Age, seasons played |
| **Physical Ability** | Player | Slow (career) | Age (peaks ~27, declines after ~30) |
| **Fitness** | GamePlayer | Frequent | Matches, injuries, rest, training |
| **Morale** | GamePlayer | Frequent | Results, playing time, team success |

**Overall Score Formula:**
```
score = (technical + physical + fitness + morale) / 4
```

All attributes are 0-100 scale.

### Ability Changes Over Time
- **Technical**: Slight improvement until ~30, then stable or slight decline
- **Physical**: Peaks at ~27, gradual decline after ~30, steeper after ~33
- These changes happen at season boundaries, not during a season

---

## Database Schema

### Migration: `players` table (reference - biographical + base abilities)
```sql
players
  - id UUID PRIMARY KEY
  - transfermarkt_id VARCHAR UNIQUE
  - name VARCHAR
  - date_of_birth DATE
  - nationality JSON (array)
  - height VARCHAR
  - foot ENUM (left/right/both)
  - technical_ability TINYINT (0-100)  -- base skill level
  - physical_ability TINYINT (0-100)   -- base athleticism
```

### Migration: `game_players` table (game-scoped - dynamic state)
```sql
game_players
  - id UUID PRIMARY KEY
  - game_id UUID REFERENCES games CASCADE
  - player_id UUID REFERENCES players
  - team_id UUID REFERENCES teams  -- current team (can change via transfer)

  -- Contract & transfer data (can change during game)
  - position VARCHAR
  - market_value VARCHAR ("€28.00m")
  - market_value_cents BIGINT
  - contract_until DATE
  - signed_from VARCHAR
  - joined_on DATE

  -- Dynamic attributes (change frequently)
  - fitness TINYINT (0-100, default 100)
  - morale TINYINT (0-100, default 70)

  -- Injury tracking
  - injury_until DATE NULL
  - injury_type VARCHAR NULL
  - is_suspended BOOLEAN

  -- Season stats
  - appearances, goals, assists, yellow_cards, red_cards
```

This separation means:
- **Player**: Who they are + their base abilities (slow-changing)
- **GamePlayer**: Current state in this save (dynamic attributes, contract, team)

---

## Implementation Order

### 1. Migration
Create `2025_01_24_000002_create_players_tables.php`:
- `players` table
- `game_players` table

### 2. Models
- `app/Models/Player.php` - reference model with Team relationship
- `app/Models/GamePlayer.php` - game-scoped with Player/Game/Team relationships

### 3. Seeder Update
Update `app/Console/Commands/SeedReferenceData.php`:
- Read from `data/{competition}/2024/players/{transfermarktId}.json`
- Parse market value string to cents
- Match players to teams via transfermarkt_id
- **Derive abilities from market value:**
  - Base ability from value tier (€50M+ = 85-95, €20-50M = 75-85, etc.)
  - Technical vs Physical split by position (forwards more technical, defenders more physical)
  - Age adjustment: physical peaks at 27, declines after 30

### 4. Projector Update
Update `GameProjector::onGameCreated()`:
- After creating Game, initialize GamePlayer records for player's team
- Set initial fitness (90-100, slight randomization)
- Set initial morale (65-80, randomized)

### 5. View: Squad Page
- `app/Http/Views/ShowSquad.php`
- `resources/views/squad.blade.php`
- Route: `GET /game/{game}/squad`

### 6. Navigation
Update `game-header.blade.php` to add Squad link.

---

## Files to Create

```
database/migrations/2025_01_24_000002_create_players_tables.php
app/Models/Player.php
app/Models/GamePlayer.php
app/Http/Views/ShowSquad.php
resources/views/squad.blade.php
```

## Files to Modify

```
app/Console/Commands/SeedReferenceData.php  # Add player seeding
app/Game/GameProjector.php                   # Initialize GamePlayers on game creation
resources/views/components/game-header.blade.php  # Add Squad nav link
routes/web.php                               # Add squad route
```

---

## Squad View Design

Group players by position category:
- **Goalkeepers**: Goalkeeper
- **Defenders**: Centre-Back, Left-Back, Right-Back
- **Midfielders**: Defensive Midfield, Central Midfield, Attacking Midfield, Left/Right Midfield
- **Forwards**: Left Winger, Right Winger, Centre-Forward, Second Striker

Display for each player:
- Name, position, age
- Market value
- **Overall score** (average of 4 attributes)
- Attribute bars: Technical, Physical, Fitness, Morale
- Injury status (if injured)
- Nationality flag(s)

---

## Verification

1. Run migration: `php artisan migrate`
2. Re-seed data: `php artisan app:seed-reference-data`
3. Verify players seeded: `php artisan tinker` → `Player::count()` should be ~500+
4. Create new game, select a team
5. Navigate to Squad page
6. Verify all squad players display with correct attributes
7. Check fitness/morale values are randomized per game

---

## Future Enhancements (not in this plan)

- Lineup selection for matches
- Match simulation using overall player scores
- Player injuries from match events
- Morale changes based on results and playing time
- Fitness recovery/fatigue between matches
- Ability degradation at season boundaries (age-based)
- Transfer market
