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

Season transitions run two pipelines sequentially: `SeasonClosingPipeline` closes the old season, `SeasonSetupPipeline` opens the new one (and also runs on its own when a game is created). Each processor implements `SeasonProcessor` with a `priority()`; lower runs first, and processors sharing a priority run in the order they are wired into the pipeline's constructor. Most processors live in `app/Modules/Season/Processors/`; the Manager and Stadium modules contribute a few of their own (marked below).

The tables are the source of truth for ordering. `tests/Unit/SeasonPipelineOrderingTest.php` pins the processor counts and the ordering the transfer domain relies on, so they cannot drift from the code without a failing test.

### SeasonClosingPipeline (29 processors — transitions only)

| Priority | Processor | What it does |
|---------:|-----------|--------------|
| 4 | `ReserveOveragePromotionProcessor` | Promotes reserve players past the U23 cutoff to the first team |
| 5 | `LoanReturnProcessor` | Returns every loaned player to his owner — after this, `team_id` is the owner again |
| 6 | `AIReserveCallUpProcessor` | AI parent clubs promote their best young reserve prospects |
| 10 | `TrophyRecordingProcessor` (Manager) | Records trophies the manager won this season |
| 15 | `LeaderboardStatsProcessor` | Increments the manager's seasons-completed counter |
| 20 | `SnapshotManagerSeasonRecordProcessor` (Manager) | Snapshots the finished season into the manager's record |
| 20 | `ContractExpirationProcessor` | Frees expired contracts to the pool (user) or auto-renews (AI); never frees a player held by a locking deal |
| 25 | `SeasonArchiveProcessor` | Archives the season before stats are reset |
| 30 | `PreContractTransferProcessor` | Completes agreed pre-contracts, in both directions |
| 35 | `AgreedTransferCompletionProcessor` | Completes agreed transfers that missed the last window |
| 35 | `ContractRenewalProcessor` | Applies pending renewal wages |
| 40 | `PlayerRetirementProcessor` | Retires announced players, announces next season's |
| 42 | `SquadReplenishmentProcessor` | AI roster maintenance: youth intake and squad trimming |
| 45 | `AIFreeAgentSigningProcessor` | AI clubs sign free agents into remaining gaps |
| 55 | `PlayerDevelopmentProcessor` | Development, market revaluation, tier recompute |
| 60 | `SeasonSettlementProcessor` | Settles actual revenue against projections |
| 60 | `UserSquadCareerSnapshotProcessor` | Snapshots season stats into career records |
| 65 | `StadiumLoanBillingProcessor` | Bills the annual stadium loan instalment |
| 65 | `StatsResetProcessor` | Resets player and game stats |
| 70 | `TransferMarketResetProcessor` | Clears offers, listings and scouting data |
| 74 | `FinalizeOtherLeaguesProcessor` | Finalises flat leagues the user never opened |
| 75 | `SeasonSimulationProcessor` | Simulates standings for leagues that were not played |
| 80 | `SupercupQualificationProcessor` | Determines next season's domestic supercup entrants |
| 82 | `DomesticCupQualificationProcessor` | Rebuilds domestic cup participants from each country's rules (see [Domestic Cups](domestic-cups.md)) |
| 85 | `PromotionRelegationProcessor` | Promotion and relegation across every division |
| 90 | `ReputationUpdateProcessor` | Updates reputation points and tiers from final positions |
| 92 | `FanLoyaltyUpdateProcessor` (Stadium) | Nudges fan loyalty from season outcomes |
| 95 | `YouthAcademyClosingProcessor` | Develops academy players and returns academy loans |
| 100 | `UefaQualificationProcessor` | Determines UEFA competition qualifiers |

The transfer domain depends on the 5 → 20 → 30 → 35 → 42 → 70 chain: loans return before contracts expire, expiry leaves locked players in place, the two completion processors move them, replenishment runs only once every deal has settled, and the market is cleared last. See [Transfer Market](transfer-market.md#locked-players).

### SeasonSetupPipeline (15 processors — new games and transitions)

| Priority | Processor | What it does |
|---------:|-----------|--------------|
| 0 | `ApplyPendingTeamSwitchProcessor` (Manager) | Applies a pro-manager team switch accepted at the season-end screen |
| 28 | `YouthAcademyPromotionProcessor` | Auto-promotes academy players so the user's squad meets its minimum |
| 30 | `LeagueFixtureProcessor` | Clears old fixtures and generates the league calendar |
| 40 | `StandingsResetProcessor` | Resets (or creates) league standings |
| 85 | `UefaSuperCupQualificationProcessor` | Writes the UEFA Super Cup entrants |
| 104 | `SeedInitialNamingDealProcessor` | Materialises a club's real-world naming deal on first setup |
| 105 | `GenerateNamingRightsOffersProcessor` | Rolls naming-rights deals over: expiry, renewal offers, cleanup |
| 106 | `ContinentalAndCupInitProcessor` | Initialises Swiss competitions, draws the first cup rounds, finalises `current_date` |
| 107 | `BudgetProjectionProcessor` | Generates the new season's budget projections |
| 108 | `PreSeasonFixtureProcessor` | Flags career games as needing pre-season opponent selection |
| 109 | `DefaultInvestmentProcessor` | Applies the default investment allocation |
| 109 | `SquadRegistrationEnforcementProcessor` | Enables squad registration and enforces squad numbers |
| 110 | `NewSeasonResetProcessor` | Clears the new-season flag and announces the summer window |
| 111 | `TransferMarketSeedProcessor` | Seeds the AI market with an initial batch of listings |
| 115 | `SeasonTicketDefaultsProcessor` | Seeds season-ticket pricing with the default preset |

## Execution and recovery

Each processor runs inside its own database transaction; there is no pipeline-wide transaction. After every processor the pipeline checkpoints on the `Game` row — `season_transition_step` (the index just completed; setup steps continue the numbering after the closing ones) and `season_transition_data` (the `SeasonTransitionData` DTO, including its metadata bag). `ProcessSeasonTransition` reads that checkpoint on start and skips every step already done, so a failed transition resumes at the processor that failed rather than repeating the ones that succeeded. On success both columns and `season_transitioning_at` are cleared and `SeasonStarted` fires.

A transition that has been running for more than a couple of minutes is treated as stalled by the game and setup-status views, which re-dispatch the job. Two commands cover the cases that does not fix:

```bash
php artisan app:resume-season-transition {game}                 # re-dispatch from the checkpoint
php artisan app:unstick-season-transition {gameId} [--dry-run]  # repair division imbalances, then re-dispatch
```

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Season/Services/SeasonClosingPipeline.php` | Orchestrates the 29 closing processors |
| `app/Modules/Season/Services/SeasonSetupPipeline.php` | Orchestrates the 15 setup processors |
| `app/Modules/Season/Jobs/ProcessSeasonTransition.php` | Runs both pipelines with checkpoint/resume |
| `app/Modules/Season/Processors/` | Individual processor implementations |
| `app/Modules/Match/Services/MatchdayService.php` | Matchday advancement logic |
| `app/Modules/Match/Services/CupTieResolver.php` | Cup tie resolution (aggregate, ET, penalties) |
| `app/Modules/Competition/Services/CupDrawService.php` | Cup draw mechanics |
