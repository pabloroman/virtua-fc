# Season Lifecycle

How seasons progress and what happens at the end of each one.

## Season Flow

```
Budget allocation → Matchday loop → Season end pipeline → Next season
```

Each season starts with mandatory budget allocation, then cycles through matchdays (league, cup, and European fixtures interspersed). Between matchdays: transfer market activity, academy development, injuries, and fitness changes. When all competitions finish, the season-end pipeline runs.

Pending actions (academy evaluation, budget allocation) block matchday advancement until resolved.

## Matchday Progression

1. Check for pending actions (blocks if any)
2. Fetch unplayed matches for current matchday across all competitions
3. Route to appropriate handler (LeagueHandler, KnockoutCupHandler, SwissFormatHandler, etc.)
4. Set lineups (auto-select for AI, user's saved lineup otherwise)
5. Simulate match
6. Post-match: update standings, award prize money, apply suspensions
7. Between-matchday: transfer market, injuries, notifications
8. Check for season end

See `MatchdayService` for the full flow.

## Competition Handlers

Different competition formats use different handlers implementing `CompetitionHandler`:

- **LeagueHandler** — Standard league with standings
- **KnockoutCupHandler** — Bracket/draws with single-leg or two-legged ties, extra time, penalties
- **LeagueWithPlayoffHandler** — League phase followed by playoff rounds
- **SwissFormatHandler** — Champions League Swiss-system (all teams play, paired by points)
- **GroupStageCupHandler** — Groups play round-robin, top teams advance to knockout

Resolved via `CompetitionHandlerResolver` based on competition's `handler_type` field.

## Season End Pipeline

21 ordered processors handle the transition. Each implements `SeasonEndProcessor` with a `priority()` (lower runs first). All processors are in `app/Modules/Season/Processors/`.

**Phase 1 — Roster cleanup** (priority 0-7): Archive season data, return loans, handle contract expirations and pre-contract transfers, apply renewals, process retirements.

**Phase 2 — Squad management** (priority 8-20): Replenish AI squads to minimum roster sizes, apply player development changes, settle finances (actual vs projected), reset stats and transfer market.

**Phase 3 — League simulation & structure** (priority 24-30): Simulate non-played leagues, determine Supercup qualifiers, handle promotion/relegation, generate new league fixtures.

**Phase 4 — New season setup** (priority 40-55): Reset standings, generate budget projections and season goals, process academy (develop loaned players, trigger evaluation).

**Phase 5 — Continental & cup setup** (priority 105-110): Determine UEFA competition qualifiers, initialize Swiss-format competitions and domestic cup draws, reset onboarding for budget allocation.

The full processor list with priorities is in `SeasonEndPipeline`.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Season/Services/SeasonEndPipeline.php` | Orchestrates all 21 processors |
| `app/Modules/Season/Processors/` | Individual processor implementations |
| `app/Modules/Match/Services/MatchdayService.php` | Matchday advancement logic |
| `app/Modules/Match/Services/CupTieResolver.php` | Cup tie resolution (aggregate, ET, penalties) |
| `app/Modules/Competition/Services/CupDrawService.php` | Cup draw mechanics |
