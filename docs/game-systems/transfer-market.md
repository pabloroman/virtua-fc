# Transfer Market System

## Overview

The transfer market system allows managers to buy and sell players. It consists of five main components:

1. **Scouting** - Search and discover players from other clubs
2. **Buying** - Bid on scouted players with counter-offer negotiation
3. **Selling** - List players and receive offers from AI clubs
4. **Loans** - Loan players in and out
5. **Contracts** - Managing player contracts, renewals, pre-contracts, and free transfers

---

## Scouting System

The scouting system is the primary way to find and recruit new players.

### Scouting Tiers

The scouting tier (determined by budget allocation) affects search speed, accuracy, and geographic scope:

| Tier | Scope | Weeks Reduction | Extra Results | Ability Fuzz Reduction |
|------|-------|-----------------|---------------|------------------------|
| Tier 0 | None | 0 | 0 | 0 |
| Tier 1 | Your league only | 0 | 0 | 0 |
| Tier 2 | All of Spain | 0 | +1 | −2 |
| Tier 3 | Europe (international) | −1 week | +2 | −4 |
| Tier 4 | Global (international) | −1 week | +3 | −6 |

**Base results:** 5-8 players. **Ability fuzz:** rand(3,7) minus fuzz reduction (minimum 1).

Tier 3+ is required for international searches.

### Search Duration

- **Narrow search** (specific position + domestic): 1 week base
- **Medium search**: 2 weeks base
- **Broad search** (position group like "any_defender"): 3 weeks base

Tier's weeks reduction is subtracted (minimum 1 week).

### Search Flow

```
1. User selects search filters (position, age, ability, market value, scope)
2. Search takes 1-3 weeks depending on scope and scouting tier
3. Results return 5-8+ players biased toward availability, with 1-2 "stretch targets"
4. User can bid on any player, request a loan, or submit a pre-contract offer
```

---

## Buying Players

Players can be bought through the scouting system.

### Asking Price Calculation

When evaluating a bid, the selling club calculates an asking price:

```
asking_price = market_value × importance_multiplier × contract_modifier × age_modifier
```

**Importance multiplier** (0.0 to 1.0, based on rank within team):
```
importance_multiplier = 1.0 + (importance × 1.0)
Range: 1.0x (worst player) to 2.0x (best player)
Players with importance > 0.85 are "key players"
```

**Contract modifier:**

| Years Remaining | Multiplier |
|-----------------|------------|
| 4+ years | 1.2x |
| 3+ years | 1.1x |
| 2+ years | 1.0x |
| 1+ year | 0.85x |
| Expiring / None | 0.5x |

**Age modifier:**

| Age | Modifier |
|-----|----------|
| Under 23 | 1.15x (young talent premium) |
| 23-29 | 1.0x |
| 29+ | max(0.5, 1.0 − (age − 29) × 0.05) |

### Bid Evaluation

The selling club evaluates bids differently for key vs non-key players:

**Key players** (importance > 0.85):

| Bid / Asking Price | Response |
|---------------------|----------|
| ≥ 105% | Accepted |
| 85-105% | Counter-offer |
| < 85% | Rejected |

**Non-key players:**

| Bid / Asking Price | Response |
|---------------------|----------|
| ≥ 95% | Accepted |
| 75-95% | Counter-offer |
| < 75% | Rejected |

**Counter-offer calculation:** `(bid + asking_price) / 2`, rounded to nearest €100K. If counter ≤ bid after rounding, auto-accepts instead.

### Transfer Completion

- If the **transfer window is open** → transfer completes immediately
- If the **window is closed** → marked as "agreed", completes at next window (Summer matchday 0, Winter matchday 19)

---

## Selling Players

### Offer Generation

- **Listed players:** **40%** chance per matchday of receiving a new offer (max 3 pending per player)
- **Unsolicited offers (star players):** **5%** chance per matchday for top 5 best players on team
- **Pre-contract offers:** **10%** chance per matchday (January-May only, expiring players)

### Pricing Logic

**Listed Players** (buyer has leverage):
```
Offer = Market Value × rand(0.85, 0.95) × Age Adjustment
```

**Unsolicited Offers** (seller has leverage):
```
Offer = Market Value × rand(1.00, 1.20) × Age Adjustment
```

**Age Adjustment:**
- Under 23: ×1.10 (young talent premium)
- 23-29: ×1.0
- 30+: max(0.5, 1.0 − 0.05 per year over 29)

All offers rounded to nearest €100K.

### Offer Expiry

| Type | Duration |
|------|----------|
| Listed | 7 days |
| Unsolicited | 5 days |
| Pre-contract | 14 days |

### Buyer Eligibility & Selection

**Eligible buyers:** AI teams in domestic leagues (excluding player's current team)

**Max transfer fee:** 30% of buyer's total squad value

**Buyer selection weighting** (who's most likely to bid):

| Player Stage | Weighting |
|--------------|-----------|
| Growing (≤23) | Stronger teams preferred 3:1 |
| Peak (24-28) | Uniform (all teams equally likely) |
| Declining (≥29) | Weaker teams preferred 3:1 |

---

## Loan System

Bidirectional loan system for both incoming and outgoing players.

### Loan Out (Your Players)

```
1. User lists a player for loan
2. Each matchday: 50% chance of finding a destination
3. Destination scored by: reputation match, position need, league tier, random variety
4. Best-scoring club from top 5 candidates is selected
5. Search expires after 21 days if no match found (minimum score: 20 points)
6. Player returns to your team at season end
```

### Loan Destination Scoring (0-100 points)

| Factor | Max Points | Logic |
|--------|-----------|-------|
| **Reputation match** | 40 | Distance between player's expected tier and destination tier. 0 tiers = 40, 1 tier = 30, 2 = 15, 3+ = 5 |
| **Position need** | 30 | Players at that position: ≤1 = 30, 2 = 20, 3 = 10, 4+ = 0 |
| **League tier** | 20 | Growing/low-ability players prefer smaller clubs; peak/high prefer bigger |
| **Random variety** | 10 | Breaks ties |

### Loan In (From Other Clubs)

Via the scouting system, you can request loans for scouted players. The selling club evaluates the request and may accept.

### Loan Tracking

Active loans show in/out status and auto-return at season end via `LoanReturnProcessor` (priority 3 in the season-end pipeline).

---

## Contract Management

### Wage Calculation

Annual wages are calculated from market value with age modifiers:

```
annual_wage = market_value × wage_percentage × age_modifier × variance(±10%)
annual_wage = max(annual_wage, league_minimum_wage)
```

**Wage percentage by market value:**

| Market Value | Wage % |
|--------------|--------|
| €100M+ | 17.5% |
| €50-100M | 15.0% |
| €20-50M | 12.5% |
| €10-20M | 11.0% |
| €5-10M | 10.0% |
| €2-5M | 9.0% |
| Under €2M | 8.0% |

**Age wage modifiers:**

| Age | Modifier | Rationale |
|-----|----------|-----------|
| 17 | 0.40x | Rookie contract |
| 18 | 0.50x | |
| 19 | 0.60x | |
| 20 | 0.70x | |
| 21 | 0.80x | |
| 22 | 0.90x | |
| 23-29 | 1.0x | Fair market (prime years) |
| 30 | 1.30x | Legacy premium begins |
| 31 | 1.60x | |
| 32 | 2.00x | |
| 33 | 2.50x | |
| 34 | 3.00x | |
| 35 | 4.00x | |
| 36 | 5.00x | |
| 37 | 6.00x | |
| 38+ | 7.00x | Legend-level contracts |

**League minimum wages:**

| League | Minimum |
|--------|---------|
| La Liga (Tier 1) | €200K/year |
| La Liga 2 (Tier 2) | €100K/year |

### Contract Renewal

```
1. Player's contract expires at end of season (June 30)
2. User can offer renewal from the Contracts page
3. Player demands: max(current_wage × 1.15, market_wage)
4. Contract length based on age: 3yr (<30), 2yr (30-32), 1yr (33+)
5. Multi-round negotiation with counter-offers
```

### Renewal Negotiation

The player's **disposition** determines how flexible they are:

**Base disposition:** 0.50

| Factor | Modifier |
|--------|----------|
| Morale ≥ 80 | +0.15 |
| Morale ≥ 60 | +0.08 |
| Morale < 40 | −0.10 |
| Appearances ≥ 25 | +0.10 |
| Appearances ≥ 15 | +0.05 |
| Appearances < 10 | −0.10 |
| Age ≥ 32 | +0.12 |
| Age ≥ 29 | +0.05 |
| Age ≤ 23 | −0.08 |
| Round 2 | −0.05 |
| Round 3+ | −0.10 |
| Has pending pre-contract | −0.15 |
| Pre-contract window open (no offer) | −0.08 |

Disposition clamped to [0.10, 0.95].

**Flexibility:** `disposition × 0.30`

**Minimum acceptable:** `player_demand × (1.0 − flexibility)`

**Contract years modifier** (adjusts effective offer):

| Years Offered vs Preferred | Modifier |
|---------------------------|----------|
| Same | 1.00x |
| +1 year | 1.08x |
| +2 years | 1.15x |
| −1 year | 0.90x |
| −2 years | 0.80x |

**Outcomes:**
- Effective offer ≥ minimum → **Accept**
- Effective offer ≥ 85% of minimum AND round < 3 → **Counter** (midpoint of minimum + demand)
- Otherwise → **Reject**

### Pre-Contract Flow

```
1. From matchday 19+, AI clubs can approach expiring players
2. 10% chance per matchday per eligible player
3. Pre-contract offers have NO transfer fee (free transfer)
4. If user accepts: player finishes season, then moves to new club
5. At season end: PreContractTransferProcessor transfers player
```

---

## End of Season Processing

Three processors handle transfer-related changes at season end:

### LoanReturnProcessor (Priority 3)
- Returns all loaned players to their parent teams
- Notifies user's team of returning players

### PreContractTransferProcessor (Priority 5)
- Transfers players who agreed to pre-contracts to their new clubs
- No fee involved (free transfer)

### ContractRenewalProcessor (Priority 6)
- Applies pending wage increases from renewals
- Clears pending_annual_wage field after applying

### ContractExpirationProcessor (Priority 5)
- Releases user's expired-contract players
- Auto-renews AI teams' expired contracts (2 years)

---

## Constants Summary

```php
// TransferService - Pricing
LISTED_PRICE_MIN = 0.85;
LISTED_PRICE_MAX = 0.95;
UNSOLICITED_PRICE_MIN = 1.00;
UNSOLICITED_PRICE_MAX = 1.20;
AGE_PREMIUM_UNDER_23 = 1.10;
AGE_PENALTY_PER_YEAR_OVER_29 = 0.05;

// TransferService - Timing
LISTED_OFFER_EXPIRY_DAYS = 7;
UNSOLICITED_OFFER_EXPIRY_DAYS = 5;
PRE_CONTRACT_OFFER_EXPIRY_DAYS = 14;
WINTER_WINDOW_MATCHDAY = 19;

// TransferService - Probabilities
LISTED_OFFER_CHANCE = 0.40;       // 40% per matchday
UNSOLICITED_OFFER_CHANCE = 0.05;  // 5% per matchday
PRE_CONTRACT_OFFER_CHANCE = 0.10; // 10% per matchday
STAR_PLAYER_COUNT = 5;

// TransferService - Buyer eligibility
MAX_BID_SQUAD_VALUE_RATIO = 0.30; // 30% of buyer's squad value

// ContractService
RENEWAL_PREMIUM = 1.15;           // 15% raise minimum
DEFAULT_RENEWAL_YEARS = 3;        // <30 = 3yr, 30-32 = 2yr, 33+ = 1yr

// LoanService
LOAN_SEARCH_DAYS = 21;
LOAN_MATCH_CHANCE = 0.50;         // 50% per matchday
LOAN_MIN_SCORE = 20;
```

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Transfer/Services/TransferService.php` | Offer generation, pricing, buyer selection |
| `app/Modules/Transfer/Services/ScoutingService.php` | Search, bid evaluation, asking price |
| `app/Modules/Transfer/Services/ContractService.php` | Wage calculation, renewal negotiation |
| `app/Modules/Transfer/Services/LoanService.php` | Loan destination scoring, search |
| `app/Modules/Season/Processors/LoanReturnProcessor.php` | Season-end loan returns |
| `app/Modules/Season/Processors/PreContractTransferProcessor.php` | Season-end pre-contract transfers |
| `app/Modules/Season/Processors/ContractRenewalProcessor.php` | Season-end wage application |
| `app/Modules/Season/Processors/ContractExpirationProcessor.php` | Season-end contract expiry |
