# Injury System

How injuries occur, their severity, and recovery.

## Overview

Injuries happen during matches and in training. Match injuries can be severe (ACL tears); training injuries are limited to minor/medium severity. Each source can produce at most one injury per matchday.

## Probability

Injury chance per player is calculated from multiple multiplicative factors:

```
probability = base_chance × durability × age × fitness × congestion × medical_tier
```

- **Durability**: A hidden per-player attribute (1-100, bell-curve distribution) representing natural injury proneness. Lower durability = higher multiplier. Generated at game creation via `InjuryService`.
- **Age**: Prime years are baseline; young and veteran players have higher multipliers.
- **Fitness**: Tired players are more injury-prone; fresh players are less.
- **Congestion** (match only): Fewer days since last match = higher risk.
- **Medical tier**: Higher investment reduces chance (up to ~45% reduction at Tier 4).

Base chances, all multiplier values, and probability caps are in `InjuryService`.

## Injury Types & Recovery

Injuries are selected by weighted randomness — minor injuries (muscle fatigue, strains) are common, severe injuries (ACL, Achilles) are rare. Training injuries draw from a lighter distribution that excludes severe types.

Recovery time is modified by age (older = slower) and medical tier (higher = faster). Minimum recovery is 1 week.

Injury types, weights, durations, and position affinities are defined in `InjuryService`.

## Strategic Impact

- **Squad depth** insures against rare but devastating long-term injuries
- **Rotation** reduces injury risk through better fitness and less congestion
- **Medical investment** compounds: fewer injuries AND faster recovery
- **Durability is hidden** — a fragile player might be a liability despite high ability

## Key File

`app/Modules/Squad/Services/InjuryService.php` — All injury logic: probability calculation, durability generation, injury type selection, recovery modifiers, medical tier effects.
