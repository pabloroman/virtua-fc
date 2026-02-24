# Transfer Market System

How players are bought, sold, loaned, and contracted.

## Components

The transfer market has five interconnected parts:

1. **Scouting** — Search for players from other clubs
2. **Buying** — Bid on scouted players with counter-offer negotiation
3. **Selling** — List players and receive AI offers
4. **Loans** — Bidirectional loan system (in and out)
5. **Contracts** — Renewals, pre-contracts, and free transfers

## Scouting

Scouting tier (from budget allocation) determines geographic scope, search speed, number of results, and ability estimation accuracy. Higher tiers unlock international searches and reduce the fuzz on reported abilities. Search duration depends on scope breadth and tier. See `ScoutingService`.

## Buying

When bidding on a player, the selling club calculates an **asking price** based on market value, the player's importance to their team, contract length, and age. Bids are evaluated relative to the asking price — key players require higher bids. Responses are: accept, counter-offer (midpoint of bid and asking price), or reject. See `ScoutingService::evaluateBid()`.

Transfers complete immediately if the window is open, otherwise they're marked "agreed" and complete at the next window (summer or winter).

## Selling

- **Listed players** receive offers each matchday with a configurable probability (max 3 pending).
- **Unsolicited offers** target the team's best players with a small daily chance.
- Both offer types apply age-based adjustments and randomized pricing around market value.

Buyer selection is weighted: younger players attract stronger teams, older players attract weaker teams. Max bid is capped at a percentage of the buyer's squad value. See `TransferService`.

## Loans

**Loan out**: Each matchday has a chance of finding a destination. Destinations are scored by reputation match, position need, league tier, and randomness. Search expires after a configured number of days. Players return at season end.

**Loan in**: Via scouting, request a loan for a scouted player. The selling club evaluates and may accept.

See `LoanService` for destination scoring logic.

## Contracts

### Wages

Annual wages are calculated from market value (tiered percentage) × age modifier × random variance, with a league minimum floor. Young players get discounted "rookie" contracts; veterans command an increasing legacy premium. See `ContractService::calculateAnnualWage()`.

### Renewals

Multi-round negotiation. The player's **disposition** (flexibility) is influenced by morale, appearances, age, negotiation round, and whether they have pending pre-contract offers. Disposition determines how far below their demand they'll accept. Offering more/fewer contract years also adjusts the effective offer. See `ContractService`.

### Pre-Contracts

From the winter window onward, AI clubs can approach players with expiring contracts. These are free transfers (no fee) that complete at season end.

## Transfer Windows

- **Summer**: Matchday 0 (season start)
- **Winter**: Configured matchday (typically matchday 19)
- Agreed transfers complete at the next open window

## Season-End Processing

Four processors handle transfer-related transitions:

| Processor | What it does |
|-----------|-------------|
| `LoanReturnProcessor` | Returns loaned players |
| `ContractExpirationProcessor` | Releases expired contracts (user), auto-renews (AI) |
| `PreContractTransferProcessor` | Completes agreed free transfers |
| `ContractRenewalProcessor` | Applies pending wage changes |

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Transfer/Services/TransferService.php` | Offer generation, pricing, buyer selection |
| `app/Modules/Transfer/Services/ScoutingService.php` | Search, bid evaluation, asking price |
| `app/Modules/Transfer/Services/ContractService.php` | Wages, renewal negotiation |
| `app/Modules/Transfer/Services/LoanService.php` | Loan destination scoring, search |
