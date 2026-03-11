# Season Lifecycle

How seasons progress and what happens at the end of each one.

## Season Flow

```
Budget allocation → Matchday loop → Season end pipeline → Next season
```

Each season starts with mandatory budget allocation, then cycles through matchdays (league, cup, and European fixtures interspersed). Between matchdays: transfer market activity, academy development, injuries, and fitness changes. When all competitions finish, the season-end pipeline runs.

Pending actions (academy evaluation, budget allocation) block matchday advancement until resolved.

## Matchday Progression

See [Matchday Advancement](matchday-advancement.md) for the full system documentation — it covers batch finding, competition handlers, round generation, deferred finalization, event-driven side effects, and season completion detection.

## Season Pipelines

Season transitions run two pipelines sequentially. Each processor implements `SeasonProcessor` with a `priority()` (lower runs first). All processors are in `app/Modules/Season/Processors/`.

### SeasonClosingPipeline (17 processors — transitions only)

**Phase 1 — Roster cleanup** (priority 0-7): Archive season data, return loans, handle contract expirations and pre-contract transfers, apply renewals, process retirements.

**Phase 2 — Squad management** (priority 8-20): Replenish AI squads to minimum roster sizes, apply player development changes, settle finances (actual vs projected), reset stats and transfer market.

**Phase 3 — League simulation & structure** (priority 24-55): Simulate non-played leagues, determine Supercup qualifiers, handle promotion/relegation, update team reputations based on final positions, develop and return academy loans.

**Phase 4 — Qualification** (priority 105): Determine UEFA competition qualifiers.

### SeasonSetupPipeline (7 processors — both new games and transitions)

**Phase 1 — Fixtures & standings** (priority 30-40): Generate league fixtures, reset standings.

**Phase 2 — Budget & academy** (priority 50-55): Generate budget projections, trigger academy evaluation.

**Phase 3 — Competitions & onboarding** (priority 106-110): Initialize continental/cup competitions, enforce squad cap, reset onboarding.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Season/Services/SeasonClosingPipeline.php` | Orchestrates 17 closing processors |
| `app/Modules/Season/Services/SeasonSetupPipeline.php` | Orchestrates 7 setup processors |
| `app/Modules/Season/Processors/` | Individual processor implementations |
| `app/Modules/Match/Services/MatchdayService.php` | Matchday advancement logic |
| `app/Modules/Match/Services/CupTieResolver.php` | Cup tie resolution (aggregate, ET, penalties) |
| `app/Modules/Competition/Services/CupDrawService.php` | Cup draw mechanics |
