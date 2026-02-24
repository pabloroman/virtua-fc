# VirtuaFC Game Systems

This documentation describes the core game systems that power VirtuaFC's football simulation. The systems are designed to create realistic player careers, match outcomes, and long-term gameplay.

## Core Philosophy

VirtuaFC aims to simulate football management with these guiding principles:

1. **Market Value as Truth** - A player's market value reflects real-world consensus on their quality and potential
2. **Age-Adjusted Reality** - Young players are valued for potential, veterans for proven quality
3. **Coherent Careers** - Players develop, peak, and decline in realistic trajectories
4. **Strength Matters** - Better teams should consistently outperform weaker ones over a season
5. **Meaningful Decisions** - Playing time, tactics, investment, and squad building should impact results

## System Documentation

### Player Lifecycle

| Document | Description |
|----------|-------------|
| [Player Abilities](player-abilities.md) | How technical and physical abilities are calculated from market value |
| [Player Potential](player-potential.md) | How potential is generated and influences development |
| [Player Development](player-development.md) | How players grow and decline over seasons |
| [Market Value Dynamics](market-value-dynamics.md) | How market value evolves with player performance |

### Match & Competition

| Document | Description |
|----------|-------------|
| [Match Simulation](match-simulation.md) | xG formula, energy system, formations, mentality, events, penalties |
| [Injury System](injury-system.md) | Injury probability, durability, medical tiers, and recovery times |
| [Season Lifecycle](season-lifecycle.md) | Season progression, matchday flow, and end-of-season pipeline (21 processors) |

### Club Management

| Document | Description |
|----------|-------------|
| [Club Economy System](club-economy-system.md) | Budget allocation, investment tiers, projected vs actual revenue, debt |
| [Transfer Market](transfer-market.md) | Scouting, buying, selling, loans, contract negotiation, pre-contracts |
| [Youth Academy](academy-redesign.md) | La Cantera: phased stat reveals, development, end-of-season evaluations |

### UI Design References

| Document | Description |
|----------|-------------|
| [Squad Page Redesign](squad-page-redesign.md) | UI/UX design spec for the squad management page |

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
| `app/Modules/Squad/Services/PlayerValuationService.php` | Ability calculation from market value, and reverse |
| `app/Modules/Squad/Services/PlayerDevelopmentService.php` | Development, potential, and market value updates |
| `app/Modules/Squad/Services/DevelopmentCurve.php` | Age-based development multiplier table |
| `app/Modules/Match/Services/MatchSimulator.php` | Match result simulation |
| `app/Modules/Squad/Services/InjuryService.php` | Injury probability and generation |
| `app/Modules/Transfer/Services/ContractService.php` | Wage calculation with age modifiers |
| `app/Modules/Transfer/Services/TransferService.php` | Transfer operations (buying, selling) |
| `app/Modules/Transfer/Services/ScoutingService.php` | Player scouting and search system |
| `app/Modules/Transfer/Services/LoanService.php` | Loan operations (in and out) |
| `app/Modules/Finance/Services/BudgetProjectionService.php` | Revenue projections and budget planning |
| `app/Modules/Academy/Services/YouthAcademyService.php` | Youth academy management |
| `app/Modules/Season/Services/SeasonEndPipeline.php` | Season-end processing orchestration |
| `config/match_simulation.php` | Tunable match simulation parameters |
| `config/finances.php` | Financial system configuration |

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
