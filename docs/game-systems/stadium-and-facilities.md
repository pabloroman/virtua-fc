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
5. **AI parity.** Every user-facing decision has a matching AI heuristic.
6. **Subsidy floor stays.** The existing public-subsidy protection for struggling clubs is preserved end-to-end. The design space to shape is the ceiling, not the floor.
7. **Mobile-first surface.** Every screen works at 375px. Categorical decisions over spreadsheets.

## End-State Vision

The mature system has four pillars layered on shared plumbing.

### Plumbing: Attendance & Fan Base

Per-match attendance replaces annualized matchday revenue. Each fixture computes a concrete attendance number from a demand curve.

**Demand curve inputs:** fan base, reputation, league position, opponent quality, derby/rival flag, competition weight (CL knockout > league > cup early round), recent form, ticket pricing tier, stadium capacity cap.

**Fan base** is a slow-moving stat (0–100) starting from reputation and shifted by trophies, European nights, promotion/relegation, homegrown stars, scandals, long-term pricing policy. Changes a little per season, a lot over multi-season arcs. Sticky in both directions — Newcastle doesn't lose its fans in the Championship.

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

Offers generated in the season-closing window. User accepts, rejects, or counters. Performance bonuses trigger at season settlement from standings and competition outcomes. This is where the "performance → money" flywheel delivers its most dramatic payoffs.

### Pillar 4 — Merchandising (Integrated, Not Standalone)

Modelled as royalties on the kit manufacturer contract, scaled by fan base, marquee-player presence (future Player-module attribute), and recent competition footprint. Not a separate decision surface — the user influences merch through signings and sponsorship choices.

## Integration With Existing Systems

| System | Integration |
|--------|-------------|
| **Finance** | `BudgetProjectionService` consumes active sponsor contracts and expected attendance; `SeasonSettlementProcessor` reconciles actual attendance + triggered bonuses. Replaces `calculateMatchdayRevenue()` and `calculateCommercialRevenue()`. Subsidy floor unchanged. |
| **Season** | New processors in `SeasonClosingPipeline`: sponsor offer generation, stadium construction tick, fan base update. |
| **Match** | `MatchFinalizationService` records per-match attendance; matchday revenue becomes the sum of per-match tickets. |
| **Competition** | Performance bonuses triggered by standings + knockout outcomes. |
| **Notification** | New types: sponsor offer, bonus triggered, construction complete, fan satisfaction warning. |
| **Reputation** | Fan base is a new slow-moving club stat that complements reputation; sponsor value scales with both. |
| **Player** | Future: marquee-player attribute feeds merchandising royalties. |

## What This Replaces

- `Team.stadium_seats` / `Team.stadium_name` migrate to a new `ClubStadium` model on `ClubProfile`; capacity becomes mutable.
- `GameInvestment.facilities_tier` retired in Phase 4; itemised facilities take over the 1.0–1.6× matchday multiplier.
- `config/finances.php` `commercial_per_seat` and `commercial_growth` retired in Phase 3; sponsor-contract income takes over.
- `BudgetProjectionService::calculateMatchdayRevenue()` becomes a sum over expected match attendance.
- `BudgetProjectionService::getBaseCommercialRevenue()` becomes a sum over active sponsor contracts.

## Roadmap

### Phase 1 — Plumbing (no user decisions)

Goal: establish attendance and fan base as first-class concepts with visible output, without adding any new decision surface.

- New `ClubStadium` model on `ClubProfile` (moves `stadium_name`/`stadium_seats` off `Team`).
- New `MatchAttendance` record per fixture.
- Demand curve service computing attendance from fan base, reputation, position, opponent, competition, form.
- New `fan_base` stat on `ClubProfile` (0–100). Seeded from reputation. Updated at season close by a new processor (trophies, promotion/relegation, European nights, homegrown academy graduates).
- `SeasonSettlementProcessor::calculateMatchdayRevenue()` replaced by a sum over recorded attendance × current per-reputation per-seat rate.
- `BudgetProjectionService::calculateMatchdayRevenue()` projects attendance deterministically from the same curve.
- New Stadium page: capacity, fan base, last-match attendance, projected vs. actual matchday revenue. No controls yet.
- Match reports show attendance.
- AI: no changes (deterministic curve applies equally to all clubs).

Deliverable: visible attendance in every match, foundational fan-base stat, no regressions to projected totals.

### Phase 2 — Ticketing as a decision layer

Goal: one meaningful player decision; validate elasticity and fan-satisfaction mechanics.

- Ticket-pricing policy presets (Accessible / Standard / Premium / Elite) on `ClubStadium`.
- Demand curve incorporates pricing-tier elasticity.
- Fan satisfaction effect: sustained premium pricing drains fan base; accessible pricing + success boosts it.
- Optional: season-ticket allocation slider.
- AI heuristic: reputation-and-performance-driven pricing.
- Notifications: fan unrest warning on sustained high pricing + poor results.

Deliverable: first real player decision, first fan-base feedback loop.

### Phase 3 — Commercial portfolio (sponsor contracts)

Goal: replace lump-sum commercial revenue with negotiated contracts and performance bonuses — the heart of the flywheel.

- New `SponsorContract` model (slot type, club, base value, duration, performance clauses, status).
- `SponsorOfferGenerationProcessor` in `SeasonClosingPipeline` creates offers scaled by reputation, fan base, and competition footprint.
- UI: contracts screen with active deals, pending offers, expiring deals.
- `BudgetProjectionService::getBaseCommercialRevenue()` replaced by a sum over active contracts.
- Performance bonuses trigger at settlement.
- AI heuristic: accept/decline/counter based on portfolio value.
- Initial slots: main shirt, kit manufacturer, stadium naming (unlocked by reputation).
- Rebalancing pass: adjust so baseline commercial income lands in the same ballpark as today's lump sum, to avoid cascading effects on wages, transfer budgets, and AI.

Deliverable: replaces commercial revenue with the most emotionally-rewarding payoff loop in the feature.

### Phase 4 — Stadium capital

Goal: long-term infrastructure strategy — the slowest, most transformative lever.

- Capacity expansion projects (committed at season end, completed after N seasons, paid in instalments).
- Itemised facilities replacing `GameInvestment.facilities_tier`.
- Naming-rights slot on `ClubStadium` linked to Pillar-3 sponsorships.
- Maintenance cost on `operating_expenses` scales with capacity and facility count.
- UI: stadium-upgrade planner.
- AI heuristic: reputation-driven expansion triggers.

Deliverable: multi-season progression arc.

## Open Design Questions

1. Does **fan base** surface to players as a 0–100 number, a qualitative label ("devoted / loyal / casual"), or indirectly via attendance trend?
2. Is **per-match ticket price tweaking** allowed, or is ticketing strictly an annual policy with category tiers?
3. How aggressive should **Phase 3 rebalancing** be? Replacing `commercial_per_seat` changes every club's baseline income and cascades into wages, transfer budgets, and AI behaviour.
4. Do we want **financial distress states** (debt, transfer bans) in parallel with the subsidy floor, or does the floor continue to prevent collapse?
5. Does **stadium expansion** reduce capacity *during* construction (Barcelona model) or only add capacity on completion (simpler)?
6. Are **naming rights** tied to the whole stadium only, or can clubs sell stand/section naming (West Ham "Betway Stand" model)?
7. How do we surface **marquee-player merchandising** without demanding a full star-power attribute on Player in Phase 3? Option: model merch as a flat fan-base multiplier in Phase 3, introduce star-power later.

## Key Files

| File | Role |
|------|------|
| `app/Modules/Finance/Services/BudgetProjectionService.php` | Matchday + commercial projection (replaced progressively) |
| `app/Modules/Season/Processors/SeasonSettlementProcessor.php` | Revenue settlement (replaced progressively) |
| `app/Modules/Season/Services/SeasonClosingPipeline.php` | New processors for sponsor offers, construction, fan base |
| `app/Modules/Match/Services/MatchFinalizationService.php` | Records per-match attendance (Phase 1) |
| `app/Models/ClubProfile.php` | New relations: `ClubStadium`, `fan_base`, `SponsorContract[]` |
| `app/Models/Team.php` | `stadium_name`/`stadium_seats` migrate off to `ClubStadium` |
| `config/finances.php` | `commercial_per_seat`/`commercial_growth` retired in Phase 3 |

## Related Docs

- [Club Economy System](club-economy-system.md)
- [Season Lifecycle](season-lifecycle.md)
- [Matchday Advancement](matchday-advancement.md)
- [Reputation System](reputation-system.md)
