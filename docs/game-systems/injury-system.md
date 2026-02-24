# Injury System

The injury system simulates realistic football injuries with varying severity, from minor muscle fatigue to season-ending ACL tears.

## Overview

Injuries happen both during matches and in training. The probability depends on multiple factors:

- **Durability** - A hidden attribute (1-100) representing injury proneness
- **Fitness** - Tired players are more likely to get injured
- **Age** - Young and veteran players are more susceptible
- **Match Congestion** - Back-to-back games increase risk (match injuries only)
- **Medical Investment** - Higher medical tier reduces injury chance and recovery time

### Match vs Training Injuries

| Source | Who | Base Chance | Severe Injuries? |
|--------|-----|-------------|------------------|
| Match | Players in the lineup (11) | 2% per player | Yes (ACL, Achilles, etc.) |
| Training | Non-playing squad members | 1.5% per player per matchday | No (minor/medium only) |

## Durability Attribute

Each player has a hidden durability value (1-100) that represents their natural resistance to injuries. Generated with a **bell-curve distribution** using 4 random dice (each 1-25, sum clamped to 1-100) when a game is created:

| Range | Label | Injury Multiplier |
|-------|-------|-------------------|
| 1-20 | Very Injury Prone | 2.0x |
| 21-40 | Injury Prone | 1.5x |
| 41-60 | Average | 1.0x |
| 61-80 | Resilient | 0.7x |
| 81-100 | Ironman | 0.4x |

Most players cluster around 50 (average), with extreme values being rare. An "Ironman" player can play every match with minimal injury risk, while a "Very Injury Prone" player might struggle to stay fit for a full season.

## Probability Calculation

Base injury chance per player per match: **2%** (training: **1.5%**)

This is modified by multipliers:

```
Final Probability = Base × Durability × Age × Fitness × Congestion × Medical
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

### Congestion Multipliers (match injuries only)

| Days Since Last Match | Multiplier |
|-----------------------|------------|
| ≤2 days | 2.0x |
| 3 days | 1.5x |
| 4 days | 1.2x |
| 5+ days | 1.0x |

### Medical Tier Multipliers (Injury Prevention)

| Tier | Multiplier | Effect |
|------|-----------|--------|
| 0 | 1.3x | +30% more injuries |
| 1 | 1.0x | Baseline |
| 2 | 0.85x | −15% fewer injuries |
| 3 | 0.70x | −30% fewer injuries |
| 4 | 0.55x | −45% fewer injuries |

### Example Calculations

**Low risk player (match):**
- Ironman (0.4x) + Prime age (1.0x) + Fresh (0.8x) + Normal rest (1.0x) + Medical Tier 4 (0.55x)
- 2% × 0.4 × 1.0 × 0.8 × 1.0 × 0.55 = **0.35%** chance

**High risk player (match):**
- Very Injury Prone (2.0x) + Veteran (1.5x) + Tired (1.5x) + Back-to-back (2.0x) + Medical Tier 1 (1.0x)
- 2% × 2.0 × 1.5 × 1.5 × 2.0 × 1.0 = **18%** chance

Match injury probability is capped at **35%**, training at **25%**.

## Injury Types

Injuries are selected with weighted randomness — minor injuries are common, severe injuries are rare.

### Match Injuries

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

### Training Injuries

Training injuries are lighter — only minor to medium severity. Severe injuries (metatarsal, ACL, Achilles) **cannot** happen in training.

| Injury | Weight |
|--------|--------|
| Muscle fatigue | 40 |
| Muscle strain | 30 |
| Calf strain | 15 |
| Ankle sprain | 8 |
| Groin strain | 5 |
| Hamstring tear | 2 |

### Position Affinities

Certain injuries are more common for specific positions:

| Position Group | Higher Risk |
|----------------|-------------|
| **Forwards (FW)** | Hamstring tears, calf strains (explosive movements) |
| **Midfielders (MF)** | Muscle fatigue, muscle strains (high running volume) |
| **Defenders (DF)** | Knee injuries, ankle sprains (tackling) |
| **Goalkeepers (GK)** | Knee contusions (diving) |

When an injury type has position affinity, its weight is doubled for players in those positions.

## Recovery Time Modifiers

### Age Modifier

Older players take longer to recover:

| Age | Recovery Modifier |
|-----|-------------------|
| <30 | 1.0x (normal) |
| 30-31 | 1.1x (+10% longer) |
| 32+ | 1.2x (+20% longer) |

### Medical Tier Modifier (Recovery Speed)

| Tier | Recovery Multiplier | Effect |
|------|--------------------|---------|
| 0 | 1.2x | 20% slower recovery |
| 1 | 1.0x | Baseline |
| 2 | 0.9x | 10% faster recovery |
| 3 | 0.8x | 20% faster recovery |
| 4 | 0.7x | 30% faster recovery |

Both modifiers are multiplicative. Minimum recovery is always **1 week**.

**Example:** A 33-year-old with a 4-week hamstring tear at Medical Tier 3:
- 4 weeks × 1.2 (age) × 0.8 (medical) = 3.84 ≈ **4 weeks**
- Same injury at Tier 1: 4 × 1.2 × 1.0 = 4.8 ≈ **5 weeks**

## Strategic Implications

### Squad Depth
Season-ending injuries (ACL, Achilles) are rare but devastating. Having quality backup players is essential insurance.

### Rotation
Playing the same players every match increases injury risk through:
- Accumulated fitness drain
- Match congestion multipliers

Rotating players, especially in less important matches, preserves your best players for crucial games.

### Medical Investment
Medical investment has compounding effects — fewer injuries AND faster recovery means:
- More available players per matchday
- Less disruption to starting XI continuity
- Better fitness levels across the squad

Crucial for small squads — good medical lets you compete with fewer players.

### Age Management
Veteran players (33+) face compounded risk:
- Higher base injury probability (1.5x)
- Longer recovery times (1.2x)
- Lower durability doesn't improve with age

Consider this when signing or extending older players.

### Transfer Scouting
When evaluating players, durability is hidden but impactful. A player with low durability might be a liability despite high ability ratings.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Squad/Services/InjuryService.php` | Core injury probability, generation, durability, and recovery logic |
| `app/Modules/Match/Services/MatchSimulator.php` | Integrates injury checks into match simulation |
| `config/match_simulation.php` | Base injury chance configuration |
