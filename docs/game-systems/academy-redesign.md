# Youth Academy: "La Cantera" (B Team)

The youth academy functions as a B team, producing homegrown talent calibrated to your squad's quality level. Academy players are generated 1-2 tiers below your first team and can be called up for matches, promoted permanently, loaned out, or dismissed.

## Season Rhythm

```
Season start → New batch arrives (all stats visible)
Throughout    → Players develop each matchday
Any time      → Call up / recall / loan / send back from first team
Season end    → Mandatory evaluation: keep / promote / loan / dismiss
```

## Tier-Relative Generation

Academy prospect quality is derived from your first team's median player tier (via `PlayerTierService`):

1. **First-team median tier** — computed from `GamePlayer.tier` column
2. **Target ability tier** — `max(1, median - ACADEMY_TIER_OFFSET[academyTier])`
3. **Potential ceiling tier** — `min(5, target + POTENTIAL_CEILING_OFFSET[academyTier])`
4. **Ability** — random within `TIER_ABILITY_RANGES[targetTier]`
5. **Potential** — random from top of target tier range to top of ceiling tier range

Higher academy tiers produce players closer to first-team quality with higher potential ceilings.

## Tiers

Academy tier (from budget allocation) determines capacity, batch size, and call-up slots:

| Tier | Capacity | Arrivals | Max Call-Ups |
|------|----------|----------|--------------|
| 1 — Basic | 4 | 2-3 | 1 |
| 2 — Good | 6 | 3-5 | 1 |
| 3 — Elite | 7 | 4-6 | 2 |
| 4 — World-Class | 8 | 4-6 | 3 |

Capacity pressure is the core tension — new arrivals can exceed remaining seats, especially if you kept players from previous seasons.

"Cantera" teams (e.g., Athletic Bilbao) only generate Spanish nationality prospects.

## Development

Academy players grow toward their potential every matchday. Growth rates:

- **Academy** — 0.45 per matchday (fast enough to see meaningful progress within a season)
- **On loan** — 0.50 per matchday (accelerated, but player is unavailable)

Called-up players do not develop through the academy system — they develop through first-team match participation instead.

## Call-Up System

Academy players can be temporarily promoted to the first-team matchday squad:

- Creates a real `GamePlayer` record (works seamlessly with lineup selection and match engine)
- `AcademyPlayer` stays linked via `called_up_game_player_id`
- Call-up slots are limited by academy tier
- Recalled players sync abilities back to their academy record
- All call-ups are automatically recalled at season end before evaluation

## Send to Academy

First-team players aged 20 or under can be sent back to the academy if there's capacity. This creates a new `AcademyPlayer` from the `GamePlayer`'s current abilities and removes the `GamePlayer` record.

## Player Management

Players can be managed individually at any time via the academy page:

| Action | Effect |
|--------|--------|
| **Keep** | Stays in academy, continues developing |
| **Promote** | Joins first team squad permanently |
| **Loan** | Frees seat now, develops faster off-screen, returns next season end |
| **Dismiss** | Permanently removed |

Players naturally leave the academy when they age past the academy age limit.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Academy/Services/YouthAcademyService.php` | Batch generation, development, call-up/recall, send-back, capacity, all actions |
| `app/Modules/Season/Processors/YouthAcademyClosingProcessor.php` | Season-end: auto-recall call-ups, loan development, returns |
| `app/Modules/Season/Processors/YouthAcademySetupProcessor.php` | Season-setup: evaluation trigger |
| `app/Http/Actions/CallUpAcademyPlayer.php` | Call up academy player to first team |
| `app/Http/Actions/RecallAcademyPlayer.php` | Recall called-up player back to academy |
| `app/Http/Actions/SendToAcademy.php` | Send first-team player back to academy |
