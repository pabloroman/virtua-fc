# Tactical Freedom Improvement Plan

## Current State Analysis

### What Exists Today

VirtuaFC's tactical system currently provides:

1. **8 formations** (4-4-2, 4-3-3, 4-2-3-1, 3-4-3, 3-5-2, 4-1-4-1, 5-3-2, 5-4-1), each with a flat attack/defense modifier pair
2. **3 mentalities** (Defensive, Balanced, Attacking), each applying own-goals and opponent-goals multipliers to xG
3. **Position compatibility** — 13 positions mapped to 14 pitch slots with 0-100 compatibility scores; out-of-position players receive a rating penalty
4. **Slot-based pitch editor** — players can be dragged to any slot on the formation diagram
5. **Mid-match changes** — formation and mentality can be switched during the live match, plus 5 substitutions in 3 windows
6. **Coach assistant tips** — fitness warnings, morale alerts, opponent formation/mentality prediction, strength comparison

### How It Feels for Users

The system is functional but **tactically shallow**. The only meaningful pre-match decisions are:

- Which 11 players to pick (squad quality is king)
- Which of 8 formations to use (mostly interchangeable since modifiers are small ±10%)
- Which of 3 mentalities to pick (the only real risk/reward lever)

There is no way to express *how* the team should play — no pressing style, no tempo, no width, no passing approach, no counter-attacking vs possession play. Changing from 4-4-2 to 4-3-3 doesn't change the team's style, only moves an xG slider by ±10%. This makes tactical choices feel cosmetic rather than strategic.

### How Real Football Tactics Work (2024-2026)

Modern football at every level revolves around several tactical dimensions that managers control independently:

| Dimension | Real-World Range | What It Affects |
|-----------|-----------------|-----------------|
| **Formation** | Structural shape (e.g., 4-3-3, 3-5-2) | Who occupies which zone of the pitch |
| **Pressing intensity** | High press → Low block | When and where to win the ball back |
| **Defensive line height** | High line → Deep line | Compactness, vulnerability to through balls |
| **Tempo / passing style** | Short build-up → Direct / Long ball | How the team moves the ball forward |
| **Width** | Narrow → Wide | Where attacking play is concentrated |
| **Mentality / risk** | Conservative → Aggressive | How many players commit forward |
| **Player roles** | Inverted winger, false 9, regista, etc. | Individual behavior within the system |

Key insight: **These dimensions are mostly orthogonal.** A 4-3-3 can play high press possession OR deep block counter-attack. The formation describes structure; the instructions describe style.

### How Football Games Handle This

| Game | Depth | Approach |
|------|-------|----------|
| **Football Manager** | Very deep | ~20 team instructions + player roles/duties + individual instructions + set pieces |
| **EA FC (Career)** | Medium | Build-up play, chance creation, defensive approach selectors |
| **Top Eleven** | Light | Formation + focus (attack through wings/middle) + mentality |
| **OSM / Hattrick** | Minimal | Formation + a few tactical orders |

VirtuaFC is closest to OSM-level. The goal is to move toward **Top Eleven / EA FC level** — meaningful choices without Football Manager's complexity. This fits VirtuaFC's identity as a game you can play in short sessions on your phone.

---

## Proposed Changes

### Design Principles

1. **Every tactical choice should create a clear trade-off** — no "always right" answers
2. **Choices should interact with squad composition** — a high press with slow defenders should be risky
3. **The match engine must reflect choices visibly** — different styles should produce different event patterns, not just nudge an xG number
4. **Keep the total number of decisions manageable** — aim for 4-6 decisions pre-match, not 20
5. **Mobile-first** — all tactical controls must work on 375px screens

---

### Phase 1: Quick Wins (Low effort, immediate improvement)

These changes enrich the existing system without new mechanics.

#### 1.1 Add More Formations (4-5 new ones)

**New formations:**
- **4-3-2-1** (Christmas tree) — narrow, midfield-dominant, creative playmaker support
- **4-1-2-1-2** (Narrow diamond) — central overload, no wingers, striker partnership
- **3-4-2-1** — two attacking midfielders behind a lone striker, wing-back dependent
- **4-4-1-1** — one deep forward + one support striker, defensive midfield solidity

**Why:** More formations give players more ways to fit their squad. A team with two great strikers but no wingers naturally wants a 4-1-2-1-2. A team with a #10 wants a 4-3-2-1 or 3-4-2-1.

**Implementation:** Add cases to `Formation` enum, define `pitchSlots()`, `requirements()`, add config modifiers. Purely additive change, no schema changes needed.

#### 1.2 Expand Mentality to 5 Levels

**Current:** Defensive / Balanced / Attacking

**Proposed:** Ultra-Defensive / Defensive / Balanced / Attacking / All-Out Attack

| Mentality | Own Goals Mod | Opponent Goals Mod | Use Case |
|-----------|:------------:|:------------------:|----------|
| Ultra-Defensive | 0.65 | 0.55 | Protect a lead, park the bus |
| Defensive | 0.80 | 0.70 | See out a game, play it safe |
| Balanced | 1.00 | 1.00 | Default, no bias |
| Attacking | 1.15 | 1.10 | Chase a goal, push forward |
| All-Out Attack | 1.35 | 1.30 | Desperate late push, very risky |

**Why:** 3 mentalities is the minimum meaningful set. 5 gives much finer control, especially in match situations. "Holding on to a 1-0 lead with 10 minutes to go" needs something stronger than Defensive but the current system doesn't offer it.

**Implementation:** Add 2 cases to `Mentality` enum, add config entries, update UI selectors (pre-match and tactical panel). No schema changes.

#### 1.3 Improve Formation Modifier Design

**Current problem:** Formation modifiers are symmetric and too similar. 4-3-3 has (1.10, 1.10) and 3-4-3 also has (1.10, 1.10) — they're functionally identical. Defensive formations (5-3-2, 5-4-1) have modifiers below 1.0 for BOTH attack AND defense, meaning they're worse at everything, which is nonsensical. A 5-4-1 should concede less even if it scores less.

**Proposed redesign:**

| Formation | Attack Mod | Defense Mod | Design Intent |
|-----------|:----------:|:----------:|---------------|
| 4-4-2 | 1.00 | 1.00 | Baseline, no bias |
| 4-3-3 | 1.08 | 1.02 | Slight attacking edge with width |
| 4-2-3-1 | 1.03 | 0.95 | Defensively solid, controlled possession |
| 3-4-3 | 1.12 | 1.08 | High risk/reward, wing overload |
| 3-5-2 | 1.00 | 0.97 | Midfield control, conservative |
| 4-1-4-1 | 0.95 | 0.90 | Deep-lying anchor, very compact |
| 5-3-2 | 0.90 | 0.85 | Defensive solidity, limited attack |
| 5-4-1 | 0.82 | 0.78 | Park the bus, ultra-defensive |
| 4-3-2-1 | 1.05 | 1.00 | Central creativity, balanced |
| 4-1-2-1-2 | 1.05 | 1.02 | Narrow but aggressive |
| 3-4-2-1 | 1.08 | 1.05 | Two playmakers, wing-back reliant |
| 4-4-1-1 | 0.97 | 0.95 | Compact, disciplined, counter-ready |

**Key change:** Defense modifier now means "multiplier on *opponent's* xG" where < 1.0 means you concede less. This is already how it works in the code — the current config values were just poorly tuned.

**Implementation:** Config-only change in `match_simulation.php`. No code changes needed.

---

### Phase 2: Team Instructions (Medium effort, high impact)

This is the core of the feature improvement. Introduces a new **Team Instructions** concept — 3 independent tactical axes that work alongside formation and mentality.

#### 2.1 Playing Style

A new selector representing how the team moves the ball:

| Style | Effect on Simulation | Trade-off |
|-------|---------------------|-----------|
| **Possession** | +5% own xG, −5% opponent xG, higher energy drain | Safe but tiring, opponent can hit on counter if you lose the ball |
| **Balanced** | No modifier | Default |
| **Counter-Attack** | −8% own xG normally, but +20% own xG when opponent plays Attacking/All-Out Attack mentality | Sacrifices normal output for devastating counters against aggressive teams |
| **Direct** | +10% own xG variance, +5% opponent xG | More goals but more chaotic; suits teams with a target man |

**Interaction with squad:** Possession style gets a bonus from midfielders with high technical ability. Counter-Attack benefits from forwards with high physical ability (pace). Direct benefits from a strong target forward.

#### 2.2 Pressing Intensity

How aggressively the team tries to win the ball back:

| Level | Effect | Trade-off |
|-------|--------|-----------|
| **High Press** | −8% opponent xG, +15% energy drain per minute | Very effective but exhausting; squad needs high physical ability |
| **Standard** | No modifier | Default |
| **Low Block** | −5% opponent xG, −10% energy drain, +5% own xG for counter-attacks | Conservative, preserves energy, but cedes possession |

**Interaction with squad:** High press calculates an "average physical ability of outfield starters". If it's below 70, the energy drain is even worse (penalty). If above 80, the xG reduction is stronger.

**Interaction with match state:** A high-pressing team that is tired (late in match) becomes less effective — the bonus decreases linearly after minute 60.

#### 2.3 Defensive Line

Where the defensive line sits:

| Height | Effect | Trade-off |
|--------|--------|-----------|
| **High Line** | −5% opponent xG from build-up, BUT if opponent has a forward with physical > 80 → opponent gets +0.1 xG "through ball" bonus | Squeezes the pitch but exposes space behind |
| **Normal** | No modifier | Default |
| **Deep** | −10% opponent xG, −5% own xG | Very hard to break down but limits your own attack |

**Interaction with squad:** A high line's vulnerability depends on the *opponent's* fastest forward. This creates a real tactical puzzle: you might play a high line against a team with slow strikers but drop deep against a team with pacy forwards.

#### 2.4 Data Model

New fields on `Game` model (defaults) and `GameMatch` model (per-match overrides):

```
playing_style:    ENUM('possession', 'balanced', 'counter_attack', 'direct')  DEFAULT 'balanced'
pressing:         ENUM('high_press', 'standard', 'low_block')                 DEFAULT 'standard'
defensive_line:   ENUM('high_line', 'normal', 'deep')                         DEFAULT 'normal'
```

These are stored as:
- `default_playing_style`, `default_pressing`, `default_defensive_line` on `games` table
- `home_playing_style`, `away_playing_style`, `home_pressing`, `away_pressing`, `home_defensive_line`, `away_defensive_line` on `game_matches` table

#### 2.5 UI Design

**Pre-match (lineup screen):** Add a "Team Instructions" section below the formation/mentality bar. Three side-by-side selectors (on mobile: stacked vertically), each with 3-4 pill buttons. Include a one-line tooltip explaining the current selection.

**Live match (tactical panel):** Add Team Instructions as a third tab in the Tactical Control Center modal, alongside Substitutions and Tactics. Changes can be made mid-match and trigger re-simulation.

**Coach assistant:** Extend tips to recommend instructions based on opponent. E.g., "Opponent's fast forwards make a high line risky" or "Their low block could be vulnerable to a possession style."

#### 2.6 AI Team Instructions

AI opponents select team instructions based on:
- Team reputation tier (elite teams favor possession/high press)
- Home/away (home teams press higher, away teams sit deeper)
- Relative strength (weaker teams favor counter-attack + low block)

This is handled in `LineupService::selectMentalityForTeam()`, extended to also select playing style, pressing, and line height.

#### 2.7 Match Engine Integration

In `MatchSimulator::simulateRemainder()`, after calculating base xG from formation + mentality, apply team instruction modifiers:

```php
// Playing style modifiers
$homeXG *= $homePlayingStyle->ownGoalsModifier($awayMentality);
$awayXG *= $awayPlayingStyle->opponentGoalsModifier($homeMentality);

// Pressing modifiers
$homeXG *= ... // pressing affects opponent's xG based on squad physical
$awayXG *= ...

// Defensive line modifiers
$homeXG *= ... // line height affects both teams' xG based on opponent forwards' pace
$awayXG *= ...
```

Energy drain is also modified:
```php
$pressingDrainModifier = match($pressing) {
    'high_press' => 1.15,
    'standard' => 1.00,
    'low_block' => 0.90,
};
// Applied in EnergyCalculator
```

---

### Phase 3: Ambitious Changes (Higher effort, long-term)

These are larger changes that would make VirtuaFC's tactical system genuinely distinctive. They should be considered for future development after Phase 1-2 are stable.

#### 3.1 Formation-vs-Formation Matchups

Instead of formations only modifying your own xG, create interaction effects when specific formations face each other:

**Example:** 3-at-the-back formations are vulnerable to traditional wingers. When 3-5-2 faces 4-3-3, the 4-3-3 gets a +5% xG bonus because the wingers exploit the space behind wing-backs.

**Implementation:** A matchup matrix in config:
```php
'formation_matchups' => [
    '3-5-2' => ['4-3-3' => ['attack_bonus' => -0.05, 'defense_penalty' => 0.05]],
    '4-4-2' => ['4-2-3-1' => ['attack_bonus' => 0.03]],
    // etc.
]
```

This creates a rock-paper-scissors dynamic that rewards scouting and tactical adaptation.

#### 3.2 Tactical Familiarity

Track how often a team plays a specific formation and style. Teams are more effective in formations they use frequently:

- First time using a formation: -5% effectiveness
- Used 3+ times this season: baseline
- Used 10+ times: +3% effectiveness

Stored as a JSON column on `Game` tracking formation usage counts. Encourages players to develop a tactical identity rather than constantly switching.

#### 3.3 Player Roles (Biggest Feature)

Allow assigning a **role** to each pitch slot, changing how the player in that slot contributes to the simulation:

**Example roles for Central Midfield slot:**
- **Box-to-Box** — contributes to both attack and defense modifiers equally
- **Deep-Lying Playmaker** — boosts team's passing-based xG, reduces own defensive contribution
- **Ball Winner** — reduces opponent's xG through midfield, minimal attack contribution

**Why this matters:** Currently all CMs are interchangeable. With roles, a team could have one Ball Winner and one Playmaker for a balanced midfield, or two Box-to-Box for high energy. This gives real meaning to squad building.

**Implementation complexity:** This is the most complex change — requires new Enum(s), per-slot role selection in the UI, role modifiers in the match engine, and role recommendations for AI teams. Estimated at 3-4x the effort of Phase 2.

#### 3.4 Event Pattern Diversity

Currently all tactical setups produce the same types of events (goals by forwards, assists by midfielders, cards by defenders). Different styles should create different event patterns:

- **Counter-attack goals:** More likely to be scored by the fastest forward, goals clustered after opponent possession phases
- **Possession goals:** More evenly distributed, more assists from midfield, goals after sustained pressure
- **High press goals:** More goals from turnovers in the opponent's half (higher chance of goals from midfielders)
- **Direct play goals:** More header goals from target man, assists from full-backs (crosses)

This enriches the post-match narrative and makes tactical choices feel real in the match experience.

---

## Implementation Priority

| Change | Phase | Effort | Impact | Depends On |
|--------|-------|--------|--------|------------|
| 1.1 New formations | 1 | Low | Medium | Nothing |
| 1.2 5-level mentality | 1 | Low | Medium | Nothing |
| 1.3 Formation modifier redesign | 1 | Very Low | Medium | Nothing |
| 2.1 Playing Style | 2 | Medium | High | 1.3 |
| 2.2 Pressing Intensity | 2 | Medium | High | 1.3 |
| 2.3 Defensive Line | 2 | Medium | High | 1.3 |
| 2.4-2.7 Data model, UI, AI, engine | 2 | Medium | Critical | 2.1-2.3 |
| 3.1 Formation matchups | 3 | Low | Medium | 1.1, 2.x |
| 3.2 Tactical familiarity | 3 | Medium | Medium | 1.1 |
| 3.3 Player roles | 3 | High | Very High | 2.x |
| 3.4 Event pattern diversity | 3 | Medium | High | 2.x, 3.3 |

## Files Affected

### Phase 1
- `app/Modules/Lineup/Enums/Formation.php` — new cases, pitchSlots, requirements
- `app/Modules/Lineup/Enums/Mentality.php` — new cases
- `config/match_simulation.php` — modifier values
- `resources/views/lineup.blade.php` — UI for new formations/mentalities
- `resources/views/partials/live-match/tactical-panel.blade.php` — mentality buttons
- `lang/es/*.php` and `lang/en/*.php` — translation keys for new options
- `app/Modules/Lineup/Services/LineupService.php` — AI mentality selection update
- `app/Modules/Lineup/Services/FormationRecommender.php` — evaluate new formations

### Phase 2
- New migration — add columns to `games` and `game_matches` tables
- New Enums — `PlayingStyle`, `PressingIntensity`, `DefensiveLineHeight`
- `app/Modules/Match/Services/MatchSimulator.php` — integrate new modifiers into xG formula
- `app/Modules/Match/Services/EnergyCalculator.php` — pressing affects energy drain
- `app/Modules/Lineup/Services/TacticalChangeService.php` — handle new instruction changes mid-match
- `app/Models/Game.php` — new default columns
- `app/Models/GameMatch.php` — new per-match columns
- `app/Http/Views/ShowLineup.php` — pass new instruction data to view
- `app/Http/Actions/SaveLineup.php` — persist new instructions
- `resources/views/lineup.blade.php` — team instructions section
- `resources/views/partials/live-match/tactical-panel.blade.php` — new instructions tab
- `resources/js/live-match.js` — handle instruction changes
- `lang/es/*.php` and `lang/en/*.php` — translation keys

### Phase 3
- New migration for role assignments, tactical familiarity tracking
- New Enum for player roles per position group
- Extensive match engine changes
- New UI for role assignment per slot
- Formation matchup configuration
