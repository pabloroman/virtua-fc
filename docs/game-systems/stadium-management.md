# Stadium & Matchday Management — Research & V1 Implementation Plan

## Context

Users have requested the ability to manage their club's stadium, facilities, and matchday ticket sales. Currently, stadium capacity is static reference data (`teams.stadium_seats`) that drives revenue but offers zero player agency. The existing design docs already anticipate this feature: *"Stadium expansion projects could increase stadium_seats over time"* and *"Stadium expansion could be tracked in a game_stadiums table that overrides teams.stadium_seats."*

This plan covers: (A) playability research, (B) feature design, (C) technical feasibility, and (D) a recommended V1 scope.

---

## A. Playability Research

### What comparable games do

| Game | Stadium Control | Ticket Pricing | Expansion | Key Tradeoff |
|------|----------------|----------------|-----------|--------------|
| **Football Manager** | None — board decides expansions based on sell-out streaks and finances. Manager can only "request" | None | Multi-season construction, funded by board | Patience — you can't rush it |
| **Top Eleven** | Direct control — build seats, upgrade facilities (parking, lights, training ground) | Per-match type pricing (league, cup, friendly). Higher price = lower attendance = lower morale boost | Incremental seat additions with currency cost + build time | Revenue vs. attendance (possession bonus) |
| **FIFA/FC Career Mode** | No stadium management | No ticket pricing | N/A | N/A |

**Key insight:** FM's approach (board decides) feels passive and frustrating — players want agency. Top Eleven's approach (granular per-match pricing + facility building) creates busy-work that conflicts with VirtuaFC's "no grinding" philosophy. The sweet spot is **seasonal strategic decisions** (like the existing budget allocation system) rather than per-match micromanagement.

### What decisions would players make and when?

| Decision | When | Frequency | Tradeoff |
|----------|------|-----------|----------|
| **Set ticket pricing tier** | Pre-season (budget allocation) | Once/season | Higher prices = more revenue per seat but lower attendance %. Lower prices = full stadium, better atmosphere bonus |
| **Launch stadium expansion** | Pre-season (budget allocation) | Once every 2-4 seasons | Costs money now (reduces transfer budget), takes 1-2 seasons to complete, increases revenue ceiling permanently |
| **Upgrade stadium facilities** | Pre-season (budget allocation) | Once/season | Already exists as "Facilities" tier — extend it to include specific upgrades (VIP boxes, fan zone, etc.) |

### The "player fantasy"

Managing a stadium in VirtuaFC should feel like being a **club president making long-term bets**: *"Do I expand the stadium now (expensive, reduces squad investment for 2 seasons) to unlock higher revenue for the next decade? Or do I keep the small stadium and invest in players to get promoted first?"*

This creates a **multi-season investment arc** that no other system in the game currently provides. Academy, medical, scouting, and facilities all reset each season — stadium expansion would be the first **persistent, cumulative investment**.

### Progression arc (5-10 seasons)

For a small club (e.g., Girona, 14,624 seats):

| Season | Action | Stadium | Revenue Impact |
|--------|--------|---------|----------------|
| 1 | Focus on squad, Facilities Tier 2 | 14,624 seats | Matchday: ~€4M |
| 2-3 | Start expansion (+4,000 seats) | 14,624 → 18,624 | Cost: ~€12M over 2 seasons |
| 4 | Expansion complete, raise ticket tier | 18,624 seats | Matchday: ~€6.5M (+60%) |
| 5-6 | Second expansion (+6,000 seats) | 18,624 → 24,624 | Cost: ~€20M |
| 7 | Add VIP boxes (Facilities Tier 3) | 24,624 seats | Matchday: ~€11M |
| 8-10 | Third expansion, premium pricing | 30,000+ seats | Matchday: ~€15M+ |

For a big club (e.g., Real Madrid, 83,186 seats):
- Expansion is less relevant (already huge)
- Focus shifts to **premium pricing**, VIP boxes, and naming rights
- Still interesting because premium pricing has attendance tradeoffs

### Interesting tradeoffs with existing systems

1. **Stadium expansion vs. Transfer budget** — Every euro spent on expansion is an euro not spent on players. A team with a small squad might overperform one season but can't sustain it without revenue growth.

2. **Ticket pricing vs. Attendance (home advantage)** — Higher prices = more revenue but lower attendance, which could reduce home advantage in match simulation. Creates a risk/reward dynamic.

3. **Facilities upgrade vs. Stadium expansion** — Facilities multiplies existing revenue; expansion increases the base. Small stadium + Tier 4 facilities might equal a big stadium + Tier 1. Player must calculate which yields better ROI.

4. **Short-term vs. Long-term** — Expansion takes 1-2 seasons to complete. During construction, you're paying but not earning. This penalizes impatient players and rewards long-term planners.

---

## B. Feature Design — V1

### V1 Core Mechanics

#### 1. Stadium Expansion (Multi-Season Projects)

Players can launch expansion projects that increase their stadium capacity over time.

**How it works:**
- At budget allocation, a new "Stadium" section shows current capacity and available expansion options
- Expansions are defined in tiers based on current capacity (you can't jump from 15K to 80K)
- Each expansion has a **cost** (paid from transfer budget / surplus) and a **build time** (1-2 seasons)
- During construction, the expansion is "in progress" — no revenue benefit yet
- When complete, `game_stadiums.capacity` increases permanently for that game
- Only one expansion at a time

**Expansion tiers:**

| Current Capacity | Expansion Size | Cost | Build Time |
|-----------------|---------------|------|------------|
| < 20,000 | +3,000 seats | €8M | 1 season |
| < 20,000 | +5,000 seats | €15M | 2 seasons |
| 20,000 - 40,000 | +5,000 seats | €15M | 1 season |
| 20,000 - 40,000 | +10,000 seats | €35M | 2 seasons |
| 40,000 - 60,000 | +8,000 seats | €30M | 1 season |
| 40,000 - 60,000 | +15,000 seats | €60M | 2 seasons |
| 60,000 - 80,000 | +10,000 seats | €50M | 2 seasons |
| 80,000+ | +5,000 seats | €40M | 2 seasons |

**Maximum capacity cap:** 105,000 seats (realistic upper bound for modern stadiums).

**Revenue impact:** Expansion directly increases `stadium_seats` used in the matchday revenue formula. A +5,000 seat expansion for a "Contenders" reputation club would yield roughly +€3M/season in matchday revenue — so a €15M investment pays back in ~5 seasons.

#### 2. Ticket Pricing Tiers (Seasonal)

Players choose a pricing strategy each season that affects the revenue-per-seat rate and attendance percentage.

**How it works:**
- At budget allocation, player selects one of 3-4 pricing tiers
- Each tier modifies revenue per seat and introduces an attendance multiplier
- Attendance affects matchday revenue (you earn less if fans don't show up)
- Attendance could also provide a small home advantage modifier in match simulation (V2)

**Pricing tiers:**

| Tier | Name | Revenue Modifier | Attendance % | Net Effect | Best For |
|------|------|-----------------|-------------|------------|----------|
| 1 | Popular (low prices) | ×0.80 | 100% | ×0.80 | Morale/atmosphere, smaller clubs building fan base |
| 2 | Standard | ×1.00 | 95% | ×0.95 | Balanced default |
| 3 | Premium | ×1.20 | 82% | ×0.984 | Near-optimal revenue, slight attendance drop |
| 4 | Exclusive | ×1.45 | 65% | ×0.9425 | Big clubs with high demand, luxury positioning |

**Design rationale:** The net effects are deliberately close (0.80 to 0.95 range), so the choice is about **club identity and secondary effects** (atmosphere, home advantage) rather than pure min-maxing. "Premium" is slightly optimal for revenue alone, but "Popular" gives a full stadium which could benefit match performance.

#### 3. Repurpose Existing "Facilities" Tier

The current Facilities investment tier (1-4) already represents stadium improvements (hospitality, corporate boxes, fan experience). Rather than creating a separate system, **keep Facilities as-is** — it already multiplies matchday revenue and the naming is generic enough to encompass stadium quality.

This means the total matchday formula becomes:

```
Matchday Revenue = (game_stadium_seats × revenue_per_seat × pricing_modifier × attendance_%)
                   × facilities_multiplier × position_factor
```

Where:
- `game_stadium_seats` = base team seats + any completed expansions
- `pricing_modifier` and `attendance_%` come from the chosen ticket pricing tier
- `facilities_multiplier` and `position_factor` work as they do today

### What V1 does NOT include (deferred to V2+)

| Feature | Why Deferred |
|---------|-------------|
| **Naming rights / sponsorship** | Adds complexity to commercial revenue model; V1 focuses on matchday |
| **Per-match ticket pricing** | Conflicts with "no grinding" philosophy; seasonal pricing is enough |
| **Attendance model based on form/opponent** | Interesting but complex; V1 uses flat attendance % per pricing tier |
| **Stadium visual progression** | Nice-to-have but not core gameplay; could add later as cosmetic |
| **Stadium naming** | Fun but cosmetic; defer to V2 |
| **Home advantage modifier from attendance** | Needs match simulation changes; V1 focuses on financial impact only |
| **Construction events/notifications** | Progress updates during build; V1 just completes at season end |
| **Relegation/promotion capacity rules** | Lower leagues requiring smaller stadiums; out of V1 scope |

---

## C. Technical Feasibility

### New Database Tables

#### `game_stadiums` — Per-game stadium state

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | PK |
| `game_id` | uuid | FK to `games` |
| `base_capacity` | integer | Starting capacity (from `teams.stadium_seats`) |
| `current_capacity` | integer | Base + completed expansions |
| `ticket_pricing_tier` | integer (1-4) | Current season's pricing choice |
| `expansion_seats` | integer, nullable | Seats being added (null = no active expansion) |
| `expansion_cost` | integer, nullable | Total cost in cents |
| `expansion_started_season` | integer, nullable | Season when expansion started |
| `expansion_duration` | integer, nullable | Seasons to complete (1 or 2) |

**Single table, single row per game.** Mutable state that evolves across seasons.

### New Migration

One migration to create `game_stadiums` table.

### Model Changes

#### New: `GameStadium` model (`app/Models/GameStadium.php`)
- Relationships: `belongsTo(Game::class)`
- Computed attributes: `is_expanding`, `expansion_completes_season`, `effective_capacity`
- Ticket pricing constants: `PRICING_TIERS` with modifiers

#### Modified: `Game` model
- Add `stadium()` relationship (`hasOne(GameStadium::class)`)

### Service Changes

#### Modified: `BudgetProjectionService` (`app/Modules/Finance/Services/BudgetProjectionService.php`)
- `calculateMatchdayRevenue()`: Use `game_stadiums.current_capacity` instead of `teams.stadium_seats` when a `GameStadium` record exists
- Apply ticket pricing tier modifiers (revenue modifier × attendance %)

#### Modified: `SeasonSettlementProcessor` (`app/Modules/Season/Processors/SeasonSettlementProcessor.php`)
- `calculateMatchdayRevenue()`: Same change — use game stadium capacity + pricing tier modifiers

#### New: `StadiumExpansionProcessor` (`app/Modules/Season/Processors/StadiumExpansionProcessor.php`)
- Priority: ~28 (after settlement at 15, before fixture generation at 30)
- Checks if any expansion project completes this season
- If complete: updates `current_capacity`, clears expansion fields
- Sends notification via `NotificationService`

### Season Pipeline Integration

The `SeasonEndPipeline` (`app/Modules/Season/Services/SeasonEndPipeline.php`) registers processors automatically. The new `StadiumExpansionProcessor` just needs to implement `SeasonEndProcessor` and declare its priority.

### Game Creation Integration

#### Modified: `GameCreationService` (`app/Modules/Season/Services/GameCreationService.php`)
- After creating the game, create a `GameStadium` record with:
  - `base_capacity` = `team.stadium_seats`
  - `current_capacity` = `team.stadium_seats`
  - `ticket_pricing_tier` = 2 (Standard, default)

### HTTP Layer

#### New View: `ShowStadium` (`app/Http/Views/ShowStadium.php`)
- Prepares data for the stadium management page
- Shows current capacity, expansion options, pricing tiers, revenue projections

#### New Action: `LaunchExpansion` (`app/Http/Actions/LaunchExpansion.php`)
- Validates player can afford expansion (from transfer budget or surplus)
- Creates expansion project on `GameStadium`
- Records `FinancialTransaction` for the cost

#### New Action: `SetTicketPricing` (`app/Http/Actions/SetTicketPricing.php`)
- Updates `ticket_pricing_tier` on `GameStadium`
- Can only be changed during pre-season (before budget is locked)

#### Modified View: `ShowFinances` — Add stadium info card showing capacity, pricing tier, active expansion

### UI / Blade Templates

#### New: `resources/views/stadium.blade.php`
- Stadium overview card (name, current capacity, pricing tier)
- Expansion section: available options with cost/duration, or in-progress expansion with countdown
- Ticket pricing selector (4 tiers with revenue impact preview)
- Revenue projection calculator ("with this pricing + expansion, your matchday revenue would be X")

#### Modified: `resources/views/finances.blade.php`
- Add stadium info summary card in the right sidebar
- Link to full stadium management page

#### Modified: Navigation
- Add "Stadium" nav item to game navigation (both desktop and mobile drawer)

### Routes

```php
Route::get('/games/{game}/stadium', ShowStadium::class)->name('game.stadium');
Route::post('/games/{game}/stadium/expand', LaunchExpansion::class)->name('game.stadium.expand');
Route::post('/games/{game}/stadium/pricing', SetTicketPricing::class)->name('game.stadium.pricing');
```

### i18n

New translation keys in both `lang/es/` and `lang/en/`:
- `stadium.php` — New file with ~30-40 keys for stadium UI (capacity, expansion, pricing tiers, etc.)

### Config

Add to `config/finances.php` or new `config/stadium.php`:
- Expansion tiers (capacity ranges, costs, durations)
- Pricing tier modifiers (revenue modifier, attendance %)
- Max capacity cap (105,000)

### Files to Create

| File | Purpose |
|------|---------|
| `app/Models/GameStadium.php` | Stadium state model |
| `database/migrations/xxxx_create_game_stadiums_table.php` | Schema |
| `app/Modules/Season/Processors/StadiumExpansionProcessor.php` | Season-end expansion completion |
| `app/Http/Views/ShowStadium.php` | Stadium page data |
| `app/Http/Actions/LaunchExpansion.php` | Start expansion |
| `app/Http/Actions/SetTicketPricing.php` | Change pricing tier |
| `resources/views/stadium.blade.php` | Stadium UI |
| `config/stadium.php` | Stadium configuration |
| `lang/es/stadium.php` | Spanish translations |
| `lang/en/stadium.php` | English translations |

### Files to Modify

| File | Change |
|------|--------|
| `app/Models/Game.php` | Add `stadium()` relationship |
| `app/Modules/Finance/Services/BudgetProjectionService.php` | Use game stadium capacity + pricing modifiers |
| `app/Modules/Season/Processors/SeasonSettlementProcessor.php` | Use game stadium capacity + pricing modifiers |
| `app/Modules/Season/Services/GameCreationService.php` | Create initial `GameStadium` on game creation |
| `app/Modules/Season/Services/SeasonEndPipeline.php` | Register `StadiumExpansionProcessor` |
| `resources/views/finances.blade.php` | Add stadium summary card |
| `resources/views/components/game-header.blade.php` | Add stadium nav link |
| `routes/web.php` | Add stadium routes |
| `lang/es/app.php` + `lang/en/app.php` | Add nav label |

---

## D. Recommended V1 Scope Summary

### Build in V1

1. **`GameStadium` model + migration** — Per-game mutable stadium state
2. **Stadium expansion projects** — Pick an expansion option, pay cost, wait 1-2 seasons, capacity increases permanently
3. **Seasonal ticket pricing** (4 tiers) — Affects revenue-per-seat rate and attendance percentage
4. **Stadium management page** — View capacity, start expansion, set pricing, see revenue projections
5. **Season-end processor** — Completes expansions when build time elapses
6. **Financial integration** — Updated matchday revenue formula uses game stadium data
7. **Notifications** — "Expansion complete! Your stadium now holds X seats"
8. **Navigation integration** — Stadium accessible from main game nav

### Explicitly NOT in V1

- Naming rights / sponsorship deals
- Per-match dynamic pricing
- Attendance model based on form/opponent/weather
- Home advantage modifier from attendance
- Stadium visual progression / cosmetics
- New stadium construction (only expansions)
- Multiple concurrent expansions

### Extensibility

The `GameStadium` model is designed to accommodate V2+ features:
- Add `naming_rights_sponsor`, `naming_rights_revenue`, `naming_rights_expires_season` columns later
- Add `home_advantage_modifier` computed from attendance
- Add `fan_engagement_level` for a fan loyalty system
- The pricing tier system can expand to per-competition pricing
- Expansion options can be made more granular (specific stand construction)

---

## E. Verification Plan

### Testing

1. **Unit test: `StadiumExpansionProcessorTest`**
   - Expansion completes when seasons elapsed >= duration
   - Capacity increases correctly
   - Expansion fields are cleared after completion
   - No-op when no expansion is active

2. **Unit test: `MatchdayRevenueTest`**
   - Revenue calculation uses game stadium capacity (not team base)
   - Pricing tier modifiers applied correctly
   - Facilities multiplier still applies on top

3. **Feature test: `StadiumManagementTest`**
   - Can view stadium page
   - Can launch expansion (deducts from budget)
   - Can't launch expansion during active one
   - Can change pricing tier in pre-season
   - Can't change pricing tier after budget locked

4. **Integration: `GameCreationTest`**
   - New game creates `GameStadium` with correct initial values

### Manual Testing

```bash
php artisan app:create-test-game
# Navigate to stadium page
# Launch an expansion
# Advance through seasons to verify completion
# Check finances page shows updated matchday revenue
```

### Run existing tests to verify no regressions

```bash
php artisan test
```

---

## F. Investment Timing Analysis

An important question surfaced during this research: **does the investment selection happen at the right time in the game, from a playability standpoint?** This analysis applies to the existing budget system and has implications for where stadium decisions should live.

### Current Game Loop Timeline

```
Season N: last match played
  → Player clicks "Start New Season"
  → Season Transition Pipeline runs (background job):
      Priority 3:   Loan returns
      Priority 5:   Archive / Contract expiration / Pre-contracts
      Priority 6-7: Renewals, retirements
      Priority 10:  Player development
      Priority 15:  Season Settlement (actual vs projected revenue reconciled)
      Priority 20:  Stats reset
      Priority 24:  Season simulation (AI seasons)
      Priority 25:  Supercup qualification
      Priority 26:  Promotion/relegation
      Priority 30:  Fixture generation
      Priority 40:  Standings reset
      Priority 50:  BudgetProjectionProcessor (projects new season revenue)
      Priority 55:  Youth academy batch generation
      Priority 105: UEFA qualification
      Priority 110: OnboardingResetProcessor (needs_onboarding = true)
  → Loading screen clears
  → ONBOARDING SCREEN:
      - Season preview (objective, competitions)
      - Budget allocation sliders (Youth / Medical / Scouting / Facilities / Transfers)
      - "Begin Season" button
  → Player allocates → CompleteOnboarding saves GameInvestment, fires SeasonStarted
  → Season N+1 starts immediately (summer window July-August)
  → Budget CAN be re-allocated mid-season (isLocked is always false)
  → 38 matchdays play out
  → Cycle repeats
```

### What Works Well

**Investment happens before you know anything — and that's correct.** You haven't played a match, don't know how transfers will go, can't predict injuries or cup draws. This mirrors how real club boards approve budgets before the season. The uncertainty creates genuine tension: *"Do I bet big on academy, or hedge with medical in case of injuries?"*

**Projections are calculated before allocation.** The player sees squad strength rankings, projected position, estimated revenue, and available surplus before touching the sliders. This is enough information to make an informed but still uncertain decision.

### What Undermines Playability

#### 1. Budget never actually locks

`ShowBudgetAllocation.php` always sets `isLocked = false`. Players can re-allocate infrastructure investment at any time via the Finances page. The UI has translation keys for `budget_locked` and `budget_locked_desc` ("Budget allocation is fixed for the season. Changes can be made at the start of next pre-season.") but **the lock is never engaged**.

**Impact:** If you can always change your mind, the pre-season allocation becomes a suggestion rather than a commitment. There's no reason to agonize over the sliders if you can adjust after matchday 10. The "trade-off tension" the system is designed to create is undermined.

#### 2. Infrastructure effects are immediate and non-persistent

When you allocate €8M to Youth Academy, you instantly get Tier 3 academy that same season. Drop to €2M next season and you're back to Tier 2. There's no build-up, no momentum, no legacy.

**Impact:** Small clubs can't gradually build infrastructure — they need to afford Tier 3+ fresh every season. There's no sense of "my club is developing over the years" because everything resets.

#### 3. All decisions happen in one moment

The onboarding screen asks the player to simultaneously decide: academy investment, medical investment, scouting investment, facilities investment, and transfer budget. That's 5 competing priorities on one screen, resolved in 30 seconds.

**Impact:** No pacing. No reveal moments. No "I should have spent more on medical" regret mid-season (since you can change it). The entire strategic layer collapses into a single interaction.

#### 4. No information evolves between decisions

You get all projections at once, make all choices at once, and start playing. There's never a moment where new information forces you to reconsider a previous choice (since you can just re-allocate anyway).

### How This Affects Stadium Feature Design

These observations lead to specific design recommendations for the stadium feature:

#### Don't add stadium to the onboarding screen

The onboarding screen already has 5 competing sliders. Adding stadium expansion and ticket pricing there would create decision overload. Stadium is a fundamentally different kind of decision (multi-season commitment) that deserves its own space.

#### Stadium should be a separate page, accessible during summer window

The narrative becomes:
1. **Pre-season onboarding** → "Here's your budget. Allocate your infrastructure and transfer budget." (seasonal, reversible)
2. **Stadium page** → "Here's your stadium. Want to expand? Change ticket prices?" (persistent, long-term)
3. **Transfer market** → "Here are available players." (tactical, reactive)

This creates three distinct decision domains with different time horizons.

#### Ticket pricing should lock after the season starts

Since the current budget allocation never locks (a playability issue), the stadium feature should at least introduce locking for its own decisions. Ticket pricing locks once the first competitive match is played. This creates a commitment the rest of the budget system lacks.

#### Stadium expansion cost should come from transfer budget

Rather than creating a new funding source, expansion should deduct from the transfer budget (the flexible pool). This creates a clear trade-off: "Do I spend €15M on a striker, or on +5,000 seats that will pay back over 5 seasons?"

### Future Considerations (Beyond Stadium V1)

These timing issues suggest broader improvements to the financial system that could come later:

| Improvement | Impact | Effort |
|------------|--------|--------|
| **Lock infrastructure allocation after matchday 1** | Restores commitment tension for all investment areas | Low — add `isLocked` check based on `current_matchday > 0` |
| **Cumulative infrastructure investment** | Small clubs can gradually build up; creates multi-season arcs | High — reworks `GameInvestment` model fundamentally |
| **Split decisions across the season** | Better pacing, more decision points | Medium — new UI moments for mid-season decisions |
| **Add maintenance costs** | Infrastructure degrades if not maintained, creating ongoing pressure | Medium — new processor + decay logic |

The stadium expansion feature naturally introduces the concept of persistent, cumulative investment. If players respond well to it, that pattern could later extend to the other infrastructure areas (academy, medical, scouting, facilities).

---

## G. Financial Analysis: Stadium Expansion Costs

This analysis calibrates expansion costs against the in-game financial model and real-world stadium economics. The goal is costs that create genuine strategic tradeoffs — expensive enough to hurt, cheap enough to attempt.

### The Revenue Model (Corrected Calculations)

All values in the codebase are stored in cents. The matchday revenue formula is:

```
Matchday Revenue = stadium_seats × revenue_per_seat × facilities_multiplier × position_factor
```

**Revenue per seat per season by reputation** (`config/finances.php`):

| Reputation | Matchday/Seat | Commercial/Seat | Combined/Seat |
|-----------|--------------|----------------|---------------|
| Elite | €800 | €2,200 | €3,000 |
| Contenders | €600 | €1,200 | €1,800 |
| Continental | €425 | €1,000 | €1,425 |
| Established | €350 | €800 | €1,150 |
| Modest | €275 | €600 | €875 |
| Professional | €200 | €500 | €700 |
| Local | €100 | €300 | €400 |

**Critical design decision:** In the proposed implementation, `BudgetProjectionService.calculateMatchdayRevenue()` would use `GameStadium.current_capacity` instead of `Team.stadium_seats`, but `getBaseCommercialRevenue()` would continue using `Team.stadium_seats`. This means **expansion only increases matchday revenue, not commercial revenue.** This is correct behavior — commercial revenue represents sponsorship and brand value, which don't automatically grow just because you added seats.

Commercial revenue is also capped at 80,000 seats (`MAX_COMMERCIAL_SEATS`), so elite clubs see no commercial benefit from expansion regardless.

### Real Stadium Sizes in the Game

**La Liga (ESP1) — 20 teams:**

| Club | Seats | Reputation | Matchday Rev (Base) |
|------|-------|-----------|-------------------|
| FC Barcelona | 99,354 | Elite | €79.5M |
| Real Madrid | 83,186 | Elite | €66.5M |
| Atlético de Madrid | 70,460 | Contenders | €42.3M |
| Real Betis | 60,721 | Continental | €25.8M |
| Athletic Bilbao | 53,289 | Contenders | €32.0M |
| Valencia CF | 49,430 | Continental | €21.0M |
| Sevilla FC | 43,883 | Continental | €18.7M |
| Real Sociedad | 39,313 | Continental | €16.7M |
| RCD Espanyol | 37,776 | Established | €13.2M |
| Elche CF | 31,388 | Modest | €8.6M |
| Real Oviedo | 30,500 | Modest | €8.4M |
| Levante UD | 26,354 | Modest | €7.2M |
| RCD Mallorca | 26,020 | Established | €9.1M |
| Celta de Vigo | 24,870 | Established | €8.7M |
| CA Osasuna | 23,576 | Established | €8.3M |
| Villarreal CF | 23,500 | Contenders | €14.1M |
| Deportivo Alavés | 19,840 | Modest | €5.5M |
| Getafe CF | 16,800 | Established | €5.9M |
| Rayo Vallecano | 14,708 | Modest | €4.0M |
| Girona FC | 14,624 | Modest | €4.0M |

**La Liga 2 (ESP2) — 22 teams:**

| Club | Seats | Reputation | Matchday Rev (Base) |
|------|-------|-----------|-------------------|
| Deportivo | 32,912 | Established | €11.5M |
| UD Las Palmas | 32,400 | Established | €11.3M |
| Málaga CF | 30,044 | Established | €10.5M |
| Sporting Gijón | 29,371 | Established | €10.3M |
| Real Valladolid | 27,618 | Established | €9.7M |
| Racing Santander | 22,308 | Established | €7.8M |
| Córdoba CF | 21,822 | Modest | €6.0M |
| Cádiz CF | 21,094 | Established | €7.4M |
| Real Zaragoza | 20,071 | Established | €7.0M |
| Granada CF | 19,336 | Established | €6.8M |
| UD Almería | 18,331 | Established | €6.4M |
| Albacete | 17,524 | Modest | €4.8M |
| CD Castellón | 15,500 | Modest | €4.3M |
| Cultural Leonesa | 13,451 | Professional | €2.7M |
| CD Leganés | 12,454 | Modest | €3.4M |
| Burgos CF | 12,194 | Professional | €2.4M |
| SD Huesca | 9,128 | Modest | €2.5M |
| SD Eibar | 8,164 | Modest | €2.2M |
| AD Ceuta FC | 6,500 | Professional | €1.3M |
| CD Mirandés | 5,759 | Professional | €1.2M |
| FC Andorra | 3,306 | Professional | €0.7M |
| Real Sociedad B | 2,500 | Professional | €0.5M |

**Distribution:**
- 80K+: 2 teams (both Elite)
- 60K-80K: 2 teams
- 40K-60K: 3 teams
- 30K-40K: 6 teams
- 20K-30K: 10 teams
- 15K-20K: 7 teams
- 10K-15K: 5 teams
- Under 10K: 7 teams

**Key insight:** The median stadium across both divisions is ~21,000 seats. The majority of playable clubs (30 of 42) have 10K-40K seat stadiums. Expansion costs need to be calibrated primarily for this range.

### Representative Budget Profiles

To calibrate expansion costs, we need to know what clubs can actually afford. Surplus = Total Revenue - Wages - Operating Expenses.

| Club Archetype | Example | Seats | Surplus | Transfer Budget (est.) |
|---------------|---------|-------|---------|----------------------|
| **Elite La Liga** | Real Madrid | 83K | ~€120M | €90-110M |
| **Contenders La Liga** | Bilbao | 53K | ~€65M | €45-60M |
| **Continental La Liga** | Sevilla | 44K | ~€48M | €35-43M |
| **Established La Liga** | Celta | 25K | ~€36M | €25-30M |
| **Modest La Liga** | Girona | 15K | ~€27M | €20-25M |
| **Established Segunda** | Deportivo | 33K | ~€15M | €10-13M |
| **Modest Segunda** | Albacete | 18K | ~€8M | €5-7M |
| **Professional Segunda** | Burgos | 12K | ~€4M | €2-3M |
| **Subsidy-zone Segunda** | FC Andorra | 3K | ~€2.5M* | €1M* |

*Receives public subsidy to guarantee minimum viable budget.

### Real-World Benchmarks

From research of actual Spanish stadium projects:

| Project | Scope | Cost | Cost/Seat |
|---------|-------|------|-----------|
| RCDE Stadium (Espanyol) | New build, 40K | €75M | €2,600 |
| Anoeta (Real Sociedad) | Renovation + 8K new | €50-78M | ~€6,000-10K per new seat |
| San Mamés (Athletic) | New build, 53K | €211M | €3,960 |
| Metropolitano (Atlético) | Reconstruction, 68K | €300M | €4,380 |
| La Romareda (Zaragoza) | Full rebuild, 43K | €140M | €3,240 |
| Gran Canaria (Las Palmas) | Renovation, 44K | €107M | €2,400 |
| FC Andorra | New build, 6K | €26M | €4,333 |
| Camp Nou (Barcelona) | Mega-renovation, 105K | €1.2B | €12,000 |
| Bernabéu (Real Madrid) | Ultra-premium rebuild | €1.35B | €15,900 |

**Realistic expansion cost per seat in Spain:** €2,500-5,000 for standard projects, €5,000-10,000 for significant renovations.

**Typical timelines:** 1-2 years for modest additions, 2-4 years for major projects.

### Revenue Impact of Expansion

Since expansion only affects matchday revenue (not commercial), the additional revenue per new seat depends solely on the club's reputation level:

| Reputation | Revenue/New Seat/Season | With Facilities T2 (×1.15) | With Facilities T3 (×1.35) |
|-----------|----------------------|--------------------------|--------------------------|
| Elite | €800 | €920 | €1,080 |
| Contenders | €600 | €690 | €810 |
| Continental | €425 | €489 | €574 |
| Established | €350 | €403 | €473 |
| Modest | €275 | €316 | €371 |
| Professional | €200 | €230 | €270 |

Position factor (1.05-1.10 for top positions, 0.85 for relegation zone) also applies, but is not guaranteed — so we use ×1.0 for base calculations.

### Assessment of Proposed V1 Costs

The original design doc proposed these expansion tiers:

| Current Capacity | Expansion | Cost | Cost/Seat |
|-----------------|-----------|------|-----------|
| < 20K | +3,000 | €8M | €2,667 |
| < 20K | +5,000 | €15M | €3,000 |
| 20K-40K | +5,000 | €15M | €3,000 |
| 20K-40K | +10,000 | €35M | €3,500 |
| 40K-60K | +8,000 | €30M | €3,750 |
| 40K-60K | +15,000 | €60M | €4,000 |
| 60K-80K | +10,000 | €50M | €5,000 |
| 80K+ | +5,000 | €40M | €8,000 |

**Payback analysis (matchday only, Facilities Tier 1, no position bonus):**

| Club | Rep | Expansion | Cost | Rev/Season | Payback | Build Time | Total Wait |
|------|-----|-----------|------|------------|---------|------------|-----------|
| FC Andorra | Prof | +3K, €8M | €8M | €0.6M | 13.3 seasons | 1 | **14.3 seasons** |
| Girona | Modest | +3K, €8M | €8M | €0.83M | 9.7 | 1 | **10.7 seasons** |
| Girona | Modest | +5K, €15M | €15M | €1.38M | 10.9 | 2 | **12.9 seasons** |
| Alavés | Modest | +3K, €8M | €8M | €0.83M | 9.7 | 1 | **10.7 seasons** |
| Celta | Estab. | +5K, €15M | €15M | €1.75M | 8.6 | 1 | **9.6 seasons** |
| Celta | Estab. | +10K, €35M | €35M | €3.5M | 10.0 | 2 | **12.0 seasons** |
| Espanyol | Estab. | +5K, €15M | €15M | €1.75M | 8.6 | 1 | **9.6 seasons** |
| Bilbao | Cont. | +8K, €30M | €30M | €4.8M | 6.25 | 1 | **7.25 seasons** |
| Bilbao | Cont. | +15K, €60M | €60M | €9.0M | 6.7 | 2 | **8.7 seasons** |
| Atlético | Cont. | +10K, €50M | €50M | €6.0M | 8.3 | 2 | **10.3 seasons** |
| R. Madrid | Elite | +5K, €40M | €40M | €4.0M | 10.0 | 2 | **12.0 seasons** |

**With Facilities Tier 3 (×1.35) — rewarding compound infrastructure investment:**

| Club | Rep | Expansion | Payback (T1) | Payback (T3) | Improvement |
|------|-----|-----------|-------------|-------------|-------------|
| Girona | Modest | +3K, €8M | 9.7 | 7.2 | -26% |
| Celta | Estab. | +5K, €15M | 8.6 | 6.3 | -27% |
| Bilbao | Cont. | +8K, €30M | 6.25 | 4.6 | -26% |
| R. Madrid | Elite | +5K, €40M | 10.0 | 7.4 | -26% |

The Facilities interaction is well-designed: players who invest in both expansion and facilities see a ~26% reduction in payback time, naturally rewarding compound infrastructure investment without any special-case logic.

### Verdict: Are the Proposed Costs Right?

**The costs work well for most of the range.** Here's the breakdown:

#### What works

1. **Mid-range clubs (20K-60K, Established/Continental/Contenders) have payback of 6-10 seasons.** This is the sweet spot — long enough to be a real commitment, short enough to see returns within a typical play session of 5-15 seasons. These clubs make up the majority of the roster.

2. **Cost as percentage of transfer budget feels right:**
   - Celta spending €15M on +5K seats = ~50-60% of one season's transfer budget. Painful, forces the player to choose between a marquee signing and long-term infrastructure. Good tradeoff.
   - Bilbao spending €30M on +8K seats = ~50-60% of transfer budget. Same dynamic.

3. **Elite clubs face a luxury problem.** Real Madrid spending €40M on +5K seats is only ~35% of their transfer budget, but the payback is 10+ seasons because matchday revenue is already enormous. The expansion is affordable but not impactful — which mirrors reality. Giant clubs don't benefit much from marginal capacity increases.

4. **The build time creates genuine delayed gratification.** A 2-season build on a €35M investment means you're cash-poor for 2 seasons before seeing any return. That's a real strategic risk.

5. **Real-world alignment is reasonable.** The proposed €2,667-€8,000 per seat range falls within Spain's actual €2,500-5,000 standard project range, with the premium for larger stadiums reflecting real-world cost escalation.

#### What needs adjustment

1. **Small/Professional clubs can't meaningfully participate.** FC Andorra (3,306 seats) has ~€1M transfer budget. The cheapest expansion (€8M for +3K seats) costs 8× their annual transfer budget — that's not a tradeoff, it's impossible. Even if they save for 2 seasons, they'd have no squad.

2. **Segunda Division Established clubs are borderline.** Deportivo (32,912 seats) has ~€10-13M transfer budget. Spending €15M on +5K seats means zero transfers for a season plus carrying debt. Possible but punishing.

3. **The <20K bracket is too uniform.** It covers clubs from 2,500 to 19,840 seats — an 8× range. A 3,000-seat club and a 19,000-seat club shouldn't face the same expansion cost.

### Revised Expansion Tiers

The following revision adds a lower bracket for very small clubs while keeping the mid-range and top-end costs intact:

| Current Capacity | Expansion | Cost | Cost/Seat | Build Time |
|-----------------|-----------|------|-----------|------------|
| **< 8,000** | **+2,000** | **€3M** | **€1,500** | **1 season** |
| **< 8,000** | **+4,000** | **€7M** | **€1,750** | **2 seasons** |
| 8,000-20,000 | +3,000 | €8M | €2,667 | 1 season |
| 8,000-20,000 | +5,000 | €15M | €3,000 | 2 seasons |
| 20,000-40,000 | +5,000 | €15M | €3,000 | 1 season |
| 20,000-40,000 | +10,000 | €35M | €3,500 | 2 seasons |
| 40,000-60,000 | +8,000 | €30M | €3,750 | 1 season |
| 40,000-60,000 | +15,000 | €60M | €4,000 | 2 seasons |
| 60,000-80,000 | +10,000 | €50M | €5,000 | 2 seasons |
| 80,000+ | +5,000 | €40M | €8,000 | 2 seasons |

**Changes from original:**
- Split `< 20,000` into `< 8,000` and `8,000-20,000`
- Added smaller, cheaper expansion options for very small clubs (€3M and €7M)
- Lower cost/seat at bottom end (€1,500-1,750) reflects that small-club construction is genuinely cheaper (simpler structures, no VIP requirements, land is cheaper)

**Payback for small clubs with revised costs:**

| Club | Rep | Expansion | Cost | Rev/Season | Payback | Realistic? |
|------|-----|-----------|------|------------|---------|-----------|
| FC Andorra (3.3K) | Prof | +2K, €3M | €3M | €0.4M | 7.5 seasons | Yes — costs 1.5× transfer budget |
| Mirandés (5.8K) | Prof | +2K, €3M | €3M | €0.4M | 7.5 | Yes — costs ~1× transfer budget |
| Eibar (8.2K) | Modest | +3K, €8M | €8M | €0.83M | 9.7 | Tight — costs ~1.5× budget |

This makes expansion accessible to small clubs while keeping payback in the 7-10 season range. A Segunda Professional club spending €3M on +2,000 seats is a significant sacrifice (their entire transfer budget for a season) but achievable, especially if they defer transfers for one window.

### Expansion Cost vs. Player Transfer Value

A useful sanity check: does the expansion cost feel "worth it" compared to signing a player?

| Investment | Cost | Annual Return | What It Replaces |
|-----------|------|--------------|-----------------|
| +3K seats (Modest) | €8M | €0.83M/season forever | 1-2 decent transfers |
| +5K seats (Established) | €15M | €1.75M/season forever | 1 good transfer |
| +8K seats (Contenders) | €30M | €4.8M/season forever | 1 star player |
| +5K seats (Elite) | €40M | €4.0M/season forever | 1 rotation player |

The trade-off reads clearly: "I could buy a good striker who helps me this season, or I could add 5,000 seats that pay for themselves over 8 seasons." The striker depreciates (ages, loses value). The seats are permanent. This creates a genuine philosophical split between **short-term competitors** and **long-term builders**, which is exactly the player fantasy the feature targets.

### Interaction with Ticket Pricing

The proposed pricing tiers multiply the per-seat rate, which means they also multiply the expansion benefit:

| Pricing Tier | Revenue Modifier | Attendance % | Net Effect on Expansion ROI |
|-------------|-----------------|-------------|---------------------------|
| Popular (Tier 1) | ×0.80 | 100% | Expansion payback +25% longer |
| Standard (Tier 2) | ×1.00 | 95% | Expansion payback +5% longer (base) |
| Premium (Tier 3) | ×1.20 | 82% | Expansion payback -3% shorter (near-optimal) |
| Exclusive (Tier 4) | ×1.45 | 65% | Expansion payback -1% shorter |

The interaction is small but directionally correct: Premium pricing slightly improves expansion ROI because you're extracting more revenue per seat. But the differences are too narrow to drive the pricing decision — which is intentional, since pricing should be about club identity and atmosphere, not pure min-maxing.

### Promotion/Relegation Impact

Stadium capacity persists across divisions, but its economic value changes because:

1. **Revenue per seat stays the same** (reputation-based, not division-based) — so a promoted club's matchday revenue doesn't jump unless reputation changes
2. **TV revenue jumps dramatically** on promotion (€6M → €40-55M for La Liga)
3. **Operating expenses increase** (×0.70 in Segunda → ×1.0 in La Liga)
4. **Commercial revenue increases** (×0.75 in Segunda → ×1.0 in La Liga)

This means: **expanding in Segunda to prepare for promotion is a valid strategy.** The seat count is ready when you get promoted, and the higher La Liga revenue makes the investment pay back faster if you stay up.

Conversely, a relegated club with a big stadium sees their matchday revenue stay the same (it's reputation-based), but their TV and commercial revenue collapse. The stadium becomes a fixed cost with stable returns while everything else shrinks — providing a financial floor that makes relegation survivable.

### Maximum Capacity & Growth Ceiling

The 105,000-seat cap is sensible — no real-world club stadium exceeds this. But growth trajectories vary:

**Small club growth path (FC Andorra, 3,306 → 30,000 over ~12 seasons):**

| Season | Action | Capacity | Cumulative Cost |
|--------|--------|----------|----------------|
| 1-2 | +2K, build 1 season | 5,306 | €3M |
| 3-4 | +4K, build 2 seasons | 9,306 | €10M |
| 5-6 | +3K, build 1 season | 12,306 | €18M |
| 7-8 | +5K, build 2 seasons | 17,306 | €33M |
| 9-10 | +5K, build 1 season | 22,306 | €48M |
| 11-12 | +5K, build 1 season | 27,306 | €63M |

Total: ~€63M across 12 seasons to go from 3,300 to 27,300 seats. If the club was promoted to La Liga along the way, the increased revenue would make later expansions more affordable. This feels like a satisfying long-term arc.

**Big club growth path (Bilbao, 53,289 → 75,000 over ~6 seasons):**

| Season | Action | Capacity | Cumulative Cost |
|--------|--------|----------|----------------|
| 1-2 | +8K, build 1 season | 61,289 | €30M |
| 3-4 | +10K, build 2 seasons | 71,289 | €80M |
| 5-6 | Done at ~71K | 71,289 | €80M |

Bilbao goes from a 53K to 71K stadium for €80M over 4 active seasons. At Contenders revenue rates, the additional 18K seats generate ~€10.8M/season, paying back in ~7.5 seasons. The club is €80M poorer in the short term but structurally stronger. A player running Bilbao must decide: "Do I build the stadium or sign a world-class striker to challenge for the title now?"

### Summary: Recommended Costs

**Keep the original costs mostly intact, but add a lower bracket for very small clubs:**

- **< 8,000**: +2,000 seats for €3M (1 season) or +4,000 for €7M (2 seasons)
- **8,000-20,000**: +3,000 for €8M (1 season) or +5,000 for €15M (2 seasons) — *unchanged*
- **20,000-40,000**: +5,000 for €15M (1 season) or +10,000 for €35M (2 seasons) — *unchanged*
- **40,000-60,000**: +8,000 for €30M (1 season) or +15,000 for €60M (2 seasons) — *unchanged*
- **60,000-80,000**: +10,000 for €50M (2 seasons) — *unchanged*
- **80,000+**: +5,000 for €40M (2 seasons) — *unchanged*
- **Cap**: 105,000 seats — *unchanged*

**Payback periods range from 6-13 seasons** depending on club reputation and facilities tier, with a sweet spot of 7-10 seasons for the most common club types.

**The costs are calibrated so that expansion costs roughly 40-60% of a single season's transfer budget** for the club archetype that would use each bracket, creating a clear "stadium or players?" tradeoff.

**The total economic picture:** Stadium expansion is a slow-burn investment that rewards patient managers. Over 10+ seasons, a club that expanded early will have compounded the benefit with facilities upgrades, promotion bonuses, and ticket pricing — while a club that spent on players may have won more trophies but plateaued financially. Both paths should be viable. Neither should be obviously dominant.
