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

### Phase 3: Transfer Market (Buy & Sell)

**Goal**: Active squad building - the core fun of football management.

**Selling Players**:
- Transfer list a player (mark as available)
- AI clubs make offers based on market value (±20% variance)
- Accept or reject offers
- Player leaves → transfer fee added to budget
- Wage bill reduced

**Buying Players**:
- Scout pool of available players (other La Liga teams, free agents)
- View scouted info: name, age, position, market value, wage demands, potential range
- Make transfer bid (from transfer budget)
- Negotiate wages (affects wage budget)
- Player joins → fee deducted, wages added

**Transfer Window**:
- Available during pre-season and between matches
- Or simplified: anytime (less realistic but more accessible)

**UI**:
- New "Transfers" page in navigation
- Two sections: "Scout Players" and "Sell Players"
- Incoming/outgoing offer notifications

**Why third**: This is the most engaging gameplay loop. Financial constraints from Phase 2 make decisions meaningful.

---

### Phase 4: Youth Academy

**Goal**: Homegrown talent pipeline - free players with high potential.

**Adds**:
- Generate 2-4 youth prospects each season (age 16-17)
- Variable potential (some gems, some busts)
- Academy screen to view prospects
- Promote to first team (low initial wages)
- Development tied to playing time

**Why fourth**: Free talent source, reduces reliance on expensive transfers. Rewards patience.

---

### Phase 5: Contract Renewals

**Goal**: Manage expiring contracts - keep your best players.

**Adds**:
- Pre-season "Contract Renewals" screen
- Players with expiring contracts demand new terms
- Wage negotiation (age and ability affect demands)
- Accept, negotiate, or let walk
- Players leave if not renewed (become free agents)

**Why fifth**: Natural maintenance task. By now the user has bought/sold players and understands wages. Renewals add long-term squad planning.

---

### Phase 6: Advanced Financials & Multi-Year Planning

**Goal**: Long-term strategy and realism.

**Adds**:
- Sponsorship deals (multi-year contracts)
- Stadium revenue
- Financial projections (3-year forecast)
- Debt system and Financial Fair Play
- Board expectations and objectives

**Why last**: Polish and depth. Core game works without this.

---

## Data Model

### GamePlayer Additions

| Field | Type | Description |
|-------|------|-------------|
| `annual_wage` | int (cents) | Annual wage in cents (Spanish clubs think annually) |
| `contract_until` | date | **Already exists** - Contract expiry date from import |
| `transfer_status` | enum | null, 'listed', 'sold' - Transfer availability |
| `is_from_academy` | bool | Homegrown player flag (future) |

**Note**: `contract_until` is already populated from Transfermarkt import data.

### Game Additions

| Field | Type | Description |
|-------|------|-------------|
| `academy_reputation` | int (1-100) | Quality of youth prospects (future) |

### New Tables

**game_finances** ✓ - 1:1 with games, tracks club finances
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

**transfer_offers** - Tracks buy/sell offers
| Field | Type | Description |
|-------|------|-------------|
| `id` | uuid | Primary key |
| `game_id` | uuid | FK to games |
| `player_id` | uuid | FK to players (reference data) |
| `game_player_id` | uuid | FK to game_players (if selling own player) |
| `from_team_id` | uuid | Buying club |
| `to_team_id` | uuid | Selling club |
| `direction` | enum | 'incoming' (AI buying from you) or 'outgoing' (you buying) |
| `offer_type` | enum | 'listed' (you put player for sale) or 'unsolicited' (AI poaching) |
| `transfer_fee` | int (cents) | Offered/agreed fee |
| `wage_offered` | int (cents) | Proposed annual wage (for buys) |
| `status` | enum | 'pending', 'accepted', 'rejected', 'rejected_by_player', 'completed', 'expired' |
| `expires_at` | date | When offer expires |
| `created_at` | timestamp | When offer was made |

**scout_pool** - Available players to buy (regenerated periodically)
| Field | Type | Description |
|-------|------|-------------|
| `id` | uuid | Primary key |
| `game_id` | uuid | FK to games |
| `player_id` | uuid | FK to players (reference data) |
| `team_id` | uuid | Current team |
| `asking_price` | int (cents) | Club's asking price |
| `wage_demand` | int (cents) | Player's wage expectation |
| `available_until` | date | When player leaves the pool |
| `scouted` | bool | Has user viewed detailed info? |

**YouthProspect** (Phase 4) - Academy players not yet promoted
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

## Player Agency in Transfers (Future Feature)

This section documents the planned player agency system for transfers. Not implemented in v1, but the data model and flow are designed to accommodate it later.

### Overview

Players have opinions about transfers. A transfer requires **three parties** to agree:
1. **Selling club** (you or AI)
2. **Buying club** (you or AI)
3. **The player** (wants to move or not?)

### Player Transfer Stance

Each player has an implicit stance based on their situation:

| Stance | Description | Behavior |
|--------|-------------|----------|
| `happy` | Wants to stay | Likely rejects external approaches |
| `neutral` | Open to offers | Will consider any reasonable move |
| `unsettled` | Wants to leave | Pushes for transfer, accepts quickly |

### Factors That Determine Stance

| Factor | Pulls Toward "Unsettled" | Pulls Toward "Happy" |
|--------|--------------------------|----------------------|
| **Playing time** | Low appearances, benched | Regular starter |
| **Morale** | Morale < 50 | Morale > 75 |
| **Contract** | Last year remaining | Long-term security |
| **Age** | Young + stuck behind star | Veteran at dream club |
| **Club stature** | Small club, big club calling | Already at top club |

**Calculation** (future):
```php
// Pseudo-code for stance calculation
$playingTimeScore = $appearances / $expectedAppearances; // 0-1
$moraleScore = $morale / 100; // 0-1
$contractScore = $yearsRemaining / 4; // 0-1 (4+ years = 1)

$happinessScore = ($playingTimeScore * 0.4) + ($moraleScore * 0.4) + ($contractScore * 0.2);

if ($happinessScore > 0.7) return 'happy';
if ($happinessScore < 0.3) return 'unsettled';
return 'neutral';
```

### Impact on Transfer Scenarios

| Scenario | Player Stance | Outcome |
|----------|---------------|---------|
| You list player for sale | Happy | Player may refuse to talk to buyers |
| You list player for sale | Unsettled | Accepts any reasonable offer quickly |
| Unsolicited offer arrives | Happy | Player rejects approach (offer blocked) |
| Unsolicited offer arrives | Unsettled | Player urges you to accept |
| You reject a good offer | Unsettled | Morale drops, may hand in transfer request |

### Transfer Request (Future)

Unhappy players may formally request a transfer:
- Triggered when morale stays low + low playing time for extended period
- Creates pressure to sell (further morale drop if ignored)
- Other clubs see player is available, make offers
- Selling price may drop (everyone knows player wants out)

### Data Model Additions (Future)

```
game_players:
  + transfer_stance: enum('happy', 'neutral', 'unsettled') - calculated or cached
  + transfer_request: bool - player has formally requested transfer
  + transfer_request_date: date - when request was made
```

### v1 vs v2 Compatibility

| Aspect | v1 (Now) | v2 (Add Player Agency) |
|--------|----------|------------------------|
| Accept/reject offers | Club decision only | Club accepts → player decides |
| Offer status values | 'rejected' | Add 'rejected_by_player' |
| Morale tracking | Already exists ✓ | Used for stance calculation |
| Playing time tracking | Already exists ✓ | Used for stance calculation |
| Transfer requests | Not implemented | Add notification + forced listing |

The v1 implementation is designed to allow inserting a "player decision" step in the offer flow without breaking changes.

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
  - [x] Dedicated Finances page in navigation
  - [x] Season-end screen shows full P&L breakdown
  - [x] Backfill command for existing games
- [ ] Phase 3a: Selling Players
  - [ ] Migration: `transfer_status` field on `game_players`
  - [ ] Migration: `transfer_offers` table
  - [ ] TransferService for offer generation and acceptance
  - [ ] Transfer list UI (mark players as available)
  - [ ] AI offer generation for listed players
  - [ ] Accept/reject offer flow
  - [ ] Player removal and financial update on sale
- [ ] Phase 3b: Signing Players
  - [ ] Migration: `scout_pool` table
  - [ ] ScoutingService for player pool generation
  - [ ] Scout UI (browse available players)
  - [ ] Make bid flow
  - [ ] Wage negotiation
  - [ ] Player addition and financial update on signing
- [ ] Phase 4: Youth Academy
- [ ] Phase 5: Contract Renewals
- [ ] Phase 6: Advanced Financials
