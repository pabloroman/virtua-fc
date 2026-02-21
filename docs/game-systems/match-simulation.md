# Match Simulation

This document describes how match results are simulated in VirtuaFC.

## Overview

Match simulation uses a **Poisson distribution** to generate realistic scorelines based on:
- Team strength ratios (from player abilities)
- Formation and mentality
- Home advantage
- Striker quality bonus
- Random performance variance

## Expected Goals Calculation

The core of match simulation is calculating **expected goals** for each team using a **ratio-based** formula.

### Base Formula

```
strengthRatio = homeStrength / awayStrength

homeXG = (strengthRatio ^ ratioExponent) × baseGoals + homeAdvantage
         × formationModifiers × mentalityModifiers + strikerBonus

awayXG = ((1/strengthRatio) ^ ratioExponent) × baseGoals
         × formationModifiers × mentalityModifiers + strikerBonus
```

When teams are equal (ratio = 1.0), both get `baseGoals` (1.3 xG). The stronger team is **always** favored regardless of venue — home advantage is a modest +0.15 on top.

### Configuration Values

| Parameter | Value | Description |
|-----------|-------|-------------|
| `base_goals` | 1.3 | xG per team when evenly matched (~2.6 total) |
| `ratio_exponent` | 2.0 | Amplifies strength ratio into xG gap |
| `home_advantage_goals` | 0.15 | Fixed home xG bonus |

### Team Strength Calculation

Team strength is calculated from the 11 players in the lineup. Ability is dominant (90% weight), while fitness/morale provide a small nudge:

```php
foreach ($lineup as $player) {
    $performance = getMatchPerformance($player); // 0.85-1.15 variance

    $effectiveTechnical = $player->technical_ability × $performance;
    $effectivePhysical = $player->physical_ability × (0.5 + $performance × 0.5);

    $playerStrength = ($effectiveTechnical × 0.55) +
                      ($effectivePhysical × 0.35) +
                      ($player->fitness × 0.05) +
                      ($player->morale × 0.05);

    $totalStrength += $playerStrength;
}

$teamStrength = ($totalStrength / 11) / 100; // Normalized to 0-1
```

Fitness and morale still affect matches through `getMatchPerformance()` modifiers — they influence the daily performance bell curve for each player.

### Striker Quality Bonus

Elite forwards boost their team's expected goals:

```php
$forwardPositions = ['Centre-Forward', 'Second Striker', 'Left Winger', 'Right Winger'];
$bestForwardScore = max(forwards' effective scores);

if ($bestForwardScore >= 85) {
    $strikerBonus = ($bestForwardScore - 85) / 60; // 0.0 to 0.25
}
```

| Best Forward Rating | Bonus xG |
|---------------------|----------|
| 94 (Mbappé) | +0.15 |
| 90 | +0.08 |
| 85 | +0.0 |
| <85 | +0.0 |

## Match Performance Variance

Each player gets a random "form on the day" modifier:

```
performance = 1.0 + (normal_distribution × 0.05)
clamped to: 0.90 - 1.10
```

This means:
- ~68% of performances are within ±5% of base ability
- ~95% are within ±10%
- The tight range rewards careful lineup crafting — the better squad reliably wins

### Morale & Fitness Influence

- **High morale (80+)**: Slight performance boost
- **Low morale (<50)**: Slight performance penalty
- **Low fitness (<70)**: Increased variance (more bad days)

## Score Generation

Final scores use **Poisson distribution**:

```php
$homeScore = poissonRandom($homeExpectedGoals);
$awayScore = poissonRandom($awayExpectedGoals);
```

Scores are capped at 6 to prevent unrealistic cricket scores.

### Example Calculations

**Real Madrid (90 avg) HOME vs Rayo Vallecano (72 avg)**

```
Home strength: 0.90 (normalized)
Away strength: 0.72 (normalized)
Ratio: 0.90 / 0.72 = 1.25

Home xG: (1.25^2) × 1.3 + 0.15 = 2.18
Away xG: (0.80^2) × 1.3 = 0.83

Typical result: 2-0 or 2-1 to Real Madrid
Home win ~73%
```

**Rayo Vallecano (72 avg) HOME vs Real Madrid (90 avg)**

```
Home strength: 0.72 (normalized)
Away strength: 0.90 (normalized)
Ratio: 0.72 / 0.90 = 0.80

Home xG: (0.80^2) × 1.3 + 0.15 = 0.98
Away xG: (1.25^2) × 1.3 = 2.03

Typical result: 0-2 or 1-2 to Real Madrid
Real Madrid win even away ~55%
```

**Even Match (both 82 avg)**

```
Ratio: 1.0

Home xG: (1.0^2) × 1.3 + 0.15 = 1.45
Away xG: (1.0^2) × 1.3 = 1.30

Typical result: 1-1 or 2-1
Home win ~39%, Draw ~28%, Away win ~33%
```

## Extra Time

Extra time uses the same ratio-based formula, scaled to 30 minutes with a 20% fatigue reduction:

```php
$etFraction = 30.0 / 90.0;
$etBaseGoals = $baseGoals × 0.8; // fatigue
$homeXG = (ratio^exp × etBaseGoals + homeAdvantage) × etFraction;
$awayXG = ((1/ratio)^exp × etBaseGoals) × etFraction;
```

## Season Simulation (Non-Played Leagues)

Leagues that the player doesn't participate in are simulated using the same ratio-based formula on a **match-by-match** basis:

1. Calculate squad strength for each team (0-100 scale from `BudgetProjectionService`)
2. Simulate all N×(N-1) fixtures (home and away for each pair)
3. Generate Poisson-distributed goals per match using ratio-based xG
4. Accumulate points (3W/1D/0L)
5. Sort by points → goal difference → goals for

This produces realistic standings with ~380 matches for a 20-team league — computationally trivial but statistically sound, as match-by-match averaging naturally produces realistic variance.

## Match Events

Beyond the scoreline, the simulation generates:

### Goals & Assists
- Goals assigned by position weight (strikers most likely)
- 60% chance of assist per goal
- Higher-rated players more likely to score/assist (dampened multiplier)

#### Goal Scorer Distribution

Goal scorer selection uses `pickGoalScorer()` with two mechanisms to prevent unrealistic concentration:

1. **Dampened quality multiplier**: `pow(effectiveScore / 70, 0.5)` instead of linear. A 90-rated CF gets only ~13% advantage over a 70-rated player (vs 29% with linear scaling).

2. **Within-match diminishing returns**: A player's weight is halved for each prior goal in the same match. This makes hat-tricks rare (~1-3 per season league-wide), matching real La Liga data.

| Position | Weight |
|----------|--------|
| Centre-Forward | 25 |
| Second Striker | 22 |
| Left/Right Winger | 15 |
| Attacking Midfield | 12 |
| Central Midfield | 6 |
| Left/Right Midfield | 5 |
| Defensive Midfield | 3 |
| Left/Right-Back | 2 |
| Centre-Back | 2 |
| Goalkeeper | 0 |

### Cards
- ~1.5 yellow cards per team per match
- Losing teams get more cards (frustration)
- ~1% chance of direct red per team

### Injuries
- ~4% chance per team per match
- Random injury type and duration

## Configuration

All parameters are tunable in `config/match_simulation.php`:

```php
return [
    'base_goals' => 1.3,
    'ratio_exponent' => 2.0,
    'home_advantage_goals' => 0.15,
    'performance_std_dev' => 0.05,
    'performance_min' => 0.90,
    'performance_max' => 1.10,
    'max_goals_cap' => 6,
    // ... event probabilities
];
```

## Live Match

Matches are played in a **live match view** where users can make decisions during the game:

### Substitutions
- Up to **5 substitutions** per match in **3 windows** (matching real football rules)
- Select a player from the bench to replace a starter
- Substituted players retain their match stats

### Tactical Changes
- **Formation changes** mid-match (e.g., switch from 4-4-2 to 3-5-2)
- **Mentality changes** (defensive, balanced, attacking)
- Changes take effect for the remaining match simulation

### Match Phases
- Pre-match → First half → Half-time → Second half → Full-time
- Extra time (cup matches): 30 minutes with fatigue modifier
- Penalty shootouts for drawn cup ties

## Implementation

See `app/Modules/Match/Services/MatchSimulator.php`:
- `simulate()` - Main match simulation
- `simulateRemainder()` - Resume from a given minute (substitution support)
- `calculateTeamStrength()` - Lineup strength calculation (ability-dominant weights)
- `calculateStrikerBonus()` - Forward quality bonus
- `getMatchPerformance()` - Per-player daily form
- `poissonRandom()` - Score generation
- `simulateExtraTime()` - Extra time with fatigue

See `app/Modules/Finance/Services/SeasonSimulationService.php`:
- `simulateLeague()` - Full match-by-match season simulation
- `simulateMatchResult()` - Single match using ratio-based xG + Poisson

## Design Rationale

### Why ratio-based xG?
The previous additive share-based formula created a "floor" that made weak teams unrealistically competitive. With the old formula, a weak team at home was actually favored vs an elite team away. The ratio-based formula ensures the stronger team is always favored, with home advantage as a modest bonus on top.

### Why Poisson distribution?
Real football goals follow a Poisson distribution. It naturally creates realistic scorelines like 1-0, 2-1, 3-2 while occasionally allowing 5-0 blowouts.

### Why ability-dominant weights (55/35/5/5)?
The old 40/25/20/15 weights gave fitness (90-100) and morale (65-80) too much influence, compressing the elite-to-bottom strength range from ~20 points to ~14 points. With 55/35/5/5 weights, the full ability gap is preserved while fitness/morale still contribute through the per-player performance modifier.

### Why a striker bonus?
Team overall strength averages all 11 players, but a world-class striker creates chances from nothing. Mbappé vs an average striker should mean more goals for that team.

### Why match-by-match season simulation?
The old single-shot approach (`strength + random_noise(±4.0) → sort`) was chaotic — the ±4.0 noise was 30-50% of typical strength gaps. Match-by-match simulation produces ~380 data points per season, averaging out randomness while still allowing upsets on individual matchdays.

## Expected Season Outcomes

With these parameters, a 38-game La Liga season should show:
- ~2.5-2.8 average goals per match
- Elite teams (Real Madrid): ~75-85 pts
- Strong teams (Atletico): ~65-75 pts
- Mid-table: ~48-58 pts
- Bottom: ~28-38 pts
- Clear separation between quality tiers
- Occasional upsets, but not chaos
