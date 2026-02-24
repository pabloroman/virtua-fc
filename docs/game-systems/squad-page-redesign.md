# Squad Page Redesign: "La Plantilla"

> **Note:** This is a UI/UX design reference document. It describes the target design for the squad page. Some features may be partially implemented or pending. For game mechanics referenced here (development, contracts, transfers), see the corresponding system documents which reflect the actual code.

## Overview

Redesign the squad page from a flat data table into a layered, decision-oriented command center. The page should answer two fundamental questions at a glance: **"Who should play this weekend?"** (tactical, short-term) and **"What does my squad need?"** (strategic, long-term) — without overwhelming the user with all the information at once.

**Current state:** A single table grouped by position showing all player attributes in uniform columns: position, number, name, flag, age, value, wage, contract, technical, physical, fitness, morale, overall. A flat summary footer shows total count, wage bill, position counts, avg fitness/morale. Development, stats, and academy live on separate tabs.

**Problems with the current design:**
1. **Information overload without hierarchy** — All 13 columns are given equal visual weight. The user cannot quickly distinguish match-ready context (fitness, morale, availability) from long-term planning context (contract, value, development).
2. **No squad-level intelligence** — The page shows individual player data but provides no aggregate analysis: position depth, squad age profile, wage distribution, or gaps that need filling.
3. **Dead-end page** — The squad page displays data but offers few inline actions. The user must navigate to transfers, lineup, or player detail to act on what they see.
4. **Mobile penalty** — On mobile, most columns are hidden (`hidden md:table-cell`), leaving only position, name, and overall score. Users on phones lose the vast majority of the page's value.
5. **Fragmented sub-pages** — Development, stats, and academy are siloed into separate tabs, forcing mental context-switching to correlate a player's current ability with their development trajectory or season contribution.

**New state:** A single-page experience with three distinct information layers (squad overview cards, player table with smart columns, and expandable player rows) plus a squad analysis sidebar. The page follows progressive disclosure: the most actionable information is visible immediately, deeper data reveals on interaction, and squad-level insights are always available in the periphery.

---

## Design Principles

1. **Decision-first, not data-first** — Every piece of information should help the user make a decision. If data doesn't inform a lineup pick, a transfer, or a contract renewal, it doesn't belong on the primary view.

2. **Progressive disclosure in three layers:**
   - **Layer 0 (Squad Overview):** Squad-level KPIs and position health at a glance (always visible above the table).
   - **Layer 1 (Player Table):** Compact player rows with the most decision-relevant attributes, smart-grouped by position.
   - **Layer 2 (Expanded Row):** Click/tap a player to expand an inline detail panel with full stats, development projection, contract info, and quick actions — no modal, no page navigation.

3. **Context-sensitive columns** — The table shows different column sets based on what the user is thinking about. "View modes" let users switch between tactical (match-day readiness) and strategic (squad planning) lenses without navigating away.

4. **Mobile as first-class citizen** — The mobile experience should be a deliberately designed card-based layout, not a table with hidden columns.

5. **Inline actions, not navigation** — List for sale, loan out, renew contract, and add to lineup should be available directly from the expanded player row. The squad page is a command center, not a read-only report.

---

## Information Architecture

### Layer 0: Squad Dashboard (Above the Table)

A horizontal strip of compact KPI cards that provide squad-level context. These are the "vital signs" of the squad that inform whether the user needs to take strategic action.

**Cards (desktop: horizontal row of 5-6 cards; mobile: 2-column grid):**

| Card | Content | Why it matters |
|------|---------|---------------|
| **Squad Size** | `23 players` with position mini-bar (colored segments: GK/DEF/MID/FWD counts) | Immediately shows if squad is thin or bloated, and where |
| **Squad Value** | `€185M` total market value | Benchmark for transfer ambitions |
| **Wage Bill** | `€24M/yr` + wage-to-revenue ratio as a health indicator (green <50%, amber 50-70%, red >70%) | Financial sustainability at a glance |
| **Transfer Budget** | `€12M available` (career mode only) | Spending power for incoming transfers |
| **Avg Age** | `26.2 years` with age distribution mini-chart (young/prime/veteran segments) | Squad lifecycle — is the team aging out? |
| **Fitness & Morale** | Avg fitness `84` + avg morale `72` with warning count (`3 players below 70 fitness`) | Match-day readiness |

**Behavior:**
- Career mode shows all 6 cards. Tournament mode shows Squad Size, Avg Age, and Fitness & Morale only.
- Each card is a self-contained visual unit — no text labels needed; the number + context tells the story.
- Tapping a card on mobile could scroll/filter to the relevant section (e.g., tapping "Fitness" sorts the table by fitness).

---

### Layer 1: Player Table with View Modes

The table is the core of the page. Instead of showing all columns at once, the user selects a **view mode** that determines which column set is visible. This keeps the table scannable and contextual.

#### View Modes (Horizontal Toggle)

| Mode | Icon | Columns Shown | Use Case |
|------|------|--------------|----------|
| **Overview** (default) | Grid icon | Position, Name, Age, Overall, Fitness, Morale, Availability status | "Who's available for the next match?" |
| **Tactical** | Football pitch icon | Position, Name, Technical, Physical, Fitness, Morale, Overall, Season Apps | "Who should start and why?" |
| **Planning** | Calendar/chart icon | Position, Name, Age, Value, Wage, Contract, Potential Range, Dev Status | "Who should I sell/renew/replace?" |
| **Stats** | Bar chart icon | Position, Name, Apps, Goals, Assists, G+A, Goals/Game, Yellow/Red Cards, Clean Sheets (GK) | "Who's contributing this season?" |

**Behavior:**
- The toggle sits between the Squad Dashboard and the table. On mobile, it's a horizontally scrollable pill bar.
- Switching modes preserves the current sort and filter state.
- The selected mode is remembered per session (stored in Alpine.js state, not persisted to backend).
- On mobile, all modes render as card lists instead of tables (see Mobile section).

#### Table Grouping

Players are grouped by position group (Goalkeepers, Defenders, Midfielders, Forwards) with collapsible group headers. Each group header shows:
- Group name with colored position badge
- Player count for that group (e.g., "Defenders (6)")
- Group average overall score

#### Table Row Design (Layer 1)

Each row is compact but information-dense:

**Always-visible columns (all modes):**
- **Position badge** — Colored, skewed badge (existing component)
- **Player name** — Primary identifier, with inline status icons:
  - Red cross: injured (with injury type on tooltip)
  - Orange clock: suspended (with matches remaining)
  - Green check: renewed
  - Amber tag: listed for sale
  - Sky arrows: on loan
  - Orange door: retiring
  - Red door: leaving free (pre-contract agreed)

**Mode-specific columns** as defined in the view modes table above.

**Overall score** — Always the rightmost column, displayed as the existing colored circle badge (emerald ≥80, lime ≥70, amber ≥60, slate <60).

#### Sorting

- Click/tap any column header to sort ascending/descending.
- Default sort: by position group order, then by overall score descending within each group.
- Client-side sorting via Alpine.js (no page reload).

#### Filtering

A compact filter row between the view mode toggle and the table:
- **Position filter:** All | GK | DEF | MID | FWD (pill buttons, colored by position group)
- **Availability filter:** All | Available | Unavailable
- **Status filter (career mode):** All | Expiring Contract | Listed | On Loan | Retiring

Filters are combinable. Active filters show a count badge and a "clear all" link.

---

### Layer 2: Expanded Player Row (Inline Detail)

Clicking/tapping a player row expands an inline detail panel below the row (accordion-style, only one expanded at a time). This replaces the current modal for most use cases and keeps the user in context.

**Layout: 3-column grid on desktop (abilities | details | actions), stacked on mobile.**

#### Column 1: Abilities & Condition

- **Ability bars** (existing component): Technical, Physical — with numeric values and color coding
- **Fitness bar** with recovery context: "Fitness 73 — recovering" or "Fitness 95 — fresh"
- **Morale bar** with trend context: "Morale 68 — declining" (if morale dropped recently)
- **Development indicator:** Growing / Peak / Declining badge with arrow icon
- **Potential range:** "72-81" (or "?" if unknown in academy context)
- **Development projection:** Mini sparkline or `67 → 71` showing next-season projection (existing data from `PlayerDevelopmentService::projectDevelopment`)

#### Column 2: Profile & Contract

- **Bio line:** Nationality flag + country name, age, height, preferred foot
- **Contract info (career mode):**
  - Market value: `€12.5M`
  - Annual wage: `€1.8M/yr`
  - Contract until: `2028` (or red "Expiring" badge if expiring this season)
  - Wage vs. value ratio indicator (is the player overpaid/underpaid relative to ability?)
- **Season stats summary:** Apps, Goals, Assists (or Clean Sheets for GK), Yellow/Red cards — compact inline
- **Playing time indicator:** Progress bar toward 15 appearances for development starter bonus (for growing players only)

#### Column 3: Quick Actions

Inline action buttons that let the user act immediately without navigating away. All actions POST to existing endpoints and refresh the row state.

**Career mode actions (context-dependent):**
- **List for Sale** / **Remove from Sale** — Toggle (existing endpoint)
- **Loan Out** — Starts loan search (existing endpoint)
- **Offer Renewal** — Opens inline renewal negotiation widget (for expiring contracts, existing endpoint)
- **View Offers** — Link to transfers page filtered to this player (if offers exist)

**All modes:**
- **View Full Profile** — Opens existing player detail modal for the complete historical view (link, not button)

**Conditional display:**
- Actions that don't apply are hidden (not disabled). E.g., "Loan Out" doesn't appear for loaned-in players.
- Status-dependent messaging replaces action buttons when relevant:
  - "Sale agreed — waiting for transfer window" (green badge)
  - "Loan search in progress" (animated sky badge)
  - "Renewal negotiation pending — player is considering offer" (amber pulse badge, with link to transfers)
  - "Pre-contract signed with another club" (red badge)
  - "Retiring at end of season" (orange badge)

---

### Squad Analysis Sidebar (Desktop Only)

On desktop (≥1280px / `xl` breakpoint), the page gains a right sidebar that provides deeper squad analysis. This sidebar is not visible on smaller screens — it's a power-user feature for desktop strategic planning sessions.

**Layout:** Main content takes `xl:col-span-3`, sidebar takes `xl:col-span-1`. Below `xl`, the sidebar content is accessible through a collapsible "Squad Analysis" section below the table.

**Sidebar Sections:**

#### 1. Position Depth Chart

A visual representation of squad coverage by position slot (not just group). Shows how many players can fill each formation slot.

```
         GK    CB    LB    RB    DM    CM    AM    LW    RW    CF
Players:  2     4     2     1     2     3     1     2     1     2
Depth:   ██   ████  ██    █    ██   ███   █    ██    █    ██
```

- Green: 2+ players (good depth)
- Amber: 1 player (thin)
- Red: 0 players (gap!)

This immediately answers "Do I need to sign a right-back?" — the core strategic question.

**Implementation note:** This requires mapping player positions to formation slots using the existing `PositionSlotMapper` and `PositionMapper` classes. A player's `position` field maps to their natural slot. Adjacent slots (e.g., a Left-Back can cover Right-Back) should show as secondary coverage in a lighter shade.

#### 2. Age Profile

A compact horizontal bar chart showing squad distribution by age band:

- **Young (≤23):** Count + names of top prospects
- **Prime (24-28):** Count
- **Veteran (29+):** Count + names of players declining

Color-coded: green (young), blue (prime), amber (veteran). This tells the user whether the squad is aging and who the succession targets are.

#### 3. Contract Watchlist (Career Mode)

A prioritized list of contract situations requiring attention:

- **Expiring this season** (red): Players whose contracts end — action needed now
- **Expiring next season** (amber): Players who will be eligible for pre-contract offers — plan ahead
- **High earners** (slate): Top 3 wage earners relative to their contribution (wage/overall ratio)

Each entry is a compact row: name, contract year, wage, with a link that expands that player's row in the table.

#### 4. Squad Health Alerts

Contextual alerts that surface when conditions are met:

- "3 players injured — consider resting starters" (when injury count > 2)
- "5 players with low morale — consider selling unhappy players" (when low morale count > 3)
- "Squad too thin at CB — only 2 centre-backs available" (when any position has ≤1 available player)
- "Heavy fixture congestion ahead — rotate your squad" (when next 3 matchdays are within 10 days)
- "Transfer window closes in 3 matchdays" (when window is open and closing soon)

These alerts are generated server-side and passed to the view. They act as a coaching prompt, nudging the user toward good decisions.

---

## Mobile Experience

On mobile (< 768px), the page transforms into a card-based layout:

### Mobile Squad Dashboard
- 2-column grid of KPI cards (compact, icon + number + label)
- Horizontally scrollable if more than 4 cards

### Mobile View Mode Tabs
- Horizontally scrollable pill bar (existing `scrollbar-hide` pattern)
- Each pill shows icon + short label: "Overview", "Tactical", "Planning", "Stats"

### Mobile Player Cards

Instead of table rows, each player renders as a card:

```
┌─────────────────────────────────────────┐
│ [CB]  Player Name                   [78]│
│       🇪🇸 23 yrs • Fit: 92 • Mor: 75  │
│       [Injured: Muscle strain - 1 wk]   │
└─────────────────────────────────────────┘
```

- Position badge (left), overall score (right), name (prominent)
- Second line shows key stats for the selected view mode:
  - Overview: nationality, age, fitness, morale
  - Tactical: technical, physical, fitness, overall
  - Planning: value, wage, contract year, dev status
  - Stats: apps, goals, assists, cards
- Availability status shown as a colored strip or inline badge below the name
- Tap to expand: same 3-section content as desktop expanded row, but stacked vertically

### Mobile Squad Analysis
- The sidebar content is accessible through a "Squad Analysis" expandable section below the player list
- Position depth chart renders as a compact vertical list
- Alerts render as a stacked card list

---

## Data Requirements

### New Data to Compute (Server-Side in ShowSquad View)

| Data Point | Source | Purpose |
|-----------|--------|---------|
| Squad total market value | `$allPlayers->sum('market_value_cents')` | Dashboard card |
| Transfer budget remaining | `$game->currentInvestment->transfer_budget` | Dashboard card |
| Wage-to-revenue ratio | `$wageBill / $game->currentFinances->projected_total_revenue * 100` | Dashboard card health indicator |
| Average squad age | `$allPlayers->avg(fn($p) => $p->age)` | Dashboard card |
| Age distribution | Count by band (≤23, 24-28, 29+) | Sidebar age profile |
| Position depth map | Group players by position, map to slots | Sidebar depth chart |
| Players expiring next season | Filter by `contract_until` within next 12 months | Sidebar contract watchlist |
| Wage-to-overall ratio per player | `$player->annual_wage / $player->overall_score` | Expanded row, sidebar overpaid indicator |
| Development projections | `PlayerDevelopmentService::projectDevelopment()` for each player | Expanded row |
| Squad health alerts | Computed from injury count, morale levels, position depth, fixture schedule | Sidebar alerts |
| Season stats (goals, assists, etc.) | Already on GamePlayer model | Stats view mode |

### Existing Data (Already Available)

All individual player attributes listed in the current `ShowSquad` view class are retained. The following data is already loaded and needs no new queries:
- Player identity (name, nationality, age, height, position, number)
- Abilities (technical, physical, fitness, morale, overall)
- Contract data (value, wage, contract_until, pending_wage)
- Status flags (injured, suspended, retiring, listed, loan, pre-contract, renewal)
- Season stats (appearances, goals, assists, cards, clean sheets)
- Development status (growing/peak/declining)
- Potential range (potential_low, potential_high)

### New Data Opportunities (Require Backend Changes)

These are ideas that would enhance the squad page but require new data to be tracked. They are **optional enhancements** that can be implemented incrementally.

| Enhancement | What's Needed | Value Added |
|------------|---------------|------------|
| **Last match rating** | Store a per-match performance rating (1-10) on `MatchEvent` or a new `match_ratings` table, computed from goals, assists, cards, and xG contribution during simulation | Shows recent form — "Who's in good form?" replaces gut feeling with data |
| **Form indicator** (last 5 matches) | Aggregate last 5 match ratings into a mini form bar (green/amber/red blocks) | At-a-glance form trend without leaving the squad page |
| **Minutes played** (season) | Track total minutes per player per season (currently only `season_appearances` is tracked, not minutes) | More granular than appearances — distinguishes starters from late subs. Useful for fatigue management |
| **Position versatility** | Store secondary positions on GamePlayer (e.g., a CM who can play DM or AM) | Dramatically improves the depth chart and lineup flexibility assessment |
| **Injury history** | Track historical injuries (currently only current injury is stored; past injuries are lost on recovery) | Risk assessment — "This player has been injured 4 times this season" informs selling decisions |
| **Morale trend** | Store morale snapshots over last N matchdays to compute direction (rising/falling/stable) | Contextualizes current morale — "68 and falling" is worse than "68 and rising" |
| **Expected contribution** | Computed metric combining ability, fitness, morale, and age curve into a single "expected value" for next season | Forward-looking decision metric for transfers and renewals |

---

## Interaction Patterns

### Expand/Collapse Player Row
- **Trigger:** Click/tap anywhere on the player row
- **Animation:** Smooth slide-down expansion (200ms ease)
- **Constraint:** Only one row expanded at a time (expanding another collapses the current)
- **State:** Managed via Alpine.js `expandedPlayerId` reactive variable
- **Keyboard:** Enter/Space to expand focused row, Escape to collapse

### View Mode Switching
- **Trigger:** Click/tap on view mode pill
- **Animation:** Columns crossfade (150ms) — no full page reload
- **State:** Alpine.js reactive variable, not URL parameter
- **Persistence:** Remembered for session duration only

### Filter Application
- **Trigger:** Click/tap on filter pills
- **Animation:** Table rows filter with fade (150ms)
- **State:** Combined filter state in Alpine.js
- **URL:** Active filters reflected in URL query params for shareability

### Quick Actions
- **Trigger:** Click action button in expanded row
- **Behavior:** POST to existing endpoint, show spinner on button, refresh row content on success
- **Confirmation:** Destructive actions (list for sale, loan out) show inline confirmation: "Are you sure? [Yes] [Cancel]"
- **Feedback:** Toast notification on success (existing flash message pattern)

### Sidebar Interaction
- **Player links:** Clicking a player name in the sidebar scrolls the table to that player and expands their row
- **Collapsible sections:** Each sidebar section is independently collapsible (Alpine.js, default expanded)

---

## URL Structure

The redesigned page consolidates the current `squad`, `squad/development`, and `squad/stats` routes into a single page with client-side view modes. The Academy remains a separate page due to its distinct management flow.

| Route | Purpose | Change |
|-------|---------|--------|
| `GET /game/{id}/squad` | Main squad page (all view modes) | **Consolidated** — now includes development + stats data |
| `GET /game/{id}/squad?mode=tactical` | Pre-selected view mode via query param | **New** — optional deep linking |
| `GET /game/{id}/squad?filter=expiring` | Pre-selected filter via query param | **New** — optional deep linking |
| `GET /game/{id}/squad/academy` | Academy page (unchanged) | No change |
| `GET /game/{id}/squad/academy/evaluate` | Academy evaluation (unchanged) | No change |

**Removed routes:**
- `GET /game/{id}/squad/development` — Merged into Planning view mode + expanded row
- `GET /game/{id}/squad/stats` — Merged into Stats view mode

The player detail modal endpoint (`GET /game/{id}/player/{playerId}/detail`) remains as a fallback for the "View Full Profile" link, but is no longer the primary way to view player details.

---

## Visual Design Direction

### Color Language

Consistent with existing codebase patterns:

| Context | Color | Usage |
|---------|-------|-------|
| Excellent (80+) | `emerald-500` | Overall score badge, ability bars |
| Good (70-79) | `lime-500` | Ability bars, score badges |
| Average (60-69) | `amber-500` | Ability bars, score badges |
| Poor (<60) | `slate-400` | Ability bars, score badges |
| Growing | `green-600` | Development status, age profile young band |
| Peak | `sky-600` | Development status, age profile prime band |
| Declining | `orange-600` | Development status, age profile veteran band |
| Danger/Urgent | `red-600` | Expiring contracts, injuries, low fitness |
| Warning | `amber-500` | Low morale, listed players, near-expiry |
| Positive action | `sky-600` | Action buttons (loan, renew) |
| Destructive | `red-600` | Sell, dismiss |

### Typography Hierarchy

- **Dashboard numbers:** `text-2xl font-bold` — Large, scannable KPIs
- **Player name (table):** `text-sm font-medium` — Primary row identifier
- **Column data:** `text-sm tabular-nums` — Aligned numeric data
- **Status badges:** `text-xs font-medium` — Compact, color-coded
- **Sidebar headers:** `text-xs font-semibold uppercase tracking-wide` — Section dividers
- **Sidebar content:** `text-sm` — Readable detail text

### Spacing & Rhythm

- Dashboard cards: `gap-3` on mobile, `gap-4` on desktop
- Table rows: `py-2.5` (slightly more breathing room than current `py-2`)
- Expanded row: `p-5` with clear border separation
- Sidebar sections: `space-y-6` between sections

---

## Technical Implementation Notes

### Alpine.js State Shape

```javascript
{
  viewMode: 'overview',           // 'overview' | 'tactical' | 'planning' | 'stats'
  expandedPlayerId: null,         // UUID or null
  positionFilter: 'all',          // 'all' | 'goalkeeper' | 'defender' | 'midfielder' | 'forward'
  availabilityFilter: 'all',      // 'all' | 'available' | 'unavailable'
  statusFilter: 'all',            // 'all' | 'expiring' | 'listed' | 'on_loan' | 'retiring'
  sortColumn: 'position',         // Column key
  sortDirection: 'asc',           // 'asc' | 'desc'
  sidebarSection: {               // Collapsed state per sidebar section
    depth: true,
    age: true,
    contracts: true,
    alerts: true
  }
}
```

### Performance Considerations

- **Single query with eager loading:** Load all players with `player` relationship in one query (existing pattern).
- **Development projections:** Compute in `ShowSquad` view class for all players in a single pass (batch computation, not per-player).
- **Sidebar data:** Computed server-side in the view class, not in Blade. All aggregations happen in PHP, not in the template.
- **Lazy loading expanded row data:** The expanded row content can be rendered server-side and fetched via AJAX (existing pattern from `player-detail-modal`), or pre-rendered in hidden divs. Given squad sizes (20-30 players), pre-rendering is acceptable and avoids AJAX latency.

### Consolidating Sub-Pages

The development and stats views are merged into the main squad page:
- `ShowSquadDevelopment` data (projections, development status) is computed in `ShowSquad` and passed to the template
- `ShowSquadStats` data (goals, assists, cards) is already on the GamePlayer model — no additional queries needed
- The `ShowSquadDevelopment` and `ShowSquadStats` view classes become unused and can be removed after migration
- Navigation tab items reduce from 4 (Squad, Development, Stats, Academy) to 2 (Squad, Academy)

---

## Scope and Phasing

### Phase 1: Core Redesign (MVP)
- Squad dashboard KPI cards
- Player table with view modes (Overview, Tactical, Planning, Stats)
- Position group headers with counts
- Expandable player rows with abilities, contract info, season stats
- Inline quick actions (list for sale, loan out, renewal)
- Basic position filter and availability filter
- Mobile card layout
- Consolidation of Development and Stats into view modes
- Remove separate Development and Stats tabs/routes

### Phase 2: Squad Intelligence
- Squad Analysis sidebar (desktop)
- Position depth chart
- Age profile visualization
- Contract watchlist
- Squad health alerts
- Status filter (expiring, listed, on loan, retiring)

### Phase 3: Enhanced Data (New Backend Features)
- Last match rating and form indicator
- Minutes played tracking
- Position versatility / secondary positions
- Injury history tracking
- Morale trend tracking

---

## Success Metrics

The redesign succeeds if:

1. **Fewer page navigations:** Users visit the transfers and player detail pages less frequently from the squad page, because the information they need is already available inline.
2. **Faster decision-making:** The view modes and squad dashboard reduce the time between "I wonder if I should sell this player" and "I'm listing them for sale."
3. **Better mobile engagement:** Mobile users can meaningfully interact with the squad page (not just see position + name + overall).
4. **Squad analysis drives transfers:** Users notice position gaps and contract expirations proactively (because the sidebar surfaces them) rather than reactively discovering problems during the transfer window.
