# Transfer Market System

## Phase 3a: Selling Players

### Overview

Allow users to sell players from their squad, either by listing them for sale or receiving unsolicited offers for their stars.

---

## User Stories

1. **As a manager**, I want to list a player for transfer so that I can receive offers and generate funds.
2. **As a manager**, I want to see all current offers for my players so that I can decide which to accept.
3. **As a manager**, I want to accept an offer so that the player leaves and I receive the transfer fee.
4. **As a manager**, I want to reject an offer so that I can wait for better offers.
5. **As a manager**, I want to remove a player from the transfer list if I change my mind.
6. **As a manager**, I want to receive unsolicited offers for my best players from AI clubs.

---

## Database Schema

### Migration: Add transfer_status to game_players

```php
$table->string('transfer_status')->nullable(); // null, 'listed', 'sold'
$table->timestamp('transfer_listed_at')->nullable();
```

### Migration: Create transfer_offers table

```php
Schema::create('transfer_offers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('game_id');
    $table->uuid('game_player_id'); // The player being sold
    $table->uuid('offering_team_id'); // The AI club making the offer

    $table->string('offer_type'); // 'listed' or 'unsolicited'
    $table->bigInteger('transfer_fee'); // In cents
    $table->string('status'); // 'pending', 'accepted', 'rejected', 'expired', 'completed'

    $table->date('expires_at');
    $table->timestamps();

    $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
    $table->foreign('game_player_id')->references('id')->on('game_players')->cascadeOnDelete();
    $table->foreign('offering_team_id')->references('id')->on('teams');
});
```

---

## Pricing Logic

### Listed Players (User wants to sell)
```
Base Price = Market Value
Modifier = 0.85 to 0.95 (random) — buyer has leverage
Age Adjustment:
  - Under 23: +10% (young talent premium)
  - 23-29: no adjustment
  - 30+: -5% per year over 29

Final = Base × Modifier × Age Adjustment
```

### Unsolicited Offers (AI wants to poach)
```
Base Price = Market Value
Modifier = 1.00 to 1.20 (random) — they're trying to tempt you
Age Adjustment: same as above

Final = Base × Modifier × Age Adjustment
```

### Example Calculations

| Player | Age | Market Value | Type | Modifier | Age Adj | Offer |
|--------|-----|--------------|------|----------|---------|-------|
| Vinicius | 24 | €180M | Unsolicited | 1.10 | 1.00 | €198M |
| Modric | 39 | €6M | Listed | 0.90 | 0.50 | €2.7M |
| Endrick | 18 | €60M | Listed | 0.92 | 1.10 | €60.7M |

---

## Offer Generation

### When Player is Listed
- Generate **1-3 offers** immediately
- Offers come from random AI teams in the same league (or top teams from other leagues for stars)
- Offers expire in **7 game days**

### Unsolicited Offers (Star Players)
- Triggered on **each matchday advance**
- **5% chance** per star player (top 5 by market value in your squad)
- Only if player is NOT already listed
- Offers expire in **5 game days** (more urgent)

---

## Services

### TransferService

```php
class TransferService
{
    // List a player for transfer
    public function listPlayer(GamePlayer $player): void;

    // Remove from transfer list
    public function unlistPlayer(GamePlayer $player): void;

    // Generate offers for a listed player
    public function generateOffersForListedPlayer(GamePlayer $player): Collection;

    // Generate unsolicited offers for star players (called on matchday advance)
    public function generateUnsolicitedOffers(Game $game): Collection;

    // Accept an offer
    public function acceptOffer(TransferOffer $offer): void;

    // Reject an offer
    public function rejectOffer(TransferOffer $offer): void;

    // Expire old offers (called periodically)
    public function expireOffers(Game $game): void;

    // Calculate offer price
    public function calculateOfferPrice(GamePlayer $player, string $offerType): int;

    // Get eligible AI teams to make offers
    public function getEligibleBuyers(GamePlayer $player): Collection;
}
```

---

## UI Components

### 1. Squad Page - Add "List for Transfer" Action

On each player row, add a dropdown or button:
- If not listed: "List for Transfer" button
- If listed: "Remove from List" button + "Listed" badge

### 2. Transfers Page (New)

**URL**: `/game/{gameId}/transfers`

**Sections**:

```
┌─────────────────────────────────────────────────────────────┐
│  TRANSFERS                                                  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  INCOMING OFFERS (3)                                        │
│  ───────────────────                                        │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Vinicius Jr.        Real Sociedad offers €42M       │   │
│  │ LW · 24 · €45M      Expires: 3 days                 │   │
│  │                     [Accept] [Reject]               │   │
│  └─────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Rodrygo             Atlético Madrid offers €88M     │   │
│  │ RW · 23 · €90M      ⭐ UNSOLICITED                  │   │
│  │                     Expires: 2 days                 │   │
│  │                     [Accept] [Reject]               │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  LISTED PLAYERS (2)                                         │
│  ──────────────────                                         │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Dani Ceballos       No offers yet                   │   │
│  │ CM · 28 · €12M      Listed 2 days ago               │   │
│  │                     [Remove from List]              │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 3. Navigation

Add "Transfers" to the game header navigation (after Finances).

---

## Event Sourcing

### Commands

```php
// List player for transfer
class ListPlayerForTransfer {
    public string $gamePlayerId;
}

// Accept transfer offer
class AcceptTransferOffer {
    public string $offerId;
}

// Reject transfer offer
class RejectTransferOffer {
    public string $offerId;
}
```

### Events

```php
// Player listed
class PlayerListedForTransfer {
    public string $gamePlayerId;
}

// Offer received
class TransferOfferReceived {
    public string $offerId;
    public string $gamePlayerId;
    public string $offeringTeamId;
    public int $transferFee;
    public string $offerType;
}

// Offer accepted - player sold
class TransferOfferAccepted {
    public string $offerId;
    public string $gamePlayerId;
    public string $buyingTeamId;
    public int $transferFee;
}

// Offer rejected
class TransferOfferRejected {
    public string $offerId;
}
```

---

## Implementation Checklist

### Database
- [ ] Migration: add `transfer_status` and `transfer_listed_at` to `game_players`
- [ ] Migration: create `transfer_offers` table
- [ ] Model: `TransferOffer` with relationships

### Services
- [ ] `TransferService` with all methods
- [ ] Pricing logic for listed vs unsolicited
- [ ] AI team selection logic

### Game Flow Integration
- [ ] Generate unsolicited offers on matchday advance
- [ ] Expire old offers on matchday advance

### UI
- [ ] Update Squad page with list/unlist buttons
- [ ] Create Transfers page
- [ ] Add Transfers to navigation
- [ ] Accept/Reject offer actions

### Event Sourcing
- [ ] Commands for list, accept, reject
- [ ] Events for all transfer actions
- [ ] Projector handlers

### Financial Integration
- [ ] On sale: add fee to `transfer_budget`
- [ ] On sale: recalculate wage bill
- [ ] Track in `game_finances.transfer_expense` (as negative = income)

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_add_transfer_status_to_game_players.php` | Add transfer fields |
| `database/migrations/xxxx_create_transfer_offers_table.php` | Offers table |
| `app/Models/TransferOffer.php` | Eloquent model |
| `app/Game/Services/TransferService.php` | Core transfer logic |
| `app/Game/Commands/ListPlayerForTransfer.php` | Command |
| `app/Game/Commands/AcceptTransferOffer.php` | Command |
| `app/Game/Events/PlayerListedForTransfer.php` | Event |
| `app/Game/Events/TransferOfferReceived.php` | Event |
| `app/Game/Events/TransferOfferAccepted.php` | Event |
| `app/Http/Views/ShowTransfers.php` | Transfers page controller |
| `app/Http/Actions/ListPlayerForTransfer.php` | Action |
| `app/Http/Actions/AcceptTransferOffer.php` | Action |
| `app/Http/Actions/RejectTransferOffer.php` | Action |
| `resources/views/transfers.blade.php` | Transfers page view |

## Files to Modify

| File | Changes |
|------|---------|
| `app/Models/GamePlayer.php` | Add transfer_status accessors |
| `app/Game/GameProjector.php` | Handle transfer events |
| `resources/views/squad.blade.php` | Add list/unlist buttons |
| `resources/views/components/game-header.blade.php` | Add Transfers nav link |
| `routes/web.php` | Add transfer routes |

---

## Future Improvements

### Notification System Enhancement

Currently, transfer notifications are displayed in the "Transfer News" section on the game dashboard alongside squad alerts (injuries, suspensions, etc.). This is a temporary solution.

**Future improvements to consider:**

1. **Unified Notification System**
   - Create a dedicated `Notification` model to store all game events
   - Types: transfer_offer_received, transfer_offer_expiring, transfer_completed, injury, suspension, contract_expiring, etc.
   - Mark notifications as read/unread
   - Show notification badge in header with unread count

2. **Notification Center**
   - Dedicated page to view all notifications
   - Filter by type (transfers, injuries, contracts, etc.)
   - Mark all as read functionality
   - Notification history

3. **In-Game News Feed**
   - Timeline-style display of all game events
   - Include AI team transfers, league news, etc.
   - More immersive experience

4. **Email-style Inbox**
   - Messages from board, scouts, agents
   - Transfer negotiations as message threads
   - Contract renewal requests
