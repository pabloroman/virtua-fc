# Tactical Freedom — Implementation Plan

## Scope

Four features, ordered by dependency:

1. **Fix Formation Modifiers** — rebalance so defensive formations actually defend
2. **Playing Style** — in-possession identity (Possession / Balanced / Counter-Attack / Direct)
3. **Pressing Intensity** — out-of-possession energy (High Press / Standard / Low Block)
4. **Defensive Line** — out-of-possession shape (High Line / Normal / Deep)

Everything else (secondary positions, marking, individual player instructions, new formations, mentality expansion) is explicitly out of scope for this iteration.

---

## Part 1: Match Simulation Engine Impact

### Current xG Formula

```
homeXG = (strengthRatio^2 × 1.3 + 0.15)
       × homeFormation.attack
       × awayFormation.defense
       × homeMentality.ownGoals
       × awayMentality.opponentGoals
       × matchFraction
       + strikerBonus
```

Each new instruction adds multipliers to this formula. The design goal: every instruction has a **clear trade-off** visible in the numbers, and instructions **interact with each other and with opponent instructions** to create genuine tactical decisions.

### 1. Fix Formation Modifiers

**Problem:** Current modifiers are symmetric — 5-3-2 gets attack=0.90 AND defense=0.90, meaning both teams simply score fewer goals. There's no real trade-off; defensive formations just make matches boring.

**Current values:**

| Formation | Attack | Defense | Net Effect |
|-----------|--------|---------|------------|
| 4-4-2 | 1.00 | 1.00 | Baseline |
| 4-3-3 | 1.10 | 1.10 | Both teams +10% (open game) |
| 4-2-3-1 | 1.00 | 0.95 | Opponent −5% |
| 3-4-3 | 1.10 | 1.10 | Both teams +10% |
| 3-5-2 | 1.05 | 1.05 | Both teams +5% |
| 4-1-4-1 | 0.95 | 0.95 | Both teams −5% |
| 5-3-2 | 0.90 | 0.90 | Both teams −10% |
| 5-4-1 | 0.85 | 0.85 | Both teams −15% |

**Reminder on how modifiers apply:**
- `homeFormation.attack` multiplies **your** xG (higher = you score more)
- `awayFormation.defense` multiplies **opponent's** xG (higher = opponent scores more against you, lower = they score less)

So `defense < 1.0` means your formation is defensively solid (opponent xG reduced).

**Fixed values — asymmetric trade-offs:**

| Formation | Attack | Defense | Your xG Change | Opponent xG Change | Identity |
|-----------|--------|---------|----------------|-------------------|----------|
| 4-4-2 | 1.00 | 1.00 | — | — | Balanced baseline |
| 4-3-3 | 1.08 | 1.04 | +8% | +4% leaked | Attacking, slightly open |
| 4-2-3-1 | 1.03 | 0.97 | +3% | −3% | Solid and creative |
| 3-4-3 | 1.12 | 1.08 | +12% | +8% leaked | Very attacking, exposed |
| 3-5-2 | 1.00 | 0.96 | — | −4% | Midfield control |
| 4-1-4-1 | 0.95 | 0.92 | −5% | −8% | Defensive midfield shield |
| 5-3-2 | 0.88 | 0.86 | −12% | −14% | Defensive, hard to break |
| 5-4-1 | 0.80 | 0.82 | −20% | −18% | Park the bus |

**Design logic:**
- Attacking formations (3-4-3, 4-3-3): you score more, but you also leak goals. The attack gain exceeds the defensive cost, making them rewarding but risky.
- Defensive formations (5-3-2, 5-4-1): you score much less, but the opponent scores even less. The defensive gain exceeds the offensive cost, making them viable for protecting leads or playing against stronger teams.
- The gap between attack and defense modifiers creates the asymmetry. 5-4-1 loses 20% of your goals but removes 18% of opponent goals — net +2% defensive advantage at a massive offensive cost.

### 2. Playing Style (In Possession)

New column in xG formula: `× playingStyleModifier`

| Style | Own xG Modifier | Opponent xG Modifier | Energy Drain | Interaction Effects |
|-------|-----------------|---------------------|--------------|---------------------|
| **Possession** | ×1.05 | ×0.95 | +10% drain | Bonus reduced if squad avg technical < 70. If opponent plays High Press → possession disrupted: own xG modifier drops to ×1.00, opponent xG modifier worsens to ×1.00 |
| **Balanced** | ×1.00 | ×1.00 | — | No interactions |
| **Counter-Attack** | ×0.92 | ×0.95 | −5% drain | If opponent plays Attacking mentality OR High Line → counter bonus: own xG modifier becomes ×1.08. If opponent plays Low Block + Deep → counter nullified: stays at ×0.92 |
| **Direct** | ×1.02 | ×1.03 | — | If opponent plays High Press → bypasses press: own xG modifier becomes ×1.08. Goal scorer weights shift: CFs +15%, AMs −10% |

**How it works in the engine:**

Playing Style adds two multipliers to the xG formula:

```
homeXG *= homePlayingStyle.ownXGModifier(context)
homeXG *= awayPlayingStyle.opponentXGModifier(context)
```

Where `context` includes opponent's instructions and squad attributes. The interaction effects override the base modifiers when conditions are met.

**Squad fitness interaction:**
- Possession's +10% energy drain is applied in `EnergyCalculator.drainPerMinute()` as a multiplier: `drain *= 1.10`
- Counter-Attack's −5% energy drain: `drain *= 0.95`
- This compounds with the existing physical ability drain reduction, meaning physically fit squads handle Possession better

**Goal scorer weight shifts (Direct style):**
Applied in `selectGoalScorer()` — the position-based weights are modified:
- Centre-Forward weight: 25 → 29 (+15%)
- Attacking Midfield weight: 12 → 11 (−10%)
- All other weights unchanged

### 3. Pressing Intensity (Out of Possession)

New column in xG formula: `× pressingModifier`

| Level | Opponent xG Modifier | Energy Drain | Fade Effect | Interaction Effects |
|-------|---------------------|--------------|-------------|---------------------|
| **High Press** | ×0.90 | +15% drain | After minute 60: modifier fades linearly from ×0.90 to ×0.97 by minute 90 | If squad avg physical < 70 → extra +5% drain, fade starts at minute 50. If opponent plays Direct → press partially bypassed: modifier becomes ×0.93 |
| **Standard** | ×1.00 | — | — | No interactions |
| **Low Block** | ×0.94 | −8% drain | — | If opponent plays Possession → low block tested more: modifier worsens to ×0.97. Own counter-attack bonus +3% xG (compact shape enables quick breaks) |

**How it works in the engine:**

Pressing only modifies the **opponent's xG**, not your own (it's a defensive instruction):

```
homeXG *= awayPressing.opponentXGModifier(minute, context)
```

**The fade mechanic** is the key innovation. High Press is powerful early but degrades:

```php
public function getPressingModifier(int $minute, float $squadAvgPhysical): float
{
    if ($this !== PressingIntensity::HIGH_PRESS) {
        return $this->baseModifier();
    }

    $fadeStart = $squadAvgPhysical < 70 ? 50 : 60;
    $fadeEnd = 90;

    if ($minute <= $fadeStart) {
        return 0.90; // Full press effect
    }

    // Linear fade from 0.90 to 0.97
    $progress = ($minute - $fadeStart) / ($fadeEnd - $fadeStart);
    return 0.90 + (0.07 * min(1.0, $progress));
}
```

**Energy drain implementation:**
Applied as a multiplier on `EnergyCalculator.drainPerMinute()`:
- High Press: `drain *= 1.15` (or 1.20 if physical < 70)
- Low Block: `drain *= 0.92`

This creates a real decision: High Press is the best defensive option early in the match, but your players tire faster. By minute 70-75, the advantage evaporates and tired legs become a liability. Substitutions become tactically important — bring fresh legs to sustain the press, or switch to Standard/Low Block.

### 4. Defensive Line (Out of Possession)

New column in xG formula: `× defensiveLineModifier`

| Height | Opponent xG Modifier | Own xG Modifier | Interaction Effects |
|--------|---------------------|-----------------|---------------------|
| **High Line** | ×0.94 | ×1.03 | If opponent's fastest forward has physical > 80 → through-ball bonus: opponent xG modifier becomes ×1.00 (high line nullified). If combined with High Press → compound bonus: opponent xG modifier becomes ×0.88 (but double energy cost) |
| **Normal** | ×1.00 | ×1.00 | No interactions |
| **Deep** | ×0.92 | ×0.94 | If combined with Counter-Attack → own xG modifier improves to ×0.98 (deep line fuels better counters). If opponent plays Possession → deep line tested: opponent xG modifier worsens to ×0.95 |

**How it works in the engine:**

Defensive Line affects **both** teams' xG:

```
homeXG *= homeDefLine.ownXGModifier(context)
homeXG *= awayDefLine.opponentXGModifier(context)
```

**The through-ball mechanic:**

```php
public function getOpponentXGModifier(array $opponentPlayers): float
{
    if ($this !== DefensiveLineHeight::HIGH_LINE) {
        return $this->baseOpponentModifier();
    }

    // Find opponent's fastest forward
    $fastestForwardPhysical = collect($opponentPlayers)
        ->filter(fn ($p) => in_array($p->position_group, ['Forward']))
        ->max('physical_ability');

    if ($fastestForwardPhysical > 80) {
        return 1.00; // High line completely nullified
    }

    return 0.94; // High line works — opponent suppressed
}
```

**Key interaction: High Line + High Press = the Flick system:**
- Pressing modifier: ×0.90 on opponent xG
- Defensive line modifier: ×0.94 on opponent xG
- Combined: ×0.846 on opponent xG (−15.4% — devastating)
- BUT: double energy drain (+15% from press + no savings), highly vulnerable to fast forwards
- This is exactly Flick's Barcelona: suffocating when it works, exploitable on the break

**Key interaction: Deep + Counter-Attack = the Simeone system:**
- Deep line: opponent xG ×0.92 (very safe)
- Counter-Attack: own xG ×0.92 normally, but ×1.08 vs aggressive opponents
- Deep + Counter combined: own xG modifier improves to ×0.98
- Against an attacking opponent with high line: own xG becomes ×1.08 × 1.03 = ×1.11 (devastating counters)
- This is exactly Simeone's Atletico: absorb pressure, punish on the break

### Summary: Complete Modified xG Formula

```
homeXG = (strengthRatio^2 × baseGoals + homeAdvantage)
       × homeFormation.attack
       × awayFormation.defense
       × homeMentality.ownGoals
       × awayMentality.opponentGoals
       × homePlayingStyle.ownXG(context)
       × awayPlayingStyle.opponentXG(context)
       × awayPressing.opponentXG(minute, context)
       × homeDefLine.ownXG(context)
       × awayDefLine.opponentXG(context)
       × matchFraction
       + strikerBonus
```

Total multipliers per team: 8 (was 4). Each new instruction adds exactly 2 multipliers (one affecting your xG, one affecting opponent's xG), keeping the formula compositional and debuggable.

### Interaction Matrix — All Combinations

The most important cross-instruction interactions:

| Your Instructions | Opponent's Instructions | Effect Description |
|---|---|---|
| High Press + High Line | Any | −15% opponent xG but massive energy drain and pace vulnerability |
| Counter-Attack + Deep | Attacking + High Line | Your counters boosted to +11% xG; their high line nullified by your pace |
| Possession + High Line | High Press | Your possession disrupted; their press effective but tiring |
| Direct + Standard | High Press | Bypasses their press (+8% own xG); their energy wasted |
| Low Block + Counter-Attack | Possession | Low block holds (-6% opponent xG); counters enabled (+3% own xG) |
| High Press | Low physical squad (<70) | Press fades early (minute 50), extra energy drain |
| High Line | Opponent fast forward (phys >80) | Your high line completely nullified |
| Possession | Squad avg technical < 70 | Possession bonus reduced (own xG modifier drops to ×1.00) |

---

## Part 2: UI/UX Changes

### Design Principles for the New Instructions

1. **Same interaction pattern as mentality** — pill buttons, not dropdowns or sliders
2. **Grouped by phase of play** — "In Possession" section and "Out of Possession" section
3. **Default is always the middle option** — Balanced/Standard/Normal. New users don't need to touch these.
4. **Consistent in both contexts** — identical selector components pre-match and mid-match
5. **Each instruction shows its current effect** — tooltip or subtext explaining the trade-off

### Pre-Match Lineup Screen Changes

**Current layout (sticky header bar):**
```
┌──────────────────────────────────────────────────┐
│ Formation: [4-4-2 ▼]   Mentality: [Balanced ▼]  │
│                              [Clear] [Auto] [OK] │
└──────────────────────────────────────────────────┘
```

**Proposed layout:**

The sticky header keeps formation + mentality + action buttons (these are the most-changed options). The new instructions go in a collapsible panel between the header and the pitch/squad area.

```
┌──────────────────────────────────────────────────────────┐
│ Formation: [4-4-2 ▼]   Mentality: [Balanced ▼]          │
│                                    [Clear] [Auto] [OK]   │
├──────────────────────────────────────────────────────────┤
│ ▼ Team Instructions                                      │
│                                                          │
│ IN POSSESSION                                            │
│ Playing Style                                            │
│ [Possession] [Balanced] [Counter] [Direct]               │
│                                                          │
│ OUT OF POSSESSION                                        │
│ Pressing        [High Press] [Standard] [Low Block]      │
│ Defensive Line  [High Line]  [Normal]   [Deep]           │
│                                                          │
│ ℹ️ High Press: opponents create fewer chances, but your  │
│ players tire faster — especially after minute 60.        │
└──────────────────────────────────────────────────────────┘
```

**Mobile layout (375px):**
```
┌────────────────────────────┐
│ Formation [4-4-2 ▼]       │
│ Mentality [Balanced ▼]    │
│ [Clear] [Auto Select] [OK]│
├────────────────────────────┤
│ ▾ Team Instructions        │
│                            │
│ IN POSSESSION              │
│ Playing Style              │
│ [Poss] [Bal] [Cntr] [Dir] │
│                            │
│ OUT OF POSSESSION          │
│ Pressing                   │
│ [High] [Standard] [Low]   │
│ Defensive Line             │
│ [High] [Normal]  [Deep]   │
│                            │
│ ℹ️ High Press: less opp.  │
│ chances, more energy cost. │
└────────────────────────────┘
```

**Implementation details:**
- The "Team Instructions" section is an Alpine.js collapsible (`x-show` with slide transition)
- Starts **collapsed** on mobile, **expanded** on desktop (`x-init="instructionsOpen = window.innerWidth >= 768"`)
- Pill buttons follow the same pattern as the mid-match mentality buttons: `border-2`, selected state has colored background
- Each group label is small caps, muted color (`text-xs font-semibold uppercase tracking-wider text-slate-500`)
- The info text at the bottom updates dynamically based on what's selected (Alpine.js `x-text`)

**Color scheme for instructions:**
- Playing Style: slate tones (neutral, strategic)
- Pressing: physical connotation — amber for High Press (energy/fire), slate for Standard, blue for Low Block (cold/defensive)
- Defensive Line: follows same pattern — amber for High (aggressive), slate for Normal, blue for Deep (cautious)

**Pill button markup pattern:**
```html
<button
    @click="selectedPressing = 'high_press'"
    :class="selectedPressing === 'high_press'
        ? 'bg-amber-100 text-amber-800 border-amber-300'
        : 'bg-white text-slate-700 border-slate-200 hover:border-slate-400'"
    class="px-3 py-2 rounded-lg border-2 text-sm font-medium min-h-[44px] transition-colors"
>
    High Press
</button>
```

### Mid-Match Tactical Panel Changes

**Current tactics tab layout:**
```
┌──────────────────────────────────────┐
│ TACTICAL CENTER           [PAUSED]   │
│ [Substitutions] [Tactics]            │
├──────────────────────────────────────┤
│ TACTICAL FORMATION                   │
│ [4-4-2] [4-3-3] [4-2-3-1] [3-4-3]  │
│ [3-5-2] [4-1-4-1] [5-3-2] [5-4-1]  │
│                                      │
│ TACTICAL MENTALITY                   │
│ [🛡 Defensive] [⚖ Balanced] [⚡ Atk] │
│                                      │
│ [Reset]              [Apply Changes] │
└──────────────────────────────────────┘
```

**Proposed tactics tab layout:**

The tactics tab is the right place for mid-match instruction changes. The panel gets taller but remains scrollable. Instructions go below the existing formation + mentality selectors.

```
┌──────────────────────────────────────┐
│ TACTICAL CENTER           [PAUSED]   │
│ [Substitutions] [Tactics]            │
├──────────────────────────────────────┤
│ FORMATION                            │
│ [4-4-2] [4-3-3] [4-2-3-1] [3-4-3]  │
│ [3-5-2] [4-1-4-1] [5-3-2] [5-4-1]  │
│                                      │
│ MENTALITY                            │
│ [🛡 Defensive] [⚖ Balanced] [⚡ Atk] │
│                                      │
│ ─────────────────────────────────    │
│                                      │
│ TEAM INSTRUCTIONS                    │
│                                      │
│ Playing Style                        │
│ [Possession] [Balanced] [Cntr] [Dir] │
│                                      │
│ Pressing                             │
│ [High Press] [Standard] [Low Block]  │
│                                      │
│ Defensive Line                       │
│ [High Line]  [Normal]   [Deep]       │
│                                      │
│ ─────────────────────────────────    │
│                                      │
│ [Reset]              [Apply Changes] │
└──────────────────────────────────────┘
```

**Key differences from pre-match:**
- No collapsible — instructions are always visible in the tactics tab (you're here to make changes)
- A horizontal divider separates "Formation/Mentality" (top) from "Instructions" (bottom) for visual clarity
- Pending changes are tracked: `pendingPlayingStyle`, `pendingPressing`, `pendingDefensiveLine` (null = no change)
- `hasTacticalChanges` computed property expands: `pendingFormation || pendingMentality || pendingPlayingStyle || pendingPressing || pendingDefensiveLine`
- "Apply Changes" POST sends all 5 values to `ProcessTacticalChange`

**Mobile scrolling:** The panel is already `overflow-y-auto`. Adding 3 more selector groups adds ~200px of height, which is comfortable within the scrollable area. No layout changes needed for mobile — the pill buttons already use responsive grid (`grid-cols-2 sm:grid-cols-4` for 4-option groups, `grid-cols-3` for 3-option groups).

### Data Flow Changes

**Pre-match save (`SaveLineup`):**
- Request adds 3 new validated fields: `playing_style`, `pressing`, `defensive_line`
- Saved to `GameMatch` (match-specific) and `Game` (defaults), same pattern as formation/mentality

**Mid-match change (`ProcessTacticalChange`):**
- Request adds 3 new nullable fields: `playing_style`, `pressing`, `defensive_line`
- `TacticalChangeService` passes them to `MatchSimulator.simulateRemainder()`
- Re-simulation from that minute onward uses new instruction modifiers

**Alpine.js state additions:**
```javascript
// Pre-match
selectedPlayingStyle: '{{ $game->default_playing_style ?? "balanced" }}'
selectedPressing: '{{ $game->default_pressing ?? "standard" }}'
selectedDefensiveLine: '{{ $game->default_defensive_line ?? "normal" }}'

// Mid-match
activePlayingStyle: config.activePlayingStyle || 'balanced'
activePressing: config.activePressing || 'standard'
activeDefensiveLine: config.activeDefensiveLine || 'normal'
pendingPlayingStyle: null
pendingPressing: null
pendingDefensiveLine: null
```

---

## Part 3: Communicating Effects to Users

### Problem Statement

The current game has formation modifiers and mentality effects baked into the simulation, but users have **zero visibility** into what these actually do. The only hint is a tooltip like "Balanced mentality: no advantage, no risk." This is insufficient — users need to understand trade-offs to make informed tactical decisions. Without this understanding, the tactical choices feel arbitrary rather than strategic.

### Strategy: Layered Disclosure

Information is presented in three layers — immediate labels, contextual tooltips, and a dedicated reference screen. Users who just want to pick and play see Layer 1. Users who want to optimize see Layer 2. Users who want to master the system see Layer 3.

### Layer 1: Labels and Visual Cues (Always Visible)

Each option's button label is self-explanatory: "High Press", "Counter-Attack", "Deep Line". No jargon that requires explanation.

**Selected state shows a one-line summary beneath the selector group:**

```
Pressing: [High Press] [Standard] [Low Block]
ℹ️ Opponents create fewer chances, but your players tire faster.
```

```
Playing Style: [Possession] [Balanced] [Counter] [Direct]
ℹ️ Control the ball and create chances, but drains energy.
```

```
Defensive Line: [High Line] [Normal] [Deep]
ℹ️ Catches opponents offside, but vulnerable to fast forwards.
```

These summaries are:
- 1 sentence maximum
- Always visible when an option is selected
- Written in plain language, no numbers
- Describe the trade-off, not just the benefit

**Implementation:** `x-text` bound to a computed property that returns the summary string based on the selected value. Translation keys in `lang/es/squad.php` and `lang/en/squad.php`.

### Layer 2: Detailed Tooltips (On Demand)

Each instruction group has a small info icon (ℹ) next to the label. Tapping/hovering shows a tooltip with more detail:

**Playing Style tooltip (when "Possession" selected):**
```
Possession Play
Your team keeps the ball and builds from the back.
• +5% chance creation
• −5% opponent chances
• Players tire 10% faster
• Less effective if your midfield technical ability is below 70
• Opponents using High Press can disrupt your possession
```

**Pressing tooltip (when "High Press" selected):**
```
High Press
Your team presses aggressively when out of possession.
• −10% opponent chances (first 60 minutes)
• Press fades after minute 60 — switch to Standard or make subs
• Players tire 15% faster
• Requires physically strong squad (physical > 70)
• Opponents using Direct play can bypass your press
```

**Defensive Line tooltip (when "High Line" selected):**
```
High Line
Your defenders push up to compress the pitch.
• −6% opponent chances
• +3% your own chances (shorter distance to goal)
• WARNING: If opponent has a fast forward (physical > 80),
  they can exploit the space behind your defense
• Combined with High Press: very effective but very tiring
```

**Implementation:** Uses the existing `x-tooltip` Alpine.js component already in the codebase. Content is dynamically bound based on current selection. The WARNING prefix highlights dangerous interactions (fast forward vs high line).

### Layer 3: Tactical Guide Screen (Dedicated Reference)

A new page accessible from the lineup screen via a "Tactical Guide" link. This is the comprehensive reference for users who want to understand the full system.

**Content structure:**

```
Tactical Guide
═══════════════

How Tactics Affect Matches
─────────────────────────
Your tactical choices modify how many chances your team creates
and concedes. Every choice involves a trade-off.

Formation
─────────
Your formation determines your team's shape on the pitch.
Attacking formations (4-3-3, 3-4-3) create more chances
but leave more space for the opponent.
Defensive formations (5-3-2, 5-4-1) are harder to break down
but produce fewer chances of your own.

[Table showing each formation with attack/defense bars]

Mentality
─────────
Mentality controls your team's risk tolerance.
[Table showing each mentality with own goals/opp goals bars]

Playing Style
─────────────
[Table with each style, description, effect, and squad fit]

Pressing Intensity
──────────────────
[Table with each level, description, effect, and energy impact]

Defensive Line
──────────────
[Table with each height, description, effect, and vulnerability]

Tactical Combinations
─────────────────────
Some instructions work especially well (or poorly) together:

🟢 High Press + High Line: Suffocating pressure. Best against
   slow opponents. Watch energy levels and substitute early.

🟢 Counter-Attack + Deep: Absorb pressure, hit on the break.
   Best against attacking teams with high lines.

🟢 Direct + Standard Press: Bypasses opponent's high press.
   Best when opponent presses hard but your team is physical.

🔴 High Line vs Fast Forward: If the opponent has a physically
   strong forward (80+), your high line is completely neutralized.

🔴 Possession vs High Press: Your opponent's press disrupts
   your build-up. Consider Direct play to bypass it.

🔴 Low Block vs Possession: The opponent can probe your
   defense patiently. Low block is less effective.
```

**Implementation:** A new Blade view (`resources/views/tactical-guide.blade.php`) and a simple view class. Linked from the lineup screen with a subtle "Tactical Guide" link near the instructions section. All content uses translation keys for ES/EN.

### Layer 3b: Coach Assistant Integration

The existing coach assistant panel (left side of lineup screen) already provides tips. Enhance it to reference the new instructions:

**Before a match:**
```
Coach: "Opponent is predicted to play High Press with an
attacking mentality. Consider Direct play to bypass their
press — your forward Lewandowski (technical 88) can hold
the ball up effectively."
```

```
Coach: "Their fastest forward has 84 physical ability.
A High Line could be risky — consider Normal or Deep
defensive line for this match."
```

```
Coach: "Your squad's average physical ability is 68. High
Press will tire your players by minute 50. Consider
Standard pressing or plan substitutions around minute 55."
```

**During a match (tactical panel context bar):**
A small contextual hint appears at the top of the tactics tab when relevant:

```
⚠ Your team is visibly tiring (avg energy 62%).
Consider switching from High Press to Standard.
```

```
ℹ You're leading 1-0. A Low Block + Counter-Attack
setup could protect the lead effectively.
```

**Implementation:** The coach assistant logic already exists in `ShowLineup.php`. Extend it with instruction-aware recommendations based on:
- Opponent's predicted instructions (from `LineupService::selectAIInstructions()`)
- Your squad's physical/technical averages
- Your squad's fastest forward (for high line warnings)

### Visual Language Summary

| Element | Where | What It Shows |
|---------|-------|---------------|
| Pill button label | Selector | Option name ("High Press") |
| One-line summary | Below selector | Trade-off in plain language |
| Info tooltip | On demand (ℹ icon) | Detailed effects with bullet points |
| Warning badge | Tooltip/coach | Dangerous matchups (fast forward vs high line) |
| Tactical guide page | Dedicated screen | Full reference with all combinations |
| Coach recommendations | Pre-match sidebar | Context-specific advice per opponent |
| Match context hints | Mid-match panel | Energy warnings, score-based suggestions |

---

## Part 4: Data Model Changes

### Migration: Add Instruction Columns

**`games` table (defaults):**
```php
$table->string('default_playing_style')->default('balanced');
$table->string('default_pressing')->default('standard');
$table->string('default_defensive_line')->default('normal');
```

**`game_matches` table (per-match):**
```php
$table->string('home_playing_style')->default('balanced');
$table->string('away_playing_style')->default('balanced');
$table->string('home_pressing')->default('standard');
$table->string('away_pressing')->default('standard');
$table->string('home_defensive_line')->default('normal');
$table->string('away_defensive_line')->default('normal');
```

### New Enums

```php
enum PlayingStyle: string {
    case POSSESSION = 'possession';
    case BALANCED = 'balanced';
    case COUNTER_ATTACK = 'counter_attack';
    case DIRECT = 'direct';
}

enum PressingIntensity: string {
    case HIGH_PRESS = 'high_press';
    case STANDARD = 'standard';
    case LOW_BLOCK = 'low_block';
}

enum DefensiveLineHeight: string {
    case HIGH_LINE = 'high_line';
    case NORMAL = 'normal';
    case DEEP = 'deep';
}
```

### Configuration

All modifier values live in `config/match_simulation.php` so they can be tuned without code changes:

```php
'playing_styles' => [
    'possession'     => ['own_xg' => 1.05, 'opp_xg' => 0.95, 'energy_drain' => 1.10],
    'balanced'       => ['own_xg' => 1.00, 'opp_xg' => 1.00, 'energy_drain' => 1.00],
    'counter_attack' => ['own_xg' => 0.92, 'opp_xg' => 0.95, 'energy_drain' => 0.95],
    'direct'         => ['own_xg' => 1.02, 'opp_xg' => 1.03, 'energy_drain' => 1.00],
],
'pressing' => [
    'high_press' => ['opp_xg' => 0.90, 'energy_drain' => 1.15, 'fade_start' => 60, 'fade_target' => 0.97],
    'standard'   => ['opp_xg' => 1.00, 'energy_drain' => 1.00],
    'low_block'  => ['opp_xg' => 0.94, 'energy_drain' => 0.92, 'counter_bonus' => 1.03],
],
'defensive_line' => [
    'high_line' => ['opp_xg' => 0.94, 'own_xg' => 1.03, 'pace_threshold' => 80],
    'normal'    => ['opp_xg' => 1.00, 'own_xg' => 1.00],
    'deep'      => ['opp_xg' => 0.92, 'own_xg' => 0.94],
],
```

---

## Part 5: Files Affected

| File | Change |
|------|--------|
| `config/match_simulation.php` | Fix formation modifiers, add instruction configs |
| New migration | Add 3 columns to `games`, 6 columns to `game_matches` |
| `app/Modules/Lineup/Enums/PlayingStyle.php` | New enum |
| `app/Modules/Lineup/Enums/PressingIntensity.php` | New enum |
| `app/Modules/Lineup/Enums/DefensiveLineHeight.php` | New enum |
| `app/Modules/Match/Services/MatchSimulator.php` | Integrate instruction modifiers into xG formula |
| `app/Modules/Match/Services/EnergyCalculator.php` | Apply energy drain modifiers from pressing/style |
| `app/Modules/Lineup/Services/LineupService.php` | AI instruction selection for opponent teams |
| `app/Modules/Lineup/Services/TacticalChangeService.php` | Handle instruction changes mid-match |
| `app/Models/Game.php` | Add default instruction columns |
| `app/Models/GameMatch.php` | Add per-match instruction columns |
| `app/Http/Views/ShowLineup.php` | Pass instruction data + coach analysis to view |
| `app/Http/Actions/SaveLineup.php` | Validate and persist instructions |
| `app/Http/Actions/ProcessTacticalChange.php` | Accept instruction changes mid-match |
| `resources/views/lineup.blade.php` | Instruction selector UI |
| `resources/views/partials/live-match/tactical-panel.blade.php` | Mid-match instruction selectors |
| `resources/js/live-match.js` | Alpine.js state for instructions |
| `resources/views/tactical-guide.blade.php` | New tactical reference page |
| `app/Http/Views/ShowTacticalGuide.php` | New view class |
| `routes/web.php` | Route for tactical guide |
| `lang/es/squad.php` | Spanish translations for instructions |
| `lang/en/squad.php` | English translations for instructions |
| `lang/es/game.php` | Mid-match instruction labels |
| `lang/en/game.php` | Mid-match instruction labels |

---

## Implementation Order

1. **Fix formation modifiers** — config change only, no migration needed
2. **New enums + migration** — data model foundation
3. **MatchSimulator + EnergyCalculator** — engine integration (testable in isolation)
4. **AI instruction selection** — so opponents use instructions too
5. **SaveLineup + ProcessTacticalChange** — backend API changes
6. **Pre-match lineup UI** — instruction selectors
7. **Mid-match tactical panel UI** — instruction selectors
8. **Tooltips + summaries** — Layer 1 + Layer 2 communication
9. **Coach assistant** — instruction-aware recommendations
10. **Tactical guide page** — Layer 3 reference
11. **Translations** — ES + EN for all new strings
