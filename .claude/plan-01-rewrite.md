# VirtuaFC Rewrite Plan

## Goal
Create a playable MVP: pick a Spanish team, advance through matchdays, see simulated results and standings update.

## Approach: Refactor by Deletion
**Not starting fresh.** We keep the working skeleton and delete the broken parts:
- Keep: Laravel config, Breeze auth, Spatie event sourcing, data files, working Game aggregate
- Delete: Over-engineered Domain/Application/Infrastructure folders with empty implementations

This saves ~2 hours of setup while giving us a clean foundation.

## Architecture Decision
**Hybrid Event Sourcing**
- Event source: Game actions (match results, future transfers)
- Eloquent: Reference data (teams, competitions, fixture templates)

**Extensibility**: The `competition.type` field and event-driven design support future additions:
- Cup competitions (Copa del Rey) - knockout format with draws
- Playoffs (Promoción) - spawned from league standings
- European competitions - qualification based on league finish

---

## Phase 1: Clean Up & Foundation

### Delete (over-engineered, incomplete)
```
app/Domain/           # 3 competing aggregates, empty repos
app/Application/      # Incomplete handlers
app/Infrastructure/   # Empty repositories
app/Game/Competitions/Team.php
app/Game/Competitions/LaLiga1.php
app/Game/Competitions/LaLiga2.php
app/Game/Competitions/Round.php
app/Game/Competitions/ScheduledMatch.php
app/Game/Calendar.php
```

### Keep & Expand
```
app/Game/Game.php              # Sole aggregate root
app/Game/GameProjector.php     # Expand for match results
app/Game/Events/GameCreated.php
app/Game/Commands/CreateGame.php
app/Models/Game.php            # Projection model
app/Models/Team.php            # Reference model
data/ESP1/2024/                # Source data
data/ESP2/2024/                # Source data
```

---

## Phase 2: Database Schema

### Reference Tables (seeded once from JSON)
```sql
teams
  - id UUID PRIMARY KEY        -- from JSON
  - transfermarkt_id INTEGER
  - name, official_name, image, country
  - stadium_name, stadium_seats
  - colors JSON
  - current_market_value VARCHAR

competitions
  - id VARCHAR(10) PRIMARY KEY -- 'ESP1', 'ESP2'
  - name, country, tier
  - type ENUM('league','cup','european')

competition_teams
  - competition_id, team_id, season

fixture_templates
  - id UUID PRIMARY KEY
  - competition_id, season
  - round_number, match_number
  - home_team_id UUID, away_team_id UUID
  - scheduled_date, location
```

### Game-Scoped Tables (projections)
```sql
games
  - id UUID PRIMARY KEY
  - user_id, player_name
  - team_id UUID
  - current_date DATE
  - current_matchday INTEGER

game_matches
  - id UUID PRIMARY KEY
  - game_id, competition_id
  - round_number, home_team_id, away_team_id
  - scheduled_date, home_score, away_score
  - played BOOLEAN

game_standings
  - game_id, competition_id, team_id
  - position, played, won, drawn, lost
  - goals_for, goals_against, points
```

---

## Phase 3: Core Implementation

### New Files to Create

```
app/Console/Commands/SeedReferenceData.php   # Seed from JSON
app/Game/Commands/AdvanceMatchday.php        # Command
app/Game/Events/MatchdayAdvanced.php         # Event
app/Game/Events/MatchResultRecorded.php      # Event
app/Game/Services/MatchSimulator.php         # Generate scores
app/Game/Services/StandingsCalculator.php    # Update standings
app/Models/Competition.php                   # Eloquent
app/Models/CompetitionTeam.php               # Eloquent
app/Models/FixtureTemplate.php               # Eloquent
app/Models/GameMatch.php                     # Projection
app/Models/GameStanding.php                  # Projection
app/Http/Actions/AdvanceMatchday.php         # HTTP action
app/Http/Views/ShowCalendar.php              # View
app/Http/Views/ShowStandings.php             # View
app/Http/Views/ShowMatchResults.php          # View
```

### Game Aggregate (expand `app/Game/Game.php`)
```php
final class Game extends AggregateRoot
{
    private Carbon $currentDate;
    private int $currentMatchday = 0;

    public static function create(CreateGame $command): self;
    public function advanceMatchday(array $matchResults): self;

    protected function applyGameCreated(GameCreated $event): void;
    protected function applyMatchdayAdvanced(MatchdayAdvanced $event): void;
}
```

### GameProjector (expand `app/Game/GameProjector.php`)
```php
class GameProjector extends Projector
{
    public function onGameCreated(GameCreated $event): void
    {
        // 1. Create Game model
        // 2. Copy fixture_templates to game_matches for player's league
        // 3. Initialize game_standings for all teams in league
    }

    public function onMatchResultRecorded(MatchResultRecorded $event): void
    {
        // 1. Update game_matches with scores
        // 2. Recalculate standings
    }

    public function onMatchdayAdvanced(MatchdayAdvanced $event): void
    {
        // Update game.current_date and current_matchday
    }
}
```

### Match Simulator (simple for MVP)
```php
class MatchSimulator
{
    public function simulate(Team $home, Team $away): MatchResult
    {
        // Poisson distribution based on stadium size (proxy for club strength)
        // Home team: lambda ~1.5-2.0
        // Away team: lambda ~0.8-1.2
        // Returns { homeScore, awayScore }
    }
}
```

---

## Phase 4: Game Flow

### 1. Create Game
```
POST /new-game
  → Game::create(command)
  → GameCreated event stored
  → GameProjector creates: Game, copies fixtures, inits standings
  → Redirect to /game/{uuid}
```

### 2. View Dashboard (`/game/{uuid}`)
- Your team info
- Next match
- Current standings (top 5 + your position)
- "Continue" button

### 3. Advance Matchday
```
POST /game/{uuid}/advance
  → Get unplayed matches for current matchday
  → For each: MatchSimulator::simulate()
  → Game::advanceMatchday(results)
  → Events: MatchdayAdvanced + MatchResultRecorded (per match)
  → GameProjector updates matches & standings
  → Redirect to /game/{uuid}/results/{matchday}
```

### 4. View Results (`/game/{uuid}/results/{matchday}`)
- All match results from that matchday
- Your match highlighted
- Link back to dashboard

### 5. View Standings (`/game/{uuid}/standings`)
- Full league table
- Your team highlighted
- Promotion/relegation zones colored

### 6. View Calendar (`/game/{uuid}/calendar`)
- All fixtures grouped by month
- Played matches show results
- Next match highlighted

---

## Phase 5: Routes

```php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class);
    Route::get('/new-game', SelectTeam::class);
    Route::post('/new-game', InitGame::class);

    Route::get('/game/{game}', ShowGame::class);
    Route::get('/game/{game}/calendar', ShowCalendar::class);
    Route::get('/game/{game}/standings', ShowStandings::class);
    Route::get('/game/{game}/results/{matchday}', ShowMatchResults::class);
    Route::post('/game/{game}/advance', AdvanceMatchday::class);
});
```

---

## Implementation Order

1. **Migrations** - Create/update tables for new schema
2. **Seeder** - `php artisan app:seed-reference-data` from JSON files
3. **Models** - Eloquent models for all tables
4. **Events** - `MatchdayAdvanced`, `MatchResultRecorded`
5. **Services** - `MatchSimulator`, `StandingsCalculator`
6. **Aggregate** - Expand `Game.php` with `advanceMatchday()`
7. **Projector** - Expand `GameProjector` for all events
8. **Actions** - `AdvanceMatchday` HTTP action
9. **Views** - Dashboard, Calendar, Standings, Results

---

## Verification

1. Run `php artisan app:seed-reference-data`
2. Verify teams/fixtures in database: `php artisan tinker` → `Team::count()` should be 42
3. Create a new game via UI, pick Real Madrid
4. Check `game_matches` table has 38 league fixtures for your team
5. Click "Continue" to advance matchday
6. Verify standings update correctly (3 pts for win, 1 for draw)
7. Advance through 5+ matchdays, verify standings make sense
8. Complete full season (38 matchdays), verify final standings

---

## Future Phases (not in this plan)

- **Phase 2**: Squad management (view players, pick lineup)
- **Phase 3**: Copa del Rey (cup format)
- **Phase 4**: European competitions (UCL/UEL qualification based on standings)
- **Phase 5**: Transfers & budget
