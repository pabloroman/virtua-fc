# Stadium & Facilities

> **Status:** Long-term vision. Phase 1 is the first implementable slice; later phases are directional and will be refined when we get to them.

The club's physical infrastructure, matchday operation, fan base, and commercial portfolio — modelled as a living system rather than the static economic backdrop it is today.

## Purpose

Turn four static inputs (fixed seat count, reputation-indexed matchday rate, lump-sum commercial revenue, monolithic facilities tier) into an interacting system of decisions and slow-moving stats. Its core design goal is a legible **"on-pitch success → off-pitch money → squad power → on-pitch success"** flywheel, with memorable payoff events (sold-out derby, kit-deal activation bonus, naming-rights renewal) replacing invisible annual arithmetic.

## Design Principles

1. **One flywheel, legible payoff.** Every lever connects visibly to recent on-pitch performance. Title won? Sponsor activation fires. CL qualified? Kit deal tier unlocks. Big derby? Stadium fills. Avoid invisible income.
2. **Slow variables are inputs, not outputs.** Fan base, reputation, stadium capacity move slowly and *drive* fast variables (attendance, sponsor value). Never the other way around.
3. **Presets before sliders.** User decisions use discrete, curated options (pricing tier, accept/reject sponsor offer). Free-text numbers invite degenerate optimization and heavy balancing work.
4. **Replace, don't stack.** Where this system owns a revenue line (matchday, commercial), it **replaces** the existing formula. Revenue is never double-counted.
5. **AI parity.** Every user-facing decision has a matching AI heuristic so the league economy stays balanced across 40+ clubs. Concrete AI behaviour is specified per phase in the roadmap below.
6. **Subsidy floor stays.** The existing public-subsidy protection for struggling clubs is preserved end-to-end. The design space to shape is the ceiling, not the floor.

## End-State Vision

The mature system has four pillars layered on shared plumbing.

### Plumbing: Attendance & Fan Base

Per-match attendance replaces annualized matchday revenue. Each fixture computes a concrete attendance number from a demand curve.

**Demand curve inputs:** fan base, reputation, league position, opponent quality, derby/rival flag, competition weight (CL knockout > league > cup early round), recent form, ticket pricing tier, stadium capacity cap.

**Fan base** is a slow-moving stat (0–100) shifted by trophies, European nights, promotion/relegation, homegrown stars, scandals, long-term pricing policy. Changes a little per season, a lot over multi-season arcs. Sticky in both directions — Newcastle doesn't lose its fans in the Championship.

**Fan base is not a pure function of reputation.** Some clubs have disproportionately loyal followings for their competitive level — Athletic Bilbao, Newcastle, St. Pauli, Union Berlin routinely sell out while sitting outside the elite tier. Others underperform their reputation on the terraces (sparse attendances at notionally big clubs). The seeded starting value therefore combines a reputation-tier baseline with a **per-club loyalty bias** (`ClubProfile.fan_loyalty`, a static attribute curated in reference data). The bias is applied once at seed time and then the stat evolves dynamically from there. Loyalty also slightly amplifies the demand curve so that high-loyalty clubs fill their stadiums even in lean years.

Displayed in match reports and a new Stadium page.

### Pillar 1 — Stadium (Capital, Multi-Season)

- **Capacity expansion** projects committed at season end, resolved over 1–2 seasons via a closing/setup processor. Paid in instalments. Running cost rises on completion.
- **Itemised facilities** replace the monolithic `GameInvestment.facilities_tier`. Discrete items (VIP boxes, hospitality suites, heating, big screen, fan zone, improved catering) each with specific revenue or fan-satisfaction effects. Sum replaces the current 1.0–1.6× tier multiplier.
- **Naming rights** slot, negotiated as a long-term stadium-linked sponsorship (see Pillar 3).
- **Maintenance cost** scales with capacity and facility count — real trade-off for clubs expanding faster than their competitive level can justify.

### Pillar 2 — Ticketing (Operational Policy)

- **Pricing tier** per season: 3–5 discrete tiers (Accessible / Standard / Premium / Elite) with built-in elasticity.
- **Fixture categories**: matches classified A/B/C by opponent prestige + competition; each category has its own pricing within the chosen tier.
- **Season tickets**: allocation slider (guaranteed revenue floor vs. matchday flexibility). Renewal rate tied to previous-season success.
- **Fan satisfaction**: overpricing drains fan base; fair pricing + success boosts it.

### Pillar 3 — Commercial Portfolio (Sponsor Contracts)

Replaces the `commercial_per_seat × position_growth` lump sum with negotiated, slot-based contracts.

| Slot | Unlock | Term | Structure |
|------|--------|------|-----------|
| Main shirt sponsor | All clubs | 2–4 seasons | Base + CL/title bonus |
| Kit manufacturer | All clubs | 3–5 seasons | Base + merch royalties + CL bonus |
| Sleeve sponsor | Established+ | 1–3 seasons | Base + performance bonuses |
| Training kit sponsor | Established+ | 1–3 seasons | Base |
| Stadium naming rights | Continental+ | 5–10 seasons | Large base + minor bonus |
| Official partners (N) | Scaled by fan base | 1–2 seasons | Flat base |

Offers generated in the **off-season window** — specifically in `SeasonSetupPipeline` before `BudgetProjectionProcessor`, so the new sponsor values feed the upcoming season's budget projection. User accepts, rejects, or counters. Performance bonuses trigger at season settlement from standings and competition outcomes. This is where the "performance → money" flywheel delivers its most dramatic payoffs.

### Pillar 4 — Merchandising (Integrated, Not Standalone)

Modelled as royalties on the kit manufacturer contract, scaled by fan base, marquee-player presence (future Player-module attribute), and recent competition footprint. Not a separate decision surface — the user influences merch through signings and sponsorship choices.

## Integration With Existing Systems

| System | Integration |
|--------|-------------|
| **Finance** | `BudgetProjectionService` consumes active sponsor contracts and expected attendance; `SeasonSettlementProcessor` reconciles actual attendance + triggered bonuses. Replaces `calculateMatchdayRevenue()` and `calculateCommercialRevenue()`. Subsidy floor unchanged. |
| **Season** | New processors across both pipelines. `SeasonClosingPipeline` runs the fan-base update and the stadium-construction tick (both reflect just-finished-season outcomes). `SeasonSetupPipeline` runs sponsor-offer generation before `BudgetProjectionProcessor` so new sponsor values feed the next season's budget. |
| **Match** | `MatchFinalizationService` records per-match attendance; matchday revenue becomes the sum of per-match tickets. |
| **Competition** | Performance bonuses triggered by standings + knockout outcomes. |
| **Notification** | New types: sponsor offer, bonus triggered, construction complete, fan satisfaction warning. |
| **Reputation** | Fan base is a new slow-moving club stat that complements reputation; sponsor value scales with both. |
| **Player** | Future: marquee-player attribute feeds merchandising royalties. |

## What This Replaces

- `Team.stadium_seats` / `Team.stadium_name` migrate to a new game-scoped `ClubStadium` model in Phase 5; capacity becomes mutable. (Phase 1 keeps them on `Team`.)
- `GameInvestment.facilities_tier` retired in Phase 5; itemised facilities take over the 1.0–1.6× matchday multiplier.
- `config/finances.php` `commercial_per_seat` and `commercial_growth` retired in Phase 4; sponsor-contract income takes over.
- `BudgetProjectionService::calculateMatchdayRevenue()` becomes a sum over expected match attendance.
- `BudgetProjectionService::getBaseCommercialRevenue()` becomes a sum over active sponsor contracts.

## Roadmap

### Phase 1 — Plumbing (no user decisions)

Goal: establish attendance and fan base as first-class concepts with visible output, without adding any new decision surface.

- New `fan_base` column on `TeamReputation` (game-scoped, 0–100). Seeded from reputation tier plus per-club `fan_loyalty` bias (see below). Updated at season close by a new `FanBaseUpdateProcessor` (trophies, promotion/relegation, European nights, homegrown academy graduates).
- New `fan_loyalty` column on `ClubProfile` (static per-club, small signed integer). Curated in reference data so high-loyalty clubs (Athletic Bilbao, Newcastle, St. Pauli, Union Berlin) start with a fan base above their tier baseline and vice versa.
- New `MatchAttendance` record per fixture, computed and persisted **pre-match** (in `MatchdayOrchestrator::processBatch` before simulation) so the live-match screen can display the figure and future phases can hook atmosphere events.
- Demand curve service computing attendance from fan base, reputation, position, opponent, competition weight, loyalty amplifier, capacity cap. Recent form and derby flags deferred to later phases.
- `SeasonSettlementProcessor::calculateMatchdayRevenue()` replaced by a sum over recorded attendance × current per-reputation per-seat rate.
- `BudgetProjectionService::calculateMatchdayRevenue()` projects attendance deterministically from the same curve.
- Match reports show attendance.
- AI: no changes (deterministic curve applies equally to all clubs).

**Deferred from original Phase 1 scope:** The dedicated `ClubStadium` model (and migrating `stadium_name`/`stadium_seats` off `Team`) is pushed to Phase 5, when capacity expansion and naming rights actually need mutable per-game stadium state. Phase 1 continues to read capacity and venue name from `Team`.

Deliverable: visible attendance in every match, foundational fan-base stat, no regressions to projected totals.

**Shipping note:** Phase 1 ships as a backend-only step. The only user-visible output is the attendance figure in match reports. The dedicated Stadium page arrives in Phase 2.

### Phase 2 — UI Skeleton (Club hub)

Goal: introduce a top-level **Club** hub in the navigation to house the non-sporting side of club management, and give the Phase 1 plumbing a visible home.

- New top-level navigation item **Club** — a shell for Finance, Stadium, Reputation, and future non-sporting club features. Replaces the current top-level **Finances** entry so the nav bar stays short on mobile.
- Move the existing **Finances** page under **Club**.
- New **Stadium** page under **Club**: capacity, fan base, last-match attendance, projected vs. actual matchday revenue. No controls yet.
- New **Reputation** page under **Club**: reputation milestones, season-over-season growth, current reputation. Visualises the existing reputation system.
- AI: no decisions (UI-only phase).

Deliverable: `Club` hub is live with three initial tenants (Finances, Stadium, Reputation); Phase 1 plumbing becomes visible to the player.

### Phase 3 — Ticketing as a decision layer

Goal: one meaningful player decision; validate elasticity and fan-satisfaction mechanics.

- Ticket-pricing policy presets (Accessible / Standard / Premium / Elite) on `ClubStadium`.
- Demand curve incorporates pricing-tier elasticity.
- Fan satisfaction effect: sustained premium pricing drains fan base; accessible pricing + success boosts it.
- Per-matchday **Día del Club** checkbox (default off) on home league fixtures. Applies a supplement on season-ticket holders, boosting matchday revenue for that match with a small attendance drop and a fan-base cost that grows with repeated use. No season cap — the compounding fan-base cost is the natural brake.
- Optional: season-ticket allocation slider.
- AI heuristic: each club picks a pricing tier based on reputation + recent form. Elite clubs default to Premium, mid-table to Standard, strugglers to Accessible. Clubs drop a tier after sustained low attendance. Día del Club has no dedicated AI behaviour — AI matchday revenue is governed by a general heuristic.
- Notifications: fan unrest warning on sustained high pricing + poor results.

Deliverable: first real player decision, first fan-base feedback loop.

### Phase 4 — Commercial portfolio (sponsor contracts)

Goal: replace lump-sum commercial revenue with negotiated contracts and performance bonuses — the heart of the flywheel.

- New `SponsorContract` model (slot type, club, base value, duration, performance clauses, status).
- `SponsorOfferGenerationProcessor` in `SeasonSetupPipeline` (before `BudgetProjectionProcessor`) creates offers scaled by reputation, fan base, and competition footprint.
- UI: contracts screen with active deals, pending offers, expiring deals.
- `BudgetProjectionService::getBaseCommercialRevenue()` replaced by a sum over active contracts.
- Performance bonuses trigger at settlement.
- AI heuristic: each club accepts the highest-value offer for every empty slot. Counters only trigger for established+ clubs with existing deals near expiry. A slot is never left empty if any offer is on the table.
- Initial slots: main shirt, kit manufacturer, stadium naming (unlocked by reputation).
- Rebalancing pass: adjust so baseline commercial income lands in the same ballpark as today's lump sum, to avoid cascading effects on wages, transfer budgets, and AI.

Deliverable: replaces commercial revenue with the most emotionally-rewarding payoff loop in the feature.

### Phase 5 — Stadium capital

Goal: long-term infrastructure strategy — the slowest, most transformative lever.

- Capacity expansion projects (committed at season end, completed after N seasons, paid in instalments). **Barcelona model**: capacity is reduced during construction rather than additive-only, making expansion a real multi-season commitment.
- Itemised facilities replacing `GameInvestment.facilities_tier`.
- Naming-rights slot on `ClubStadium` linked to Pillar-3 sponsorships (whole-stadium only, no per-stand deals).
- Maintenance cost on `operating_expenses` scales with capacity and facility count.
- UI: stadium-upgrade planner.
- AI heuristic: clubs trigger expansion when fan base consistently exceeds current capacity utilisation and budget allows. Elite clubs with 95%+ sellout rates are the most aggressive. Never expand while in financial distress.

Deliverable: multi-season progression arc.

## Design Decisions

### Resolved

- **Fan base surface**: displayed to the player as a 0–100 number.
- **Stadium expansion during construction**: Barcelona model — capacity drops during the build, not additive-only. Makes expansion a real multi-season commitment.
- **Naming rights granularity**: whole-stadium only; no per-stand / per-section deals.
- **Día del Club mechanic**: per-matchday checkbox (default off) available on every home league fixture. Ticking it applies a supplement on season-ticket holders for that match, multiplying matchday revenue at the cost of a small attendance drop (socios who skip rather than pay) and a fan-base hit. The fan-base hit grows with repeated use within a season — frequency is self-regulating, no hard season cap. Home league fixtures only (Copa and CL finals are played at neutral venues and therefore cannot be home games). This is an intentional carve-out from Principle 5 (AI parity): the mechanic is narrow enough that AI clubs don't get a dedicated heuristic — their matchday revenue is handled by a general AI heuristic instead.

### Decided later (non-blocking for the vision)

- **Phase 4 rebalancing aggressiveness.** Replacing `commercial_per_seat` cascades into wages, transfer budgets, and AI behaviour. Balancing pass during Phase 4 implementation.
- **Financial distress states** (debt, transfer bans) beyond the current subsidy floor. Revisit when/if gameplay needs a harder failure mode.
- **Marquee-player merchandising surface.** In Phase 4, merch is a flat fan-base multiplier; a proper star-power attribute on Player is deferred to a later phase.

## Key Files

| File | Role |
|------|------|
| `app/Modules/Finance/Services/BudgetProjectionService.php` | Matchday + commercial projection (replaced progressively) |
| `app/Modules/Season/Processors/SeasonSettlementProcessor.php` | Revenue settlement (replaced progressively) |
| `app/Modules/Season/Services/SeasonClosingPipeline.php` | New processors: fan-base update, stadium-construction tick |
| `app/Modules/Season/Services/SeasonSetupPipeline.php` | New processor: sponsor-offer generation, before `BudgetProjectionProcessor` (Phase 4) |
| `app/Modules/Match/Services/MatchdayOrchestrator.php` | Pre-match hook that persists `MatchAttendance` before simulation (Phase 1) |
| `app/Models/TeamReputation.php` | New column: `fan_base` (game-scoped, Phase 1) |
| `app/Models/ClubProfile.php` | New column: `fan_loyalty` (static per-club bias, Phase 1). Later phases add `SponsorContract[]` |
| `app/Models/Team.php` | `stadium_name`/`stadium_seats` migrate off to a game-scoped `ClubStadium` in Phase 5 |
| `config/finances.php` | New keys: `baseline_fill_rate`, `fan_base_seed`, `fan_base_deltas` (Phase 1). `commercial_per_seat`/`commercial_growth` retired in Phase 4 |

## Related Docs

- [Club Economy System](club-economy-system.md)
- [Season Lifecycle](season-lifecycle.md)
- [Matchday Advancement](matchday-advancement.md)
- [Reputation System](reputation-system.md)
