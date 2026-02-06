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
- Projected Matchday Revenue (base × facilities × position factor)
- Projected Prize Money (assume Round of 16 cup exit — conservative)
- Commercial Revenue (known — based on reputation)

**Projected Wages** = Current squad's annual wages (known quantity)

**Projected Surplus** = Projected Revenue − Projected Wages

### Actual Revenue Calculation

At season end, actual revenue is calculated from real results:
- Actual TV Rights (from final league position)
- Actual Matchday Revenue (from final position)
- Actual Prize Money (from actual cup run)
- Commercial Revenue (same as projected)
- Transfer Sales (actual sales made)

**Actual Wages** = Pro-rated wages for all players

**Actual Surplus** = Actual Revenue − Actual Wages

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

**La Liga:**

| Position | TV Revenue |
|----------|-----------|
| 1st | €100M |
| 2nd-4th | €80-90M |
| 5th-8th | €60-75M |
| 9th-14th | €50-60M |
| 15th-20th | €40-50M |

**La Liga 2:**

| Position | TV Revenue |
|----------|-----------|
| 1st-2nd | €18-20M |
| 3rd-6th | €12-15M |
| 7th-15th | €8-12M |
| 16th-22nd | €6-8M |

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

Uses the `stadium_seats` field from the teams table (real stadium capacity data).

**Revenue Per Seat** (based on club reputation):

| Reputation | Revenue/Seat/Season | Rationale |
|------------|---------------------|-----------|
| Elite | €1,500 | Premium pricing, corporate hospitality, merchandise |
| Continental | €800 | Strong pricing power, good hospitality |
| Established | €500 | Standard La Liga pricing |
| Modest | €350 | Lower ticket prices, less hospitality |
| Local | €200 | Budget pricing, minimal extras |

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
| Real Madrid | 83,000 | Elite (€1,500) | €124.5M | ×1.0 | ×1.1 (2nd) | **€137M** |
| Villarreal | 23,000 | Established (€500) | €11.5M | ×1.15 | ×1.0 (6th) | **€13.2M** |
| Promoted club | 12,000 | Local (€200) | €2.4M | ×1.0 | ×0.85 (18th) | **€2M** |

**Future Enhancement:** Stadium expansion projects could increase `stadium_seats` over time, allowing clubs to grow their matchday revenue ceiling.

### 4. Commercial Revenue

The "sticky" income based on **Club Reputation** — changes slowly over seasons.

| Reputation Level | Commercial Revenue | Examples |
|-----------------|-------------------|----------|
| Elite | €150-200M | Real Madrid, Barcelona |
| Continental | €50-80M | Atlético, Sevilla |
| Established | €20-35M | Villarreal, Betis |
| Modest | €8-15M | Celta, Osasuna |
| Local | €3-6M | Promoted clubs |

**Reputation shifts:**
- Finish top 4 consistently: Reputation slowly increases
- Win trophy: Reputation boost
- Get relegated: Reputation drops significantly
- Consistent mid-table: Stable

*Takes 3-5 seasons for meaningful shifts.*

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
Surplus = Total Revenue − Total Wages
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
- Reputation: Established (€500/seat)
- Commercial revenue: €28M
- Current facilities: Tier 2 (×1.15)
- Carried debt from last season: €0

**Projected Position:** 8th (based on squad strength vs league)

**Projected Revenue:**

| Source | Projected |
|--------|-----------|
| TV Rights (8th place) | €65M |
| Copa del Rey (assume R16 exit) | €0.75M |
| Matchday (23k × €500 × 1.15 × 1.0) | €13.2M |
| Commercial | €28M |
| Transfer Sales | €0 (can't predict) |
| **Total Projected** | **€107M** |

**Projected Wages:** €85M (current squad)

**Projected Surplus:** €107M − €85M = **€22M**

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
| TV Rights | €65M | €70M | +€5M |
| Copa del Rey | €0.75M | €1.75M | +€1M |
| Matchday | €13.2M | €13.2M | €0 |
| Commercial | €28M | €28M | €0 |
| Transfer Sales | €0 | €35M | +€35M |
| **Total** | **€107M** | **€148M** | **+€41M** |

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
Actual Surplus    = €148M − €82.5M = €65.5M
Projected Surplus = €22M
Variance          = €65.5M − €22M = +€43.5M (positive!)
```

**Result:** The player overperformed AND sold a player. They have a **€43.5M bonus** added to next season's projected surplus.

### Next Season Preview

```
Next Season Projected Surplus: €25M (estimated)
Plus bonus from overperformance: +€43.5M
Available to Allocate: €68.5M
```

The good season creates more resources for an even more ambitious budget next year.

### What If They Had Underperformed?

If they'd finished 14th with no player sales:

```
Actual Revenue: ~€100M (lower TV, lower matchday factor)
Actual Wages: €85M
Actual Surplus: €15M

Variance = €15M − €22M = -€7M (debt!)
```

Next season they'd have €7M less to allocate — painful consequences for a bad year.

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
| `reputation_level` | enum | elite, continental, established, modest, local |
| `commercial_revenue` | int (cents) | Current commercial earnings |

*Note: Matchday revenue is calculated from `teams.stadium_seats` × revenue-per-seat (based on reputation). No need to store base matchday separately.*

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
| `projected_commercial_revenue` | int (cents) | Known commercial |
| `projected_total_revenue` | int (cents) | Sum of projected |
| `projected_wages` | int (cents) | Current squad wages |
| `projected_surplus` | int (cents) | Projected revenue − wages |
| **Actuals (season end)** | | |
| `actual_tv_revenue` | int (cents) | Actual TV rights |
| `actual_prize_revenue` | int (cents) | Actual cup earnings |
| `actual_matchday_revenue` | int (cents) | Actual matchday |
| `actual_commercial_revenue` | int (cents) | Actual commercial |
| `actual_transfer_income` | int (cents) | Player sales |
| `actual_total_revenue` | int (cents) | Sum of actual |
| `actual_wages` | int (cents) | Pro-rated wages paid |
| `actual_surplus` | int (cents) | Actual revenue − wages |
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
- [ ] Create `club_profiles` table with base financial data for all La Liga/La Liga 2 teams
- [ ] Populate base matchday revenue, reputation level, commercial revenue for each team
- [ ] Modify `game_finances` table for projected vs actual structure
- [ ] Create `game_investments` table
- [ ] Add `joined_at` field to `game_players` for wage pro-rating

### Phase 2: Projection System
- [ ] Implement squad strength calculation (avg OVR of best 18)
- [ ] Implement projected position calculation (rank teams by strength)
- [ ] Implement projected revenue calculation (TV, matchday, prizes, commercial)
- [ ] Implement projected wages calculation
- [ ] Implement projected surplus calculation
- [ ] Show projections in pre-season UI

### Phase 3: Budget Allocation UI
- [ ] Create pre-season budget allocation screen
- [ ] Implement 5-slider interface
- [ ] Show available surplus (projected minus carried debt)
- [ ] Show real-time tier feedback as sliders move
- [ ] Enforce minimum Tier 1 requirements (€1.5M total)
- [ ] Save allocations to `game_investments`

### Phase 4: Actual Revenue & Settlement
- [ ] Implement actual revenue calculation at season end
- [ ] Implement pro-rated wage calculation at season end
- [ ] Calculate variance (actual surplus − projected surplus)
- [ ] Handle positive variance (bonus for next season)
- [ ] Handle negative variance (debt carried forward)
- [ ] Show season financial summary with projected vs actual comparison

### Phase 5: Investment Effects
- [ ] Youth Academy: Modify youth prospect generation based on tier
- [ ] Medical: Modify injury service for tier multipliers (injury rate, recovery, fitness)
- [ ] Scouting: Modify scouting service for geographic restrictions + hidden gems
- [ ] Facilities: Apply matchday multiplier in revenue calculation

### Phase 6: Season Flow Integration
- [ ] Pre-season: Calculate projections → show allocation screen → save investments
- [ ] During season: Track transfers for wage pro-rating
- [ ] Season end: Calculate actuals → settlement → carry debt/bonus
- [ ] Loop back to pre-season with updated finances

---

## Migration from Current System

The current financial system will be replaced:

1. **Keep:** Basic `game_finances` table structure (modified)
2. **Remove:** TV revenue based on squad value (now based on league position)
3. **Remove:** Fixed wage budget / transfer budget split
4. **Add:** Player-controlled budget allocation
5. **Add:** Investment tiers with gameplay effects
6. **Add:** Club profiles with base financial characteristics

Existing games will be migrated with sensible defaults based on their current financial state.
