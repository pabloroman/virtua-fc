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

For each team in league:
  Calculate squad strength
  Rank teams by strength
  Your projected position = your rank
```

This projected position is then used to estimate:
- Projected TV Rights (from position table)
- Projected Matchday Revenue (stadium_seats × revenue_per_seat × facilities × position factor)
- Projected Prize Money (assume Round of 16 cup exit — conservative)
- Commercial Revenue (season 1: stadium_seats × commercial_per_seat; season 2+: prior season's actual)

**Projected Wages** = Current squad's annual wages (known quantity)

**Projected Operating Expenses** = Fixed costs by reputation (non-playing staff, admin, travel, etc.)

**Projected Surplus** = Projected Revenue − Projected Wages − Operating Expenses

### Actual Revenue Calculation

At season end, actual revenue is calculated from real results:
- Actual TV Rights (from final league position)
- Actual Matchday Revenue (from final position)
- Actual Prize Money (from actual cup run)
- Actual Commercial Revenue (projected × position-based growth multiplier)
- Transfer Sales (actual sales made)

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

**Example:**
- Season 1: Projected €50M surplus, allocated €50M, actually earned €40M → €10M debt
- Season 2: Projected €55M surplus, minus €10M debt = €45M available to allocate

**Guardrails:**
- Debt accumulates but doesn't trigger "game over"
- Severe debt (>50% of projected revenue) triggers a warning
- Player can dig out by selling players or cutting investment

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

*Known at season end, counted as revenue for that season.*

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

**European Competition (future):**

| Competition | Group Stage | Final |
|-------------|-------------|-------|
| Champions League | €15M | €100M+ |
| Europa League | €4M | €30M |
| Conference League | €2M | €10M |

### 3. Matchday Revenue

Driven by: **Stadium Seats × Revenue Per Seat × Facilities Multiplier × Position Factor**

Uses the `stadium_seats` field from the teams table (real stadium capacity data). Revenue per seat rates are defined per competition config (`CompetitionConfig::getRevenuePerSeat()`).

**Revenue Per Seat** (based on club reputation, from competition config):

| Reputation | Revenue/Seat/Season | Rationale |
|------------|---------------------|-----------|
| Elite | €1,300 | Premium pricing, corporate hospitality, merchandise |
| Contenders | €800 | Strong pricing power, good hospitality |
| Continental | €500 | Solid attendance, some hospitality |
| Established | €250 | Standard La Liga pricing |
| Modest | €150 | Lower ticket prices, less hospitality |
| Professional | €80 | Basic pricing |
| Local | €40 | Budget pricing, minimal extras |

**Facilities Multiplier:**

| Tier | Multiplier | What it represents |
|------|-----------|-------------------|
| Tier 1 | ×1.0 | Basic stadium operations |
| Tier 2 | ×1.15 | Improved hospitality, better fan experience |
| Tier 3 | ×1.35 | Premium facilities, corporate boxes |
| Tier 4 | ×1.6 | World-class matchday experience |

**Position Factor:**

| Position | Factor | Rationale |
|----------|--------|-----------|
| 1st-4th | ×1.1 | Higher attendance, big match excitement |
| 5th-10th | ×1.0 | Normal attendance |
| 11th-16th | ×0.95 | Slight drop in casual fans |
| 17th-20th | ×0.85 | Relegation worry reduces attendance |

**Formula:**
```
Base Matchday = Stadium Seats × Revenue Per Seat
Matchday Revenue = Base × Facilities Multiplier × Position Factor
```

**Examples:**

| Club | Seats | Reputation | Base | Facilities | Position | Total |
|------|-------|------------|------|------------|----------|-------|
| Real Madrid | 81,000 | Elite (€1,300) | €105M | ×1.0 | ×1.1 (2nd) | **€116M** |
| Celta de Vigo | 25,000 | Established (€250) | €6.2M | ×1.0 | ×0.95 (11th) | **€5.9M** |
| Promoted club | 12,000 | Local (€40) | €480K | ×1.0 | ×0.85 (18th) | **€408K** |

**Future Enhancement:** Stadium expansion projects could increase `stadium_seats` over time, allowing clubs to grow their matchday revenue ceiling.

### 4. Commercial Revenue

Calculated algorithmically from **Stadium Seats × Commercial Per Seat Rate** (from `CompetitionConfig::getCommercialPerSeat()`). Stadium size creates natural per-club variance within reputation tiers.

**Commercial Per Seat** (based on club reputation):

| Reputation | Per Seat | Example (40K stadium) |
|------------|----------|-----------------------|
| Elite | €3,500 | €140M |
| Contenders | €1,400 | €56M |
| Continental | €1,500 | €60M |
| Established | €1,000 | €40M |
| Modest | €800 | €32M |
| Professional | €500 | €20M |
| Local | €200 | €8M |

**Season flow:**
- **Season 1:** `BudgetProjectionService` calculates initial commercial revenue from `stadium_seats × config rate`
- **Season end:** `SeasonSettlementProcessor` applies position-based growth multiplier to get `actual_commercial_revenue`
- **Season 2+:** `BudgetProjectionService` reads previous season's `actual_commercial_revenue` as the base

**Position-based growth multipliers** (in `config/finances.php`):

| Positions | Multiplier | Effect |
|-----------|-----------|--------|
| 1st-4th | ×1.05 | +5% growth |
| 5th-8th | ×1.02 | +2% growth |
| 9th-14th | ×1.00 | Flat |
| 15th-17th | ×0.98 | -2% decline |
| 18th-20th | ×0.95 | -5% decline |

This means commercial revenue grows organically based on league performance over seasons.

### 5. Transfer Sales

Simply: **Sum of all players sold during the season**

This is 100% player-controlled and can dramatically change finances.

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
- Bought in March (4 months): 33% of annual wage

### Payment Timing

Wages are calculated and paid at **end of season**.

This means:
- Selling a player mid-season → you pay partial wages, wage bill decreases next year
- Buying a player mid-season → you pay partial wages this year

---

## Surplus & Budget Allocation

### Surplus Calculation

```
Surplus = Total Revenue − Total Wages − Operating Expenses
```

This is the money available for allocation.

### The 5 Sliders

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

The remaining surplus can be allocated freely.

*Note: Future lower leagues (Primera RFEF) may allow Tier 0 in some areas.*

---

## Investment Areas & Effects

### Youth Academy

**Tier Thresholds:**

| Tier | Cost | Prospects/Year | Quality Floor | Quality Ceiling |
|------|------|---------------|---------------|-----------------|
| Tier 1 | €500K | 1-2 | 40 OVR | 82 OVR |
| Tier 2 | €2M | 2-3 | 50 OVR | 82 OVR |
| Tier 3 | €8M | 3-5 | 58 OVR | 82 OVR |
| Tier 4 | €20M | 4-6 | 66 OVR | 82 OVR |

**Key insight:** Higher tiers produce more prospects with a higher quality floor. The ceiling is the same for all — any club can get lucky with a gem, but Tier 4 wastes less time on unusable players.

### Medical & Sports Science

**Tier Thresholds:**

| Tier | Cost | Injury Rate | Recovery Speed | Fitness Decay |
|------|------|-------------|----------------|---------------|
| Tier 1 | €300K | +20% more injuries | Normal | Fast |
| Tier 2 | €1.5M | Normal (baseline) | Normal | Normal |
| Tier 3 | €5M | −20% fewer injuries | +25% faster | Slow |
| Tier 4 | €12M | −40% fewer injuries | +50% faster | Very slow |

**Key insight:** Crucial for small squads — good medical lets you compete with fewer players.

### Scouting Network

**Tier Thresholds:**

| Tier | Cost | Player Discovery |
|------|------|------------------|
| Tier 1 | €200K | Your league only |
| Tier 2 | €1M | All of Spain |
| Tier 3 | €4M | Europe (top + secondary leagues) |
| Tier 4 | €10M | Global + hidden gems appear |

**Key insight:** "Hidden gems" are players whose market value is lower than their actual ability. Only Tier 4 scouting surfaces these in searches.

### Facilities

**Tier Thresholds:**

| Tier | Cost | Matchday Multiplier |
|------|------|---------------------|
| Tier 1 | €500K | ×1.0 (baseline) |
| Tier 2 | €3M | ×1.15 |
| Tier 3 | €10M | ×1.35 |
| Tier 4 | €25M | ×1.6 |

**Key insight:** Creates a feedback loop — invest now, earn more matchday revenue later, reinvest.

### Transfers

No tiers — this is simply your buying power in the transfer market.

---

## Example: Full Season (Villarreal)

### Pre-Season: Budget Planning

**Club Profile:**
- Mid-table La Liga club
- Stadium: Estadio de la Cerámica (23,000 seats)
- Reputation: Contenders (€800/seat matchday, €1,400/seat commercial)
- Current facilities: Tier 2 (×1.15)
- Carried debt from last season: €0

**Projected Position:** 8th (based on squad strength vs league)

**Projected Revenue:**

| Source | Projected |
|--------|-----------|
| TV Rights (8th place) | €58M |
| Copa del Rey (assume R16 exit) | €0.85M |
| Matchday (23k × €800 × 1.15 × 1.0) | €21.2M |
| Commercial (23k × €1,400) | €32.2M |
| Transfer Sales | €0 (can't predict) |
| **Total Projected** | **€112M** |

**Projected Wages:** €85M (current squad)
**Operating Expenses:** €50M (contenders tier)

**Projected Surplus:** €112M − €85M − €50M = **−€23M**

*Note: A negative surplus is realistic — many La Liga clubs rely on player sales to balance books. The transfer budget comes from expected sales revenue.*

### Budget Allocation (Player Choice)

The player decides to focus on youth development, budgeting based on projected surplus:

| Area | % | € Amount | Tier Achieved |
|------|---|----------|---------------|
| Youth Academy | 36% | €8M | Tier 3 |
| Medical | 23% | €5M | Tier 3 |
| Scouting | 18% | €4M | Tier 3 |
| Facilities | 0% | €0 | (keep Tier 2) |
| Transfers | 23% | €5M | — |
| **Total** | **100%** | **€22M** | |

*Note: With a tighter budget, the player prioritizes infrastructure over transfers, betting on youth development and good scouting to find bargains.*

### Season Results (Actual Performance)

The team overperformed! Also sold a player mid-season.

- Finished **6th** in La Liga (better than projected 8th)
- Reached Copa del Rey **Quarter-finals**
- Sold one player for **€35M** in January

### Actual Revenue:

| Source | Projected | Actual | Variance |
|--------|-----------|--------|----------|
| TV Rights | €58M | €65M | +€7M |
| Copa del Rey | €0.85M | €1.75M | +€0.9M |
| Matchday | €21.2M | €21.2M | €0 |
| Commercial | €32.2M | €32.8M | +€0.6M (6th = ×1.02) |
| Transfer Sales | €0 | €35M | +€35M |
| **Total** | **€112M** | **€156M** | **+€44M** |

### Actual Wages:

| Item | Amount |
|------|--------|
| Squad wages (full season) | €78M |
| Sold player (6 months) | €3M |
| New signing (4 months) | €1.5M |
| **Total Actual Wages** | **€82.5M** |

*(Lower than projected €85M because of the mid-season sale)*

### Season End Settlement

```
Actual Surplus    = €156M − €82.5M − €50M = €23.5M
Projected Surplus = −€23M
Variance          = €23.5M − (−€23M) = +€46.5M (positive!)
```

**Result:** The player overperformed AND sold a player. They have a **€46.5M bonus** added to next season's projected surplus. The commercial revenue also grew slightly (×1.02 for finishing 6th), which carries forward.

### Next Season Preview

```
Next Season Projected Surplus: −€20M (estimated)
Plus bonus from overperformance: +€46.5M
Available to Allocate: €26.5M
```

The good season (especially transfer income) creates resources for a more ambitious budget next year.

### What If They Had Underperformed?

If they'd finished 14th with no player sales:

```
Actual Revenue: ~€107M (lower TV, lower matchday factor, flat commercial)
Actual Wages: €85M
Operating Expenses: €50M
Actual Surplus: −€28M

Variance = −€28M − (−€23M) = −€5M (debt!)
```

Next season they'd have €5M less to allocate — painful consequences for a bad year.

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

### New/Modified Tables

**club_profiles** — Base financial characteristics per team

| Field | Type | Description |
|-------|------|-------------|
| `team_id` | uuid | FK to teams |
| `reputation_level` | enum | elite, contenders, continental, established, modest, professional, local |

*Note: Both matchday and commercial revenue are calculated algorithmically from `teams.stadium_seats` × per-seat rates (defined in competition configs, keyed by reputation). No revenue amounts are stored on the profile itself.*

**Future: Stadium expansion** could be tracked in a `game_stadiums` table that overrides `teams.stadium_seats` for a specific game, allowing players to invest in stadium growth.

**game_finances** — Season financial state (modified)

| Field | Type | Description |
|-------|------|-------------|
| `game_id` | uuid | FK to games |
| `season` | int | Season number |
| **Projections (pre-season)** | | |
| `projected_position` | int | Expected league finish (from squad strength) |
| `projected_tv_revenue` | int (cents) | Projected TV rights |
| `projected_prize_revenue` | int (cents) | Conservative cup estimate |
| `projected_matchday_revenue` | int (cents) | Projected matchday |
| `projected_commercial_revenue` | int (cents) | Algorithmic or prior-season commercial |
| `projected_total_revenue` | int (cents) | Sum of projected |
| `projected_wages` | int (cents) | Current squad wages |
| `projected_operating_expenses` | int (cents) | Fixed costs by reputation |
| `projected_surplus` | int (cents) | Projected revenue − wages − opex |
| **Actuals (season end)** | | |
| `actual_tv_revenue` | int (cents) | Actual TV rights |
| `actual_prize_revenue` | int (cents) | Actual cup earnings |
| `actual_matchday_revenue` | int (cents) | Actual matchday |
| `actual_commercial_revenue` | int (cents) | Projected × growth multiplier |
| `actual_transfer_income` | int (cents) | Player sales |
| `actual_total_revenue` | int (cents) | Sum of actual |
| `actual_wages` | int (cents) | Pro-rated wages paid |
| `actual_operating_expenses` | int (cents) | Same as projected (fixed) |
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
| `youth_academy_amount` | int (cents) | € allocated |
| `youth_academy_tier` | int | Resulting tier (1-4) |
| `medical_amount` | int (cents) | € allocated |
| `medical_tier` | int | Resulting tier (1-4) |
| `scouting_amount` | int (cents) | € allocated |
| `scouting_tier` | int | Resulting tier (1-4) |
| `facilities_amount` | int (cents) | € allocated |
| `facilities_tier` | int | Resulting tier (1-4) |
| `transfer_budget` | int (cents) | € for transfers |

---

## Implementation Phases

### Phase 1: Data Model & Club Profiles
- [x] Create `club_profiles` table with reputation level for all La Liga/La Liga 2 teams
- [x] Modify `game_finances` table for projected vs actual structure
- [x] Create `game_investments` table
- [x] Use existing `joined_on` field in `game_players` for wage pro-rating

### Phase 2: Projection System
- [x] Implement squad strength calculation (avg OVR of best 18)
- [x] Implement projected position calculation (rank teams by strength)
- [x] Implement projected revenue calculation (TV, matchday, prizes, commercial)
- [x] Implement projected wages and operating expenses calculation
- [x] Implement projected surplus calculation
- [x] Show projections in pre-season UI
- [x] Create BudgetProjectionService for generating projections
- [x] Create SeasonSettlementProcessor for calculating actual revenue/variance

### Phase 3: Budget Allocation UI
- [x] Create pre-season budget allocation screen
- [x] Implement 5-slider interface
- [x] Show available surplus (projected minus carried debt)
- [x] Show real-time tier feedback as sliders move
- [x] Enforce minimum Tier 1 requirements (€1.5M total)
- [x] Save allocations to `game_investments`
- [x] Add budget allocation banner to pre-season page

### Phase 4: Actual Revenue & Settlement
- [x] Implement actual revenue calculation at season end
- [x] Implement pro-rated wage calculation at season end
- [x] Calculate variance (actual surplus − projected surplus)
- [x] Handle positive variance (bonus for next season)
- [x] Handle negative variance (debt carried forward)
- [ ] Show season financial summary with projected vs actual comparison

### Phase 5: Investment Effects
- [ ] Youth Academy: Modify youth prospect generation based on tier
- [ ] Medical: Modify injury service for tier multipliers (injury rate, recovery, fitness)
- [ ] Scouting: Modify scouting service for geographic restrictions + hidden gems
- [x] Facilities: Apply matchday multiplier in revenue calculation

### Phase 6: Season Flow Integration
- [x] Pre-season: Calculate projections → show allocation screen → save investments
- [x] During season: Track transfers for wage pro-rating
- [x] Season end: Calculate actuals → settlement → carry debt/bonus
- [x] Loop back to pre-season with updated finances

---

## Key Implementation Notes

- Revenue per-seat rates (matchday and commercial) are defined in competition configs (`LaLigaConfig`, `LaLiga2Config`, `DefaultLeagueConfig`), not on models.
- `ClubProfile` only stores `reputation_level` — no revenue amounts.
- Operating expenses are configured in `config/finances.php` by reputation tier.
- Commercial growth multipliers are configured in `config/finances.php` under `commercial_growth`.
- The financial model does not include taxes — operating expenses absorb all non-wage costs (staff, admin, travel, etc.).
