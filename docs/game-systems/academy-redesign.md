# Youth Academy: "La Cantera"

## Overview

The youth academy is an active management loop with discovery, development, and hard choices. A batch of prospects arrives at season start with hidden stats, stats reveal gradually, players develop toward their potential over matchdays, and a mandatory end-of-season evaluation forces decisions (dismiss / loan / keep / promote) under limited capacity pressure.

---

## The Season Rhythm

```
SEASON START (after budget allocation)
│
├─ New batch of prospects arrives (identity only: name, age, nationality, position)
├─ If academy is over capacity → forced to dismiss/promote before continuing
│
├── Matchdays 1-9: Stats hidden. You manage blind. Position & gut feeling only.
├── Matchday ~10: FIRST REVEAL → Technical & Physical abilities become visible
├── Matchdays 10-18: You see who can play. Anticipation builds.
│
├── Winter Window: SECOND REVEAL → Potential range now visible
│
├── Remaining matchdays: Players develop. Loaned ones grow faster off-screen.
│
└── SEASON END: EVALUATION ──────────────────────────────────────────┐
    Loaned players return (occupying seats again)                    │
    For each player, you MUST choose:                                │
    • Keep in academy (continues developing)                         │
    • Promote to first team (joins squad immediately)                │
    • Loan out (develops faster, returns end of season)              │
    • Dismiss (gone forever)                                         │
    BUT: next season's batch is coming → need to free seats          │
    AND: players aged 21+ MUST be promoted or dismissed              │
```

---

## Capacity & Batch Size

Tier is determined by youth academy budget allocation (see [Club Economy System](club-economy-system.md)).

| Tier | Capacity | New Arrivals/Season | Potential Range | Starting Ability |
|------|----------|---------------------|-----------------|-----------------|
| 0    | 0        | 0                   | —               | —               |
| 1    | 4        | 2-3                 | 60-70           | 35-50           |
| 2    | 6        | 3-5                 | 65-75           | 40-55           |
| 3    | 8        | 5-7                 | 70-82           | 45-60           |
| 4    | 10       | 6-8                 | 75-90           | 50-70           |

The key tension: at Tier 3-4, new arrivals can exceed remaining capacity, especially if you kept players from previous seasons. You're forced to make hard calls.

### Prospect Generation

- **Age range:** 16-19 years old
- **Potential variance:** ±3-8 points from the tier's base range
- **Position selection** (weighted random):

| Position | Weight |
|----------|--------|
| Centre-Back | 15% |
| Central Midfield | 15% |
| Centre-Forward | 13% |
| Defensive Midfield | 10% |
| Attacking Midfield | 10% |
| Left-Back | 8% |
| Right-Back | 8% |
| Left Winger | 8% |
| Right Winger | 8% |
| Goalkeeper | 5% |

**Cantera teams** (e.g., Athletic Bilbao): Only Spanish nationality prospects are generated.

---

## Stat Reveal Phases

Reveal phase is computed from the current matchday and game date — no database column needed.

**Phase 0 — "The Unknown" (matchdays 1-9):**
Only identity visible: name, nationality, age, position. Abilities and potential show as "?".

| Pos | Name             | Country | Age | TEC | PHY | POT | OVR |
|-----|------------------|---------|-----|-----|-----|-----|-----|
| CB  | Diego Alvarado   | ES      | 16  | ?   | ?   | ?   | ?   |
| LW  | Marco Delgado    | AR      | 17  | ?   | ?   | ?   | ?   |

**Phase 1 — "The Glimpse" (matchday 10 until winter window):**
Technical and Physical abilities become visible. Potential still hidden.

| Pos | Name             | Country | Age | TEC | PHY | POT | OVR |
|-----|------------------|---------|-----|-----|-----|-----|-----|
| CB  | Diego Alvarado   | ES      | 16  | 62  | 58  | ?   | 60  |
| LW  | Marco Delgado    | AR      | 17  | 49  | 45  | ?   | 47  |

**Phase 2 — "The Verdict" (winter window onward):**
Potential range revealed. The "jackpot" moment.

| Pos | Name             | Country | Age | TEC | PHY | POT   | OVR |
|-----|------------------|---------|-----|-----|-----|-------|-----|
| CB  | Diego Alvarado   | ES      | 16  | 64  | 60  | 68-74 | 62  |
| LW  | Marco Delgado    | AR      | 17  | 51  | 47  | 83-89 | 49  |

Marco, who looked mediocre, has elite potential. Now the hard choice.

---

## Development (Stat Growth)

Academy players grow toward their potential throughout the season at roughly **35% of the gap per season** (in academy) or **50% of the gap per season** (on loan).

Growth happens every matchday (small increments visible on the academy page).

**Growth formula per matchday:**
```
growth_per_matchday = (potential - current_ability) × season_growth_rate / total_matchdays

where season_growth_rate = 0.35 (in academy) or 0.50 (on loan)
```

**Example trajectory:**
```
17-year-old striker, Technical 48 / Physical 52 / Potential 82

After Season 1 (in academy):     Tech 59 / Phys 62    (+11 / +10)
After Season 2 (loaned, 1.5×):   Tech 73 / Phys 74    (+14 / +12)
After Season 3 (promoted):       Now a 67 OVR 20-year-old with 82 ceiling
                                  → legitimate first-team contributor
```

Loaned players develop ~43% faster but are invisible until they return at season end.

---

## Evaluation

At season end, a **mandatory evaluation screen** appears. The user cannot advance matchdays until every academy player has been assigned an action. This uses the `pending_actions` blocking mechanism.

**The evaluation screen shows:**
- All academy players with their currently-revealed stats
- Capacity bar: "7/8 seats used"
- Returning from loan count: "2 players returning"
- Next season incoming: "5-7 new prospects expected"
- For each player: 4 action buttons (keep / promote / loan / dismiss)
- Players aged 21+ highlighted with "must decide" indicator (cannot keep)

**Actions available:**

| Action  | Effect | Seat Impact |
|---------|--------|-------------|
| Keep    | Stays in academy, continues developing next season | Keeps seat |
| Promote | Joins first team with contract | Frees seat |
| Loan    | Leaves academy, develops at accelerated rate, returns end of season | Frees seat now, takes seat later |
| Dismiss | Permanently removed | Frees seat |

---

## Loan Mechanic

Deliberately simple — no destination team, no negotiation:
- Player disappears from academy (frees a seat)
- Develops at **0.50 growth rate** off-screen (vs 0.35 in academy — ~43% faster)
- Full season growth applied at season end
- Automatically returns at season end (occupies a seat again)
- Loaning is a **development accelerator** that costs future capacity pressure

---

## Age-Out Rule

Players who are 21+ at evaluation time are flagged as "must decide." They can only be promoted or dismissed — no more academy time. This prevents hoarding and creates natural urgency.

---

## Season End Processing

The `YouthAcademyProcessor` (priority 55 in the season pipeline) handles:

1. Develops loaned academy players at the 0.50 growth rate
2. Returns loaned players to the academy
3. Marks non-loaned players as needing evaluation
4. Adds `academy_evaluation` pending action and notification

New batch generation happens after the evaluation is completed.

---

## What This Design Achieves

| Concern                            | Solution                                                                 |
|------------------------------------|--------------------------------------------------------------------------|
| "Don't know what to expect"        | Clear seasonal rhythm: batch → reveal → evaluation → repeat              |
| "Not clear what to do with him"    | 4 clear actions at defined evaluation moments with obvious tradeoffs     |
| "Jackpot" feeling                  | Hidden stats + phased reveal = genuine suspense. Phase 2 is the climax   |
| Income avenue                      | Promoted players can be sold via transfers                               |
| Engagement during season           | Stats ticking up, reveal milestones, evaluation moments                  |
| Strategic depth                    | Capacity limits, loan vs keep tradeoff, age-out pressure                 |

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Academy/Services/YouthAcademyService.php` | Batch generation, development, reveal phases, promotion, loan, dismiss |
| `app/Modules/Season/Processors/YouthAcademyProcessor.php` | Season-end loan development, returns, evaluation trigger |
