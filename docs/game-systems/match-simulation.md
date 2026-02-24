# Match Simulation

This document describes how match results are simulated in VirtuaFC.

## Overview

Match simulation uses a **Poisson distribution** to generate realistic scorelines based on:
- Team strength ratios (from player abilities)
- Formation and mentality modifiers
- Home advantage
- Striker quality bonus
- Energy/stamina system
- Random performance variance

## Expected Goals Calculation

The core of match simulation is calculating **expected goals** for each team using a **ratio-based** formula.

### Base Formula

```
strengthRatio = homeStrength / awayStrength

homeXG = (strengthRatio ^ ratioExponent) × baseGoals + homeAdvantage
         × homeFormation.attackModifier
         × awayFormation.defenseModifier
         × homeMentality.ownGoalsModifier
         × awayMentality.opponentGoalsModifier
         × matchFraction

awayXG = ((1/strengthRatio) ^ ratioExponent) × baseGoals
         × awayFormation.attackModifier
         × homeFormation.defenseModifier
         × awayMentality.ownGoalsModifier
         × homeMentality.opponentGoalsModifier
         × matchFraction
```

Formation modifiers, mentality modifiers, and match fraction are all multiplicative. When teams are equal (ratio = 1.0), both get `baseGoals` (1.3 xG). The stronger team is **always** favored regardless of venue — home advantage is a modest +0.15 on top.

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
    $performance = getMatchPerformance($player); // 0.90-1.10 variance
    $energyModifier = getEnergyEffectiveness($player); // 0.6-1.0

    $effectiveTechnical = $player->technical_ability × $performance;
    $effectivePhysical = $player->physical_ability × (0.5 + $performance × 0.5);

    $playerStrength = ($effectiveTechnical × 0.55) +
                      ($effectivePhysical × 0.35) +
                      ($player->fitness × 0.05) +
                      ($player->morale × 0.05);

    $playerStrength *= $energyModifier;
    $totalStrength += $playerStrength;
}

$teamStrength = ($totalStrength / 11) / 100; // Normalized to 0-1
```

Returns a fallback of 0.30 for incomplete lineups (<11 players).

### Striker Quality Bonus

Elite forwards boost their team's expected goals:

```php
$forwardPositions = ['Centre-Forward', 'Second Striker', 'Left Winger', 'Right Winger'];
$bestForwardScore = max(forwards' effective scores);

if ($bestForwardScore >= 85) {
    $strikerBonus = ($bestForwardScore - 85) / 60; // 0.0 to ~0.25
}
```

| Best Forward Rating | Bonus xG |
|---------------------|----------|
| 94 (Mbappé) | +0.15 |
| 90 | +0.08 |
| 85 | +0.0 |
| <85 | +0.0 |

## Formation Modifiers

Each formation has attack and defense modifiers that multiplicatively adjust expected goals:

| Formation | Lineup | Attack | Defense |
|-----------|--------|--------|---------|
| 4-4-2 | 1 GK, 4 DEF, 4 MID, 2 FWD | 1.00 | 1.00 |
| 4-3-3 | 1 GK, 4 DEF, 3 MID, 3 FWD | 1.10 | 1.10 |
| 4-2-3-1 | 1 GK, 4 DEF, 5 MID, 1 FWD | 1.00 | 0.95 |
| 3-4-3 | 1 GK, 3 DEF, 4 MID, 3 FWD | 1.10 | 1.10 |
| 3-5-2 | 1 GK, 3 DEF, 5 MID, 2 FWD | 1.05 | 1.05 |
| 4-1-4-1 | 1 GK, 4 DEF, 5 MID, 1 FWD | 0.95 | 0.95 |
| 5-3-2 | 1 GK, 5 DEF, 3 MID, 2 FWD | 0.90 | 0.90 |
| 5-4-1 | 1 GK, 5 DEF, 4 MID, 1 FWD | 0.85 | 0.85 |

A team's `attackModifier` scales their own xG, while their `defenseModifier` scales the opponent's xG.

## Mentality Modifiers

| Mentality | Own Goals Modifier | Opponent Goals Modifier |
|-----------|-------------------|------------------------|
| Defensive | 0.80 | 0.70 |
| Balanced | 1.00 | 1.00 |
| Attacking | 1.15 | 1.10 |

An attacking mentality boosts your own scoring (+15%) but also concedes more (+10%). A defensive mentality reduces both your own (-20%) and the opponent's (-30%) goals.

### AI Mentality Selection

AI teams choose mentality based on reputation tier and matchup:

**Reputation tiers:**
- **Bold**: elite, contenders
- **Mid**: continental, established
- **Cautious**: modest, professional, local

**Strength thresholds:** `isStronger` if team average - opponent average ≥ 5; `isWeaker` if ≤ -5.

| Venue | Matchup | Bold | Mid | Cautious |
|-------|---------|------|-----|----------|
| Home | Stronger | Attacking | Balanced | Balanced |
| Home | Similar | Balanced | Balanced | Balanced |
| Home | Weaker | Balanced | Defensive | Defensive |
| Away | Stronger | Balanced | Balanced | Defensive |
| Away | Similar | Balanced | Defensive | Defensive |
| Away | Weaker | Defensive | Defensive | Defensive |

## Energy / Stamina System

Players lose energy over the course of a match, reducing their effectiveness in the second half and extra time.

### Energy Drain Formula

```
drain_per_minute = base_drain
                 - (physical_ability - 50) × physical_ability_factor
                 + max(0, age - age_threshold) × age_penalty_per_year

For goalkeepers: drain × gk_drain_multiplier
```

**Configuration:**

| Parameter | Value | Description |
|-----------|-------|-------------|
| `base_drain_per_minute` | 0.75 | Base energy lost per minute |
| `physical_ability_factor` | 0.005 | Drain reduction per physical ability point above 50 |
| `age_threshold` | 28 | Age at which aging penalty begins |
| `age_penalty_per_year` | 0.015 | Extra drain per year above 28 |
| `gk_drain_multiplier` | 0.5 | Goalkeepers tire at half the rate |

### Effectiveness Modifier

As energy drops, player effectiveness decreases:

```
effectiveness = min_effectiveness + (energy / 100) × (1 - min_effectiveness)
```

- At 100% energy: **1.0x** (full strength)
- At 50% energy: **0.8x**
- At 0% energy: **0.6x** (minimum effectiveness)

This makes late-game substitutions meaningful — fresh legs contribute more than tired starters.

## Match Performance Variance

Each player gets a random "form on the day" modifier using a Box-Muller normal distribution:

```
basePerformance = 1.0 + (z × std_dev)    // z from normal distribution
moraleModifier = (morale - 65) / 200       // range: -0.075 to +0.175
fitnessModifier = (fitness - 70) / 300     // only if fitness < 70

performance = clamp(basePerformance + moraleModifier + fitnessModifier, 0.90, 1.10)
```

**Configuration:**

| Parameter | Value | Description |
|-----------|-------|-------------|
| `performance_std_dev` | 0.05 | Standard deviation (5%) |
| `performance_min` | 0.90 | Minimum performance modifier |
| `performance_max` | 1.10 | Maximum performance modifier |

This means:
- ~68% of performances are within ±5% of base ability
- ~95% are within ±10%
- High morale (80+) shifts the curve upward; low morale (<50) shifts it down
- Low fitness (<70) applies an additional penalty
- The tight range rewards careful lineup crafting — the better squad reliably wins

## Score Generation

Final scores use **Poisson distribution**:

```php
$homeScore = poissonRandom($homeExpectedGoals);
$awayScore = poissonRandom($awayExpectedGoals);
```

Scores are capped at **6 goals per team** to prevent unrealistic cricket scores.

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

**Even Match (both 82 avg)**

```
Ratio: 1.0

Home xG: (1.0^2) × 1.3 + 0.15 = 1.45
Away xG: (1.0^2) × 1.3 = 1.30

Typical result: 1-1 or 2-1
Home win ~39%, Draw ~28%, Away win ~33%
```

## Match Events

Beyond the scoreline, the simulation generates goals, assists, cards, and injuries.

### Goals & Assists

Goal scorer selection uses `pickGoalScorer()` with two mechanisms to prevent unrealistic concentration:

1. **Dampened quality multiplier**: `sqrt(effectiveScore / 70)` instead of linear. A 90-rated CF gets only ~13% advantage over a 70-rated player (vs 29% with linear scaling).

2. **Within-match diminishing returns**: A player's weight is halved for each prior goal in the same match. This makes hat-tricks rare (~1-3 per season league-wide), matching real La Liga data.

**Goal Scorer Position Weights:**

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

**Assist Position Weights:**

| Position | Weight |
|----------|--------|
| Attacking Midfield | 25 |
| Left/Right Winger | 20 |
| Central Midfield | 15 |
| Left/Right Midfield | 12 |
| Second Striker | 10 |
| Centre-Forward | 8 |
| Left/Right-Back | 8 |
| Defensive Midfield | 6 |
| Centre-Back | 2 |
| Goalkeeper | 1 |

Each goal has a **60% chance** of having an assist. The assist is selected from remaining players using the assist weights (never the goal scorer).

### Own Goals

Each goal has a **1.0% chance** of being an own goal instead. Own goals are attributed by position weight (defenders most likely):

| Position | Own Goal Weight |
|----------|----------------|
| Centre-Back | 40 |
| Left/Right-Back | 20 |
| Defensive Midfield | 15 |
| Goalkeeper | 5 |

### Cards

- Yellow cards: **1.4 per team per match** on average (Poisson distributed)
- Direct red cards: **0.5% base chance** per team, increasing +50% per goal deficit
- A second yellow in the same match results in a red card

**Card Position Weights** (defenders and defensive midfielders commit the most fouls):

| Position | Weight |
|----------|--------|
| Centre-Back | 20 |
| Defensive Midfield | 18 |
| Left/Right-Back | 12 |
| Central Midfield | 10 |
| Left/Right Midfield | 8 |
| Centre-Forward | 8 |
| Attacking Midfield | 6 |
| Second Striker | 6 |
| Left/Right Winger | 5 |
| Goalkeeper | 4 |

### Injuries

- **2.0% chance** per player per match
- **1.5% chance** per non-playing player per matchday (training injuries)
- Medical tier investment reduces injury chance (up to 45% reduction at Tier 4)

See [Injury System](injury-system.md) for full details.

### Event Reassignment

If a player is removed during the match (injury or red card), any goals or assists assigned to them for subsequent events are reassigned to available teammates using the same weighted selection algorithm.

## Extra Time

Extra time uses the same ratio-based formula, scaled to 30 minutes with a 20% fatigue reduction:

```php
$etFraction = 30.0 / 90.0;
$etBaseGoals = $baseGoals × 0.8; // fatigue reduces goal-scoring
$homeXG = (ratio^exp × etBaseGoals + homeAdvantage) × etFraction;
$awayXG = ((1/ratio)^exp × etBaseGoals) × etFraction;
```

## Penalty Shootout

When a cup tie is still drawn after extra time, a penalty shootout determines the winner.

### Penalty Conversion Formula

```
Base success rate: 75%

Kicker bonus:  (technical_ability - 50) × 0.15
             + (morale - 50) × 0.06

GK penalty:    (technical_ability - 50) × 0.10

Luck factor:   ±5 (random)

conversion_chance = base + kicker_bonus - gk_penalty + luck
Clamped to: 50% - 95%
```

- Standard 5 kicks each, then sudden death
- In sudden death, if still tied, the simulation ensures one team scores and one misses to guarantee resolution

## Live Match

Matches are played in a **live match view** where users can make decisions during the game:

### Substitutions
- Up to **5 substitutions** per match in **3 windows** (matching real football rules)
- Select a player from the bench to replace a starter
- Substituted players retain their match stats
- Fresh substitutes have full energy, improving team effectiveness late in matches

### Tactical Changes
- **Formation changes** mid-match (e.g., switch from 4-4-2 to 3-5-2)
- **Mentality changes** (defensive, balanced, attacking)
- Changes take effect for the remaining match simulation via `simulateRemainder()`

### Match Phases
- Pre-match → First half → Half-time → Second half → Full-time
- Extra time (cup matches): 30 minutes with fatigue modifier
- Penalty shootouts for drawn cup ties

## Season Simulation (Non-Played Leagues)

Leagues that the player doesn't participate in are simulated using the same ratio-based formula on a **match-by-match** basis:

1. Calculate squad strength for each team (average OVR of best 18 players)
2. Simulate all N×(N-1) fixtures (home and away for each pair)
3. Generate Poisson-distributed goals per match using ratio-based xG
4. Accumulate points (3W/1D/0L)
5. Sort by points → goal difference → goals for

This produces realistic standings with ~380 matches for a 20-team league — computationally trivial but statistically sound, as match-by-match averaging naturally produces realistic variance.

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
    'own_goal_chance' => 1.0,
    'assist_chance' => 60.0,
    'yellow_cards_per_team' => 1.4,
    'direct_red_chance' => 0.5,
    'injury_chance' => 2.0,
    'training_injury_chance' => 1.5,
    // ... energy parameters, formation modifiers, mentality modifiers
];
```

## Implementation

See `app/Modules/Match/Services/MatchSimulator.php`:
- `simulate()` - Main match simulation
- `simulateRemainder()` - Resume from a given minute (substitution support)
- `calculateTeamStrength()` - Lineup strength calculation (ability-dominant weights)
- `calculateStrikerBonus()` - Forward quality bonus
- `getMatchPerformance()` - Per-player daily form with morale/fitness modifiers
- `pickGoalScorer()` / `pickAssistProvider()` - Weighted event attribution
- `poissonRandom()` - Score generation
- `simulateExtraTime()` - Extra time with fatigue
- `simulatePenaltyShootout()` - Penalty shootout with kicker-vs-goalkeeper duel

See `app/Modules/Finance/Services/SeasonSimulationService.php`:
- `simulateLeague()` - Full match-by-match season simulation
- `simulateMatchResult()` - Single match using ratio-based xG + Poisson

## Design Rationale

### Why ratio-based xG?
The previous additive share-based formula created a "floor" that made weak teams unrealistically competitive. The ratio-based formula ensures the stronger team is always favored, with home advantage as a modest bonus on top.

### Why Poisson distribution?
Real football goals follow a Poisson distribution. It naturally creates realistic scorelines like 1-0, 2-1, 3-2 while occasionally allowing 5-0 blowouts.

### Why ability-dominant weights (55/35/5/5)?
The old 40/25/20/15 weights gave fitness and morale too much influence, compressing the elite-to-bottom strength range. With 55/35/5/5 weights, the full ability gap is preserved while fitness/morale still contribute through the per-player performance modifier.

### Why a striker bonus?
Team overall strength averages all 11 players, but a world-class striker creates chances from nothing. Mbappé vs an average striker should mean more goals for that team.

### Why an energy system?
Without energy, substitutions and squad depth are meaningless. The energy system makes fresh substitutes valuable, rewards rotation, and gives tactical choices (e.g., early attacking sub vs. late defensive sub) genuine impact on the match outcome.

## Expected Season Outcomes

With these parameters, a 38-game La Liga season should show:
- ~2.5-2.8 average goals per match
- Elite teams (Real Madrid): ~75-85 pts
- Strong teams (Atletico): ~65-75 pts
- Mid-table: ~48-58 pts
- Bottom: ~28-38 pts
- Clear separation between quality tiers
- Occasional upsets, but not chaos
