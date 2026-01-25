# Copa del Rey Implementation Plan

## Overview

Implement Copa del Rey cup competition with knockout rounds, draws, and progression. The cup runs parallel to the league season with teams entering at different stages based on division.

## Data Structure (from ESPCUP)

- **7 rounds** total
- Rounds 1-5: single-leg knockout
- Round 6 (Semifinal): two-legged
- Round 7 (Final): single-leg at neutral venue
- **117 teams** from Primera, Segunda, and lower divisions

## Database Changes

### 1. New Tables

**`cup_round_templates`** - Static round configuration
```php
Schema::create('cup_round_templates', function (Blueprint $table) {
    $table->id();
    $table->string('competition_id', 10);
    $table->string('season', 10);
    $table->unsignedTinyInteger('round_number');
    $table->string('round_name');              // "Primera ronda", "Cuartos de final"
    $table->enum('type', ['one_leg', 'two_leg']);
    $table->date('first_leg_date');
    $table->date('second_leg_date')->nullable();
    $table->unsignedSmallInteger('teams_entering')->default(0); // How many teams enter this round

    $table->unique(['competition_id', 'season', 'round_number']);
});
```

**`cup_ties`** - Game-specific cup matchups (one per pairing, may have 1 or 2 legs)
```php
Schema::create('cup_ties', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('game_id');
    $table->string('competition_id', 10);
    $table->unsignedTinyInteger('round_number');
    $table->uuid('home_team_id');
    $table->uuid('away_team_id');
    $table->uuid('first_leg_match_id')->nullable();
    $table->uuid('second_leg_match_id')->nullable();
    $table->uuid('winner_id')->nullable();
    $table->boolean('completed')->default(false);
    $table->json('resolution')->nullable();     // {"type": "normal|extra_time|penalties", "detail": "..."}

    // Foreign keys
});
```

### 2. Modify `game_matches` Table

Add columns for cup-specific data:
```php
$table->boolean('is_extra_time')->default(false);
$table->unsignedTinyInteger('home_score_penalties')->nullable();
$table->unsignedTinyInteger('away_score_penalties')->nullable();
$table->uuid('cup_tie_id')->nullable();  // Links to cup_ties
```

### 3. Modify `games` Table

Add cup tracking:
```php
$table->unsignedTinyInteger('cup_round')->default(0);  // Current round in cup (0 = not started)
$table->boolean('cup_eliminated')->default(false);
```

## New Models

### CupRoundTemplate
```php
class CupRoundTemplate extends Model
{
    protected $casts = [
        'round_number' => 'integer',
        'first_leg_date' => 'date',
        'second_leg_date' => 'date',
    ];

    public function isTwoLegged(): bool
    {
        return $this->type === 'two_leg';
    }
}
```

### CupTie
```php
class CupTie extends Model
{
    use HasUuids;

    public function firstLegMatch(): BelongsTo;
    public function secondLegMatch(): BelongsTo;
    public function homeTeam(): BelongsTo;
    public function awayTeam(): BelongsTo;

    public function getAggregateScore(): array; // [home_total, away_total]
    public function getWinner(): ?Team;
}
```

## New Services

### CupDrawService

Handles random draw generation for each round:

```php
class CupDrawService
{
    public function conductDraw(string $gameId, int $roundNumber): array
    {
        // Get teams still in the cup at this round
        // For round 1: All teams marked to enter at round 1
        // For later rounds: Winners from previous round + new entries

        // Randomly pair teams
        // Create CupTie records
        // Create GameMatch records for first leg

        return $ties;
    }

    private function getTeamsForRound(string $gameId, int $roundNumber): Collection
    {
        // Teams entering at this round (from competition_teams with entry_round)
        // Plus winners from previous round
    }
}
```

### CupTieResolver

Determines winners of cup ties:

```php
class CupTieResolver
{
    public function resolve(CupTie $tie): ?string // Returns winner team_id or null if not complete
    {
        if (!$tie->firstLegMatch?->played) return null;

        $round = CupRoundTemplate::where('round_number', $tie->round_number)->first();

        if ($round->isTwoLegged()) {
            if (!$tie->secondLegMatch?->played) return null;
            return $this->resolveTwoLeggedTie($tie);
        }

        return $this->resolveSingleLegMatch($tie->firstLegMatch);
    }

    private function resolveSingleLegMatch(GameMatch $match): string
    {
        // If draw after 90', needs extra time/penalties
        // Return winner team_id
    }

    private function resolveTwoLeggedTie(CupTie $tie): string
    {
        // Calculate aggregate
        // Apply away goals if tied
        // If still tied, use extra time/penalties from second leg
    }
}
```

## Extended MatchSimulator

Add methods for extra time and penalties:

```php
// Add to MatchSimulator
public function simulateExtraTime(Team $home, Team $away, Collection $homePlayers, Collection $awayPlayers): MatchResult
{
    // Similar to regular simulation but 30 mins
    // Lower expected goals (~0.5 per team)
}

public function simulatePenalties(Collection $homePlayers, Collection $awayPlayers): array
{
    // Returns [home_score, away_score]
    // 5 penalties each, sudden death if tied
}
```

## Modified Game Flow

### 1. Cup Initialization (on Game Create)

In `GameProjector::onGameCreated`:
- Seed cup round templates for ESPCUP
- Add cup teams to `competition_teams` with entry round
- Do NOT create matches yet (draws happen during season)

### 2. Cup Draw Events

New events and commands:
- `ConductCupDraw` command
- `CupDrawConducted` event
- Projector creates ties and first-leg matches

### 3. Advancing the Calendar

Current system advances by matchday. For cups:
- Track `current_date` alongside `current_matchday`
- When a cup round date arrives, trigger draw (if needed) and add matches to calendar
- UI shows both league matchday and cup rounds

### 4. Match Resolution

For cup matches in `GameProjector::onMatchResultRecorded`:
- Check if match is part of a cup tie
- If single-leg and draw: trigger extra time/penalties simulation
- If two-legged: update tie, check if complete
- If tie complete: record winner, mark teams eliminated

## Files to Create

1. `database/migrations/2025_01_25_000003_create_cup_tables.php`
2. `app/Models/CupRoundTemplate.php`
3. `app/Models/CupTie.php`
4. `app/Game/Services/CupDrawService.php`
5. `app/Game/Services/CupTieResolver.php`
6. `app/Game/Commands/ConductCupDraw.php`
7. `app/Game/Events/CupDrawConducted.php`
8. `app/Game/Events/CupTieCompleted.php`
9. `app/Http/Views/ShowCupBracket.php`
10. `resources/views/cup.blade.php`

## Files to Modify

1. `app/Console/Commands/SeedReferenceData.php` - Seed ESPCUP data
2. `app/Game/GameProjector.php` - Handle cup initialization and match resolution
3. `app/Game/Services/MatchSimulator.php` - Add extra time and penalties
4. `app/Models/Game.php` - Add cup_round and cup_eliminated attributes
5. `app/Models/GameMatch.php` - Add cup-specific fields
6. `app/Http/Actions/AdvanceMatchday.php` - Handle cup round progression
7. `routes/web.php` - Add cup routes

## Team Entry Points

Based on Spanish football pyramid:
- **Round 1**: Lower league teams (Segunda B and below)
- **Round 2**: Segunda División teams enter
- **Round 3**: La Liga teams enter

This will be configured in `competition_teams` with an `entry_round` column.

## UI Changes

### Cup Bracket View (`/game/{id}/cup`)
- Visual bracket showing all rounds
- Completed ties show scores and winners
- Upcoming ties show "TBD" until draw
- Highlight player's team

### Calendar Integration
- Show cup matches alongside league fixtures
- Different styling for cup matches

### Game Header
- Show cup progress (e.g., "Copa del Rey: Quarter-finals")

## Verification

1. Create a new game with a La Liga team
2. Verify cup is initialized with team marked to enter at Round 3
3. Advance to Round 1 date, verify draw happens for lower-league teams
4. Advance to Round 3, verify player's team appears in draw
5. Play a cup match, verify winner determined (including extra time if drawn)
6. Verify bracket updates correctly
7. Test two-legged semifinal with aggregate scoring
8. Test elimination removes team from further rounds
