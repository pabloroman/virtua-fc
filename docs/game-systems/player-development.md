# Player Development

How players develop (or decline) over seasons.

## Overview

At the end of each season, players' technical and physical abilities change based on age, playing time, and distance from potential. Development is calculated separately for technical and physical — physical ability peaks earlier and declines faster than technical.

## Development Curve

Development uses per-age multipliers applied to a base development constant. Multipliers above 1.0 mean growth; below 1.0 mean decline. Technical and physical have separate curves. The multiplier table and base constants are in `DevelopmentCurve`.

## Bonuses

Two bonuses can accelerate growth (growing players only):

- **Playing time bonus**: Players with enough appearances in a season develop faster. The threshold and multiplier are constants in `DevelopmentCurve`.
- **Quality gap bonus**: Players far from their potential develop faster, using a continuous formula proportional to the gap (capped). Only applies under a certain age. See `calculateQualityGapBonus()` in `PlayerDevelopmentService`.

## Potential Cap

Players cannot exceed their potential through development. As they approach their ceiling, the quality gap bonus naturally diminishes.

## Market Value Update

After development changes are applied, market values are recalculated to reflect the new ability level. See [Market Value Dynamics](market-value-dynamics.md).

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Squad/Services/PlayerDevelopmentService.php` | Season-end development processing, quality gap bonus, potential generation |
| `app/Modules/Squad/Services/DevelopmentCurve.php` | Age multiplier table, base development constant, appearance bonus |
