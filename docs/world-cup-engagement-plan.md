# World Cup Tournament Mode - Engagement & Virality Strategy

## Context

VirtuaFC is launching a World Cup 2026 tournament mode (48 teams, groups A-L, knockout bracket through to the final). The game is currently single-player with no social, competitive, or cross-game features. The goal is to maximize engagement and organic viral growth tied to the real-world WC2026 event (June-July 2026).

Simple "share on social media" buttons are low-impact. What follows are higher-leverage strategies based on what the codebase already supports and what actually drives sharing behavior.

---

## Key Insight: Why People Share

People don't share because you give them a button. They share because the content expresses their **identity** ("I'm a Brazil fan"), tells a **story** ("I took Morocco to the final"), or creates **social tension** ("Can you beat my run?"). Every feature below is designed around one of these psychological drivers.

---

## Proposed Features (Ranked by Impact x Feasibility)

### 1. "My World Cup Story" - Shareable Tournament Summary Card

**What:** After the tournament ends, generate a visually striking summary card showing the player's entire World Cup journey: team flag, group results, knockout path with scores, final placement, top scorer, and a dramatic moment.

**Why it works:** The "Spotify Wrapped" mechanic:
- **Personal** - every card is unique to the player's run
- **Visual** - designed for screenshots on Instagram stories, Twitter, WhatsApp
- **Identity-expressing** - "This is MY country, this is MY story"
- Sharing is **self-motivated** - people want to show off, not because you asked

**Implementation plan:**

1. **New Model: `TournamentShare`** — stores shareable data snapshots
   - Fields: `id` (UUID), `game_id`, `share_token` (unique, URL-safe), `team_id`, `team_name`, `player_name`, `final_placement`, `group_results` (JSON), `knockout_path` (JSON), `top_scorer` (JSON), `top_assister` (JSON), `headline_moment` (JSON), `created_at`
   - Snapshot approach means the card works even if the game is deleted

2. **Public route: `GET /wc/{shareToken}`** — no auth required
   - New View class: `ShowWorldCupStory`
   - Dedicated Blade template with full OG meta tags

3. **Blade template: `resources/views/wc-story.blade.php`**
   - Optimized for social preview (1200x630 aspect ratio for OG image)
   - Sections: team flag hero, group stage mini-table, knockout path timeline, final result, top scorer/assister, "Play VirtuaFC" CTA

4. **OG Meta Tags:**
   - `og:title`: "[Team] - World Cup 2026 | VirtuaFC"
   - `og:description`: "[Player] led [Team] to [placement]. [Top scorer] scored [N] goals."
   - `og:image`: Server-rendered card image
   - `twitter:card`: `summary_large_image`

5. **OG Image generation** (two options):
   - **Option A (simpler):** `browsershot` (Puppeteer) to screenshot a Blade template into PNG
   - **Option B (no dependency):** PHP GD/Intervention Image to draw the card programmatically

6. **Trigger:** When tournament ends, auto-create `TournamentShare` record. Add "Share Your Story" button to tournament-end view that copies the URL.

7. **Data sources** (all already in DB): `GameStanding` (group results), `CupTie` + `GameMatch` (knockout path), `GamePlayer` (stats), `MatchEvent` (dramatic moments)

**Files to create/modify:**
- `app/Models/TournamentShare.php` (new)
- `database/migrations/xxxx_create_tournament_shares_table.php` (new)
- `app/Http/Views/ShowWorldCupStory.php` (new)
- `resources/views/wc-story.blade.php` (new)
- `resources/views/wc-story-og.blade.php` (new, for OG image rendering)
- `routes/web.php` (add public route)
- `app/Http/Views/ShowTournamentEnd.php` (trigger share generation)
- `resources/views/tournament-end.blade.php` (add share button)

---

### 2. Challenge Links - "Can You Beat My Run?"

**What:** After completing the tournament, the player gets a unique challenge URL: "I won the World Cup with South Korea. Can you?" The link takes the recipient to start a new game with the same team.

**Why it works:**
- Creates a **1:1 viral loop** (highest conversion kind)
- Introduces **social competition** between friends
- Recipient has a **clear, emotionally compelling CTA**
- After recipient plays, they get their own challenge link = **chain reaction**

**Implementation plan:**

1. **Extend `TournamentShare`** with challenge fields:
   - `challenge_token`, `challenger_name`, `challenged_by` (FK to another TournamentShare)

2. **Public route: `GET /challenge/{challengeToken}`**
   - Landing page: "[Challenger] led [Team] to [Placement]. Think you can do better?"
   - CTA: "Accept Challenge" → game creation with pre-selected team
   - If not logged in → register with `?challenge={token}&team={teamId}` params

3. **Post-challenge comparison:** When challenged player finishes, show head-to-head

4. **Tournament-end integration:** "Challenge a Friend" button alongside story card

**Files to create/modify:**
- `app/Models/TournamentShare.php` (extend)
- `app/Http/Views/ShowChallengeLanding.php` (new)
- `resources/views/challenge-landing.blade.php` (new)
- `routes/web.php` (add route)
- `app/Modules/Season/Services/TournamentCreationService.php` (accept challenge context)
- `resources/views/tournament-end.blade.php` (add button)

---

### 3. Country Leaderboard - "How Is Your Country Doing?"

**What:** Public page showing aggregate stats across ALL players: most-picked team, win rates, average placement per country, total goals scored.

**Why it works:**
- Taps into **national pride** and **tribal identity**
- Creates a **reason to return** after finishing your run
- Provides **social proof** and **FOMO** ("120,000 people played, Spain leads with 34% win rate")
- Public page = SEO value

**Implementation plan:**

1. **New Service: `LeaderboardService`**
   - Aggregates across completed tournament games per team: times picked, win rate, avg finish, total goals
   - Cache with 5-minute TTL

2. **Public route: `GET /worldcup/leaderboard`**
   - Sortable country table with flags, stats
   - Hero section: total games played, total completed
   - Mobile-responsive with sticky first column

3. **OG tags** for the leaderboard page

**Files to create/modify:**
- `app/Modules/Competition/Services/LeaderboardService.php` (new)
- `app/Http/Views/ShowLeaderboard.php` (new)
- `resources/views/wc-leaderboard.blade.php` (new)
- `routes/web.php` (add route)

---

### 4. Dramatic Moment Cards - Shareable Match Highlights

**What:** After key dramatic moments (last-minute goal, penalty shootout win, upset, hat trick), auto-generate a compact "moment card" the player can share.

**Why it works:**
- Captures **emotional peaks** when engagement is highest
- More **frequent touchpoints** than end-of-tournament summary
- Mimics how people already share football moments on social media

**Implementation plan:**

1. **New Service: `DramaticMomentDetector`**
   - Detects: last-minute winner (85'+), hat trick, penalty shootout, giant killing, comeback
   - Returns `DramaticMoment` DTO

2. **New Model: `MomentCard`** — `id`, `game_id`, `game_match_id`, `moment_token`, `moment_type`, `headline`, `metadata` (JSON)

3. **Post-match modal:** If dramatic moment detected, show special modal with share button

4. **Public route: `GET /wc/moment/{momentToken}`** — shareable moment page with OG tags

**Files to create/modify:**
- `app/Modules/Match/Services/DramaticMomentDetector.php` (new)
- `app/Modules/Match/DTOs/DramaticMoment.php` (new)
- `app/Models/MomentCard.php` (new)
- `database/migrations/xxxx_create_moment_cards_table.php` (new)
- `app/Http/Views/ShowMomentCard.php` (new)
- `resources/views/wc-moment.blade.php` (new)
- `resources/views/components/moment-modal.blade.php` (new)
- `routes/web.php` (add route)

---

### 5. Zero-Friction Entry from Landing Page

**What:** Dedicated `/worldcup` page with a grid of all 48 country flags. One tap starts the tournament immediately. Defer account creation to after the player is invested.

**Why it works:**
- Removes the **biggest conversion killer** (registration before value)
- The flag grid is **visually compelling** content
- Challenge link path becomes: click → pick team → play (three steps)

**Implementation plan:**

1. **Public route: `GET /worldcup`** — 48 flags in responsive grid
2. **Guest session flow:** Click flag → `POST /worldcup/start` → session-based game if not logged in
3. **Deferred registration:** After first match, prompt to save progress
4. **Simplified v1 alternative:** Flag grid is public, clicking redirects to register/login with team pre-selected via query param (much less complex)

**Complexity note:** Full guest flow is architecturally invasive (touches auth, game creation, middleware). The simplified v1 (public page → login required) gets 80% of the value with 20% of the effort.

**Files to create/modify:**
- `app/Http/Views/ShowWorldCupEntry.php` (new)
- `app/Http/Actions/StartWorldCupGame.php` (new)
- `resources/views/worldcup-entry.blade.php` (new)
- `routes/web.php` (add routes)
- `app/Modules/Season/Services/TournamentCreationService.php` (support team pre-selection)

---

### 6. Real-Time Sync with Actual World Cup

**What:** Sync in-game matchday progression with the real WC2026 schedule (June 11 - July 19, 2026). On the day Brazil plays IRL, that's when the in-game Brazil match is available.

**Why it works:**
- Creates **daily engagement loops** tied to real events
- Every real match = social media conversation = organic discovery
- Creates **urgency** (can't play ahead)

**Tradeoff:** Limits play-at-your-own-pace. Offer as opt-in "Live Mode" alongside standard.

**Implementation plan:**
- `games.live_mode` boolean field
- `AdvanceMatchday` checks real date >= next matchday date
- Countdown UI when matches aren't yet available
- Daily notification when new matches unlock

---

### 7. Bracket Predictions (Pre-Tournament Engagement)

**What:** Before playing, fill out a complete bracket prediction. After the tournament, compare predictions vs actual results.

**Why it works:**
- **Pre-game engagement** - viral loop starts before gameplay
- Bracket predictions are a **proven viral mechanic** (March Madness)
- Standalone entry point for non-players

**Implementation plan:**
- New `BracketPrediction` model with JSON predictions field
- Interactive bracket UI (Alpine.js)
- Post-tournament accuracy score + comparison card
- Shareable comparison page

---

## Recommended Priority & Sequencing

| Priority | Feature | Viral Mechanism | Effort | Dependencies |
|----------|---------|----------------|--------|-------------|
| **P0** | Zero-friction entry | Reduces drop-off, enables all viral loops | Medium-High | None |
| **P0** | Challenge links | 1:1 viral loop (highest conversion) | Low-Medium | Story card model |
| **P1** | My World Cup Story card | Broadcast sharing (high reach) | Medium | None |
| **P1** | Country leaderboard | Retention + national pride + SEO | Medium | None |
| **P2** | Dramatic moment cards | Mid-game sharing touchpoints | Medium | None |
| **P2** | Bracket predictions | Pre-game engagement funnel | Medium-High | None |
| **P3** | Real-time WC sync | Daily engagement loop | High | Schedule data |

### Suggested Build Order

1. **Story Card (#1)** — establishes public routes, OG tags, `TournamentShare` model, share infrastructure
2. **Challenge Links (#2)** — extends story card with challenge dimension (low incremental effort)
3. **Country Leaderboard (#3)** — independent, can be built in parallel with #1/#2
4. **Dramatic Moment Cards (#4)** — reuses share infrastructure from #1
5. **Zero-Friction Entry (#5)** — most complex but highest impact. Start with simplified v1 (public flag grid → login required)
6. **Bracket Predictions (#7)** and **Real-Time Sync (#6)** — nice-to-haves for v1.1

### Shared Infrastructure (Built Once, Reused)

- **Public route group** in `routes/web.php` (no auth middleware)
- **OG meta tag system** in layouts (yield/section for per-page tags)
- **`TournamentShare` model** (story card, challenge links, moment cards)
- **Share token generation** utility (URL-safe unique tokens)
- **Public page layout** (guest layout variant with VirtuaFC branding, no auth nav)
