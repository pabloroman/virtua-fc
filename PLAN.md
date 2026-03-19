# Salary + Signing Bonus Negotiation — Implementation Plan

## Design Summary

**Core idea:** When the user submits a transfer bid, they also specify their **wage offer** and an optional **signing bonus**. The selling club evaluates the transfer fee as before. If the fee is accepted, the **player** then evaluates the wage offer in the same resolution cycle (same matchday). This adds meaningful player negotiation without adding extra matchday rounds.

**Applies to:** User bids (incoming transfers), free agent signings, and pre-contract offers.
**Does NOT apply to:** Outgoing transfers (AI buying from user), AI-generated offers.

---

## 1. Flow: User Bid with Salary Negotiation

### Current Flow
1. User submits bid (transfer fee only) → `SubmitTransferBid` action
2. Next matchday: `resolveIncomingBids()` evaluates fee → accepted/counter/rejected
3. If accepted: `completeIncomingTransfer()` auto-assigns wage from `calculateWageDemand()`

### New Flow
1. User submits bid with **fee + wage offer + signing bonus** → `SubmitTransferBid` action (updated)
2. Next matchday: `resolveIncomingBids()` evaluates fee → accepted/counter/rejected (unchanged)
3. If fee accepted: **player evaluates wage offer** (new step, same matchday)
   - **Accept**: Transfer completes. Player gets offered wage + signing bonus deducted from transfer budget.
   - **Counter**: Offer moves to `salary_countered` status. Player proposes a counter-wage. User can accept counter, submit new wage offer (re-enters the flow at step 3 on next matchday), or walk away (deal collapses, fee refunded).
   - **Reject**: Deal collapses entirely. Transfer fee is NOT deducted (fee was never actually paid since the player refused to come).

### Key Insight: No Extra Matchday Rounds for Happy Path
- Fee negotiation: 1-3 matchday rounds (unchanged)
- Salary negotiation: **0 extra rounds** if user offers fairly. Only adds rounds if the player counters and the user wants to re-negotiate.
- Counter-offer resolution is **instant** (user accepts/declines the counter in the UI, no matchday advance needed).

---

## 2. Flow: Free Agent Signing with Salary Negotiation

### Current Flow
1. User clicks "Sign Free Agent" → `SignFreeAgent` action
2. Wage auto-calculated, player joins immediately

### New Flow
1. User sees wage demand + signing bonus field on scout report
2. User submits **wage offer + signing bonus** → `SignFreeAgent` action (updated)
3. Player evaluates **immediately** (no matchday wait — free agents are eager):
   - **Accept**: Player joins with offered wage. Signing bonus deducted from transfer budget.
   - **Counter**: Return to scout report with counter-wage displayed. User can accept or adjust.
   - **Reject**: Flash error. User can try again with a better offer.

### Why Instant for Free Agents?
Free agents have no selling club to negotiate with. The only negotiation is with the player. Making them wait a matchday would add friction with no strategic value.

---

## 3. Flow: Pre-Contract Offers

### Current Flow
1. User submits pre-contract offer with wage → `SubmitPreContractOffer` action
2. Next matchday: `resolveIncomingPreContractOffers()` evaluates → accepted/rejected

### New Flow
1. User submits pre-contract offer with **wage + signing bonus** → updated action
2. Next matchday: evaluation now uses the signing bonus as a sweetener in the disposition calc
3. Accepted/rejected (no counter for pre-contracts — keeps it simple, and these are already simpler deals)

---

## 4. Data Model Changes

### `transfer_offers` table — new columns (migration)
```
signing_bonus       BIGINT DEFAULT 0    -- Signing bonus in cents (deducted from transfer budget)
offered_wage        BIGINT NULLABLE     -- Already exists! Currently auto-set; now user-provided
salary_status       VARCHAR NULLABLE    -- NULL (no negotiation yet), 'pending', 'accepted', 'countered', 'rejected'
wage_counter        BIGINT NULLABLE     -- Player's counter-wage demand (cents)
wage_demand         BIGINT NULLABLE     -- Player's initial wage demand (for UI reference)
```

**No new model needed.** The `TransferOffer` model already tracks `offered_wage`. We add `signing_bonus`, `salary_status`, `wage_counter`, and `wage_demand` to it. This avoids creating a separate `SalaryNegotiation` model — the negotiation is part of the transfer offer lifecycle.

### `game_players` table — no changes needed
The `annual_wage` field is already updated on transfer completion with `$offer->offered_wage`.

---

## 5. Salary Evaluation Logic (new method in `ContractService`)

```php
public function evaluateSalaryOffer(TransferOffer $offer): string
```

**Player's decision logic:**

1. **Calculate wage demand** — same as `calculateWageDemand()` (market-value based)
2. **Calculate disposition** — adapted from renewal negotiation:
   - Base: 0.50
   - **Club reputation bonus**: +0.15 (elite), +0.10 (continental), +0.05 (established), -0.05 (modest), -0.10 (local)
   - **Age factor**: +0.12 (32+, wants stability), +0.05 (29-31), -0.08 (≤23, has leverage)
   - **Free agent bonus**: +0.10 (no current club, more desperate)
   - **Transfer fee factor**: +0.05 if high fee paid (player knows club is invested)
3. **Calculate minimum acceptable** = demand × (1.0 - disposition × 0.30)
4. **Signing bonus effect**: Effective offer = offered_wage + (signing_bonus / contract_years × 0.5)
   - The signing bonus is treated as a partial wage equivalent (spread over contract, at 50% weight since it's one-time)
5. **Decision**:
   - effective_offer ≥ minimum_acceptable → **accept**
   - effective_offer ≥ minimum_acceptable × 0.85 → **counter** (midpoint between minimum and demand)
   - Below → **reject**

---

## 6. UI Changes

### A. Scout Report (bid submission form) — `scout-report-results.blade.php`

**Currently:** Single `bid_amount` input + "Submit Bid" button.

**New:** Three inputs in the bid form:
- `bid_amount` — Transfer fee (existing, unchanged)
- `wage_offer` — Annual wage offer (new input, pre-filled with wage demand from scout report)
- `signing_bonus` — Optional signing bonus (new input, default 0)

For free agents: Only `wage_offer` + `signing_bonus` (no fee).

### B. Incoming Transfers — `incoming-transfers.blade.php`

**New states to display:**
- `salary_countered` — Show player's counter-wage with Accept/Revise/Walk Away buttons
- `salary_rejected` — Show "Player rejected your wage offer. Deal collapsed." (informational)

### C. Notifications

New notification types:
- `salary_accepted` — "Player X accepted your wage offer of €Y/year"
- `salary_countered` — "Player X demands €Y/year (you offered €Z). Respond in transfers."
- `salary_rejected` — "Player X rejected your wage offer. The transfer has fallen through."

---

## 7. Signing Bonus Mechanics

- **Deducted from transfer budget** (same pool as transfer fees)
- **Paid on transfer completion** (not on agreement)
- **Recorded as a financial transaction** (new category: `CATEGORY_SIGNING_BONUS`)
- **Range guidance**: UI shows a suggested range (0-15% of annual wage for normal transfers, 0-30% for free agents where there's no transfer fee)
- **Effect on disposition**: Acts as a sweetener. A generous bonus can bridge a wage gap of ~10-15%.

---

## 8. Wage Cap Interaction

**No changes to the wage cap system.** The existing check already validates:
```
(currentWageBill + offeredWage) <= projectedWages × 1.10
```

The signing bonus does NOT count against the wage cap (it's a one-time payment from transfer budget, not recurring wages). This creates an interesting strategic choice: if you're near the wage cap, you can offer a lower annual wage but sweeten with a signing bonus.

---

## 9. Implementation Order

### Phase 1: Backend Core
1. Migration: Add columns to `transfer_offers`
2. `TransferOffer` model: Add new constants and accessors
3. `ContractService::evaluateSalaryOffer()` — salary evaluation logic
4. `TransferService::submitBid()` — accept wage_offer + signing_bonus params
5. `TransferService::resolveIncomingBids()` — chain salary evaluation after fee acceptance
6. `TransferService::acceptSalaryCounter()` — new method for counter acceptance
7. `TransferService::reviseSalaryOffer()` — new method for submitting revised wage
8. `TransferService::walkAwayFromSalary()` — collapse deal, no fee charged
9. `SignFreeAgent` action — add wage negotiation
10. Pre-contract offer — add signing bonus support

### Phase 2: UI
11. Scout report form — add wage_offer + signing_bonus inputs
12. Incoming transfers — salary counter/reject states
13. Action classes — `AcceptSalaryCounter`, `ReviseSalaryOffer`, `WalkAwayFromSalary`

### Phase 3: Notifications + Translations
14. NotificationService — new salary notification methods
15. Spanish + English translations for all new strings

### Phase 4: Tests
16. Unit tests for salary evaluation logic
17. Feature tests for the full negotiation flow
