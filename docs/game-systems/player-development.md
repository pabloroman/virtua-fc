# Player Development

This document describes how players develop (or decline) over seasons.

## Overview

At the end of each season, players' abilities change based on:
- **Age** - Young players grow, veterans decline
- **Playing Time** - Starters develop faster
- **Quality Gap** - Players far from potential develop faster
- **Potential Cap** - Players cannot exceed their ceiling

## Development Curve

### Age Multipliers

Each age has development multipliers for technical and physical abilities:

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
| 25 | 1.0x | 0.95x | Peak |
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

For **growing** players (multiplier > 1.0):
```
change = BASE_DEVELOPMENT × multiplier × bonuses
```

For **declining** players (multiplier < 1.0):
```
change = -(1.0 - multiplier) × BASE_DEVELOPMENT
```

**Example decline at age 32** (tech: 0.6, phys: 0.4):
- Technical: -(1.0 - 0.6) × 2 = -0.8 ≈ -1
- Physical: -(1.0 - 0.4) × 2 = -1.2 ≈ -1

## Playing Time Bonus

Players who play regularly develop faster:

```
MIN_APPEARANCES_FOR_BONUS = 15 games
APPEARANCE_BONUS = 1.5x (50% faster development)
```

A young player with 20+ appearances develops 50% faster than a bench player.

## Quality Gap Bonus

Players far from their potential develop faster - they have more room to grow:

| Gap to Potential | Development Bonus |
|------------------|-------------------|
| 20+ points | 1.5x (+50%) |
| 15 points | 1.38x (+38%) |
| 10 points | 1.25x (+25%) |
| 5 points | 1.12x (+12%) |
| <5 points | 1.0x (none) |

**Only applies to growing players (age < 28).**

### Example: Wonderkid Development

Player: 17 years old, current: 72, potential: 92 (gap: 20)

```
Base development: 2
Age multiplier (17): 1.6x (technical)
Playing time bonus: 1.5x (15+ appearances)
Quality gap bonus: 1.5x (20 point gap)

Technical change = 2 × 1.6 × 1.5 × 1.5 = 7.2 ≈ +7
```

With all bonuses, a well-played wonderkid can gain 7+ points in a season!

## Potential Cap

Players cannot exceed their potential through development:

```php
if ($techChange > 0) {
    $newTech = min($newTech, $potential);
}
```

As players approach their ceiling, growth naturally slows.

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

| Season | Age | Current | Change | New |
|--------|-----|---------|--------|-----|
| Start | 36 | 88 | — | 88 |
| 1 | 37 | 88 | -1 | 87 |
| 2 | 38 | 87 | -1 | 86 |
| 3 | 39 | 86 | -1 | 85 |

### Bench Player vs Starter

Same player (age 19, current: 70, potential: 85):

| Role | Appearances | Development |
|------|-------------|-------------|
| Starter | 30 | +4 to +5 |
| Rotation | 12 | +2 to +3 |
| Bench | 3 | +2 |

Playing time matters significantly for development!

## Season End Processing

At season end, for each player:

1. Calculate age-based multipliers
2. Check playing time bonus eligibility
3. Calculate quality gap bonus
4. Apply development changes
5. Cap at potential
6. Update market value (see [Market Value Dynamics](market-value-dynamics.md))
7. Reset season appearances

## Implementation

See `app/Game/Services/PlayerDevelopmentService.php`:
- `calculateDevelopment()` - Main development calculation
- `calculateQualityGapBonus()` - Gap-based development bonus
- `processSeasonEndDevelopment()` - Full season processing

See `app/Game/Services/DevelopmentCurve.php`:
- `AGE_CURVES` - Age multiplier constants
- `calculateChange()` - Growth/decline calculation
- `qualifiesForBonus()` - Playing time check

## Design Rationale

### Why does physical decline faster than technical?
Real footballers maintain their technical skills longer than their physical attributes. A 35-year-old can still pass and finish, but won't sprint as fast. This creates realistic late-career profiles.

### Why a quality gap bonus?
Players with high potential but low current ability often develop rapidly when given opportunities. They're at top clubs with great coaching and high expectations. This rewards investing in wonderkids.

### Why does playing time matter?
Match experience is crucial for development. A young player learning on the pitch improves faster than one training but not playing. This creates meaningful squad management decisions.
