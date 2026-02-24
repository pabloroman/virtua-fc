# Market Value Dynamics

How player market values evolve over seasons.

## Overview

Market value is recalculated at the end of each season based on current ability, age, and performance trend. This creates a feedback loop: development affects value, and value influences transfer pricing, wage demands, and potential confidence.

## Calculation

```
market_value = base_value(ability) × age_multiplier × trend_multiplier
```

- **Base value**: Mapped from average ability (tech + phys / 2) via tiered brackets with randomness within each bracket.
- **Age multiplier**: Young players command a premium (up to ~1.8x), peak years are 1.0x, and veterans are heavily discounted (down to ~0.15x).
- **Trend multiplier**: Young players who improved significantly get a bonus (up to ~1.4x). Declining players of any age get penalized (down to ~0.7x). Only applied when previous ability is known.

Values are clamped between €100K and €200M.

See `abilityToMarketValue()` in `PlayerValuationService`.

## Feedback Loop

Market value changes compound over a career:

- **Young player improves** → Value increases → Confirms potential → More valuable transfer asset
- **Veteran declines** → Value decreases → Lower wage demands → Consider selling/replacing

## Key File

`app/Modules/Squad/Services/PlayerValuationService.php` — Both directions: market value to ability (seeding) and ability to market value (season-end recalculation).
