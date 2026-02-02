# Match Simulation

This document describes how match results are simulated in VirtuaFC.

## Overview

Match simulation uses a **Poisson distribution** to generate realistic scorelines based on:
- Team strength (from player abilities)
- Formation and mentality
- Home advantage
- Striker quality bonus
- Random performance variance

## Expected Goals Calculation

The core of match simulation is calculating **expected goals** for each team.

### Base Formula

```
homeExpectedGoals = (baseHomeGoals + homeAdvantage + strengthContribution)
                    × formationModifiers × mentalityModifiers + strikerBonus

awayExpectedGoals = (baseAwayGoals + strengthContribution × awayDisadvantage)
                    × formationModifiers × mentalityModifiers + strikerBonus
```

### Configuration Values

| Parameter | Value | Description |
|-----------|-------|-------------|
| `base_home_goals` | 0.5 | Base expected goals for home team |
| `base_away_goals` | 0.3 | Base expected goals for away team |
| `strength_multiplier` | 3.0 | How much strength adds to xG |
| `strength_exponent` | 2.0 | Amplifies strong vs weak gaps |
| `home_advantage_goals` | 0.15 | Extra xG for home team |
| `away_disadvantage_multiplier` | 0.8 | Away team strength reduction |

### Team Strength Calculation

Team strength is calculated from the 11 players in the lineup:

```php
foreach ($lineup as $player) {
    $performance = getMatchPerformance($player); // 0.85-1.15 variance

    $effectiveTechnical = $player->technical_ability × $performance;
    $effectivePhysical = $player->physical_ability × (0.5 + $performance × 0.5);

    $playerStrength = ($effectiveTechnical × 0.40) +
                      ($effectivePhysical × 0.25) +
                      ($player->fitness × 0.20) +
                      ($player->morale × 0.15);

    $totalStrength += $playerStrength;
}

$teamStrength = ($totalStrength / 11) / 100; // Normalized to 0-1
```

The strength exponent amplifies differences:

```php
$homeStrength = pow($homeStrength, 2.0);
$awayStrength = pow($awayStrength, 2.0);
```

This means an 85-rated team vs a 75-rated team has a larger effective gap than the raw 10-point difference.

### Striker Quality Bonus

Elite forwards boost their team's expected goals:

```php
$forwardPositions = ['Centre-Forward', 'Second Striker', 'Left Winger', 'Right Winger'];
$bestForwardScore = max(forwards' effective scores);

if ($bestForwardScore >= 80) {
    $strikerBonus = ($bestForwardScore - 80) / 40; // 0.0 to 0.5
}
```

| Best Forward Rating | Bonus xG |
|---------------------|----------|
| 94 (Mbappé) | +0.35 |
| 90 | +0.25 |
| 85 | +0.125 |
| <80 | +0.0 |

## Match Performance Variance

Each player gets a random "form on the day" modifier:

```
performance = 1.0 + (normal_distribution × 0.06)
clamped to: 0.85 - 1.15
```

This means:
- ~68% of performances are within ±6% of base ability
- ~95% are within ±12%
- Extreme performances (0.85 or 1.15) are rare

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

Scores are capped at 7 to prevent unrealistic cricket scores.

### Example Calculations

**Real Madrid (92 avg) vs Rayo Vallecano (73 avg) at Home**

```
Home strength: (0.92)^2 = 0.85
Away strength: (0.73)^2 = 0.53
Total: 1.38

Home xG contribution: (0.85 / 1.38) × 3.0 = 1.85
Away xG contribution: (0.53 / 1.38) × 3.0 × 0.8 = 0.92

Home xG: 0.5 + 0.15 + 1.85 + 0.35 (striker) = 2.85
Away xG: 0.3 + 0.92 = 1.22

Typical result: 3-1 or 2-1 to Real Madrid
```

**Even Match (both 85 avg)**

```
Home strength: (0.85)^2 = 0.72
Away strength: (0.85)^2 = 0.72
Total: 1.44

Home xG contribution: 0.5 × 3.0 = 1.5
Away xG contribution: 0.5 × 3.0 × 0.8 = 1.2

Home xG: 0.5 + 0.15 + 1.5 + 0.2 = 2.35
Away xG: 0.3 + 1.2 + 0.2 = 1.7

Typical result: 2-1 or 2-2
```

## Match Events

Beyond the scoreline, the simulation generates:

### Goals & Assists
- Goals assigned by position weight (strikers most likely)
- 60% chance of assist per goal
- Higher-rated players more likely to score/assist

### Cards
- ~1.7 yellow cards per team per match
- Losing teams get more cards (frustration)
- ~1.5% chance of direct red per team

### Injuries
- ~5% chance per team per match
- Random injury type and duration (1-6 weeks)

## Configuration

All parameters are tunable in `config/match_simulation.php`:

```php
return [
    'base_home_goals' => 0.5,
    'base_away_goals' => 0.3,
    'strength_multiplier' => 3.0,
    'strength_exponent' => 2.0,
    'home_advantage_goals' => 0.15,
    'away_disadvantage_multiplier' => 0.8,
    'performance_std_dev' => 0.06,
    'performance_min' => 0.85,
    'performance_max' => 1.15,
    'max_goals_cap' => 7,
    // ... event probabilities
];
```

## Implementation

See `app/Game/Services/MatchSimulator.php`:
- `simulate()` - Main match simulation
- `calculateTeamStrength()` - Lineup strength calculation
- `calculateStrikerBonus()` - Forward quality bonus
- `getMatchPerformance()` - Per-player daily form
- `poissonRandom()` - Score generation

## Design Rationale

### Why Poisson distribution?
Real football goals follow a Poisson distribution. It naturally creates realistic scorelines like 1-0, 2-1, 3-2 while occasionally allowing 5-0 blowouts.

### Why the strength exponent?
Without amplification, an 85 vs 75 rated match is too close to 50-50. The exponent ensures quality differences translate to meaningful result differences over a season.

### Why a striker bonus?
Team overall strength averages all 11 players, but a world-class striker creates chances from nothing. Mbappé vs an average striker should mean more goals for that team.

### Why low base goals?
Low base goals (0.5, 0.3) make strength contribution the dominant factor. This ensures better teams consistently outperform weaker ones rather than relying on random base goals.

## Expected Season Outcomes

With these parameters, a 38-game La Liga season should show:
- ~2.5-2.8 average goals per match
- Top scorer: 25-30 goals
- Top teams (90+ rated) finishing top 4
- Clear separation between quality tiers
- Occasional upsets, but not chaos
