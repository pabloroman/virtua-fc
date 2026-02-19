# Academy Redesign: "La Cantera"

## Overview

Redesign the youth academy from a passive slot machine into an active management loop with discovery, development, and hard choices.

**Current state:** Investment is set at season start, prospects randomly spawn with full stats revealed, only action is "Promote to First Team."

**New state:** Batch of prospects arrives at season start with hidden stats, stats reveal gradually, players develop toward their potential over matchdays, and a mandatory end-of-season evaluation forces decisions (dismiss / loan / keep / promote) under limited capacity pressure.

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
├── Matchday 19: SECOND REVEAL → Potential range now visible
│
├── Matchdays 20-38: Remaining players develop. Loaned ones grow faster off-screen.
│
└── SEASON END: EVALUATION ──────────────────────────────────────────┐
    Loaned players return (occupying seats again)                    │
    For each player, you MUST choose:                                │
    • Keep in academy (continues developing)                         │
    • Promote to first team (joins squad immediately)                │
    • Loan out (develops 1.5× faster, returns end of season)         │
    • Dismiss (gone forever)                                         │
    BUT: next season's batch is coming → need to free seats          │
    AND: players aged 21+ MUST be promoted or dismissed              │
```

---

## Capacity & Batch Size

| Tier | Capacity (seats) | New Arrivals/Season | Potential Range | Starting Ability |
|------|-----------------|---------------------|-----------------|-----------------|
| 0    | 0               | 0                   | —               | —               |
| 1    | 4               | 2-3                 | 60-70           | 35-50           |
| 2    | 6               | 3-5                 | 65-75           | 40-55           |
| 3    | 8               | 5-7                 | 70-82           | 45-60           |
| 4    | 10              | 6-8                 | 75-90           | 50-70           |

The key tension: at Tier 3-4, new arrivals can exceed remaining capacity, especially if you kept players from previous seasons. You're forced to make hard calls.

---

## Stat Reveal Phases

**Phase 0 — "The Unknown" (matchdays 1-9):**
Only identity visible: name, nationality, age, position. Abilities and potential show as "?".

| Pos | Name             | Country | Age | TEC | PHY | POT | OVR |
|-----|------------------|---------|-----|-----|-----|-----|-----|
| CB  | Diego Alvarado   | 🇪🇸     | 16  | ?   | ?   | ?   | ?   |
| LW  | Marco Delgado    | 🇦🇷     | 17  | ?   | ?   | ?   | ?   |

You can only make decisions based on position need and gut feeling.

**Phase 1 — "The Glimpse" (matchdays 10-18):**
Technical and Physical abilities become visible. Potential still hidden.

| Pos | Name             | Country | Age | TEC | PHY | POT | OVR |
|-----|------------------|---------|-----|-----|-----|-----|-----|
| CB  | Diego Alvarado   | 🇪🇸     | 16  | 62  | 58  | ?   | 60  |
| LW  | Marco Delgado    | 🇦🇷     | 17  | 49  | 45  | ?   | 47  |

**Phase 2 — "The Verdict" (matchday 19+, mid-season evaluation):**
Potential range revealed. The "jackpot" moment.

| Pos | Name             | Country | Age | TEC | PHY | POT   | OVR |
|-----|------------------|---------|-----|-----|-----|-------|-----|
| CB  | Diego Alvarado   | 🇪🇸     | 16  | 64  | 60  | 68-74 | 62  |
| LW  | Marco Delgado    | 🇦🇷     | 17  | 51  | 47  | 83-89 | 49  |

Marco, who looked mediocre, has elite potential. Now the hard choice.

---

## Development (Stat Growth)

Academy players grow toward their potential throughout the season at roughly **30-40% of the gap per season**.

Growth happens every matchday (small increments visible on the academy page).

**Growth formula per matchday:**
```
growth_per_matchday = (potential - current_ability) * season_growth_rate / total_matchdays

where season_growth_rate ≈ 0.35 (in academy) or 0.50 (on loan, 1.5× bonus)
```

**Example trajectory:**
```
17-year-old striker, Technical 48 / Physical 52 / Potential 82

After Season 1 (in academy):     Tech 59 / Phys 62    (+11 / +10)
After Season 2 (loaned, 1.5×):   Tech 73 / Phys 74    (+14 / +12)
After Season 3 (promoted):       Now a 67 OVR 20-year-old with 82 ceiling
                                  → legitimate first-team contributor
```

Loaned players develop 1.5× faster but are invisible until they return at season end.

---

## Evaluation Screens

At season end, a **mandatory evaluation screen** appears. The user cannot advance matchdays until every academy player has been assigned an action. This uses the `pending_actions` blocking mechanism.

**The evaluation screen shows:**
- All academy players with their currently-revealed stats
- Capacity bar: "7/8 seats used"
- Returning from loan count (end of season only): "2 players returning"
- Next season incoming (end of season only): "5-7 new prospects expected"
- For each player: 4 action buttons (keep / promote / loan / dismiss)
- Players aged 21+ highlighted with "must decide" indicator (cannot keep)

**Actions available:**

| Action  | Effect | Seat Impact |
|---------|--------|-------------|
| Keep    | Stays in academy, continues developing next season | Keeps seat |
| Promote | Joins first team with 2-year contract | Frees seat |
| Loan    | Leaves academy, develops 1.5× faster, returns end of season | Frees seat now, takes seat later |
| Dismiss | Permanently removed | Frees seat |

---

## Loan Mechanic

Deliberately simple — no destination team, no negotiation:
- Player disappears from academy (frees a seat)
- Develops at 1.5× rate off-screen
- Automatically returns at season end (occupies a seat again)
- Only available at end-of-season evaluation
- Loaning is a **development accelerator** that costs future capacity pressure

---

## Age-Out Rule

Players who are 21+ at evaluation time are flagged as "must decide." They can only be promoted or dismissed — no more academy time. This prevents hoarding and creates natural urgency.

---

## Implementation Plan

### Database Changes

**Modify `academy_players` table:**
- `is_on_loan` (boolean, default false) — whether player is currently loaned
- `seasons_in_academy` (integer, default 1) — tracks tenure
- `initial_technical` (integer) — starting technical ability (before development)
- `initial_physical` (integer) — starting physical ability (before development)

No `reveal_phase` column needed — computed from `$game->current_matchday`:
- Matchday < 10: Phase 0 (identity only)
- Matchday 10-18: Phase 1 (abilities visible)
- Matchday >= 19: Phase 2 (potential visible)

### Service Changes (YouthAcademyService)

1. **Remove** `trySpawnProspect()` per-matchday logic
2. **Add** `generateSeasonBatch(Game $game)` — creates batch at season start based on tier
3. **Add** `developPlayers(Game $game)` — grows abilities each matchday
4. **Add** `getRevealPhase(Game $game): int` — returns 0, 1, or 2 based on matchday
5. **Add** `getCapacity(int $tier): int` — returns max seats for tier
6. **Add** `loanPlayer(AcademyPlayer $player)` — marks as on loan
7. **Add** `dismissPlayer(AcademyPlayer $player)` — removes from academy
8. **Add** `returnLoans(Game $game)` — brings loaned players back at season end

### Season Pipeline Changes

- **YouthAcademyProcessor** (season end): Return loaned players, increment `seasons_in_academy`, generate new batch for next season, add `academy_evaluation` pending action if players exist

### New Actions

- `EvaluateAcademy` — handles the evaluation form submission (batch of decisions for all players)
- `DismissAcademyPlayer` — removes from academy (if needed outside evaluation)

### New Views

- `squad-academy-evaluation.blade.php` — the mandatory evaluation screen
- Update `squad-academy.blade.php` — show reveal phases (hidden stats as "?"), capacity indicator, loan status

### Route Changes

- `GET /game/{gameId}/squad/academy/evaluate` → `ShowAcademyEvaluation`
- `POST /game/{gameId}/squad/academy/evaluate` → `EvaluateAcademy`

### Translation Keys (lang/es/)

**squad.php additions:**
- `academy_evaluation` → 'Evaluación de Cantera'
- `academy_capacity` → 'Plazas'
- `academy_keep` → 'Mantener'
- `academy_dismiss` → 'Descartar'
- `academy_loan_out` → 'Ceder'
- `academy_must_decide` → 'Decisión obligatoria'
- `academy_over_capacity` → 'La cantera está llena. Debes liberar plazas.'
- `academy_returning_loans` → ':count jugadores regresan de cesión'
- `academy_incoming` → ':count nuevos canteranos esperados'
- `academy_on_loan` → 'Cedido'
- `academy_hidden_stats` → '?'
- `academy_age_limit` → 'Límite de edad alcanzado'

**messages.php additions:**
- `academy_evaluation_required` → 'Debes evaluar a los canteranos antes de continuar.'
- `academy_evaluation_complete` → 'Evaluación de cantera completada.'
- `academy_player_dismissed` → ':player ha sido descartado de la cantera.'
- `academy_player_loaned` → ':player ha sido cedido.'

**notifications.php additions:**
- `academy_batch_title` → 'Nuevos canteranos'
- `academy_batch_message` → ':count nuevos jugadores han llegado a la cantera.'
- `academy_evaluation_title` → 'Evaluación de cantera'
- `academy_evaluation_message` → 'Es momento de evaluar a tus canteranos.'

---

## What This Design Achieves

| Concern                            | Solution                                                                 |
|------------------------------------|--------------------------------------------------------------------------|
| "Don't know what to expect"        | Clear seasonal rhythm: batch → reveal → evaluation → repeat              |
| "Not clear what to do with him"    | 4 clear actions at defined evaluation moments with obvious tradeoffs     |
| "Jackpot" feeling                  | Hidden stats + phased reveal = genuine suspense. Phase 2 is the climax   |
| Income avenue                      | Promoted players can be sold via transfers. Future: direct academy sales  |
| Engagement during season           | Stats ticking up, reveal milestones, evaluation moments                  |
| Strategic depth                    | Capacity limits, loan vs keep tradeoff, age-out pressure                 |
