# Club Economy System

The Club Economy System creates meaningful strategic choices through resource allocation. Players must balance short-term competitiveness (transfers) against long-term infrastructure (academy, medical, scouting, facilities).

## Design Philosophy

1. **Simple inputs, complex outcomes** — A few high-level decisions compound over seasons
2. **Trade-offs matter** — Can't excel at everything; must choose priorities
3. **Emergent identity** — Your choices define your club's DNA organically
4. **Club size matters** — Bigger clubs have more resources, but all clubs face trade-offs

---

## Core Financial Loop

```
┌─────────────────────────────────────────────────────────────────┐
│                     SEASON FINANCIAL CYCLE                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  PRE-SEASON (Budget Planning)                                    │
│  ────────────────────────────                                    │
│  • System calculates PROJECTED revenue based on squad strength   │
│  • Projected wages estimated from current contracts              │
│  • Projected Surplus = Projected Revenue − Projected Wages       │
│  • Player allocates Projected Surplus across 5 areas             │
│  • This is your BUDGET — spend within it or take risks           │
│                                                                  │
│  DURING SEASON                                                   │
│  ─────────────                                                   │
│  • Actual revenue accumulates based on real performance          │
│  • Transfer activity affects wages and transfer income           │
│                                                                  │
│  SEASON END (Settlement)                                         │
│  ───────────────────────                                         │
│  • ACTUAL revenue calculated from real results                   │
│  • ACTUAL wages calculated (pro-rated)                           │
│  • Variance = Actual Surplus − Projected Surplus                 │
│  • Positive variance → bonus funds for next season               │
│  • Negative variance → debt carried forward                      │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Projected vs Realized Revenue

### The Core Concept

At season start, you don't know how you'll perform. The system **projects** your revenue based on squad strength, and you allocate budget based on that projection. At season end, **actual** revenue is calculated. The difference creates consequences.

### Projected Revenue Calculation

**Projected Position** is estimated by comparing your squad strength to the league:

```
Squad Strength = Average OVR of best 18 players
  where OVR per player = (Technical + Physical + Fitness + Morale) / 4
  (Fitness defaults to 70, Morale defaults to 70 for new players)

For each team in league:
  Calculate squad strength
  Rank teams by strength
  Your projected position = your rank
```

This projected position is then used to estimate:
- Projected TV Rights (from competition config position table)
- Projected Matchday Revenue (stadium_seats × revenue_per_seat × facilities_multiplier)
- Projected Commercial Revenue (season 1: stadium_seats × commercial_per_seat; season 2+: prior season's actual)

**Projected Wages** = Current squad's annual wages (known quantity)

**Projected Operating Expenses** = Fixed costs by reputation × tier multiplier

**Projected Surplus** = Projected Revenue − Projected Wages − Operating Expenses

### Public Subsidy (Subvenciones Públicas)

If the projected surplus minus carried debt is insufficient to cover minimum viable infrastructure plus a minimum transfer budget, the system provides a public subsidy:

```
Minimum Viable = Total infrastructure minimums + €1M transfer budget
If Raw Available < Minimum Viable:
    Subsidy = Minimum Viable - Raw Available
```

This guarantees that even struggling clubs can function at minimum levels.

### Solidarity Funds

Clubs in Tier 2 (Segunda División) and below receive **€1M** in solidarity funds per season, representing UEFA/RFEF redistribution. This is added to projected revenue automatically.

### Actual Revenue Calculation

At season end, actual revenue is calculated from real results:
- Actual TV Rights (from final league position)
- Actual Matchday Revenue (from final position)
- Actual Commercial Revenue (projected × position-based growth multiplier)
- Transfer Sales (actual sales made during the season)
- Cup/European prize money

**Actual Wages** = Pro-rated wages for all players

**Actual Surplus** = Actual Revenue − Actual Wages − Operating Expenses

### Variance & Consequences

```
Variance = Actual Surplus − Projected Surplus
```

| Variance | What Happened | Consequence |
|----------|---------------|-------------|
| Positive | Overperformed or sold players | Bonus added to next season's surplus |
| Zero | Met expectations | No adjustment |
| Negative | Underperformed | Debt: reduces next season's surplus |

### Debt Mechanics

Debt is not catastrophic — it's carried forward and reduces your next season's available surplus.

```
Next Season's Allocatable Surplus = Projected Surplus − Carried Debt
```

---

## Revenue Sources

### 1. TV Rights

The predictable baseline. Distributed by league based on final position.

**La Liga** (from `LaLigaConfig::TV_REVENUE`):

| Position | TV Revenue |
|----------|-----------|
| 1st | €155M |
| 2nd | €140M |
| 3rd-4th | €72-105M |
| 5th-8th | €58-68M |
| 9th-14th | €44-55M |
| 15th-20th | €40-43M |

**La Liga 2** (from `LaLiga2Config::TV_REVENUE`):

| Position | TV Revenue |
|----------|-----------|
| 1st-2nd | €8.5-9M |
| 3rd-6th | €7-8M |
| 7th-14th | €6-6.5M |
| 15th-22nd | €5-5.5M |

### 2. Competition Prizes

Variable based on cup and European runs.

**Copa del Rey:**

| Round | Prize |
|-------|-------|
| Round of 32 | €250K |
| Round of 16 | €500K |
| Quarter-finals | €1M |
| Semi-finals | €2M |
| Runner-up | €5M |
| Winner | €10M |

*Cumulative — winning the cup = ~€19M total*

**European Competitions:**

| Competition | Range | Structure |
|-------------|-------|-----------|
| Champions League | €9M-€80M | 36-position Swiss format, prize by final position |
| Europa League | €5M base + bonuses | ~€30M for winner |
| Conference League | €2.5M base + bonuses | ~€10M for winner |

### 3. Matchday Revenue

Driven by: **Stadium Seats × Revenue Per Seat × Facilities Multiplier**

Uses the `stadium_seats` field from the teams table (real stadium capacity data). Revenue per seat rates come from `config/finances.php`, keyed by reputation.

**Revenue Per Seat** (based on club reputation):

| Reputation | Revenue/Seat/Season |
|------------|---------------------|
| Elite | €800 |
| Contenders | €600 |
| Continental | €425 |
| Established | €350 |
| Modest | €275 |
| Professional | €200 |
| Local | €100 |

Tier 2 clubs receive **75% reduction** of the base matchday rate.

**Facilities Multiplier** (from `GameInvestment::FACILITIES_MULTIPLIER`):

| Tier | Multiplier | What it represents |
|------|-----------|-------------------|
| Tier 1 | ×1.0 | Basic stadium operations |
| Tier 2 | ×1.15 | Improved hospitality, better fan experience |
| Tier 3 | ×1.35 | Premium facilities, corporate boxes |
| Tier 4 | ×1.6 | World-class matchday experience |

### 4. Commercial Revenue

Calculated from **Stadium Seats × Commercial Per Seat Rate**. Stadium size creates natural per-club variance within reputation tiers. Capped at **80,000 seats** to prevent oversized stadiums generating disproportionate income.

**Commercial Per Seat** (based on club reputation, from `config/finances.php`):

| Reputation | Per Seat | Example (40K stadium) |
|------------|----------|-----------------------|
| Elite | €2,200 | €88M |
| Contenders | €1,200 | €48M |
| Continental | €1,000 | €40M |
| Established | €800 | €32M |
| Modest | €600 | €24M |
| Professional | €500 | €20M |
| Local | €300 | €12M |

**Season flow:**
- **Season 1:** `BudgetProjectionService` calculates initial commercial revenue from `min(stadium_seats, 80000) × config rate`
- **Season 2+:** `BudgetProjectionService` reads previous season's `actual_commercial_revenue` as the base
- **Season end:** `SeasonSettlementProcessor` applies position-based growth multiplier to get `actual_commercial_revenue`

**Position-based growth multipliers** (in `config/finances.php`):

| Positions | Multiplier | Effect |
|-----------|-----------|--------|
| 1st-4th | ×1.05 | +5% growth |
| 5th-8th | ×1.02 | +2% growth |
| 9th-14th | ×1.00 | Flat |
| 15th-17th | ×0.98 | -2% decline |
| 18th-20th | ×0.95 | -5% decline |

### 5. Transfer Sales

Simply: **Sum of all players sold during the season**

This is 100% player-controlled and can dramatically change finances.

---

## Operating Expenses

Fixed costs by reputation level (from `config/finances.php`):

| Reputation | Annual Operating Expenses |
|------------|--------------------------|
| Elite | €75M |
| Contenders | €50M |
| Continental | €35M |
| Established | €20M |
| Modest | €12.5M |
| Professional | €10M |
| Local | €5M |

**Tier multiplier:** Tier 1 (La Liga) = 1.0×, Tier 2 (Segunda División) = 0.70×

---

## Wages

### Calculation

Wages are the sum of all player contracts, pro-rated by time at club.

```
Player Wage Cost = Annual Wage × (months at club ÷ 12)
```

**Examples:**
- Player on roster all season: 100% of annual wage
- Sold in January (6 months): 50% of annual wage
- Bought in January (6 months): 50% of annual wage

### Payment Timing

Wages are calculated and paid at **end of season**.

---

## Surplus & Budget Allocation

### Surplus Calculation

```
Surplus = Total Revenue − Total Wages − Operating Expenses
```

### The 5 Investment Areas

In pre-season, the player allocates 100% of their surplus across 5 areas:

```
┌─────────────────────────────────────────────────────────────┐
│  BUDGET ALLOCATION 2025/26                                  │
│                                                             │
│  Surplus: €40M                                              │
│                                                             │
│  Youth Academy   ████████░░░░░░░░░░░░  20%  →  €8M (Tier 3) │
│  Medical         ████░░░░░░░░░░░░░░░░  10%  →  €4M (Tier 3) │
│  Scouting        ██░░░░░░░░░░░░░░░░░░   5%  →  €2M (Tier 2) │
│  Facilities      ██░░░░░░░░░░░░░░░░░░   5%  →  €2M (Tier 1) │
│  Transfers       ████████████░░░░░░░░  60%  →  €24M         │
│                                        ────                  │
│                                        100%                  │
└─────────────────────────────────────────────────────────────┘
```

The € amount determines what **tier** you achieve in each infrastructure area.

### Minimum Requirements (La Liga / La Liga 2)

Professional clubs must maintain minimum Tier 1 in all infrastructure areas:

| Area | Minimum Investment |
|------|-------------------|
| Youth Academy | €500K |
| Medical | €300K |
| Scouting | €200K |
| Facilities | €500K |
| **Total Minimum** | **€1.5M** |

---

## Investment Areas & Effects

### Youth Academy

| Tier | Cost | Capacity | Arrivals/Year | Potential Range | Ability Range |
|------|------|----------|---------------|-----------------|---------------|
| Tier 1 | €500K | 4 | 2-3 | 60-70 | 35-50 |
| Tier 2 | €2M | 6 | 3-5 | 65-75 | 40-55 |
| Tier 3 | €8M | 8 | 5-7 | 70-82 | 45-60 |
| Tier 4 | €20M | 10 | 6-8 | 75-90 | 50-70 |

Higher tiers produce more prospects with higher quality floors and potential ceilings. See [Youth Academy](academy-redesign.md).

### Medical & Sports Science

| Tier | Cost | Injury Prevention | Recovery Speed |
|------|------|-------------------|----------------|
| Tier 0 | €0 | +30% more injuries | 20% slower |
| Tier 1 | €300K | Baseline | Normal |
| Tier 2 | €1.5M | −15% fewer injuries | 10% faster |
| Tier 3 | €5M | −30% fewer injuries | 20% faster |
| Tier 4 | €12M | −45% fewer injuries | 30% faster |

See [Injury System](injury-system.md) for detailed multipliers.

### Scouting Network

| Tier | Cost | Scope | Extra Results | Ability Fuzz Reduction |
|------|------|-------|---------------|------------------------|
| Tier 1 | €200K | Your league only | +0 | −0 |
| Tier 2 | €1M | All of Spain | +1 | −2 |
| Tier 3 | €4M | Europe (international) | +2 | −4 |
| Tier 4 | €10M | Global (international) | +3 | −6 |

Base search returns 5-8 players. Tier 3+ unlocks international searches. Higher tiers also reduce search duration by up to 1 week. See [Transfer Market](transfer-market.md).

### Facilities

| Tier | Cost | Matchday Multiplier |
|------|------|---------------------|
| Tier 1 | €500K | ×1.0 (baseline) |
| Tier 2 | €3M | ×1.15 |
| Tier 3 | €10M | ×1.35 |
| Tier 4 | €25M | ×1.6 |

Creates a feedback loop — invest now, earn more matchday revenue later, reinvest.

### Transfers

No tiers — this is simply your buying power in the transfer market.

---

## Emergent Club Identities

The system naturally creates different club identities based on consistent choices:

| Pattern | Typical Allocation | Emergent Identity |
|---------|-------------------|-------------------|
| Heavy academy investment | 30%+ to Youth | "La Masia" — produces own talent |
| Heavy scouting + low transfers | 20%+ Scouting, <40% Transfers | "Moneyball" — finds bargains, sells high |
| Minimal infrastructure | <15% total to infrastructure | "Sugar Daddy" — buys success |
| Balanced investment | ~20% each area | "Sustainable" — steady growth |

**No labels or achievements** — the player discovers their identity through play.

---

## Data Model

### Key Tables

**club_profiles** — Base financial characteristics per team

| Field | Type | Description |
|-------|------|-------------|
| `team_id` | uuid | FK to teams |
| `reputation_level` | enum | elite, contenders, continental, established, modest, professional, local |

Revenue per-seat rates are calculated algorithmically from `teams.stadium_seats` × per-seat rates (defined in `config/finances.php`, keyed by reputation). No revenue amounts are stored on the profile itself.

**game_finances** — Season financial state

| Field | Type | Description |
|-------|------|-------------|
| `game_id` | uuid | FK to games |
| `season` | int | Season number |
| **Projections** | | |
| `projected_position` | int | Expected league finish |
| `projected_tv_revenue` | int (cents) | Projected TV rights |
| `projected_matchday_revenue` | int (cents) | Projected matchday |
| `projected_commercial_revenue` | int (cents) | Algorithmic or prior-season commercial |
| `projected_total_revenue` | int (cents) | Sum of projected |
| `projected_wages` | int (cents) | Current squad wages |
| `projected_operating_expenses` | int (cents) | Fixed costs by reputation |
| `projected_surplus` | int (cents) | Projected revenue − wages − opex |
| **Actuals** | | |
| `actual_tv_revenue` | int (cents) | Actual TV rights |
| `actual_matchday_revenue` | int (cents) | Actual matchday |
| `actual_commercial_revenue` | int (cents) | Projected × growth multiplier |
| `actual_transfer_income` | int (cents) | Player sales |
| `actual_total_revenue` | int (cents) | Sum of actual |
| `actual_wages` | int (cents) | Pro-rated wages paid |
| `actual_surplus` | int (cents) | Actual revenue − wages − opex |
| **Settlement** | | |
| `variance` | int (cents) | Actual surplus − Projected surplus |
| `carried_debt` | int (cents) | Debt from previous season |

**game_investments** — Budget allocation per season

| Field | Type | Description |
|-------|------|-------------|
| `game_id` | uuid | FK to games |
| `season` | int | Season number |
| `available_surplus` | int (cents) | Projected surplus − carried debt |
| `youth_academy_amount` / `_tier` | int | € allocated and resulting tier |
| `medical_amount` / `_tier` | int | € allocated and resulting tier |
| `scouting_amount` / `_tier` | int | € allocated and resulting tier |
| `facilities_amount` / `_tier` | int | € allocated and resulting tier |
| `transfer_budget` | int (cents) | € for transfers |

---

## Key Implementation Notes

- Revenue per-seat rates (matchday and commercial) are defined in `config/finances.php` keyed by reputation, not in competition configs or models.
- `ClubProfile` only stores `reputation_level` — no revenue amounts.
- Operating expenses are configured in `config/finances.php` by reputation tier.
- Commercial growth multipliers are configured in `config/finances.php` under `commercial_growth`.
- The financial model does not include taxes — operating expenses absorb all non-wage costs (staff, admin, travel, etc.).
- `BudgetProjectionService` handles all projection calculations at season start.
- `SeasonSettlementProcessor` handles all actual revenue calculations and settlement at season end.
