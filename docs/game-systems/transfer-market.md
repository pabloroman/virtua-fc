# Transfer Market System

## Overview

The transfer market system allows managers to buy and sell players. It consists of two main components:

1. **Transfers** - Buying and selling players for a transfer fee
2. **Contracts** - Managing player contracts, renewals, and free transfers

---

## Phase 3a: Selling Players (Implemented)

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

## Phase 3b: Contract Management (Implemented)

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

### ContractService

Handles contract-related operations:

```php
class ContractService
{
    // Wage Calculation
    public function calculateAnnualWage(int $marketValueCents, int $minimumWageCents, ?int $age): int;

    // Contract Renewal
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
// Transfers
Route::get('/game/{gameId}/transfers', ShowTransfers::class);
Route::post('/game/{gameId}/transfers/list/{playerId}', ListPlayerForTransfer::class);
Route::post('/game/{gameId}/transfers/unlist/{playerId}', UnlistPlayerFromTransfer::class);
Route::post('/game/{gameId}/transfers/accept/{offerId}', AcceptTransferOffer::class);
Route::post('/game/{gameId}/transfers/reject/{offerId}', RejectTransferOffer::class);
Route::post('/game/{gameId}/transfers/renew/{playerId}', OfferRenewal::class);

// Contracts
Route::get('/game/{gameId}/squad/contracts', ShowContracts::class);
```

---

## Implementation Status

### Completed

- [x] Migration: add `transfer_status` and `transfer_listed_at` to `game_players`
- [x] Migration: add `pending_annual_wage` to `game_players`
- [x] Migration: create `transfer_offers` table
- [x] Model: `TransferOffer` with relationships and helper methods
- [x] Model: `GamePlayer` transfer and contract helper methods
- [x] `TransferService` with all transfer methods
- [x] `ContractService` with renewal methods
- [x] Pricing logic for listed, unsolicited, and pre-contract offers
- [x] AI team selection logic
- [x] Generate offers on matchday advance (listed, unsolicited, pre-contract)
- [x] Expire old offers on matchday advance
- [x] Transfer windows (Summer/Winter)
- [x] `PreContractTransferProcessor` for end of season
- [x] `ContractRenewalProcessor` for end of season
- [x] Squad page with list/unlist buttons and status badges
- [x] Transfers page with all offer types
- [x] Contracts page with renewals and pre-contracts
- [x] Accept/Reject offer actions
- [x] Offer renewal action
- [x] Financial integration (transfer fees added to balance)

### Not Implemented (Future)

- [ ] Event sourcing for transfer actions
- [ ] Buying players (Phase 3b)
- [ ] Transfer negotiations (counter-offers)
- [ ] Agent fees
- [ ] Loan system
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

### Notification System Enhancement

Currently, transfer notifications appear in context on the Transfers and Contracts pages. Future improvements:

1. **Unified Notification System** - Dedicated `Notification` model with read/unread status
2. **Notification Center** - Central page for all game events
3. **In-Game News Feed** - Timeline of transfers, renewals, league news
4. **Email-style Inbox** - Messages from board, scouts, agents

### Contract Negotiations

Currently, renewals are accepted automatically at the player's demanded terms. Future improvements:

1. **Negotiation rounds** - Back-and-forth on wage demands
2. **Player happiness** - Affects likelihood of accepting
3. **Rival interest** - Other clubs can offer better terms
4. **Contract bonuses** - Signing bonuses, performance bonuses
