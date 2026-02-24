# Market Value Dynamics

This document describes how player market values evolve over seasons based on ability changes and age.

## Overview

Market value is recalculated at the end of each season based on:
- **Current Ability** - Primary factor (average of technical and physical)
- **Age** - Young players command premium, veterans discounted
- **Performance Trend** - Improving young players gain value, declining players lose it

This creates a feedback loop where development affects value, and value influences potential confidence.

## Value Calculation

### Step 1: Base Value from Ability

The average of technical and physical ability determines the base market value:

| Average Ability | Base Value Range |
|-----------------|------------------|
| 88+ | €60-150M |
| 83-87 | €35-70M |
| 78-82 | €20-40M |
| 73-77 | €10-25M |
| 68-72 | €5-12M |
| 63-67 | €2-6M |
| 58-62 | €1-3M |
| 50-57 | €500K-1.5M |
| 45-49 | €200-600K |
| Below 45 | €100-300K |

### Step 2: Age Multiplier

Age significantly impacts value:

| Age | Multiplier | Description |
|-----|------------|-------------|
| ≤19 | 1.8x | Young star premium |
| 20-21 | 1.5x | Developing talent |
| 22-23 | 1.3x | Emerging player |
| 24-26 | 1.1x | Approaching peak |
| 27-28 | 1.0x | Peak years |
| 29-30 | 0.85x | Post-peak |
| 31-32 | 0.65x | Declining |
| 33-34 | 0.45x | Late career |
| 35-36 | 0.30x | Twilight |
| 37+ | 0.15x | End of career |

**Example**: Two players rated 88
- Age 21: €80M × 1.5 = **€120M**
- Age 34: €80M × 0.45 = **€36M**

### Step 3: Performance Trend Multiplier

How the player changed this season affects value momentum. This is only applied when a previous ability is known (i.e., not the first season).

#### Young Players (≤24) Improving

| Improvement | Multiplier |
|-------------|------------|
| +5 or more | 1.4x (hot prospect) |
| +3 to +4 | 1.25x |
| +1 to +2 | 1.1x |
| No change | 1.0x |

#### All Players Declining

| Decline | Multiplier |
|---------|------------|
| -4 or worse | 0.7x (concerning) |
| -2 to -3 | 0.85x |
| -1 | 0.95x |

## Career Value Trajectories

### Rising Star: Lamine Yamal

| Season | Age | Ability | Change | Base | Age× | Trend× | Final |
|--------|-----|---------|--------|------|------|--------|-------|
| Start | 17 | 81 | — | — | — | — | €120M |
| 1 | 18 | 85 | +4 | €47M | 1.8 | 1.25 | €106M |
| 2 | 19 | 89 | +4 | €80M | 1.8 | 1.25 | €180M |
| 3 | 20 | 92 | +3 | €100M | 1.5 | 1.25 | €200M* |
| 4 | 21 | 94 | +2 | €100M | 1.5 | 1.1 | €165M |

*Capped at €200M

### Stable Prime: World Class Player

| Season | Age | Ability | Change | Base | Age× | Trend× | Final |
|--------|-----|---------|--------|------|------|--------|-------|
| Start | 27 | 91 | — | — | — | — | €150M |
| 1 | 28 | 91 | 0 | €100M | 1.0 | 1.0 | €100M |
| 2 | 29 | 90 | -1 | €100M | 0.85 | 0.95 | €81M |
| 3 | 30 | 89 | -1 | €80M | 0.85 | 0.95 | €65M |

### Veteran Decline: Lewandowski

| Season | Age | Ability | Change | Base | Age× | Trend× | Final |
|--------|-----|---------|--------|------|------|--------|-------|
| Start | 36 | 88 | — | — | — | — | €15M |
| 1 | 37 | 87 | -1 | €60M | 0.15 | 0.95 | €9M |
| 2 | 38 | 85 | -2 | €47M | 0.15 | 0.85 | €6M |
| 3 | 39 | 83 | -2 | €35M | 0.15 | 0.85 | €4.5M |

## Feedback Loop

Market value changes create a coherent feedback loop:

```
Young Player Improves
        ↓
Market Value Increases
        ↓
Higher Value = Confirmed Potential
        ↓
More Valuable Asset (better transfer return)
```

```
Veteran Declines
        ↓
Market Value Decreases
        ↓
Lower Value = Reduced Future
        ↓
Consider Selling/Replacing
```

## Value Boundaries

- **Minimum**: €100K (no player worth less)
- **Maximum**: €200M (realistic ceiling)

## Implementation

See `app/Modules/Squad/Services/PlayerValuationService.php`:
- `abilityToMarketValue()` - Full calculation with base value, age multiplier, and trend
- `marketValueToRawAbility()` - Reverse conversion (for seeding)

See `app/Modules/Squad/Services/PlayerDevelopmentService.php`:
- `processSeasonEndDevelopment()` - Triggers market value recalculation after development changes

## Design Rationale

### Why update market value?
Without updates, a player who improves from 75 to 90 over five seasons would still be valued at their original price. This breaks immersion and makes transfers unrealistic.

### Why such large age multipliers?
Real football shows massive value differences by age. Bellingham (21) is worth more than Modric (38) despite similar current ability. The age multiplier reflects contract length, resale value, and career potential.

### Why a trend multiplier?
Players on an upward trajectory are more valuable — they might improve further. Players declining are riskier investments. This creates realistic market dynamics.

### Why cap at €200M?
Only a handful of players have ever been valued above €150M. The cap prevents unrealistic inflation and keeps values grounded in reality.
