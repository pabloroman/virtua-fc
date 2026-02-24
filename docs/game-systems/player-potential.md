# Player Potential

How player potential is generated and how it influences development.

## Overview

Each player has a hidden **potential** rating (1-99) representing their ability ceiling. Players see a **scouted range** (low-high estimate) rather than the exact value. Potential is generated at game start and influences development via the quality gap bonus (see [Player Development](player-development.md)).

## Potential Generation

Potential is calculated as current ability plus a bonus, varying by age bracket:

- **Young players (≤20)**: Large random range above current ability, plus a value-based bonus. High market value relative to age-typical value indicates proven potential and adds more.
- **Developing players (21-24)**: Moderate range, with a reduced value bonus.
- **Peak players (25-28)**: Small range, minimal value bonus.
- **Veterans (29+)**: Potential reflects proven ceiling. Bonus based on how exceptional their market value is for their age (separate typical-value table for veterans).

The value bonus compares actual market value against a typical value for the player's age, producing a ratio that maps to a bonus amount. See `generatePotential()`, `getValuePotentialBonus()`, and `getVeteranPotentialBonus()` in `PlayerDevelopmentService`.

## Scouted Range

Players don't show exact potential. Scouts provide an estimated range with age-dependent uncertainty:

```
Low  = max(current_ability, potential - uncertainty)
High = min(99, potential + uncertainty)
```

Younger players have wider uncertainty. Higher scouting tiers reduce ability fuzz in scout reports, making estimates more precise.

## How Potential Affects Gameplay

- **Growth cap**: Players cannot exceed their potential through development.
- **Quality gap bonus**: Players far from their potential develop faster (see [Player Development](player-development.md)).
- **Squad decisions**: Potential range is a key factor when deciding whether to invest in young players, promote from academy, or sell.

## Key File

`app/Modules/Squad/Services/PlayerDevelopmentService.php` — Potential generation, value bonus calculation, and the interaction with development.
