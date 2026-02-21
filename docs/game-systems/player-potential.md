# Player Potential

This document describes how player potential is calculated and how it influences development.

## Overview

Each player has a hidden **potential** rating (1-99) representing the maximum ability they can reach. Players also have visible **potential range** (low-high) that scouts can estimate.

Potential is influenced by:
- Current ability (floor)
- Age (younger = more room to grow)
- Market value (proven quality indicator)

## Potential Generation

### Base Potential Range by Age

| Age | Base Range Above Current | Uncertainty |
|-----|--------------------------|-------------|
| ≤20 | 8-20 points | ±5-10 |
| 21-24 | 4-12 points | ±4-7 |
| 25-28 | 0-5 points | ±2-4 |
| 29+ | Based on proven quality | ±2 |

### Market Value Bonus

High market value for a player's age indicates **proven potential**. A €120M 17-year-old has demonstrated they belong at the elite level.

#### Young Players (Under 29)

Compare actual value to typical value for age:

| Age | Typical Value |
|-----|---------------|
| ≤17 | €500K |
| 18-19 | €2M |
| 20-21 | €5M |
| 22-23 | €10M |
| 24-25 | €15M |
| 26-28 | €20M |

Calculate value ratio and apply bonus:

| Value Ratio | Potential Bonus |
|-------------|-----------------|
| 100x+ typical | +10 |
| 50x+ typical | +8 |
| 20x+ typical | +6 |
| 10x+ typical | +4 |
| 5x+ typical | +2 |

**Example**: Lamine Yamal (17, €120M, current: 81)
- Typical for 17: €500K
- Ratio: €120M / €500K = 240x
- Bonus: +10
- Base range: 8-20 (avg 14)
- Total range: 14 + 10 = 24
- Potential: 81 + 24 = **99** (capped)

#### Veterans (29+)

Veterans with exceptional market value have **proven their ceiling**:

| Value Ratio (vs age typical) | Potential Above Current |
|------------------------------|-------------------------|
| 10x+ typical | +8 |
| 5-10x typical | +5 |
| 3-5x typical | +3 |
| 2-3x typical | +1 |
| Below 2x | +0 |

**Example**: Lewandowski (36, €15M, current: 88)
- Typical for 36: €1.5M
- Ratio: 10x
- Bonus: +8
- Potential: 88 + 8 = **96**

This means Lewandowski has proven he can perform at 96 level (his historical peak).

## Scouted Potential Range

Players don't show exact potential. Instead, scouts provide an estimated range:

```
Low = max(current_ability, potential - uncertainty)
High = min(99, potential + uncertainty)
```

**Example**: Young prospect (current: 72, potential: 85, uncertainty: 7)
- Scouted range: 78-92
- True potential (hidden): 85

## How Potential Affects Development

### Growth Cap

Players cannot exceed their potential through development:

```php
if ($techChange > 0) {
    $newTech = min($newTech, $potential);
}
```

### Quality Gap Bonus

Players far from their potential develop faster (see [Player Development](player-development.md)):

| Gap to Potential | Development Bonus |
|------------------|-------------------|
| 20+ points | +50% |
| 15 points | +38% |
| 10 points | +25% |
| 5 points | +12% |
| <5 points | None |

## Example Potentials

| Player | Age | Value | Current | Potential | Range |
|--------|-----|-------|---------|-----------|-------|
| Lamine Yamal | 17 | €120M | 81 | 99 | 89-99 |
| Pau Cubarsi | 17 | €30M | 76 | 98 | 88-99 |
| Random 17yo | 17 | €500K | 55 | 69 | 59-79 |
| Gavi | 20 | €90M | 84 | 99 | 89-99 |
| Pedri | 21 | €80M | 86 | 96 | 89-99 |
| Bellingham | 21 | €150M | 88 | 99 | 93-99 |
| Mbappé | 25 | €180M | 91 | 94 | 90-98 |
| Lewandowski | 36 | €15M | 86 | 94 | 92-96 |
| Modric | 38 | €5M | 77 | 82 | 80-84 |

## Potential Recalculation

Potential can be recalculated when:
- Market value changes significantly
- Player exceeds expected development
- At season end based on performance

This allows the game to adjust estimates based on how players actually perform.

## Implementation

See `app/Modules/Squad/Services/PlayerDevelopmentService.php`:
- `generatePotential()` - Initial potential calculation
- `getValuePotentialBonus()` - Young player value bonus
- `getVeteranPotentialBonus()` - Veteran proven quality bonus
- `recalculatePotential()` - Update potential based on changes

## Design Rationale

### Why does market value influence potential?
A €120M 17-year-old has proven themselves at the highest level. Their value reflects the football world's consensus that they have elite potential. Without this connection, a cheap wonderkid and an expensive one would have similar potential ranges.

### Why do veterans have potential above current?
A €15M 36-year-old like Lewandowski has proven they can perform at 90+ level. Their "potential" represents their historical ceiling - what they've already demonstrated they can achieve. This affects how we view their career trajectory.

### Why uncertainty in scouting?
Real football scouting involves uncertainty. A scout might see a young player and estimate "could be 85-95". The game reflects this by showing a range rather than exact potential. This creates meaningful decision-making around which young players to invest in.
