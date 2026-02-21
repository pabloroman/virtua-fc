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

| Tier | Scope | Speed | Results |
|------|-------|-------|---------|
| Tier 1 | Your league only | Slow | 5-8 players |
| Tier 2 | All of Spain | Normal | 5-8 players |
| Tier 3 | Europe (top + secondary leagues) | Fast | 5-8 players |
| Tier 4 | Global + hidden gems | Fastest | 5-8 players, more accurate estimates |

### Search Flow

```
1. User selects search filters (position, age, ability, market value, scope)
2. Search takes 1-3 weeks depending on scope and scouting tier
3. Results return 5-8 players biased toward availability, with 1-2 "stretch targets"
4. User can bid on any player, request a loan, or submit a pre-contract offer
```

### Search Filters

- **Position** - Filter by player position
- **Age range** - Minimum and maximum age
- **Ability range** - Minimum and maximum ability level
- **Market value range** - Budget-aware search
- **Expiring contracts** - Find players available on free transfer
- **Scope** - Domestic or international (requires higher scouting tier)

---

## Buying Players

Players can be bought through the scouting system.

### Bid Evaluation

When you submit a bid, the selling club evaluates it based on:

- **Player importance** (0.0–1.0): How valuable the player is to their current team
- **Contract situation**: Players with expiring contracts are cheaper
- **Age**: Young players command a premium

### Bid Responses

| Response | Condition |
|----------|-----------|
| **Accepted** | Bid ≥ 105% of asking price (key players) or ≥ 95% (others) |
| **Counter-offer** | Bid in the 85-105% range of asking price |
| **Rejected** | Bid below threshold |

Counter-offers are AI-generated bids that the user can accept. This creates a natural negotiation flow.

### Transfer Completion

- If the **transfer window is open** → transfer completes immediately
- If the **window is closed** → marked as "agreed", completes at next window (Summer matchday 0, Winter matchday 19)

---

## Loan System

Bidirectional loan system for both incoming and outgoing players.

### Loan Out (Your Players)

```
1. User lists a player for loan via scouting
2. Each matchday: 50% chance of finding a destination
3. Destination scored by: reputation match, position need, league tier, random variety
4. Best-scoring club from top 5 candidates is selected
5. Search expires after 21 days if no match found
6. Player returns to your team at season end
```

### Loan In (From Other Clubs)

Via the scouting system, you can request loans for scouted players. The selling club evaluates the request and may accept.

### Loan Tracking

Active loans show in/out status and auto-return at season end via `LoanReturnProcessor` (priority 3 in the season-end pipeline).

---

## Selling Players

### Features

- **List players for transfer** - Put players on the market to receive offers
- **Receive offers** - AI clubs make offers based on player value and age
- **Unsolicited offers** - Top players receive poaching attempts from other clubs
- **Accept/Reject offers** - Decide which offers to accept
- **Transfer windows** - Sales complete at Summer (matchday 0) or Winter (matchday 19) windows

### Transfer Flow

```
1. User lists player → status = 'listed'
2. Each matchday: 40% chance of new offer (max 3 pending)
3. User accepts offer → status = 'agreed', waiting for window
4. Transfer window arrives → player.team_id changes, user receives fee
5. Player leaves squad, finances updated
```

### Pricing Logic

**Listed Players** (buyer has leverage):
```
Offer = Market Value × (0.85 to 0.95) × Age Adjustment
```

**Unsolicited Offers** (seller has leverage):
```
Offer = Market Value × (1.00 to 1.20) × Age Adjustment
```

**Age Adjustment**:
- Under 23: +10% (young talent premium)
- 23-29: no adjustment
- 30+: -5% per year over 29 (minimum 50%)

---

## Contract Management

### Features

- **Expiring contracts** - Players with contracts ending at season's end
- **Contract renewals** - Offer players a new contract to keep them
- **Pre-contract offers** - AI clubs can poach players with expiring contracts
- **Free transfers** - Players leave for free at end of contract if not renewed

### Contract Renewal Flow

```
1. Player's contract expires at end of season (June 30)
2. User can offer renewal from the Contracts page
3. Player demands: max(current wage × 1.15, market wage)
4. Contract length based on age: 3yr (<30), 2yr (30-32), 1yr (33+)
5. If accepted: contract_until extended, pending_annual_wage stored
6. At season end: ContractRenewalProcessor applies new wage
```

### Pre-Contract Flow

```
1. From matchday 19+, AI clubs can approach expiring players
2. 10% chance per matchday per eligible player
3. Pre-contract offers have NO transfer fee (free transfer)
4. If user accepts: player finishes season, then moves to new club
5. At season end: PreContractTransferProcessor transfers player
```

### Key Difference from Transfers

| Aspect | Transfer | Pre-Contract |
|--------|----------|--------------|
| Fee | Based on market value | Free (€0) |
| Timing | Complete at transfer window | Complete at end of season |
| Eligibility | Any listed player | Only expiring contracts |
| User receives | Transfer fee | Nothing |

---

## Database Schema

### game_players table additions

```php
$table->string('transfer_status')->nullable();      // null, 'listed'
$table->timestamp('transfer_listed_at')->nullable();
$table->unsignedBigInteger('pending_annual_wage')->nullable(); // For renewals
```

### transfer_offers table

```php
Schema::create('transfer_offers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('game_id');
    $table->uuid('game_player_id');
    $table->uuid('offering_team_id');

    $table->string('offer_type');     // 'listed', 'unsolicited', 'pre_contract'
    $table->bigInteger('transfer_fee'); // In cents (0 for pre-contract)
    $table->string('status');         // 'pending', 'agreed', 'rejected', 'expired', 'completed'

    $table->date('expires_at');
    $table->timestamps();
});
```

---

## Services

### ScoutingService

Handles player search, bid evaluation, and pre-contract evaluation:

```php
class ScoutingService
{
    // Search
    public function submitSearch(Game $game, array $filters): ScoutReport;
    public function processSearch(ScoutReport $report): void;
    public function cancelSearch(ScoutReport $report): void;

    // Bid Evaluation
    public function evaluateBid(GamePlayer $player, int $bidAmount): array;
    public function evaluatePreContractOffer(GamePlayer $player, int $wage): array;
}
```

### TransferService

Handles all transfer-related operations:

```php
class TransferService
{
    // Listing
    public function listPlayer(GamePlayer $player): void;
    public function unlistPlayer(GamePlayer $player): void;

    // Offer Generation (called on matchday advance)
    public function generateOffersForListedPlayers(Game $game): Collection;
    public function generateUnsolicitedOffers(Game $game): Collection;
    public function generatePreContractOffers(Game $game): Collection;

    // Offer Management
    public function acceptOffer(TransferOffer $offer): void;
    public function rejectOffer(TransferOffer $offer): void;
    public function expireOffers(Game $game): int;

    // Transfer Completion (at windows)
    public function isTransferWindow(Game $game): bool;
    public function completeAgreedTransfers(Game $game): Collection;
    public function completePreContractTransfers(Game $game): Collection;

    // Pricing
    public function calculateOfferPrice(GamePlayer $player, string $offerType): int;
    public function getEligibleBuyers(GamePlayer $player): Collection;
}
```

### LoanService

Handles bidirectional loan operations:

```php
class LoanService
{
    // Loan Out
    public function searchLoanDestination(GamePlayer $player): ?array;
    public function processLoanOut(GamePlayer $player, Team $destination): Loan;

    // Loan In
    public function processLoanIn(GamePlayer $player, Game $game): Loan;

    // Loan Management
    public function returnLoans(Game $game): Collection;
    public function getActiveLoans(Game $game): Collection;
}
```

### ContractService

Handles contract-related operations including multi-round negotiation:

```php
class ContractService
{
    // Wage Calculation
    public function calculateAnnualWage(int $marketValueCents, int $minimumWageCents, ?int $age): int;

    // Contract Renewal (with negotiation)
    public function calculateRenewalDemand(GamePlayer $player): array;
    public function processRenewal(GamePlayer $player, int $newWage, int $contractYears): bool;
    public function applyPendingWages(Game $game): Collection;

    // Queries
    public function getPlayersEligibleForRenewal(Game $game): Collection;
    public function getPlayersWithPendingRenewals(Game $game): Collection;
    public function getContractsByExpiryYear(Game $game): Collection;
    public function getExpiringContracts(Game $game, int $year): Collection;
}
```

---

## End of Season Processing

Two processors handle contract-related changes at season end:

### PreContractTransferProcessor (Priority 5)

- Runs before player development
- Transfers players who agreed to pre-contracts to their new clubs
- No fee involved (free transfer)
- Updates player's team_id and gives them a new contract (2-4 years)

### ContractRenewalProcessor (Priority 6)

- Runs after pre-contract transfers
- Applies pending wage increases from renewals
- Clears pending_annual_wage field after applying

---

## UI Pages

### Transfers Page (`/game/{gameId}/transfers`)

Shows:
- Unsolicited offers (amber) - clubs poaching your players
- Offers for listed players (blue) - responses to your listings
- Agreed transfers (green) - waiting for transfer window
- Listed players - your players on the market
- Expiring contracts notice - link to Contracts page
- Recent sales - completed transfers

### Contracts Page (`/game/{gameId}/squad/contracts`)

Shows:
- Pre-contract offers received (purple) - clubs approaching expiring players
- Players leaving on free transfer (purple) - agreed pre-contracts
- Renewals agreed (teal) - pending wage increases
- Expiring contracts (red) - players needing renewal with wage demands
- Contract overview - visual grid by expiry year

### Squad Page Integration

- "Contracts" button with red badge showing count of expiring contracts
- Links to dedicated Contracts page
- Players show status badges: "Leaving (Free)", "Renewed", "Sale Agreed", "Listed"
- Expiring contract years highlighted in red

---

## Routes

```php
// Scouting
Route::get('/game/{gameId}/scouting', ShowScoutingHub::class);
Route::get('/game/{gameId}/scouting/{reportId}/results', ShowScoutReportResults::class);
Route::post('/game/{gameId}/scouting/search', SubmitScoutSearch::class);
Route::post('/game/{gameId}/scouting/cancel', CancelScoutSearch::class);
Route::post('/game/{gameId}/scouting/{playerId}/bid', SubmitTransferBid::class);
Route::post('/game/{gameId}/scouting/{playerId}/loan', RequestLoan::class);
Route::post('/game/{gameId}/scouting/{playerId}/pre-contract', SubmitPreContractOffer::class);
Route::post('/game/{gameId}/scouting/counter/{offerId}/accept', AcceptCounterOffer::class);

// Transfers (incoming offers, outgoing)
Route::get('/game/{gameId}/transfers', ShowScouting::class);
Route::get('/game/{gameId}/transfers/outgoing', ShowTransfers::class);
Route::post('/game/{gameId}/transfers/list/{playerId}', ListPlayerForTransfer::class);
Route::post('/game/{gameId}/transfers/unlist/{playerId}', UnlistPlayerFromTransfer::class);
Route::post('/game/{gameId}/transfers/accept/{offerId}', AcceptTransferOffer::class);
Route::post('/game/{gameId}/transfers/reject/{offerId}', RejectTransferOffer::class);

// Contract Renewals (with negotiation)
Route::post('/game/{gameId}/transfers/renew/{playerId}', SubmitRenewalOffer::class);
Route::post('/game/{gameId}/transfers/accept-counter/{playerId}', AcceptRenewalCounter::class);
Route::post('/game/{gameId}/transfers/decline-renewal/{playerId}', DeclineRenewal::class);
Route::post('/game/{gameId}/transfers/reconsider-renewal/{playerId}', ReconsiderRenewal::class);
```

---

## Implementation Status

### Completed

- [x] Selling: list players, receive offers (listed + unsolicited), accept/reject
- [x] Buying: scouting hub, search with filters, bid on players, counter-offer negotiation
- [x] Loans: loan out (destination scoring), loan in (via scouting), auto-return at season end
- [x] Pre-contracts: user and AI can submit pre-contract offers for expiring contract players
- [x] Contract renewals: multi-round negotiation with counter-offers, decline, reconsider
- [x] Transfer windows (Summer matchday 0, Winter matchday 19)
- [x] `ScoutingService` with tiered search, bid evaluation, pre-contract evaluation
- [x] `TransferService` with all transfer methods
- [x] `ContractService` with negotiation logic
- [x] `LoanService` with destination scoring and both-direction loans
- [x] `PreContractTransferProcessor` for end of season
- [x] `ContractRenewalProcessor` for end of season
- [x] `LoanReturnProcessor` for end of season
- [x] Financial integration (transfer fees, loan savings, wage impact)
- [x] Notification integration (transfer results, contract events)

### Not Implemented (Future)

- [ ] Agent fees
- [ ] Release clauses
- [ ] Sell-on clauses

---

## Constants

```php
// TransferService
const LISTED_PRICE_MIN = 0.85;
const LISTED_PRICE_MAX = 0.95;
const UNSOLICITED_PRICE_MIN = 1.00;
const UNSOLICITED_PRICE_MAX = 1.20;
const AGE_PREMIUM_UNDER_23 = 1.10;
const AGE_PENALTY_PER_YEAR_OVER_29 = 0.05;
const LISTED_OFFER_EXPIRY_DAYS = 7;
const UNSOLICITED_OFFER_EXPIRY_DAYS = 5;
const PRE_CONTRACT_OFFER_EXPIRY_DAYS = 14;
const UNSOLICITED_OFFER_CHANCE = 0.05;  // 5%
const PRE_CONTRACT_OFFER_CHANCE = 0.10; // 10%
const STAR_PLAYER_COUNT = 5;
const WINTER_WINDOW_MATCHDAY = 19;

// ContractService
const RENEWAL_PREMIUM = 1.15;  // 15% raise
const DEFAULT_RENEWAL_YEARS = 3;
```

---

## Future Improvements

- **Agent fees** - Agents taking a cut of transfer deals
- **Release clauses** - Mandatory sale prices in contracts
- **Sell-on clauses** - Percentage of future sale price owed to previous club
- **Player happiness** - Affects likelihood of accepting renewal/transfer
- **Contract bonuses** - Signing bonuses, performance bonuses
