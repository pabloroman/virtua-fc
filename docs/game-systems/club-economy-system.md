# Club Economy System

The Club Economy System creates the interconnected relationship between squad planning and financial management. Every decision has financial implications, and financial constraints shape squad decisions.

## Overview

This system encompasses:
- **Player Contracts**: Wages, contract length, renewals
- **Financial Model**: Revenue streams, expenses, budgets
- **Transfer Market**: Buying and selling players
- **Youth Academy**: Homegrown talent pipeline

## Implementation Phases

### Phase 1: Contracts & Wage Bill (Foundation)

**Goal**: Every player has a contract. You see your financial commitment.

**Adds**:
- `weekly_wage` and `contract_end_season` fields to players
- Wage calculation from market value
- Dashboard showing total wage bill, highest earners
- Season end flags expiring contracts

**Why first**: Everything else depends on contracts existing.

---

### Phase 2: Basic Financial Model ✓

**Goal**: Money matters. Club size and performance = revenue.

**Adds**:
- `game_finances` table (1:1 with games) with balance, budgets, revenue/expense tracking
- TV revenue based on squad value (club size proxy)
- Performance bonus based on league position
- Cup prize money for progression
- Season P&L calculated at season end
- Budget calculation for next season (carries over balance)
- Finances display on dashboard and season-end screen

**Revenue Model**:
- **TV Rights**: Based on squad market value (bigger clubs = more TV money)
  - €800M+ squad → €140M TV revenue
  - €600M+ squad → €110M TV revenue
  - €400M+ squad → €90M TV revenue
  - And so on down to €15M for small clubs
- **Performance Bonus**: Linear from 1st (€15M) to 20th (€0)
- **Cup Prizes**: Cumulative per round (R64: €100K → Winner: €10M total)

**Why second**: Creates the constraint that makes decisions meaningful.

---

### Phase 3: Contract Renewals

**Goal**: First real decision point - who do you keep?

**Adds**:
- Pre-season "Contract Renewals" screen
- Wage negotiation with expiring players
- Players leave if not renewed (become free agents)
- Age and ability affect wage demands

**Why third**: First squad planning mechanic with emotional weight.

---

### Phase 4: Selling Players

**Goal**: Generate funds by offloading players.

**Adds**:
- Transfer list functionality
- AI teams make offers based on market value
- Accept/reject/counter negotiations
- Sale proceeds go to transfer budget

**Why fourth**: Natural complement to renewals. Need money? Sell someone.

---

### Phase 5: Youth Academy

**Goal**: Homegrown talent pipeline.

**Adds**:
- Generate 2-4 youth prospects each season (age 16-17)
- Variable potential (some gems, some busts)
- Academy screen to view and promote prospects
- Low initial wages, development tied to playing time

**Why fifth**: Free talent source, reduces reliance on expensive transfers.

---

### Phase 6: Buying Players (Scouting & Transfers)

**Goal**: Complete the transfer market loop.

**Adds**:
- Scout pool of available players
- Scouted info (ability, potential range, asking price)
- Bidding and contract negotiation
- Transfer fees from budget

**Why sixth**: Most complex piece. Financial constraints make it meaningful.

---

### Phase 7: Advanced Financials & Multi-Year Planning

**Goal**: Long-term strategy and realism.

**Adds**:
- TV rights revenue (league position based)
- Sponsorship deals (multi-year contracts)
- Stadium revenue
- Financial projections (3-year forecast)
- Debt system and Financial Fair Play

**Why last**: Polish and depth. Core game works without this.

---

## Data Model

### GamePlayer Additions

| Field | Type | Description |
|-------|------|-------------|
| `annual_wage` | int (cents) | Annual wage in cents (Spanish clubs think annually) |
| `contract_until` | date | **Already exists** - Contract expiry date from import |
| `is_transfer_listed` | bool | Player available for sale |
| `is_from_academy` | bool | Homegrown player flag |

**Note**: `contract_until` is already populated from Transfermarkt import data.

### Game Additions

| Field | Type | Description |
|-------|------|-------------|
| `academy_reputation` | int (1-100) | Quality of youth prospects (future) |

### New Tables

**game_finances** - 1:1 with games, tracks club finances
| Field | Type | Description |
|-------|------|-------------|
| `game_id` | uuid | FK to games |
| `balance` | int (cents) | Current club balance (can be negative) |
| `wage_budget` | int (cents) | Annual wage budget |
| `transfer_budget` | int (cents) | Available for transfers |
| `tv_revenue` | int (cents) | Season TV revenue |
| `performance_bonus` | int (cents) | League position bonus |
| `cup_bonus` | int (cents) | Cup progression prize money |
| `total_revenue` | int (cents) | Sum of all revenue |
| `wage_expense` | int (cents) | Total wages paid |
| `transfer_expense` | int (cents) | Transfer fees spent |
| `total_expense` | int (cents) | Sum of all expenses |
| `season_profit_loss` | int (cents) | Net result for season |

**GameFinancialEvent** (future) - Tracks all financial transactions
- `game_id`, `season`, `type`, `amount`, `description`, `created_at`

**YouthProspect** (future) - Academy players not yet promoted
- `game_id`, `player_id`, `potential`, `generated_season`, `promoted_at`

---

## Design Principles

### 1. Decisions Have Trade-offs
- Keep aging star = high wages, declining ability
- Sell young talent = immediate funds, lose future potential
- Promote youth = development opportunity, weaker squad now

### 2. Success Breeds Success (But Carefully)
- Winning = more revenue = bigger budget
- But wage inflation can outpace revenue growth
- Balances prevent runaway dynasties

### 3. Multiple Paths to Success
- Big club: Buy stars, high wages, win now
- Selling club: Develop and sell, sustainable profit
- Academy focus: Patience, low costs, emotional payoff

### 4. Transparency
- Player knows their wage demands upfront
- Clear budget constraints before decisions
- No hidden gotchas

---

## Revenue Streams (Reference)

### TV Revenue (Based on Squad Market Value)

Club size is proxied by squad market value - bigger clubs have larger fanbases and command higher TV fees.

| Squad Value | La Liga TV | La Liga 2 TV |
|-------------|------------|--------------|
| €800M+      | €140M      | -            |
| €600M+      | €110M      | -            |
| €400M+      | €90M       | -            |
| €200M+      | €60M       | €15M         |
| €100M+      | €40M       | €10M         |
| €50M+       | €25M       | €7M          |
| <€50M       | €15M       | €5M          |

### Performance Bonus (League Position)

Linear interpolation from 1st to 20th place:
- 1st place: €15M
- 10th place: ~€7.5M
- 20th place: €0

### Cup Prize Money (Cumulative)

| Round | Prize (per round) | Cumulative |
|-------|-------------------|------------|
| Round of 64 | €100K | €100K |
| Round of 32 | €250K | €350K |
| Round of 16 | €500K | €850K |
| Quarter-finals | €1M | €1.85M |
| Semi-finals | €2M | €3.85M |
| Final | €5M | €8.85M |
| Winner | +€10M | €18.85M |

### Wage Guidelines (% of Market Value per Year)

| Player Tier       | Annual Wage % | Example (Market Value → Wage)    |
|-------------------|---------------|----------------------------------|
| Elite (€100M+)    | 17.5%         | €180M → €31.5M/year              |
| Star (€50-100M)   | 15%           | €80M → €12M/year                 |
| Regular (€10-50M) | 10-12.5%      | €20M → €2-2.5M/year              |
| Squad (€2-10M)    | 8-10%         | €5M → €400-500K/year             |
| Youth (<€2M)      | 8%            | €500K → €40K/year                |

### Age-Based Wage Modifiers

Contracts reflect when they were signed, not current market value:

| Age       | Modifier | Rationale                                      |
|-----------|----------|------------------------------------------------|
| 17        | 0.40x    | First pro contract, no negotiating power       |
| 18        | 0.50x    | Still on rookie deal                           |
| 19        | 0.60x    | Early career, limited leverage                 |
| 20        | 0.70x    | Developing talent                              |
| 21        | 0.80x    | Approaching prime                              |
| 22        | 0.90x    | Near-prime                                     |
| 23-29     | 1.00x    | Fair market value                              |
| 30        | 1.30x    | Starting legacy premium                        |
| 31        | 1.60x    | Signed big contract at peak                    |
| 32        | 2.00x    | Peak contract, value declining                 |
| 33        | 2.50x    | Significant overpay relative to value          |
| 34        | 3.00x    | Late career premium                            |
| 35        | 4.00x    | Legacy contract                                |
| 36        | 5.00x    | End-of-career legend deals                     |
| 37        | 6.00x    | Exceptional veteran                            |
| 38+       | 7.00x    | Club legend status                             |

**Examples:**
- Yamal (18, €120M): €120M × 17.5% × 0.5 = **€10.5M** (rookie deal - bargain!)
- Pedri (23, €80M): €80M × 15% × 1.0 = **€12M** (fair market)
- Lewandowski (37, €15M): €15M × 11% × 6.0 = **€9.9M** (legacy premium)
- Modric (40, €6M): €6M × 8% × 7.0 = **€3.4M** (late career reduction)

### Minimum Wage Constraints

Spanish football has regulated minimum salaries, stored in the `competitions` table:

| League    | Minimum Annual Wage | Database Field              |
|-----------|---------------------|-----------------------------|
| La Liga   | €200,000            | `minimum_annual_wage` cents |
| La Liga 2 | €100,000            | `minimum_annual_wage` cents |
| Cups      | none                | inherit from team's league  |

All calculated wages are enforced to respect these minimums. The ContractService queries the competition to get the applicable minimum.

### Wage Variance

Players with identical market values should have different wages (±10% variance) to create squad diversity and negotiation dynamics.

---

## Implementation Status

- [x] Phase 1: Contracts & Wage Bill
  - [x] Migration: `annual_wage` added to `game_players`
  - [x] Migration: `minimum_annual_wage` added to `competitions`
  - [x] ContractService with wage calculation, variance, minimums
  - [x] Age-based wage modifiers (young = rookie deals, veterans = legacy contracts)
  - [x] GameProjector generates wages for new games
  - [x] Squad UI shows wages and total wage bill
  - [x] Backfill command for existing games
- [x] Phase 2: Basic Financial Model
  - [x] Migration: `game_finances` table
  - [x] GameFinances model with formatted accessors
  - [x] FinancialService with revenue/expense calculations
  - [x] TV revenue based on squad market value (club size proxy)
  - [x] Performance bonus based on league position
  - [x] Cup prize money cumulative per round
  - [x] FinancialProcessor for season-end P&L calculation
  - [x] FinancialResetProcessor for new season budget preparation
  - [x] GameProjector initializes finances for new games
  - [x] Dashboard shows finances card (balance, budgets)
  - [x] Season-end screen shows full P&L breakdown
  - [x] Backfill command for existing games
- [ ] Phase 3: Contract Renewals
- [ ] Phase 4: Selling Players
- [ ] Phase 5: Youth Academy
- [ ] Phase 6: Buying Players
- [ ] Phase 7: Advanced Financials
