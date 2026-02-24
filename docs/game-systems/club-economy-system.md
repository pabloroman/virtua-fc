# Club Economy System

The financial system that drives strategic resource allocation across seasons.

## Core Loop

At season start, the system **projects** revenue based on squad strength and calculates a surplus. The player allocates that surplus across 5 investment areas. At season end, **actual** revenue is calculated from real results. The difference (variance) carries forward as bonus or debt.

```
Pre-season:  Project revenue → Calculate surplus → Allocate budget
In-season:   Transfer activity affects wages/income
Season end:  Calculate actuals → Settlement → Carry variance to next season
```

## Revenue Sources

- **TV Rights**: Position-based, defined per competition config (`LaLigaConfig`, `LaLiga2Config`, etc.)
- **Matchday Revenue**: Stadium seats × revenue-per-seat (by reputation) × facilities multiplier
- **Commercial Revenue**: Stadium seats (capped at 80K) × commercial-per-seat (by reputation). Season 1 uses base config; subsequent seasons use prior season's actual as base, adjusted by a position-based growth multiplier.
- **Competition Prizes**: Cup and European competition prize money, cumulative by round advanced.
- **Transfer Sales**: Sum of players sold during the season.
- **Solidarity Funds**: Tier 2+ clubs receive a fixed annual solidarity payment.
- **Public Subsidy**: If projected surplus is insufficient for minimum viable infrastructure + transfer budget, a subsidy covers the shortfall.

Revenue-per-seat rates, commercial-per-seat rates, operating expenses, and growth multipliers are all in `config/finances.php`, keyed by reputation level.

## Expenses

- **Wages**: Sum of all player contracts, pro-rated by time at club (calculated at season end).
- **Operating Expenses**: Fixed costs by reputation level, with a tier multiplier (Tier 2 clubs pay less). Defined in `config/finances.php`.

## Budget Allocation

The player allocates 100% of surplus across 5 investment areas, each with tiered effects:

| Area | Effect |
|------|--------|
| **Youth Academy** | Determines academy tier: capacity, batch size, prospect quality range |
| **Medical** | Reduces injury chance and recovery time |
| **Scouting** | Expands geographic scope, adds search results, reduces ability estimation fuzz |
| **Facilities** | Multiplies matchday revenue |
| **Transfers** | Direct transfer market spending power |

Tier thresholds (cost → tier) and their effects are defined in each respective system. Professional clubs must meet minimum Tier 1 investment in all infrastructure areas.

## Variance & Debt

```
Variance = Actual Surplus − Projected Surplus
```

Positive variance (overperformed/sold players) adds to next season's surplus. Negative variance is carried as debt, reducing next season's allocatable surplus.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Finance/Services/BudgetProjectionService.php` | Pre-season projections: squad strength ranking, revenue estimates, surplus |
| `app/Modules/Season/Processors/SeasonSettlementProcessor.php` | Season-end actuals: real revenue, pro-rated wages, variance, debt |
| `config/finances.php` | Per-seat rates, operating expenses, commercial growth multipliers, tier multipliers |
| Competition configs (`app/Modules/Competition/Configs/`) | TV revenue tables, prize money by competition |
