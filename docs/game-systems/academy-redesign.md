# Youth Academy: "La Cantera"

The youth academy produces homegrown talent through a seasonal loop of discovery, development, and hard choices.

## Season Rhythm

```
Season start → New batch arrives (stats hidden)
Matchday ~10 → Abilities revealed
Winter window → Potential range revealed
Season end  → Mandatory evaluation: keep / promote / loan / dismiss
```

A batch of prospects arrives at season start with only identity visible (name, age, nationality, position). Abilities reveal at matchday ~10, and potential range at the winter window — creating genuine suspense about who's a gem and who's a dud.

## Capacity & Tiers

Academy tier (from budget allocation) determines capacity, batch size, and prospect quality range. Higher tiers produce more prospects with higher quality floors and potential ceilings. Tier configuration is in `YouthAcademyService`.

Capacity pressure is the core tension — new arrivals can exceed remaining seats, especially if you kept players from previous seasons. "Cantera" teams (e.g., Athletic Bilbao) only generate Spanish nationality prospects.

## Development

Academy players grow toward their potential every matchday at a configured growth rate. Loaned players develop faster (higher growth rate) but are invisible until they return at season end.

## Evaluation

At season end, a **mandatory evaluation** blocks progression until every academy player is assigned an action:

| Action | Effect |
|--------|--------|
| **Keep** | Stays in academy, continues developing |
| **Promote** | Joins first team squad |
| **Loan** | Frees seat now, develops faster off-screen, returns next season end |
| **Dismiss** | Permanently removed |

Players aged 21+ **must** be promoted or dismissed — no more academy time.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Academy/Services/YouthAcademyService.php` | Batch generation, development, reveal phases, capacity, all actions |
| `app/Modules/Season/Processors/YouthAcademyProcessor.php` | Season-end: loan development, returns, evaluation trigger |
