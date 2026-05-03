# Player Abilities

How a player's overall ability is calculated from real-world market value data at game start.

## Overview

Players have a single core attribute — **Overall Score** (30-99). This is derived from market value with age-based adjustments. Match performance is then modulated at simulation time by transient state (fitness, morale, daily form variance), but the stored `overall_score` is a stable baseline.

## Calculation Pipeline

### 1. Market Value → Raw Ability

Market value is mapped to an ability range via tiered brackets (e.g., €100M+ maps to 88-95). See `marketValueToRawAbility()` in `PlayerValuationService`.

### 2. Age Adjustment

Raw ability is adjusted because market value means different things at different ages:

- **Young players (under 23)**: Ability is capped. Their market value includes a "potential premium" — a €120M 17-year-old isn't yet a 91-rated player. The cap is age-based with bonuses for exceptionally high value.
- **Prime players (23-30)**: No adjustment. Market value directly reflects current ability.
- **Veterans (31+)**: Ability is boosted. A €15M 36-year-old is exceptional for their age — their low market value is due to age, not skill. The boost is proportional to how much their value exceeds the typical value for their age.

See `adjustAbilityForAge()`.

## Key File

`app/Modules/Player/Services/PlayerValuationService.php` — All ability calculation logic: value-to-ability tiers, age adjustments, and the reverse conversion (`overallScoreToMarketValue()`).
