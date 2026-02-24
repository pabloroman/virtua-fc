# Season Lifecycle

This document describes the season progression flow and the end-of-season processing pipeline.

## Season Flow

A season follows this general progression:

```
GAME START / NEW SEASON
│
├── Budget Allocation (mandatory before first matchday)
│   └── Allocate surplus across 5 investment areas
│
├── Matchday Loop (typically 38 matchdays for La Liga)
│   ├── Pre-match: Set lineup, formation, mentality
│   ├── Match simulation (live match with subs/tactical changes)
│   ├── Post-match: Standings update, injuries, suspensions
│   ├── Between matchdays:
│   │   ├── Transfer market activity (offers, scouting)
│   │   ├── Academy player development (gradual stat growth)
│   │   ├── Academy stat reveals (Phase 1 at matchday 10, Phase 2 at winter)
│   │   ├── Training injuries for non-playing squad
│   │   └── Transfer windows (Summer: matchday 0, Winter: matchday 19)
│   └── Cup matches interspersed (Copa del Rey, European competitions)
│
├── Season End Trigger
│   └── All competitions completed for the season
│
└── Season End Pipeline (21 processors in priority order)
    └── See below
```

## Season End Pipeline

At the end of each season, a pipeline of 21 ordered processors handles the transition to the next season. Each processor implements `SeasonEndProcessor` with a `priority()` method (lower = runs first).

### Phase 1: Roster Cleanup (Priority 0-7)

| Priority | Processor | What it does |
|----------|-----------|-------------|
| 0 | **SeasonArchiveProcessor** | Archives standings, player stats, season awards (champion, top scorer, most assists, best goalkeeper), match results. Deletes archived data to free space. |
| 3 | **LoanReturnProcessor** | Returns all loaned players to their parent teams. Notifies user. |
| 5 | **ContractExpirationProcessor** | Releases user's expired-contract players. Auto-renews AI teams' contracts for 2 years. Uses June 30 cutoff. |
| 5 | **PreContractTransferProcessor** | Completes free transfers from agreed pre-contracts (both outgoing and incoming). |
| 6 | **ContractRenewalProcessor** | Applies pending contract renewals — new wages take effect. |
| 7 | **PlayerRetirementProcessor** | Retires players marked for retirement. Announces new retirement candidates (age 33+ probability). |

### Phase 2: Squad Management (Priority 8-20)

| Priority | Processor | What it does |
|----------|-----------|-------------|
| 8 | **SquadReplenishmentProcessor** | Ensures AI teams have viable rosters. Enforces minimums: 2 GK, 5 DEF, 5 MID, 3 FWD, 22 total. Generates players scaled to team's average ability. |
| 10 | **PlayerDevelopmentProcessor** | Applies ability growth/decline for all players. Recalculates market values. Uses batch upsert for performance. |
| 15 | **SeasonSettlementProcessor** | Calculates actual season revenue (TV, matchday, commercial, transfers, cups, subsidies). Reconciles vs projections. Calculates variance and carries debt. |
| 20 | **StatsResetProcessor** | Resets player match stats (appearances, goals, assists, cards). Clears suspensions. Updates game season number. Marks notifications as read. |
| 20 | **TransferMarketResetProcessor** | Deletes scout reports, shortlisted players, and transfer offers. Clears transfer status on players. |

### Phase 3: League Simulation & Structure (Priority 24-30)

| Priority | Processor | What it does |
|----------|-----------|-------------|
| 24 | **SeasonSimulationProcessor** | Simulates full standings for non-played leagues (AI divisions). Skips player's own league and Swiss-format competitions. |
| 25 | **SupercupQualificationProcessor** | Determines Supercup qualifiers (4 teams): cup finalists + league champion/runner-up. |
| 26 | **PromotionRelegationProcessor** | Handles promotion/relegation between divisions using country-specific rules. Swaps teams. Re-simulates affected leagues. Updates game's competition if player's team moves. |
| 30 | **LeagueFixtureProcessor** | Deletes old matches/cup ties. Generates league fixtures for the new season. |

### Phase 4: New Season Setup (Priority 40-106)

| Priority | Processor | What it does |
|----------|-----------|-------------|
| 40 | **StandingsResetProcessor** | Resets league standings for new season. Preserves team ordering from previous season. |
| 50 | **BudgetProjectionProcessor** | Determines season goal (title, CL, survival, etc.) based on reputation. Generates budget projections (revenue, wages, surplus). |
| 55 | **YouthAcademyProcessor** | Develops loaned academy players (1.5× rate). Returns loans. Marks players needing evaluation. Adds pending action. |

### Phase 5: Continental & Cup Setup (Priority 105-110)

| Priority | Processor | What it does |
|----------|-----------|-------------|
| 105 | **UefaQualificationProcessor** | Determines UEFA competition qualifiers (UCL/UEL/UECL) from league standings. Applies cup winner cascade logic. Qualifies UEL winner to next UCL. Fills remaining slots to reach 36 teams. |
| 106 | **ContinentalAndCupInitProcessor** | Initializes Swiss-format competitions (UCL) with standings and fixtures. Conducts domestic cup draws. Sets `current_date` to earliest fixture. |
| 110 | **OnboardingResetProcessor** | Resets onboarding flag so player must configure investment allocation for the upcoming season. |

## Matchday Progression

Within a season, matchday advancement follows this flow:

1. **Check for pending actions** — Academy evaluation, budget allocation, etc. block advancement until resolved
2. **Fetch unplayed matches** for current matchday across all competitions
3. **Route to handler** — LeagueHandler, KnockoutCupHandler, SwissFormatHandler, etc.
4. **Set lineups** — Auto-select for AI teams (respecting fitness rotation), use user's saved lineup
5. **Simulate match** — Full match simulation with events
6. **Post-match processing** — Update standings, award prize money, apply suspensions
7. **Between-matchday processing** — Transfer market, injuries, notifications
8. **Check season end** — If all fixtures complete, trigger the end pipeline

## Competition Structure

The game runs multiple competitions simultaneously within a season:

| Competition | Handler | Matchdays |
|-------------|---------|-----------|
| La Liga / La Liga 2 | LeagueHandler | 38 (or 42 for 22-team leagues) |
| Copa del Rey | KnockoutCupHandler | Interspersed (knockout rounds) |
| Supercopa de España | KnockoutCupHandler | Pre-season |
| Champions League | SwissFormatHandler → KnockoutCupHandler | Swiss phase + knockout |
| Europa League | GroupStageCupHandler → KnockoutCupHandler | Groups + knockout |
| Conference League | GroupStageCupHandler → KnockoutCupHandler | Groups + knockout |

Cup ties can be single-leg or two-legged, with extra time and penalties for drawn ties. See [Match Simulation](match-simulation.md) for details.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Season/Services/SeasonEndPipeline.php` | Orchestrates all 21 processors |
| `app/Modules/Season/Contracts/SeasonEndProcessor.php` | Interface all processors implement |
| `app/Modules/Season/Processors/` | All 21 processor implementations |
| `app/Modules/Season/Services/GameCreationService.php` | Creates new game, dispatches setup job |
| `app/Modules/Match/Services/MatchdayService.php` | Matchday advancement logic |
| `app/Modules/Match/Services/CupTieResolver.php` | Cup tie resolution (aggregate, ET, penalties) |
| `app/Modules/Competition/Services/CupDrawService.php` | Cup draw mechanics |
