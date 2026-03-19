# Contract Negotiation — Phased Plan

## Overview

This plan introduces a **synchronous, chat-like contract negotiation interface** to replace the current async model (submit offer → wait for matchday → see result). Since the "player" is AI, the server responds instantly — no WebSocket needed, just `fetch()` POST calls from Alpine.js.

**Phase 1 (this implementation):** Synchronous renewal negotiation chat.
**Phase 2 (future):** Extend the chat to transfer salary + signing bonus negotiation (new signings, free agents, pre-contracts).

---

## Phase 1: Synchronous Renewal Chat

### Why Renewals First

- **Simpler scope** — only one counterparty (the player). No selling club involved.
- **Existing logic** — `ContractService::evaluateOffer()` already does accept/counter/reject. We just call it synchronously instead of during matchday advance.
- **Validates the UX** — proves the chat paradigm works before adding transfer complexity.
- **Biggest time savings** — renewals currently take 2-4 minutes across 1-3 matchdays. The chat reduces this to ~15 seconds in a single session. That's a 10-15x improvement.

### User Flow

1. User clicks "Negotiate" on a player with an expiring contract (from outgoing-transfers page or player-detail modal)
2. A **chat modal** opens. The player's agent speaks first: shows wage demand, contract years preference, and mood indicator
3. User fills in wage offer + contract years in the input area at the bottom → clicks "Submit Offer"
4. A brief typing indicator (300-500ms) appears, then the agent responds:
   - **Accept**: Success message, modal auto-closes after 1.5s, page reloads
   - **Counter**: Agent proposes a counter-wage. User sees Accept / Counter / Walk Away options
   - **Reject**: Agent rejects. Modal shows "Close" button only
5. If countered, user can accept (instant resolution), submit a revised offer (repeats step 4), or walk away
6. Max 3 rounds (same as current system)

### Backend: Single JSON Endpoint

**New action: `NegotiateRenewal`**
Route: `POST /game/{gameId}/negotiate/renewal/{playerId}`

```
Request:  { action: "start" | "offer" | "accept_counter" | "walk_away", wage?: int, years?: int }
Response: { status: "ok", negotiation_status: "open"|"accepted"|"rejected"|"walked_away", messages: [...] }
```

- `start` — returns player's demand + mood as opening message. If an existing `player_countered` negotiation exists, resumes it.
- `offer` — creates/updates `RenewalNegotiation`, immediately calls `evaluateOffer()`. Returns result as chat message.
- `accept_counter` — calls existing `ContractService::acceptCounterOffer()`.
- `walk_away` — cancels the negotiation.

### New Method: `ContractService::negotiateSync()`

Wraps existing `initiateNegotiation()` + `evaluateOffer()` into one synchronous call:

```php
public function negotiateSync(GamePlayer $player, int $offerWage, int $offeredYears): array
{
    // If continuing from a counter, call submitNewOffer() first
    // Then immediately evaluate (same logic as resolveRenewalNegotiations)
    // Return: ['result' => 'accepted'|'countered'|'rejected', 'negotiation' => ...]
}
```

No existing methods are modified. The async path in `CareerActionProcessor` stays but becomes a no-op (no `offer_pending` records will exist).

### State: Reuse `RenewalNegotiation` Model

No schema changes. The existing model already tracks rounds, demands, offers, counters, disposition. Records transition through states within a single request instead of across matchdays.

If the user navigates away mid-negotiation while a counter is active, they can reopen the chat — the `start` action detects the `player_countered` record and resumes.

### Cooldown

One negotiation per player per game-date. If rejected, user must advance at least one matchday before re-opening. Prevents brute-force spam while preserving temporal pacing.

### Alpine.js Component: `negotiationChat`

New file `resources/js/negotiation-chat.js` (~200-250 lines), registered in `app.js`.

Key state: `messages[]`, `loading`, `negotiationStatus`, `offerWage`, `offerYears`.

Each message: `sender` (agent/system), `type` (demand/response/accepted/rejected), `content` (text, wage, years, mood), optional `options` (canAccept, canCounter, canWalkAway, suggestedWage).

Uses the same `fetch()` pattern as `live-match.js`.

### Blade Component: `negotiation-chat-modal.blade.php`

Teleported modal at max-width `md`. Listens for `@open-negotiation.window` event.

Visual design:
- Agent messages: left-aligned bubbles on `bg-surface-700`
- User actions: right-aligned bubbles on `bg-accent-blue/15`
- System outcomes: centered, muted text
- Mood indicator in header (existing dot + label pattern)
- Fixed input area at bottom: wage input + years dropdown + Submit + Walk Away
- Typing indicator: animated dots (300-500ms delay)

### Migration from Async UI

**Remove:** "Renewal Counter-Offers" and "Renewal Offers Pending" sections from `outgoing-transfers.blade.php`.

**Replace:** Expiring contracts "Renew" button dispatches `$dispatch('open-negotiation', {...})`. Player-detail modal's `<x-renewal-modal>` replaced with chat trigger button.

**Delete:** `renewal-modal.blade.php`, `SubmitRenewalOffer.php`, `AcceptRenewalCounter.php`, associated routes.

**Keep:** `DeclineRenewal` and `ReconsiderRenewal` actions (used outside chat, from "Declined Renewals" section).

**Simplify:** `ShowOutgoingTransfers.php` — remove `$activeNegotiations`/`$negotiatingPlayers` view data.

---

## Phase 2 (Future): Transfer Salary + Signing Bonus Negotiation

Extends the same `negotiation-chat-modal` component for salary negotiation on new signings:

- **Transfer bids**: After fee is agreed with selling club, a salary chat opens with the player. User negotiates wage + optional signing bonus.
- **Free agents**: Chat opens directly (no selling club). User negotiates wage + signing bonus.
- **Pre-contracts**: Chat opens for wage + signing bonus negotiation.

### Signing Bonus Mechanic
- One-time payment from transfer budget (not wage cap)
- Acts as a sweetener: bridges ~10-15% wage gaps
- Creates strategic tension: wage cap pressure → lower salary + higher bonus
- New columns on `transfer_offers`: `signing_bonus`, `salary_status`, `wage_counter`, `wage_demand`

### New Disposition Factors (for new signings)
- Club reputation bonus/penalty
- Free agent bonus (+0.10)
- Transfer fee factor (+0.05 if high fee paid)

---

## Files to Change (Phase 1)

### New Files
| File | Purpose |
|------|---------|
| `resources/js/negotiation-chat.js` | Alpine.js chat component |
| `resources/views/components/negotiation-chat-modal.blade.php` | Chat modal template |
| `app/Http/Actions/NegotiateRenewal.php` | JSON endpoint for negotiation actions |

### Modified Files
| File | Change |
|------|--------|
| `resources/js/app.js` | Import + register `negotiationChat` |
| `routes/web.php` | Add `POST` route for negotiate endpoint |
| `app/Modules/Transfer/Services/ContractService.php` | Add `negotiateSync()` method |
| `resources/views/outgoing-transfers.blade.php` | Remove async sections, add chat modal |
| `resources/views/partials/player-detail.blade.php` | Replace `<x-renewal-modal>` with chat trigger |
| `app/Http/Views/ShowOutgoingTransfers.php` | Remove `$activeNegotiations`/`$negotiatingPlayers` |
| `lang/es/transfers.php` + `lang/en/transfers.php` | Add chat message translations |

### Files to Remove
| File | Reason |
|------|--------|
| `resources/views/components/renewal-modal.blade.php` | Replaced by negotiation-chat-modal |
| `app/Http/Actions/SubmitRenewalOffer.php` | Replaced by NegotiateRenewal |
| `app/Http/Actions/AcceptRenewalCounter.php` | Absorbed into NegotiateRenewal |

## Implementation Steps

1. Backend endpoint — `NegotiateRenewal` action + `negotiateSync()` + route
2. Alpine component — `negotiation-chat.js` with message state, fetch, typing indicator
3. Chat modal template — `negotiation-chat-modal.blade.php` with bubbles, inputs, mood
4. Wire into outgoing-transfers — replace renewal modal, remove async sections
5. Wire into player-detail — replace `<x-renewal-modal>` with chat trigger
6. Translations — Spanish + English keys for agent messages
7. Remove old files — `renewal-modal.blade.php`, `SubmitRenewalOffer.php`, `AcceptRenewalCounter.php`, old routes
8. Simplify view — clean up `ShowOutgoingTransfers.php`
9. Tests — unit test for `negotiateSync()`, feature test for JSON endpoint
