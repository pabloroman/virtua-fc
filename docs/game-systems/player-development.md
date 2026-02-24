# Player Development

This document describes how players develop (or decline) over seasons.

## Overview

At the end of each season, players' abilities change based on:
- **Age** - Young players grow, veterans decline (separate curves for technical and physical)
- **Playing Time** - Starters develop faster
- **Quality Gap** - Players far from potential develop faster (growing players only)
- **Potential Cap** - Players cannot exceed their ceiling

## Development Curve

### Age Multipliers

Each age has separate development multipliers for technical and physical abilities. Physical ability peaks earlier and declines faster than technical ability:

| Age | Technical | Physical | Status |
|-----|-----------|----------|--------|
| 16 | 1.8x | 2.0x | Growing |
| 17 | 1.6x | 1.8x | Growing |
| 18 | 1.4x | 1.5x | Growing |
| 19 | 1.3x | 1.3x | Growing |
| 20 | 1.2x | 1.2x | Growing |
| 21 | 1.1x | 1.1x | Growing |
| 22 | 1.05x | 1.0x | Growing |
| 23-24 | 1.0x | 1.0x | Peak |
| 25 | 1.0x | 0.95x | Peak (physical starts declining) |
| 26 | 1.0x | 0.9x | Peak |
| 27 | 0.95x | 0.85x | Peak |
| 28 | 0.9x | 0.8x | Peak |
| 29 | 0.85x | 0.7x | Declining |
| 30 | 0.8x | 0.6x | Declining |
| 31 | 0.7x | 0.5x | Declining |
| 32 | 0.6x | 0.4x | Declining |
| 33 | 0.5x | 0.3x | Declining |
| 34+ | 0.4x | 0.2x | Declining |

### Base Development

```
BASE_DEVELOPMENT = 2 points per season
```

For **growing** players (multiplier ≥ 1.0):
```
change = round(BASE_DEVELOPMENT × multiplier × bonuses)
```

For **declining** players (multiplier < 1.0):
```
change = -round((1.0 - multiplier) × BASE_DEVELOPMENT)
```

**Example decline at age 32** (tech: 0.6, phys: 0.4):
- Technical: -round((1.0 - 0.6) × 2) = -round(0.8) = **-1**
- Physical: -round((1.0 - 0.4) × 2) = -round(1.2) = **-1**

**Example decline at age 30** (tech: 0.8, phys: 0.6):
- Technical: -round((1.0 - 0.8) × 2) = -round(0.4) = **0** (no decline yet)
- Physical: -round((1.0 - 0.6) × 2) = -round(0.8) = **-1**

This means technical ability remains stable into the late 20s while physical ability starts dropping earlier — matching real football.

## Playing Time Bonus

Players who play regularly develop faster:

```
MIN_APPEARANCES_FOR_BONUS = 15 games
APPEARANCE_BONUS = 1.5x (50% faster development)
```

Only applies to growing players (multiplier ≥ 1.0). A young player with 20+ appearances develops 50% faster than a bench player.

## Quality Gap Bonus

Players far from their potential develop faster — they have more room to grow. This uses a **continuous formula**, not discrete tiers:

```
If age ≥ 28: no bonus (1.0x)
If gap ≤ 5: no bonus (1.0x)
Otherwise: bonus = 1.0 + (gap / 40), capped at 1.5x
```

| Gap to Potential | Development Bonus |
|------------------|-------------------|
| 20+ points | 1.5x (+50%) |
| 15 points | 1.375x (+37.5%) |
| 10 points | 1.25x (+25%) |
| 5 points | 1.0x (none) |
| <5 points | 1.0x (none) |

**Only applies to growing players (age < 28).**

### Combined Example: Wonderkid Development

Player: 17 years old, current: 72, potential: 92 (gap: 20)

```
Base development: 2
Age multiplier (17): 1.6x (technical)
Playing time bonus: 1.5x (15+ appearances)
Quality gap bonus: 1.5x (20 point gap)

Technical change = round(2 × 1.6 × 1.5 × 1.5) = round(7.2) = +7
```

With all bonuses, a well-played wonderkid can gain 7+ points in a season.

## Potential Cap

Players cannot exceed their potential through development:

```php
if ($techChange > 0) {
    $newTech = min($newTech, $potential);
}
```

As players approach their ceiling, growth naturally slows because the quality gap bonus diminishes.

## Development Examples

### Young Star: Lamine Yamal

| Season | Age | Current | Potential | Gap | Change | New |
|--------|-----|---------|-----------|-----|--------|-----|
| Start | 17 | 81 | 99 | 18 | — | 81 |
| 1 | 18 | 81 | 99 | 18 | +5 | 86 |
| 2 | 19 | 86 | 99 | 13 | +4 | 90 |
| 3 | 20 | 90 | 99 | 9 | +3 | 93 |
| 4 | 21 | 93 | 99 | 6 | +2 | 95 |
| 5 | 22 | 95 | 99 | 4 | +1 | 96 |

### Veteran: Lewandowski

| Season | Age | Current | Tech Change | Phys Change |
|--------|-----|---------|-------------|-------------|
| Start | 36 | 88 | — | — |
| 1 | 37 | 87 | -1 | -2 |
| 2 | 38 | 85 | -1 | -2 |
| 3 | 39 | 83 | -1 | -2 |

### Bench Player vs Starter

Same player (age 19, current: 70, potential: 85):

| Role | Appearances | Development |
|------|-------------|-------------|
| Starter | 30 | +4 to +5 |
| Rotation | 12 | +2 to +3 |
| Bench | 3 | +2 |

Playing time matters significantly for development.

## Season End Processing

At season end, for each player:

1. Calculate age-based multipliers (separate for technical and physical)
2. Check playing time bonus eligibility (≥15 appearances)
3. Calculate quality gap bonus (continuous formula, age < 28 only)
4. Apply development changes (growth capped at potential, decline uncapped, abilities bounded 1-99)
5. Update market value (see [Market Value Dynamics](market-value-dynamics.md))
6. Reset season appearances

## Implementation

See `app/Modules/Squad/Services/PlayerDevelopmentService.php`:
- `calculateDevelopment()` - Main development calculation
- `calculateQualityGapBonus()` - Continuous gap-based development bonus
- `processSeasonEndDevelopment()` - Full season processing

See `app/Modules/Squad/Services/DevelopmentCurve.php`:
- `AGE_CURVES` - Age-based multiplier table
- `calculateChange()` - Applies base development × multiplier × bonuses
- `BASE_DEVELOPMENT` (2), `APPEARANCE_BONUS` (1.5), `MIN_APPEARANCES` (15)

## Design Rationale

### Why does physical decline faster than technical?
Real footballers maintain their technical skills longer than their physical attributes. A 35-year-old can still pass and finish, but won't sprint as fast. This creates realistic late-career profiles.

### Why a quality gap bonus?
Players with high potential but low current ability often develop rapidly when given opportunities. They're at top clubs with great coaching and high expectations. This rewards investing in wonderkids.

### Why does playing time matter?
Match experience is crucial for development. A young player learning on the pitch improves faster than one training but not playing. This creates meaningful squad management decisions.

### Why a continuous formula for quality gap?
Discrete tiers (e.g., 15 points = 1.38x, 14 points = 1.25x) create arbitrary cliff edges. The continuous formula `1.0 + gap/40` provides smooth, proportional acceleration.
