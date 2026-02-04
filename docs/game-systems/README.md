# VirtuaFC Game Systems

This documentation describes the core game systems that power VirtuaFC's football simulation. The systems are designed to create realistic player careers, match outcomes, and long-term gameplay.

## Core Philosophy

VirtuaFC aims to simulate football management with these guiding principles:

1. **Market Value as Truth** - A player's market value reflects real-world consensus on their quality and potential
2. **Age-Adjusted Reality** - Young players are valued for potential, veterans for proven quality
3. **Coherent Careers** - Players develop, peak, and decline in realistic trajectories
4. **Strength Matters** - Better teams should consistently outperform weaker ones over a season
5. **Meaningful Decisions** - Playing time, tactics, and squad building should impact results

## System Documentation

| Document | Description |
|----------|-------------|
| [Player Abilities](player-abilities.md) | How technical and physical abilities are calculated from market value |
| [Player Potential](player-potential.md) | How potential is generated and influences development |
| [Player Development](player-development.md) | How players grow and decline over seasons |
| [Market Value Dynamics](market-value-dynamics.md) | How market value evolves with player performance |
| [Match Simulation](match-simulation.md) | How match results are determined |
| [Injury System](injury-system.md) | Injury probability, durability, and recovery times |
| [Club Economy System](club-economy-system.md) | Contracts, wages, finances overview (implementation roadmap) |
| [Transfer Market](transfer-market.md) | Selling players, contract renewals, pre-contract offers, free transfers |

## The Unified Loop

All systems work together in a coherent feedback loop:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PLAYER LIFECYCLE                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  GAME START                                                                  │
│  ───────────                                                                 │
│  Market Value ──► Calculate Abilities (with age adjustment)                  │
│                         │                                                    │
│                         ▼                                                    │
│               Calculate Potential (with market value influence)              │
│                                                                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  EACH SEASON                                                                 │
│  ───────────                                                                 │
│        ┌──────────────────────────────────────────────────────────┐         │
│        │                                                          │         │
│        ▼                                                          │         │
│  Age + Playing Time + Quality Gap ──► Development Changes         │         │
│        │                                                          │         │
│        ▼                                                          │         │
│  New Abilities ──► Calculate New Market Value ───────────────────►│         │
│        │                 (with age + trend multipliers)           │         │
│        │                                                          │         │
│        ▼                                                          │         │
│  (Market value feeds back into potential confidence)              │         │
│        │                                                          │         │
│        └──────────────────────────────────────────────────────────┘         │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Key Files

| File | Purpose |
|------|---------|
| `app/Console/Commands/SeedReferenceData.php` | Initial ability calculation from market value |
| `app/Game/Services/PlayerDevelopmentService.php` | Development, potential, and market value updates |
| `app/Game/Services/DevelopmentCurve.php` | Age-based development multipliers |
| `app/Game/Services/MatchSimulator.php` | Match result simulation |
| `app/Game/Services/InjuryService.php` | Injury probability and generation |
| `app/Game/Services/ContractService.php` | Wage calculation with age modifiers |
| `config/match_simulation.php` | Tunable match simulation parameters |

## Design Decisions

### Why Market Value?

We use market value as the primary input because:
- It's publicly available data (from Transfermarkt)
- It reflects consensus on player quality
- It already accounts for age, potential, and current form
- It creates a natural connection between in-game value and ability

### Why Age Adjustments?

Market value means different things at different ages:
- A €120M 17-year-old is valued for **potential** (future ability)
- A €120M 27-year-old is valued for **current ability** (peak performance)
- A €15M 36-year-old is valued despite age decline (proven quality)

### Why Potential Matters?

Potential creates meaningful long-term decisions:
- Young players with high potential are worth investing in
- Veterans may be better now but have limited upside
- Development systems reward patience and good squad building
