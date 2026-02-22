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
