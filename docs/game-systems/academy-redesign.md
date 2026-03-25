# Youth Academy: "La Cantera"

The youth academy produces homegrown talent through a seasonal loop of discovery and development.

## Season Rhythm

```
Season start → New batch arrives (stats hidden)
Matchday ~10 → Abilities revealed
Winter window → Potential range revealed
Season end  → Loaned players return, new cycle begins
```

A batch of prospects arrives at season start with only identity visible (name, age, nationality, position). Abilities reveal at matchday ~10, and potential range at the winter window — creating genuine suspense about who's a gem and who's a dud.

## Tiers

Academy tier (from budget allocation) determines batch size and prospect quality range. Higher tiers produce more prospects with higher quality floors and potential ceilings. Tier configuration is in `YouthAcademyService`.

"Cantera" teams (e.g., Athletic Bilbao) only generate Spanish nationality prospects.

## Development

Academy players grow toward their potential every matchday at a configured growth rate. Loaned players develop faster (higher growth rate) but are invisible until they return at season end.

## Player Management

Players can be managed individually at any time via the academy page:

| Action | Effect |
|--------|--------|
| **Promote** | Joins first team squad with a professional contract |
| **Loan** | Develops faster off-screen, returns next season end |
| **Dismiss** | Permanently removed |

Players naturally leave the academy when they age past the academy age limit.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Academy/Services/YouthAcademyService.php` | Batch generation, development, reveal phases, all actions |
| `app/Modules/Season/Processors/YouthAcademyClosingProcessor.php` | Season-end: loan development, returns |
