# Youth Academy: "La Cantera" (B Team)

The youth academy functions as a B team, producing homegrown talent calibrated to your squad's quality level. Academy players are generated with quality following a normal distribution, so most prospects cluster around the expected average for the academy tier with occasional gems and busts. Promotion is a one-way, permanent move.

## Season Rhythm

```
Season start → New batch arrives (all stats visible)
Throughout    → Players develop each matchday
Any time      → Promote / loan / dismiss
Season end    → Mandatory evaluation: keep / promote / loan / dismiss
```

## Quality Distribution (Normal / Gaussian)

Academy prospect quality follows a **normal distribution** (bell curve):

1. **Ability mean** = `ACADEMY_BASE_QUALITY[academyTier] + TEAM_CONTEXT_BONUS[teamMedianTier]`
2. **Technical & physical** — sampled independently from N(mean, σ=7), clamped to [35, 90]
3. **Potential** — `max(technical, physical)` + normally distributed upside (mean per tier, σ=5)
4. **Potential floor** — guaranteed minimum per academy tier (45/50/55/60)
5. **Potential ceiling** — hard cap at 95

This produces realistic clustering: most prospects are average for their tier, with rare gems in the upper tail and occasional busts in the lower tail.

## Tiers

Academy tier (from budget allocation) determines batch size, base quality mean, and potential upside. The academy has no capacity limit.

| Tier | Arrivals | Base Quality Mean | Potential Upside Mean |
|------|----------|-------------------|----------------------|
| 1 — Basic | 2-3 | 45 | +10 |
| 2 — Good | 3-5 | 52 | +12 |
| 3 — Elite | 4-6 | 62 | +12 |
| 4 — World-Class | 4-6 | 70 | +10 |

Team context adjusts the ability mean by -3 to +4 based on first-team median tier.

"Cantera" teams (e.g., Athletic Club) only generate Spanish nationality prospects.

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

Players aged 21+ (`PlayerAge::ACADEMY_END`) are automatically promoted to the first team at the start of each new season via `YouthAcademyPromotionProcessor`. This applies even if the squad exceeds the 30-player cap. A notification informs the manager of the promoted players.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Academy/Services/YouthAcademyService.php` | Batch generation, development, capacity, all actions |
| `app/Modules/Season/Processors/YouthAcademyClosingProcessor.php` | Season-end: loan development, returns |
| `app/Modules/Season/Processors/YouthAcademyPromotionProcessor.php` | Season-setup: auto-promote overage players |
