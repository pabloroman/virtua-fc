# Release Clauses (Cláusulas de Rescisión) — Build Plan

> **Status:** planned / not yet implemented. Unlike the other documents in this folder
> (which describe shipped systems conceptually), this is an implementation build plan with
> code references and phasing. Once the feature ships, distil the conceptual half into the
> standard game-systems style and trim the implementation detail.

## Overview

A **release clause** is a pre-agreed fee written into a player's contract that lets another
club force a sale by paying it: the selling club **cannot refuse the fee**, but the player
can still reject **personal terms**. Clauses are **mandatory** for Spanish (`ES`) clubs and
**optional** elsewhere. Both the user and AI can trigger them. A clause is a **contract
attribute**, negotiated near market value via a "golden handcuffs" model.

This mirrors real Spanish football, where buyout clauses are effectively mandatory in every
La Liga / Segunda contract (Royal Decree 1006/1985) and rare/optional in other leagues, and
follows the Football Manager "minimum fee release clause" precedent.

---

## Release strategy

**New games only — no backfill.** The clause is seeded at game initialization, exactly like
market value and salary.

- Add `release_clause` (nullable `unsignedBigInteger`, cents) to **`game_player_templates`**
  *and* **`game_players`**.
- **Precompute** the template value in `GamePlayerTemplateService::prepareTemplateRow` (the
  template knows its club country via `team()` → `Team.country`). Because seeded wages are the
  deterministic baseline, the seeded ES clause = `es_floor_multiplier × market_value_cents`;
  non-ES templates seed `NULL`. **Do not** compute at game-init.
- `SetupNewGame::initializeGamePlayersFromTemplates` copies it **verbatim** into `game_players`
  (INSERT…SELECT), alongside `market_value_cents` / `annual_wage`.

**Feature gate — existing saves see nothing.** A half-feature with no clause ecosystem is
worse than none (the only live control would let users make their own stars cheaper to poach),
so the feature is hidden entirely for existing saves. Gate via an explicit flag, mirroring the
proven `squad_registration_enabled` pattern:

- Migration: `$table->boolean('release_clauses_enabled')->default(false)` on `games` →
  **existing saves = off**. (`default(false)`, not `true` — `true` would enable every existing
  save.)
- `GameCreationService::create()`: set `'release_clauses_enabled' => true` for all new games
  (regardless of managed country).
- **Every** feature surface checks `$game->release_clauses_enabled`: clause display, explore
  "Pay clause" button, `triggerReleaseClause`, AI-trigger generation, renewal clause control.

The flag answers *"is the feature on for this game"*; the template seed provides the *initial
clause data*. New game = flag on + clauses seeded; existing save = flag off + all-null. No data
inference (games are not strictly Spain-centric — a French-club save is valid — so inferring
"feature on" from whether any clause exists would misfire).

---

## Core design

**Country predicate:** `team.country === 'ES'` (`Team.country` stores uppercase 2-char codes;
England is `'EN'`). ES → mandatory; others → optional.

**Storage & invariant:** `release_clause` on `game_players` is a contract attribute —
non-null ⟺ under contract. Set/recomputed at every contract touchpoint and **nulled at
contract expiry**. **No season-end ratchet**: the clause is frozen between agreements, so a
developing wonderkid keeps his old (cheap) clause until renewed — intentional, matching how
real Spanish clauses get out of date.

**Amount — hybrid "golden handcuffs."** Reuses the unified
`ContractService::calculateWageDemand` (`ContractService.php:283-348`). Let
`ratio = offeredWage / playerDemand`:

- `maxTolerableClause = MV × tolerance(ratio)` — a config curve rising with the wage premium,
  hard-capped (~2.5×).
- `ES floor = MV × es_floor_multiplier` (~1.25×, tunable) — the mandatory minimum.
- **Derived default** at agreement = ES floor (ES) / `null` (non-ES).
- **User may raise** within `[floor, maxTolerableClause]`; going higher requires a bigger wage
  (which lifts `maxTolerable`). Server validates `requested ≤ maxTolerable(offeredWage)`; the UI
  clamps the input to the live max.

> v1 simplification: player resistance is enforced as a **cap**, not a counter-offer round —
> this avoids threading a third axis through the chat-style negotiation UI. Full
> clause-counter-negotiation is a later enhancement.

New helper signatures on `ContractService`:

```php
calculateReleaseClause(
    int $marketValueCents,
    ?int $offeredWageCents,
    ?int $wageDemandCents,
    ?string $clubCountry,          // a country string, NOT a Team — bulk paths need no per-player Team load
    ?int $userRequestedCents = null
): ?int;

maxTolerableReleaseClause(/* … */): int;
```

All multipliers live in a new `config/finances.php` `release_clause` block.

**Ownership rule (single source of truth):** `owner = activeLoan->parent_team_id ?? team_id`.
Used for the country check, the clause value, fee routing, and trigger eligibility everywhere
— because `team_id` points at the *current location* (loan destination), not the owner.

---

## Phase 1 — Data, seeding, clause-as-contract-attribute, display *(no triggering yet)*

**Migrations**
- `release_clause` (nullable `unsignedBigInteger`) on **`game_player_templates`** and
  **`game_players`** (after `pending_annual_wage`; no timestamps; nullable ⇒ no DB default
  needed for the bulk `insert()` path).
- `triggered_release_clause` (`boolean` default `false`) on `transfer_offers`.
- `release_clauses_enabled` (`boolean` default **`false`**) on `games`.

**Seeding & init**
- `GamePlayerTemplateService::prepareTemplateRow` — precompute `release_clause` for ES-club
  templates (`= es_floor_multiplier × market_value_cents`); `null` otherwise.
- `SetupNewGame::initializeGamePlayersFromTemplates` — copy `release_clause` verbatim.
- `GameCreationService::create()` — set `release_clauses_enabled => true`.

**Models**
- `GamePlayer` — fillable/casts + `hasReleaseClause()` + `getFormattedReleaseClauseAttribute()`
  (**returns null when null** — `Money::format` has a strict `int` hint and throws on null).
- `GamePlayerTemplate` — fillable/casts.
- `TransferOffer` — `triggered_release_clause` fillable/casts.
- `Game` — `release_clauses_enabled` fillable/cast/`@property`.

**`ContractService`** — `calculateReleaseClause(...)`, `maxTolerableReleaseClause(...)`, private
ES-country check, config block.

**Recompute at in-game contract touchpoints** (all behind the flag):
- `completeFreeAgentSigning` (`TransferCompletionService.php:288-291`)
- `completeIncomingTransfer` (`:215-218`) — recompute for the **new** club
- `completePreContractTransfer` (`:130-133`) — compute from `offer.offered_wage` (this path
  does **not** write `annual_wage`)
- `processRenewal` (`ContractService.php:391-392`) — immediate mandatory ES recompute
- `PlayerGeneratorService::create/createBulk` (youth/reserve intake) — resolve the club country
  string per batch (no Team object)
- `ContractExpirationProcessor` (`:117`) — add `release_clause = null` to the free-agent bulk
  update (also fixes the pre-existing orphaned `contract_until`/`annual_wage` left on freed
  players)

**No backfill command** (new games only).

**Display** — player-detail contract section + squad views, gated on `hasReleaseClause()` **and**
`release_clauses_enabled`; verify 375px + dark/light. New `transfers.*` keys es+en
("Cláusula de rescisión", with accents).

## Phase 2 — User triggers a clause (buy an AI player)

`TransferService::triggerReleaseClause(Game $game, GamePlayer $player): TransferOffer`

- **Guards:** feature enabled; player has a clause; window open; `!$player->isUserOwned($game)`
  (server-side); **not on loan / not called-up** (block in v1); affordable via
  `availableBudget() = transfer_budget − committedBudget()`.
- **Create the INCOMING offer fully:** `offer_type = TYPE_USER_BID` (so `committedBudget()`
  reserves the fee = escrow), `direction = INCOMING`, `status = STATUS_FEE_AGREED`,
  `transfer_fee = release_clause`, `triggered_release_clause = true`,
  `offering_team_id = game->team_id`, `selling_team_id = owner`, `game_date = current_date`,
  `expires_at` set, `offered_wage`/`offered_years` bootstrapped. Mirror the existing sync-bid
  builder so no NOT-NULL FK / direction-default / date bug.
- Reject other non-terminal offers for the player; cancel any active `RenewalNegotiation`.
- Run the existing **personal-terms negotiation** (player may reject). **Wire
  `FEE_AGREED → AGREED`** when `terms_status = 'accepted'`; **expire + release reserved budget**
  if terms are rejected or stall.
- **Do NOT patch `evaluateBid`** — it is dead code on this path (`evaluateBid` only runs in
  `negotiateTransferFeeSync` on `STATUS_PENDING` offers). Forced fee acceptance comes purely
  from creating the offer at `STATUS_FEE_AGREED`.

**Salary cap** — `SalaryCapService` is enforced **human-only and up-front at the
signing/terms step**, with **no completion backstop** (agreed deals always complete, even over
cap). A clause's wage is set during the personal-terms negotiation, so the cap check belongs
there (reuse the existing terms-acceptance enforcement) and must surface a clear **up-front**
rejection — do not rely on a completion-time block, because there isn't one.

**Completion** — in `completeIncomingTransfer`, re-assert the player is still owned by the
expected seller before mutating. The transfer fee is already escrowed at trigger time via
`committedBudget()` (see below), so the buyer can't be left short; if any completion-time
budget guard does fire, emit a high-priority notification rather than silently dropping the deal.

**HTTP / UI** — `PayReleaseClause` action + `POST /game/{gameId}/transfers/pay-clause/{playerId}`
inside the `game.owner` group. Button on **`explore-player-row.blade.php`** (where AI players
appear — *not* the player-detail modal, which 404s on AI-owned players), gated in the View
layer. Confirm via `<x-modal>`. Flash `messages.*` + `transfers.*` es/en.

## Phase 3 — AI triggers a clause on the user's player

**Scope:** AI-buys-user-player only. **AI-to-AI stays on the existing `GameTransfer` path,
untouched** (AI-to-AI transfers never create `TransferOffer` rows and cap fees at market value,
so they cannot reuse this pipeline — and are out of scope).

- Daily generation: low-probability, affordability-gated **on the clause amount**
  (`AITeamBudgetCalculator` available ≥ clause, **not** market value); pick a user-owned player
  with a clause an eligible AI club wants and can afford; skip players that already have a
  non-terminal offer (exclusivity).
- Create an **OUTGOING offer directly at `STATUS_AGREED`** (skip `PENDING` — it is
  non-consensual), `triggered_release_clause = true`, `selling_team_id = user team`. Consciously
  replicate `acceptOffer` side-effects: reject the player's other pending offers; **clause
  overrides squad minimums** (skip `validateRemoval`, with a comment).
- Completes via the existing outgoing pipeline (`completeAgreedTransfers` already filters to
  user-owned players).
- **Notification:** new `GameNotification::TYPE_PLAYER_LEFT_VIA_RELEASE_CLAUSE` wired into
  `getTypeClasses()`, `getDefaultIcon()`, and `NAVIGATION_MAP` (→ transfer-activity); dedup keyed
  on `player_id`; `notifications.*_title`/`_message` pair es/en; metadata
  `{clause_amount, buying_team_id, player_id}`.

## Phase 4 — Renewals raise the clause (strategic lever)

Phase 1 already recomputes the mandatory ES clause on renewal, so Phase 4 is the **optional
user-raise UI**:

- Surface a clause control in the renewal negotiation bound to a live
  `maxTolerableReleaseClause(offeredWage)`; the user sets the clause in `[floor, max]`; raising
  it requires a bigger wage. Extend `NegotiateRenewal` (currently validates only `wage` + `years`)
  to accept + server-validate `clause`.
- **UI reality:** the renewal modal is a ~387-line **chat** component (wage stepper + years
  dropdown). Adding the clause control is a distinct sub-workstream; prefer a compact clause
  stepper bound to the live max over threading it through the chat message state.
- Non-ES clubs: same control, optional opt-in.

---

## Cross-cutting invariants

- **Feature flag** `release_clauses_enabled` gates every surface (display, trigger, AI-trigger,
  renewal control).
- **Ownership** = `activeLoan->parent_team_id ?? team_id` — used for the ES check, clause value,
  fee routing, and eligibility.
- **Loaned / called-up players** cannot be clause-triggered in v1 (user or AI).
- **Budget:** clause offers use `offer_type = TYPE_USER_BID` (reserved by `committedBudget()`);
  affordability subtracts `committedBudget()`.
- **Concurrency:** reject sibling non-terminal offers when advancing any offer to `AGREED`;
  re-assert ownership in completion (prevents double-sale).
- **Window:** `expires_at` always set; an agreed-but-unwindowed clause completes at season end
  (`AgreedTransferCompletionProcessor` priority 35 runs before the market reset at 70).
- **No season-end ratchet.**

## Internationalization (es + en)

Spanish term: **cláusula de rescisión** (with accents). Keys:
`transfers.release_clause`, `transfers.pay_release_clause`, `transfers.clause_max_tolerated`;
`messages.clause_triggered_in` / `messages.clause_triggered_out`;
`notifications.player_left_via_clause_title` / `_message`; a squad column label if shown; plus
the `GameNotification` type wiring above.

## Verification

- Template seeds the ES clause / `null` for non-ES; a new game copies it; an **existing save
  stays all-null with the flag off**; the flag gates every surface.
- ES floor + golden-handcuffs tolerance math; clause recomputed on transfer-in for the new club.
- Money-to-seller for user-incoming **and** AI-outgoing-on-user.
- Free-agent / loaned / called-up skips; `!isUserOwned` guard.
- Budget reserved on trigger + an over-budget second trigger blocked.
- `FEE_AGREED` stall → expiry + budget release; window-close-mid-deal; double-trigger exclusivity.
- AI affordability gates on clause amount, not market value; contract expiry nulls the clause.
- Simulate a season to confirm AI triggers are rare and do not destabilise budgets.
- Manual: new vs existing save behaviour; pay-clause flow end-to-end from explore (es + en).

## Rollout sequence

1. Ship the migrations (template + `game_players` + `release_clauses_enabled`) **together with**
   the reference-data re-seed (`app:seed-reference-data`) that populates the template clause, so
   a new game cannot be created flagged-on but clause-less. (Belt-and-suspenders: ES template
   seeding derives the clause deterministically from `market_value_cents` + country.)
2. Existing saves never re-read templates → zero risk of silently gaining clauses; the flag keeps
   their UI off regardless.

## Risks / notes

- Clause-as-cap is the v1 expression of "players refuse high clauses"; full counter-negotiation
  is a later enhancement.
- Pre-contract completion does not write `annual_wage` — compute the clause there from
  `offer.offered_wage`.
- Multipliers / tolerance curve stay in `config/finances.php` so balancing is a config tweak.
- Optional future: expose `release_clauses_enabled` as a user-facing toggle on the new-game
  screen.
