# Player Abilities

How player abilities are calculated from real-world market value data at game start.

## Overview

Players have two core attributes — **Technical Ability** and **Physical Ability** (both 30-99). The **Overall Score** is their average. These are derived from market value with age-based adjustments.

## Calculation Pipeline

### 1. Market Value → Raw Ability

Market value is mapped to an ability range via tiered brackets (e.g., €100M+ maps to 88-95). See `marketValueToRawAbility()` in `PlayerValuationService`.

### 2. Age Adjustment

Raw ability is adjusted because market value means different things at different ages:

- **Young players (under 23)**: Ability is capped. Their market value includes a "potential premium" — a €120M 17-year-old isn't yet a 91-rated player. The cap is age-based with bonuses for exceptionally high value.
- **Prime players (23-30)**: No adjustment. Market value directly reflects current ability.
- **Veterans (31+)**: Ability is boosted. A €15M 36-year-old is exceptional for their age — their low market value is due to age, not skill. The boost is proportional to how much their value exceeds the typical value for their age.

See `adjustAbilityForAge()`.

### 3. Position-Based Split

The base ability is split into technical and physical based on position. Attackers and playmakers skew technical; defenders skew physical. A small random variance creates individual variation. Ratios per position are defined in `PlayerValuationService`.

### 4. Veteran Physical Decline

Veterans receive an additional physical ability reduction (age 31+ gets a smaller multiplier, 34+ a larger one), reflecting that physical attributes fade faster than technical ones.

## Key File

`app/Modules/Squad/Services/PlayerValuationService.php` — All ability calculation logic: value-to-ability tiers, age adjustments, position splits, and the reverse conversion (`abilityToMarketValue()`).
