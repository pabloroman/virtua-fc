# Player Abilities

This document describes how player abilities (technical and physical) are calculated from market value data.

## Overview

Players have two core attributes:
- **Technical Ability** (1-99): Skill, technique, decision-making
- **Physical Ability** (1-99): Speed, strength, stamina, athleticism

The **Overall Score** is the average of these two values.

## Calculation Process

### Step 1: Market Value to Raw Ability

Market value is converted to a base ability tier:

| Market Value | Raw Ability Range |
|--------------|-------------------|
| €100M+ | 88-95 |
| €50-100M | 83-90 |
| €20-50M | 78-85 |
| €10-20M | 73-80 |
| €5-10M | 68-75 |
| €2-5M | 63-70 |
| €1-2M | 58-65 |
| Under €1M | 50-60 |
| Unknown | 45-55 |

### Step 2: Age Adjustment

Raw ability is adjusted based on age to account for the different meanings of market value:

#### Young Players (Under 23)
Their market value includes a "potential premium" - they're not at peak skill yet.

```
Age Cap = 73 + (age - 17) × 2

Bonus for exceptional value:
  €100M+ → +8 to cap
  €50M+  → +5 to cap
  €20M+  → +3 to cap
```

**Example**: Lamine Yamal (17, €120M)
- Raw ability: 91 (€100M+ tier)
- Age cap: 73 + 0 + 8 = 81
- Final: min(91, 81) = **81**

#### Prime Players (23-30)
Market value directly reflects current ability. No adjustment.

**Example**: Mbappé (25, €180M)
- Raw ability: 91
- No adjustment (prime age)
- Final: **91**

#### Veterans (31+)
Their low market value is due to age, not skill loss. We boost based on how exceptional their value is for their age.

```
Typical value for age:
  31-32: €5M
  33-34: €3M
  35-36: €1.5M
  37+:   €0.8M

Value ratio = actual value / typical value

Boost:
  10x+ typical → +12
  5-10x       → +8
  3-5x        → +5
  2-3x        → +3
  1-2x        → +1
```

**Example**: Lewandowski (36, €15M)
- Raw ability: 76 (€10-20M tier)
- Typical value for 36: €1.5M
- Value ratio: €15M / €1.5M = 10x
- Boost: +12
- Final: **88**

### Step 3: Position-Based Split

The base ability is split between technical and physical based on position:

| Position | Technical Ratio | Physical Ratio |
|----------|-----------------|----------------|
| Attacking Midfield | 70% | 30% |
| Second Striker | 70% | 30% |
| Centre-Forward | 65% | 35% |
| Left/Right Winger | 65% | 35% |
| Goalkeeper | 55% | 45% |
| Central Midfield | 55% | 45% |
| Defensive Midfield | 45% | 55% |
| Left/Right Back | 45% | 55% |
| Centre-Back | 35% | 65% |

A small variance (±2-5 points) is applied to create individual variation.

### Step 4: Veteran Physical Decline

Veterans receive an additional physical ability reduction:
- Age 31-33: Physical × 0.96
- Age 34+: Physical × 0.92

This reflects that physical abilities decline faster than technical ones.

## Examples

| Player | Age | Market Value | Raw | Adjusted | Tech | Phys | Overall |
|--------|-----|--------------|-----|----------|------|------|---------|
| Lamine Yamal | 17 | €120M | 91 | 81 | 83 | 79 | 81 |
| Pedri | 21 | €80M | 86 | 86 | 88 | 84 | 86 |
| Mbappé | 25 | €180M | 91 | 91 | 93 | 89 | 91 |
| Lewandowski | 36 | €15M | 76 | 88 | 91 | 78 | 85 |
| Modric | 38 | €5M | 71 | 79 | 82 | 71 | 77 |
| Average 36yo | 36 | €1.5M | 61 | 62 | 64 | 57 | 61 |

## Implementation

See `app/Console/Commands/SeedReferenceData.php`:
- `calculateAbilities()` - Main calculation
- `marketValueToRawAbility()` - Value tier lookup
- `adjustAbilityForAge()` - Age-based adjustments

## Design Rationale

### Why cap young players?
A €120M 17-year-old hasn't reached their peak yet. Their value reflects what they *could* become, not what they are. Without capping, Yamal would be rated 91+ at 17, which is unrealistic.

### Why boost veterans?
A €15M 36-year-old is exceptional - most players that age are worth €1-3M. Lewandowski commands high value because he's still performing at elite level. Without boosting, he'd be rated 72, which doesn't reflect reality.

### Why different technical/physical ratios?
Strikers and attacking midfielders rely more on technique (finishing, passing, movement). Defenders rely more on physicality (strength, pace, stamina). This creates realistic player profiles.
