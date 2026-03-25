# Youth Academy: "La Cantera" (B Team)

The youth academy functions as a B team, producing homegrown talent calibrated to your squad's quality level. Academy players are generated 1-2 tiers below your first team and can be promoted permanently, loaned out, or dismissed. Young first-team players (age 20 or under) can also be sent back to the academy.

## Season Rhythm

```
Season start → New batch arrives (all stats visible)
Throughout    → Players develop each matchday
Any time      → Promote / loan / send back from first team
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

Academy tier (from budget allocation) determines capacity and batch size:

| Tier | Capacity | Arrivals |
|------|----------|----------|
| 1 — Basic | 4 | 2-3 |
| 2 — Good | 6 | 3-5 |
| 3 — Elite | 7 | 4-6 |
| 4 — World-Class | 8 | 4-6 |

Capacity pressure is the core tension — new arrivals can exceed remaining seats, especially if you kept players from previous seasons.

"Cantera" teams (e.g., Athletic Bilbao) only generate Spanish nationality prospects.

## Development

Academy players grow toward their potential every matchday. Growth rates:

- **Academy** — 0.45 per matchday (fast enough to see meaningful progress within a season)
- **On loan** — 0.50 per matchday (accelerated, but player is unavailable)

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
| `app/Modules/Academy/Services/YouthAcademyService.php` | Batch generation, development, capacity, all actions |
| `app/Modules/Season/Processors/YouthAcademyClosingProcessor.php` | Season-end: loan development, returns |
| `app/Modules/Season/Processors/YouthAcademySetupProcessor.php` | Season-setup: evaluation trigger |
