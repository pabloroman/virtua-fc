# Tactical Freedom Improvement Plan

## Part 1: Research — How Tactics Work Elsewhere

### Football Manager 2024 (FM24)

FM24 is the deepest tactical simulation available. Its system is organized around three match phases plus player roles:

**Mentality (5 levels):** Very Defensive → Defensive → Balanced → Positive → Very Attacking. Unlike VirtuaFC's flat multipliers, FM's mentality adjusts *many* underlying variables simultaneously — it shifts pressing intensity, line of engagement, tempo, width, directness, and time-wasting all together. Players on "Automatic" duty also adjust their behavior based on the team's mentality.

**In Possession Instructions:**
- Passing Directness (short build-up ↔ direct/route one)
- Tempo (slow deliberate ↔ fast-paced)
- Attacking Width (narrow ↔ very wide)
- "Pass Into Space" toggle — crucial for fast forwards
- Final Third approach — work ball into box vs. cross from deep

**Out of Possession Instructions:**
- Defensive Line Height (very deep ↔ very high)
- Line of Engagement (where forwards start pressing — separate from defensive line)
- Pressing Intensity (trigger press rarely ↔ much more often)
- "Prevent Short GK Distribution" toggle
- Defensive Width (narrow central block ↔ wide)
- Use Offside Trap toggle

The gap between Line of Engagement and Defensive Line defines the team's vertical compactness. A high engagement line + high defensive line = suffocating press with little space for opponents. A high engagement + low defensive line = trap (lure them forward then hit on the counter).

**Transition Instructions:**
- After losing ball: Counter-Press (gegenpressing) vs. Regroup (fall back into shape)
- After winning ball: Counter-Attack (immediate fast break) vs. Hold Shape (retain possession, build patiently)

**Player Roles (~50+ unique role/duty combinations):**
Each position has multiple roles, each with 1-3 "duties" (Defend/Support/Attack). Examples:
- Central Midfield: Box-to-Box, Deep-Lying Playmaker (D/S), Advanced Playmaker (S/A), Mezzala (S/A), Carrilero, Ball-Winning Midfielder (D/S), Roaming Playmaker
- Striker: Advanced Forward, Deep-Lying Forward, Target Man, Pressing Forward, Poacher, False Nine, Trequartista
- Full-back: Full-Back (D/S/A), Wing-Back (D/S/A), Inverted Wing-Back (D/S/A), Complete Wing-Back
- Wide forward: Winger, Inside Forward, Inverted Winger, Raumdeuter, Wide Target Man

Roles change player movement, positioning, passing tendencies, pressing behavior, and defensive tracking — they're not cosmetic.

**Tactical Familiarity:** Teams take time to learn a new tactical setup. Playing an unfamiliar formation/style reduces effectiveness. Familiarity builds over weeks of training. This prevents constant tactic-switching and rewards developing a tactical identity.

**Set Pieces:** Full set piece routines for corners, free kicks, throw-ins — including player positioning, target selection, delivery type.

---

### EA FC 25 (FC IQ System)

EA FC 25 overhauled its entire tactical system with "FC IQ," built on three pillars:

**Player Roles (31 roles, 52 unique combos with Focus):**
Every position has specific roles with 1-3 focuses (more attacking or defensive). Examples:
- GK: Goalkeeper, Sweeper Keeper
- CB: Defender, Stopper, Ball-Playing Defender
- Full-back: Fullback, Wingback, Falseback (inverted), Attacking Wingback
- CDM: Holding, Centre-Half, Deep-Lying Playmaker
- CM: Box-to-Box, Playmaker, Half-Winger
- Winger: Winger, Inside Forward, Wide Playmaker
- ST: Advanced Forward, Poacher, Target Forward, False 9

**Role Familiarity System:** Players have 4 familiarity levels with each role. A player with two plus signs (++) is world-class in that role, while a yellow exclamation mark means "out of position" and performs less effectively. This creates real squad-building tension — you can't just put anyone anywhere.

**Team Tactics (Build-Up Style + Defensive Approach):**
Rather than granular sliders, EA FC 25 uses preset combinations:
- Build-Up Styles: Short Passing, Balanced, Long Ball, Counter-Attack
- Defensive Approaches: Balanced, Press After Possession Loss, Press on Heavy Touch, Drop Back

**Tactical Presets:** Named systems like Tiki Taka, Gegenpressing, Park the Bus, Counter-Attack — each preset is a specific combination of key player roles + build-up style + defensive approach. All presets work with all formations.

**Tactic Metrics:** The game analyzes your setup across 4 dimensions and shows strengths/weaknesses:
- Width (how wide in/out of possession)
- Length (how spread vertically)
- Endurance (how much movement/rotation)
- Build-Up (passing lane quality, role creativity)

**Smart Tactics:** D-pad quick changes during matches. The system also *suggests* tactical adjustments by analyzing the current match state — e.g., "you're losing, consider switching to a more attacking approach."

**Tactic Codes:** 11-digit shareable codes encoding formation + build-up + defense + all 11 roles. Universal across platforms. This created a massive community metagame of sharing and importing tactics.

---

### Top Eleven (Mobile Manager by Nordeus)

Top Eleven is the closest comparable to VirtuaFC — a mobile-first football manager. Its tactical system has moderate depth:

**Formations:** Highly flexible — no fixed preset list. Players can be placed on a grid, and the game labels the resulting formation. This gives more freedom than VirtuaFC's 8 fixed formations, but less clarity about what each formation does.

**8 Tactical Orders (4 offensive, 4 defensive):**
- **Offensive:** Mentality (Normal/Hard Attacking), Focus Passing (Mixed/Through Middle/Through Wings), Passing Style (Short/Long/Mixed), Force Counter-attacks (On/Off)
- **Defensive:** Pressing Style (Low/Medium/High), Tackling Style (Normal/Hard), Marking Style (Zonal/Man), Offside Trap (On/Off)

This is notably more than VirtuaFC's formation + mentality. The "Focus Passing" (through middle vs wings) is a simple but effective way to express attacking style. The pressing/tackling/marking defensive options create real defensive identity.

**Playstyles (since 2020):** Individual player playstyles define tendencies — similar to EA FC's concept but simpler. Not all players have one.

**Key Difference from VirtuaFC:** Top Eleven gives 8 tactical orders covering offense *and* defense, while VirtuaFC gives only 2 (formation + mentality). Top Eleven's "Focus Passing" alone adds a meaningful dimension VirtuaFC completely lacks.

---

### OSM (Online Soccer Manager) and Hattrick

**OSM:** Minimal tactical system. Choose a formation from presets, pick a "playing style" (attacking/neutral/defensive), and optionally set "attack through wings" or "attack through middle." No pressing, no defensive line, no player roles.

**Hattrick:** Older web-based game with a simple but clever system:
- 5 mentalities (plus "play it cool")
- Tactical orders: Pressing, Counter-Attack, Attack on Wings, Attack in Middle, Play Creatively, Long Shots
- Only one tactical order active at a time
- Each order has clear trade-offs (pressing helps win midfield but tires players)

**Key insight:** Even the simplest games (Hattrick) give players *some* way to express attacking style beyond mentality. VirtuaFC currently doesn't.

---

### Real-World Football Tactics (2024-2026)

Modern football managers control several independent tactical dimensions. Here's how elite La Liga teams use them:

**Pressing Systems:**
- **Gegenpressing (counter-pressing):** Win the ball back within 5-8 seconds of losing it. Popularized by Klopp, now used by many. Physically very demanding — requires fitness and coordination. In La Liga, Athletic Bilbao under Valverde and Barcelona under Flick use versions of this.
- **Positional pressing:** Not an all-out press but pressing in specific zones. Ancelotti's Real Madrid presses selectively — conserving energy, pressing only when the opponent enters certain trigger zones.
- **Low block:** Atletico Madrid's signature. Sit deep, stay compact, deny space, hit on the counter. Simeone's teams accept less possession (~40-45%) but are extremely hard to break down.
- The gap between pressing line and defensive line is the **vertical compactness** — this is a *real* tactical variable that managers adjust.

**Build-Up Play:**
- **Positional play (juego de posición):** Guardiola's Barcelona and Man City. Players occupy specific zones, create numerical superiority through position, build from the back with short passes. Requires high technical ability across all positions.
- **Direct play:** Bypass the midfield, use target men and fast forwards. More common in lower La Liga / Segunda División. Route one football.
- **Progressive build-up:** Somewhere between. Real Sociedad under Alguacil builds patiently but isn't as positional as Guardiola — more fluid, using a diamond midfield that transitions to 4-3-3 in-game.

**Defensive Line:**
- **High line:** Squeezes the pitch, supports pressing, but vulnerable to through balls behind the defense. Effective against slow strikers, dangerous against fast ones like Vinicius Jr. Barcelona under Flick plays a very high line.
- **Deep line:** Atletico's approach. Very compact, hard to break down, but surrenders territory and relies on counter-attacks for offense.
- **Offside trap:** Used with high lines. High reward (catches opponents offside) but high risk (one mistimed step and the attacker is through on goal).

**Width:**
- **Wide play:** Full-backs push high, wingers stay wide, stretch the defense horizontally. Classic Real Madrid with Carvajal and Mendy bombing forward.
- **Narrow/central play:** Inverted wing-backs tuck into midfield, play goes through the center. Guardiola's Man City with the "Cancelo role" (inverted full-backs creating a 2-3-5 in possession).
- **Asymmetric width:** One full-back overlaps wide, the other inverts. This is increasingly common — e.g., one side provides width while the other overloads the center.

**Key Tactical Innovations in 2024-2026:**
1. **Inverted full-backs** — Full-backs moving into central midfield in possession. Creates numerical advantage in midfield while keeping wide options from wingers.
2. **Hybrid formations** — Teams that defend in one shape (e.g., 4-4-2) but attack in another (e.g., 3-2-5). The formation is fluid, not fixed.
3. **Hybrid forwards** — Players like Mbappé, Vinicius, who play across winger/striker roles interchangeably. The modern frontline blurs the distinction between AM, winger, and striker.
4. **Goalkeeper as outfield player** — Short passing from GK has risen from 44.6% to 54.9% in 4 seasons. GKs are part of build-up play.
5. **Return of the #9** — After years of false 9s, teams are using traditional target strikers again (Haaland, Lewandowski, Morata). Direct approach counters pressing structures.
6. **Two-striker systems returning** — Juventus, PSG, and others use split strikers to stretch defenses horizontally.

---

## Part 2: Gap Analysis — VirtuaFC vs. The Field

### Comparison Matrix

| Tactical Dimension | FM24 | EA FC 25 | Top Eleven | VirtuaFC | Gap Severity |
|---|---|---|---|---|---|
| **Formations** | ~30+ | ~25 | Free grid | 8 fixed | Medium |
| **Mentality levels** | 5 | N/A (preset) | 2 (normal/hard) | 3 | Low-Medium |
| **Playing style / Build-up** | Tempo + Directness + Width | Build-Up Style (4 options) | Focus Passing + Passing Style | **None** | **Critical** |
| **Pressing intensity** | Line of Engagement + Trigger Press | Defensive Approach (4 options) | Pressing Style (3 levels) | **None** | **Critical** |
| **Defensive line height** | Defensive Line slider + Offside Trap | Drop Back vs. High | Offside Trap toggle | **None** | **High** |
| **Transition behavior** | Counter-Press vs Regroup / Counter vs Hold | Implicit in presets | Force Counter-attacks toggle | **None** | **High** |
| **Width control** | Attacking Width + Defensive Width | Width metric | Through Wings/Middle | **None** | **Medium** |
| **Marking style** | Man/Zonal per player | Implicit in AI | Zonal/Man toggle | **None** | **Medium** |
| **Player positional versatility** | Full position training | 4-tier familiarity per position | N/A | **Single position only** | **High** |
| **Individual player instructions** | 10+ per-player toggles | Implicit in roles | N/A | **None** | **High** (long-term) |
| **Player roles** | ~50+ role/duty combos | 31 roles, 52 combos | Playstyles (limited) | **None** | **High** (long-term) |
| **Role familiarity** | Training-based familiarity | 4-tier familiarity per role | N/A | **None** | **Medium** (long-term) |
| **Tactical familiarity** | Team learns over weeks | N/A | Implicit (stick to formation) | **None** | **Low** |
| **Formation interactions** | Implicit via player movement | Implicit via AI | N/A | **None** | **Medium** |
| **Set pieces** | Full routines | Limited | N/A | **None** | **Low** |
| **Tactic sharing** | N/A | 11-digit codes | N/A | **None** | **Low** (nice-to-have) |
| **Coach/AI suggestions** | Assistant feedback | Smart Tactics + weakness analysis | N/A | Basic tips | **Medium** |
| **Mid-match changes** | Full tactical changes | D-pad quick tactics + suggestions | Full changes | Formation + mentality only | **Medium** |

### The 9 Critical Gaps

Ranked by impact on user experience:

**1. No way to express playing style (CRITICAL)**
This is the single biggest gap. Every competitor — even the simplest (Hattrick, OSM) — gives users *some* control over attacking approach. VirtuaFC has zero. A user cannot say "I want to play possession football" or "I want to counter-attack." The entire attacking identity is reduced to a mentality slider.

**2. No pressing control (CRITICAL)**
Pressing is the defining tactical concept of modern football. Gegenpressing vs. low block is how fans describe teams in real life. VirtuaFC's simulation has energy/stamina mechanics that would naturally support pressing (high press = more energy drain) but there's no user-facing control.

**3. No defensive line height (HIGH)**
Whether to play a high line or sit deep is one of the most important and most discussed tactical decisions in real football. It has a natural trade-off (high line = catches offside but vulnerable to pace) that would interact beautifully with VirtuaFC's existing physical ability attribute.

**4. No transition behavior (HIGH)**
What happens in the 5 seconds after winning/losing the ball is tactically crucial. Counter-pressing vs. regrouping, and counter-attacking vs. holding possession, are separate dimensions from mentality. FM24 and Top Eleven both model this.

**5. No width/focus control (MEDIUM)**
"Attack through wings" vs. "attack through the middle" is a simple but effective knob that even Top Eleven provides. It gives meaning to having good wingers vs. good central midfielders.

**6. No marking control (MEDIUM)**
Top Eleven provides Zonal/Man marking. Marking style is a real tactical decision (man-mark Messi vs. defend zonally) with clear risk/reward. Missing from VirtuaFC entirely.

**7. Players locked to single position (HIGH)**
Every GamePlayer has exactly one position. In real football, Araujo plays CB and RB, Cancelo plays both flanks, Kimmich operates at DM and RB. FM24 has full position training, EA FC 25 has 4-tier position familiarity. VirtuaFC treats all Centre-Backs identically regardless of their real versatility.

**8. No individual player instructions (HIGH, long-term)**
FM24 allows 10+ per-player toggles (stay back, roam, close down more, etc.). EA FC 25 embeds this in roles. VirtuaFC has zero per-player tactical control — every player follows the same team-level instructions. This limits tactical expression significantly. Should be built after team instructions are solid.

**9. No player roles (HIGH, long-term)**
Both FM24 and EA FC 25 have deep role systems with familiarity. This gives meaning to individual player development and squad building — a CM isn't just a CM, they're a specific type of CM. This is the biggest long-term gap but also the most complex to implement.

### Where VirtuaFC Sits Today

```
Hattrick/OSM ← VirtuaFC is here → Top Eleven → EA FC 25 → FM24
(minimal)       (barely above)      (moderate)    (rich)     (deep)
```

VirtuaFC has **fewer tactical options than any competitor in its weight class**. Even the simplest mobile managers provide at least a pressing toggle and an attack focus (wings/middle). The formation modifier + mentality system is the bare minimum. The simulation engine is solid statistically, but users can't *do* anything with it beyond picking players and a mentality.

### What VirtuaFC Should NOT Copy

- FM24's granularity (20+ individual sliders) — too complex for mobile sessions
- EA FC 25's real-time player movement system — irrelevant for a simulation game
- Full set piece routines — low ROI for a text-based simulation
- Tactic sharing codes — nice-to-have, not core

### What VirtuaFC SHOULD Copy

- **Top Eleven's 8 tactical orders** as the minimum bar (VirtuaFC should at least match this)
- **EA FC 25's Build-Up Style + Defensive Approach** as the ideal model (small number of named options with clear identities)
- **FM24's transition concept** (counter-press vs regroup, counter vs hold) as a simple toggle
- **EA FC 25's tactic metrics** showing strengths/weaknesses of your setup
- **FM24's tactical familiarity** as a light bonus system

---

## Part 3: Updated Implementation Plan

### Design Principles (refined after research)

1. **Match Top Eleven as the minimum bar** — at least 6-8 tactical decisions, not 2
2. **Use EA FC 25's "named options" approach** — not sliders, but 3-4 named choices per dimension with clear identities
3. **Every instruction must interact with squad attributes** — pressing needs physical ability, possession needs technical ability
4. **Instructions must interact with each other AND with opponent's instructions** — counter-attack is devastating against an opponent playing attacking mentality + high line
5. **The existing energy/stamina system is a natural fit** — pressing should burn energy, low block should conserve it
6. **Mobile-first: 6-7 total tactical decisions pre-match, displayed as pill-button selectors**
7. **Players are not locked to one position** — every player can have alternate positions reflecting real versatility (Araujo at RB, Cancelo on both flanks, Kimmich at DM or RB)
8. **Build toward individual player instructions** — team-level instructions first (Phase 2), then per-player tactical orders (Phase 3) for maximum depth

---

### Phase 1: Quick Wins + Player Versatility

**1.1 Add 4 New Formations**
- 4-3-2-1 (Christmas tree), 4-1-2-1-2 (diamond), 3-4-2-1, 4-4-1-1
- Fills squad-composition gaps (no wingers? diamond. Have a #10? Christmas tree)

**1.2 Expand Mentality to 5 Levels**
- Ultra-Defensive / Defensive / Balanced / Attacking / All-Out Attack
- Matches FM24's 5-tier system, gives critical match-state granularity

**1.3 Fix Formation Modifiers**
- Current 5-3-2 has attack=0.90 AND defense=0.90 (worse at everything — broken)
- Redesign so defensive formations sacrifice attack for real defensive solidity
- 5-4-1 should be: attack=0.82, defense=0.78 (concede much less, score less)

**1.4 Secondary Positions (Player Versatility)**

Currently every `GamePlayer` has exactly one `position`, and the `PositionSlotMapper` treats all players of the same position identically. In real football, players like Araujo can play CB and RB, Cancelo plays both flanks, and Kimmich operates as DM or RB. This is a fundamental limitation.

**Data model:**
- Add `alternate_positions` JSON column to `game_players` table (0-3 additional positions per player)
- Primary position stays in the existing `position` column
- Example: Araujo → `position: "Centre-Back"`, `alternate_positions: ["Right-Back"]`

**Compatibility scoring upgrade:**
- Add `PositionSlotMapper::getPlayerCompatibilityScore(primary, alternates, slotCode)` method
- Alternate positions are looked up as if they were the player's primary, but capped at 85 compatibility (good but not quite natural)
- Example: Araujo at RB slot → `max(40 from generic CB, 85 from RB alternate)` = **85** vs the old flat **40**
- An 80-rated Araujo at RB: old system = 56 effective, new system = **74 effective** (genuinely usable)

**Data sources:**
- **Real players (reference JSON):** Add `alternate_positions` field to `data/2025/ESP1/teams.json`, etc. Curated from real-world data (Transfermarkt, FBref position history)
- **Generated players (`PlayerGeneratorService`):** Position-based probability rules reflecting real patterns:
  - LBs → 30% chance of Left Midfield alternate, 15% Left Winger
  - CBs → 20% DM, 15% RB or LB
  - DMs → 40% CM, 20% CB
  - Wingers → 35% corresponding Midfield, 20% opposite wing, 15% CF
  - CFs → 35% SS, 15% either wing
  - Goalkeepers → no alternates
  - Cap at 2 alternates per generated player

**UI changes:**
- Squad list: show alternate positions as small secondary badges next to primary → `[CB] Araujo · [LI]`
- Lineup pitch: compatibility indicator becomes per-player (not per-position), showing actual score
- Player detail card: list all playable positions with compatibility tiers

**Files affected:**
- New migration: `alternate_positions` JSON column on `game_players`
- `app/Models/GamePlayer.php` — add to `$fillable`, `$casts` as array
- `app/Support/PositionSlotMapper.php` — add `getPlayerCompatibilityScore()`, update `getEffectiveRating()` to accept alternates
- `data/2025/ESP1/teams.json` (and ESP2, UCL, etc.) — add `alternate_positions` to real player data
- `app/Modules/Season/Jobs/SetupNewGame.php` — read `alternate_positions` from JSON
- `app/Modules/Squad/Services/PlayerGeneratorService.php` — generate alternates for synthetic players
- `app/Modules/Squad/DTOs/GeneratedPlayerData.php` — add `alternatePositions` field
- `app/Modules/Lineup/Services/FormationRecommender.php` — use player-aware compatibility
- `app/Modules/Lineup/Services/LineupService.php` — use player-aware compatibility in auto-select
- `app/Http/Views/ShowLineup.php` — pass `alternate_positions` to JavaScript
- `resources/views/lineup.blade.php` — display alternate positions in player cards

---

### Phase 2: Team Instructions (migration + new enums + engine changes)

The core feature. Three new tactical axes, modeled after EA FC 25's "named options" approach:

**2.1 Playing Style** (in possession)
| Style | Identity | Effect | Squad Fit |
|---|---|---|---|
| Possession | Tiki-taka / positional play | +5% own xG, −5% opponent xG, +10% energy drain | High technical midfield |
| Balanced | No specific identity | No modifier | Any squad |
| Counter-Attack | Simeone / low-block transition | −8% own xG normally, +20% vs attacking opponents | Fast forwards, solid defense |
| Direct | Route one / target man | +10% xG variance, +5% opponent xG | Strong target striker |

**2.2 Pressing Intensity** (out of possession)
| Level | Identity | Effect | Squad Fit |
|---|---|---|---|
| High Press | Gegenpressing | −8% opponent xG, +15% energy drain, fades after min 60 | High physical outfield |
| Standard Press | Balanced approach | No modifier | Any squad |
| Low Block | Deep defending | −5% opponent xG, −10% energy drain, +5% own xG on counters | Disciplined defenders |

**2.3 Defensive Line** (out of possession)
| Height | Identity | Effect | Squad Fit |
|---|---|---|---|
| High Line | Guardiola / Flick | −5% opponent xG, BUT opponent's fastest forward physical >80 → +0.1 xG through-ball bonus | Fast centre-backs |
| Normal | Standard | No modifier | Any squad |
| Deep | Simeone / Mourinho | −10% opponent xG, −5% own xG | Physical centre-backs |

**2.4 Attacking Focus** (simple, Top Eleven-inspired)
| Focus | Identity | Effect | Squad Fit |
|---|---|---|---|
| Wings | Wide play, crosses | Goal scorer weights shift: wingers +30%, CFs −10% | Good wingers |
| Mixed | No bias | No modifier | Any squad |
| Central | Through the middle | Goal scorer weights shift: AMs/CFs +20%, wingers −20% | Good #10 and strikers |

**2.5 Marking Style** (out of possession, Top Eleven-inspired)
| Style | Identity | Effect | Squad Fit |
|---|---|---|---|
| Zonal | Positional defending | Default behavior — players defend space, not individuals. Solid against fluid rotations | Well-organized defenders |
| Man Marking | Follow assigned opponent | −5% opponent xG from key creator (best player marked tightly), BUT +8% xG if marker is outclassed (physical/technical gap >15) | Physical, disciplined defenders |

Marking adds a meaningful risk/reward decision. Man marking is devastating against teams with one star player (neutralize their Messi) but risky if the marker is outclassed — the attacker drags them out of position, creating space for others. Zonal is safer but can't target a specific threat.

**Why 5 axes:**
- Playing Style = how you attack (in possession)
- Pressing = how you defend (out of possession intensity)
- Defensive Line = where you defend (out of possession shape)
- Attacking Focus = where you attack (in possession direction)
- Marking = how you track opponents (out of possession method)

This gives 4 × 3 × 3 × 3 × 2 = 216 possible instruction combinations (before formation and mentality). Combined with 12 formations × 5 mentalities, that's 12,960 unique tactical setups. Enough to feel like real tactical freedom without FM-level complexity.

**2.6 Instruction Interactions (the key differentiator)**

What makes this system interesting is that instructions interact with each other and the opponent:

| Your Setup | Opponent Setup | Interaction |
|---|---|---|
| Counter-Attack | Attacking mentality + High Line | Your counter-attacks are devastating (+20% xG bonus) |
| High Press | Low physical squad (<70 avg) | Press fades faster, energy drain worsened |
| High Line | Opponent has fast forward (physical >80) | They get through-ball bonus (+0.1 xG) |
| Possession | Opponent plays High Press | You're under pressure, higher turnover risk |
| Direct + Wings | Opponent plays 3-at-the-back | Wingers exploit wing-back gaps |
| Low Block + Counter | Your squad has fast forwards | Counter-attack goals more likely |
| Man Marking | Opponent has one star creator (>85 overall) | Star player's goal/assist contribution reduced by −30% |
| Man Marking | Opponent has balanced squad (no clear star) | Marking is spread thin — minimal benefit, risk of positional gaps |
| Man Marking | Your marker is outclassed (physical gap >15) | Marker gets dragged out of position — opponent xG bonus +8% |
| Zonal Marking | Opponent uses fluid rotations (Possession style) | Zonal holds shape — no gaps to exploit |

**2.7 Coach Assistant Enhancement**

The coach should analyze opponent instructions and recommend counter-strategies:
- "Opponent is expected to press high. Your squad's physical ability (avg 74) should cope, but consider a Direct style to bypass their press."
- "They play a high defensive line. Your striker Mbappé (physical 89) could exploit the space behind — consider Counter-Attack."
- "Opponent sits deep with a Low Block. Possession play with wing focus could stretch their defense."

---

### Phase 3: Ambitious (future, after Phase 2 stabilizes)

**3.1 Individual Player Instructions**

The most requested advanced feature. Allow the user to give specific tactical orders to individual players, overriding or specializing the team-level instructions.

**Per-player instruction options (curated, not freeform):**

| Position Group | Available Instructions | Effect |
|---|---|---|
| **Defenders** | Stay Back / Balanced / Join Attack | Modifies player's contribution to attack events (goal/assist weights) and defensive positioning risk |
| **Defenders** | Mark Tightly / Hold Position | Tight marking reduces specific opponent's output but risks being pulled out of position |
| **Full-backs** | Overlap / Invert / Balanced | Overlap: provides width (winger-like event weights). Invert: tucks into midfield (CM-like contribution). Changes both offensive and defensive profile |
| **Midfielders** | Sit Deep / Box-to-Box / Push Forward | Shifts player's contribution between defensive recovery and attacking output |
| **Midfielders** | Free Roam / Hold Position | Free roam increases creative output but reduces defensive coverage |
| **Wingers** | Stay Wide / Cut Inside / Free Roam | Stay wide: more crosses, overlap with FB. Cut inside: more shots, inverted winger profile. Free roam: unpredictable |
| **Forwards** | Drop Deep / Stay Central / Run Channels | Drop deep: false 9 profile (more assists, fewer goals). Stay central: poacher (more goals). Run channels: exploits high lines |

**Design constraints:**
- Maximum 1-2 instructions per player (not FM's 10+ individual toggles)
- Only available for players in the starting XI (not bench)
- UI: tap a player on the pitch → small popup with 2-3 instruction choices
- Default is always "Balanced" / "Follow team instructions" — individual instructions are optional overrides
- Each instruction has clear squad-fit requirements (e.g., "Overlap" on a full-back with physical <60 = warning)

**Data model:**
- `player_instructions` JSON column on `game_matches` (maps player_id → instruction enum)
- Example: `{"uuid-of-fullback": "overlap", "uuid-of-winger": "cut_inside"}`
- No migration on `game_players` — instructions are per-match, not permanent

**Match engine integration:**
- Individual instructions modify the player's event weights (goals, assists, cards) and effective rating in their slot
- A full-back with "Overlap" instruction: +20% assist weight, −10% defensive contribution, +5% energy drain
- A winger with "Cut Inside": shifts goal weight from winger profile toward AM/SS profile
- A forward with "Drop Deep": reduces goal weight by 20%, increases assist weight by 30% (false 9 effect)

**AI opponents:** AI managers pick 0-2 individual instructions for their key players based on squad composition and team style, adding tactical variety to matches.

**3.2 Formation-vs-Formation Matchups**
Config-driven matrix of interaction bonuses when specific formations face each other. Rewards scouting.

**3.3 Tactical Familiarity**
Light familiarity bonus — teams get +3% effectiveness in formations used 10+ times this season, −5% in first-time formations. Encourages tactical identity.

**3.4 Player Roles (the biggest feature)**
Per-slot role selection from a curated list per position group. Start with 2-3 roles per position, not FM's 6-8. Include familiarity per player-role combination. This extends individual instructions (3.1) into a full role system with training-based progression.

**3.5 Event Pattern Diversity**
Different instructions produce different event distributions — counter-attack goals come from fast forwards on the break, possession goals come from sustained midfield play with more assists. Makes the live match narrative feel different.

**3.6 Transition Behavior**
Add a 6th team axis: Counter-Press / Standard / Regroup (what happens in the 5 seconds after losing the ball). Counter-press burns energy but recovers possession faster.

---

## Implementation Priority

| Change | Phase | Effort | Impact | Gap Addressed |
|--------|-------|--------|--------|---------------|
| 1.1 New formations | 1 | Low | Medium | Formation variety |
| 1.2 5-level mentality | 1 | Low | Medium | Match-state granularity |
| 1.3 Fix formation modifiers | 1 | Very Low | Medium | Broken defensive formations |
| 1.4 Secondary positions | 1 | Medium | **High** | **Players locked to single position** |
| 2.1 Playing Style | 2 | Medium | **Critical** | **No attacking identity** |
| 2.2 Pressing Intensity | 2 | Medium | **Critical** | **No pressing control** |
| 2.3 Defensive Line | 2 | Medium | **High** | **No defensive shape** |
| 2.4 Attacking Focus | 2 | Low | **High** | **No width/direction** |
| 2.5 Marking Style | 2 | Low | **Medium** | **No defensive method** |
| 2.6 Instruction interactions | 2 | Medium | **Critical** | Instructions must matter |
| 2.7 Coach enhancement | 2 | Low | Medium | User guidance |
| 3.1 Individual player instructions | 3 | High | **Very High** | **No per-player tactics** |
| 3.2 Formation matchups | 3 | Low | Medium | Rock-paper-scissors |
| 3.3 Tactical familiarity | 3 | Medium | Medium | Tactical identity |
| 3.4 Player roles | 3 | High | Very High | Role meaning |
| 3.5 Event pattern diversity | 3 | Medium | High | Narrative variety |
| 3.6 Transition behavior | 3 | Low | Medium | Counter-press concept |

## Files Affected

### Phase 1
- `app/Modules/Lineup/Enums/Formation.php` — new cases, pitchSlots, requirements
- `app/Modules/Lineup/Enums/Mentality.php` — new cases
- `config/match_simulation.php` — modifier values
- `resources/views/lineup.blade.php` — UI for new formations/mentalities
- `resources/views/partials/live-match/tactical-panel.blade.php` — mentality buttons
- `lang/es/*.php` and `lang/en/*.php` — translation keys
- `app/Modules/Lineup/Services/LineupService.php` — AI mentality selection
- `app/Modules/Lineup/Services/FormationRecommender.php` — evaluate new formations
- New migration — `alternate_positions` JSON column on `game_players`
- `app/Models/GamePlayer.php` — add `alternate_positions` to fillable/casts
- `app/Support/PositionSlotMapper.php` — add `getPlayerCompatibilityScore()`, update `getEffectiveRating()`
- `data/2025/ESP1/teams.json` (and ESP2, UCL, etc.) — add `alternate_positions` to real player data
- `app/Modules/Season/Jobs/SetupNewGame.php` — read `alternate_positions` from JSON
- `app/Modules/Squad/Services/PlayerGeneratorService.php` — generate alternates for synthetic players
- `app/Modules/Squad/DTOs/GeneratedPlayerData.php` — add `alternatePositions` field
- `app/Http/Views/ShowLineup.php` — pass `alternate_positions` to JavaScript

### Phase 2
- New migration — add columns to `games` and `game_matches` tables
- New Enums — `PlayingStyle`, `PressingIntensity`, `DefensiveLineHeight`, `AttackingFocus`, `MarkingStyle`
- `app/Modules/Match/Services/MatchSimulator.php` — integrate instruction modifiers into xG formula + goal scorer weight adjustments + marking effects
- `app/Modules/Match/Services/EnergyCalculator.php` — pressing affects energy drain
- `app/Modules/Lineup/Services/TacticalChangeService.php` — handle instruction changes mid-match
- `app/Modules/Lineup/Services/LineupService.php` — AI instruction selection (including marking)
- `app/Models/Game.php` — new default columns
- `app/Models/GameMatch.php` — new per-match columns
- `app/Http/Views/ShowLineup.php` — pass instruction data to view
- `app/Http/Actions/SaveLineup.php` — persist instructions
- `resources/views/lineup.blade.php` — team instructions section
- `resources/views/partials/live-match/tactical-panel.blade.php` — instructions tab
- `resources/js/live-match.js` — handle instruction changes
- `lang/es/*.php` and `lang/en/*.php` — translation keys

### Phase 3
- `game_matches` migration — add `player_instructions` JSON column for per-player tactical orders
- New Enums for individual instructions per position group (DefenderInstruction, FullbackInstruction, MidfielderInstruction, WingerInstruction, ForwardInstruction)
- `app/Modules/Match/Services/MatchSimulator.php` — individual instruction effects on event weights and effective ratings
- New migration for role assignments, tactical familiarity tracking
- New Enum for player roles per position group
- Role familiarity per player
- `resources/views/lineup.blade.php` — tap-to-configure per-player instructions on the pitch
- New UI for role assignment per slot
- Formation matchup configuration
