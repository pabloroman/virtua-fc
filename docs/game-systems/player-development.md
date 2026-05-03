# Player Development

How players develop (or decline) over seasons.

## Overview

At the end of each season, a player's `overall_score` changes based on age, playing time, and distance from potential. The development curve is single-axis: a single signed change per age band rather than separate technical and physical trajectories.

## Development Curve

Development uses per-age signed change values. Positive values mean growth, zero means plateau, negative means decline. The age curve is in `DevelopmentCurve::AGE_CURVES`.

## Bonuses

Two bonuses can accelerate growth (growing players only):

- **Playing time bonus**: Players with enough appearances in a season develop faster. Below the floor (`MIN_APPEARANCES_FOR_GROWTH`) they still develop in training, but at a halved rate (`TRAINING_ONLY_GROWTH_FACTOR`).
- **Quality gap bonus**: Young players far from their potential get a flat +1 bonus on top of curve growth. See `calculateQualityGapBonus()` in `PlayerDevelopmentService`.

## Potential Cap

Players cannot exceed their potential through development.

## Market Value Update

After development changes are applied, market values are recalculated to reflect the new ability level. See [Market Value Dynamics](market-value-dynamics.md).

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Player/Services/PlayerDevelopmentService.php` | Season-end development processing, quality gap bonus, potential generation |
| `app/Modules/Player/Services/DevelopmentCurve.php` | Age curve table, appearance scaling |
| `app/Modules/Season/Processors/PlayerDevelopmentProcessor.php` | One-shot SQL pipeline that applies the curve plus a discretized market-value/tier recompute to every game player at season end |
