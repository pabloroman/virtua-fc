# Injury System

The injury system simulates realistic football injuries with varying severity, from minor muscle fatigue to season-ending ACL tears.

## Overview

Every time a player participates in a match, they have a chance of getting injured. The probability depends on multiple factors:

- **Durability** - A hidden attribute (1-100) representing injury proneness
- **Fitness** - Tired players are more likely to get injured
- **Age** - Young and veteran players are more susceptible
- **Match Congestion** - Back-to-back games increase risk

## Durability Attribute

Each player has a hidden durability value (1-100) that represents their natural resistance to injuries. This is generated with a bell-curve distribution when a game is created:

| Range | Label | Injury Multiplier | Distribution |
|-------|-------|-------------------|--------------|
| 1-20 | Very Injury Prone | 2.0x | ~2% |
| 21-40 | Injury Prone | 1.5x | ~20% |
| 41-60 | Average | 1.0x | ~50% |
| 61-80 | Resilient | 0.7x | ~26% |
| 81-100 | Ironman | 0.4x | ~2% |

Most players are average, but some are naturally more fragile or resilient. An "Ironman" player can play every match with minimal injury risk, while a "Very Injury Prone" player might struggle to stay fit for a full season.

## Probability Calculation

Base injury chance per player per match: **4%**

This is modified by multipliers:

```
Final Probability = Base × Durability × Age × Fitness × Congestion
```

### Age Multipliers

| Age | Multiplier | Reason |
|-----|------------|--------|
| ≤20 | 1.3x | Still developing physically |
| 21-29 | 1.0x | Prime years |
| 30-32 | 1.2x | Early decline |
| 33+ | 1.5x | Veteran wear |

### Fitness Multipliers

| Fitness | Multiplier | State |
|---------|------------|-------|
| ≤30 | 2.5x | Exhausted |
| 31-50 | 2.0x | Very tired |
| 51-70 | 1.5x | Tired |
| 71-85 | 1.0x | Normal |
| 86-100 | 0.8x | Fresh |

### Congestion Multipliers

| Days Since Last Match | Multiplier |
|-----------------------|------------|
| ≤2 days | 2.0x |
| 3 days | 1.5x |
| 4 days | 1.2x |
| 5+ days | 1.0x |

### Example Calculations

**Low risk player:**
- Ironman (0.4x) + Prime age (1.0x) + Fresh (0.8x) + Normal rest (1.0x)
- 4% × 0.4 × 1.0 × 0.8 × 1.0 = **1.3%** chance

**High risk player:**
- Very Injury Prone (2.0x) + Veteran (1.5x) + Tired (1.5x) + Back-to-back (2.0x)
- 4% × 2.0 × 1.5 × 1.5 × 2.0 = **36%** chance (capped at 35%)

The maximum probability is capped at **35%** to prevent certainty.

## Injury Types

Injuries are selected with weighted randomness - minor injuries are common, severe injuries are rare.

| Injury | Duration | Weight | Probability |
|--------|----------|--------|-------------|
| **Minor** | | | |
| Muscle fatigue | 1 week | 30 | 23.4% |
| Muscle strain | 1-2 weeks | 25 | 19.5% |
| **Medium** | | | |
| Calf strain | 2-3 weeks | 18 | 14.1% |
| Ankle sprain | 2-4 weeks | 16 | 12.5% |
| Groin strain | 2-4 weeks | 14 | 10.9% |
| **Serious** | | | |
| Hamstring tear | 3-6 weeks | 10 | 7.8% |
| Knee contusion | 3-5 weeks | 8 | 6.3% |
| **Long-term** | | | |
| Metatarsal fracture | 8-12 weeks | 4 | 3.1% |
| **Severe** | | | |
| ACL tear | 24-36 weeks | 2 | 1.6% |
| Achilles rupture | 20-28 weeks | 1 | 0.8% |

### Position Affinities

Certain injuries are more common for specific positions:

- **Forwards/Midfielders**: Hamstring tears, calf strains (explosive movements)
- **Defenders**: Knee injuries, ankle sprains (tackling)
- **Goalkeepers**: Knee contusions (diving)

When an injury type has position affinity, its weight is doubled for players in those positions.

## Recovery Time Modifiers

Older players take longer to recover:

| Age | Recovery Modifier |
|-----|-------------------|
| <30 | 1.0x (normal) |
| 30-31 | 1.1x |
| 32+ | 1.2x |

A 35-year-old with a 4-week hamstring tear would actually be out for ~5 weeks.

## Strategic Implications

### Squad Depth
Season-ending injuries (ACL, Achilles) are rare but devastating. Having quality backup players is essential insurance.

### Rotation
Playing the same players every match increases injury risk through:
- Accumulated fitness drain
- Match congestion multipliers

Rotating players, especially in less important matches, preserves your best players for crucial games.

### Age Management
Veteran players (33+) face compounded risk:
- Higher base injury probability
- Longer recovery times
- More severe fitness impacts

Consider this when signing or extending older players.

### Transfer Scouting
When evaluating players, durability is hidden but impactful. A player with low durability might be a liability despite high ability ratings.

## Key Files

| File | Purpose |
|------|---------|
| `app/Game/Services/InjuryService.php` | Core injury probability and generation logic |
| `app/Game/Services/MatchSimulator.php` | Integrates injury checks into match simulation |
| `database/migrations/*_add_durability_to_game_players.php` | Durability column |
| `app/Console/Commands/BackfillPlayerDurability.php` | Utility to regenerate durability values |
