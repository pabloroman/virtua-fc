# Match Simulation

How match results are simulated in VirtuaFC.

## Overview

Match simulation calculates **expected goals (xG)** for each team using a ratio-based formula, then generates actual scores via **Poisson distribution**. The xG is influenced by team strength, formation, mentality, home advantage, and a striker quality bonus. During matches, players lose energy over time, affecting their contribution.

## xG Formula

```
homeXG = (strengthRatio ^ exponent) × baseGoals + homeAdvantage
         × formation modifiers × mentality modifiers × matchFraction

awayXG = ((1/strengthRatio) ^ exponent) × baseGoals
         × formation modifiers × mentality modifiers × matchFraction
```

The stronger team is always favored regardless of venue — home advantage is a modest additive bonus on top.

**Team strength** is calculated from the 11-player lineup with ability-dominant weights (overall_score 95%, morale 5%), each modified by a per-player energy effectiveness modifier and a random daily performance variance (normal distribution, tight range). See `calculateTeamStrength()` in `MatchSimulator`.

All base values and exponents are configurable in `config/match_simulation.php`.

### Strength floor (distribution-derived rescale)

Because outcomes depend on the strength **ratio** (`ratio ^ skill_dominance`) and strength is `mean(rating)/100` on a zero-baseline scale, leagues whose ratings cluster in a high, narrow band (e.g. the cash-rich, egalitarian Premier League) produce ratios near 1.0 — making matches coin flips and tables too flat. To correct this, each team's strength is rescaled by a per-match **floor** before the ratio is formed:

```
strength = (rating - floor) / (100 - floor)
```

The floor is **derived per competition** from its own rating band (`CompetitionStrengthFloorResolver`) so a high-narrow league gets a large floor and a low-wide league a small one, from a single global knob `strength_ratio_target` (R): `floor = (R·bottom - top)/(R - 1)`. Domestic-league matches use their league's floor; cups and continental matches use a global cross-band floor (which collapses toward 0, i.e. raw globally-consistent overalls, preserving genuine cross-league quality gaps). The rescale lives in `MatchOutcomeModel::applyFloor()` (a floor of 0 is a no-op) and is applied in both the full `MatchSimulator` path (via `setStrengthFloor()`, set by `FullMatchSimulationService`/`MatchResimulationService`) and the lightweight `AIMatchResolver`. Set `strength_floor_enabled => false` to disable. The `app:diagnose-strength-realism` command measures the effect and `--auto-floor` previews the production-derived floor.

The floor is calibrated on **static** top-11 `overall_score`, so it is applied to a team's **static** ability *before* the match-time modifiers (form, energy, out-of-position penalty), which are then applied as multipliers on the floored baseline. This ordering is load-bearing: the floor is derived to sit at least `strength_floor_margin` below the weakest squad, so static ability never reaches `applyFloor()`'s `0.02` clamp. The pre-#1283 code applied the floor to already-eroded match-time strength, which let a fatigued or out-of-position side fall through the floor, collapse toward `0.02`, and explode the home/away ratio into 13-0 / 21-0 blow-outs. `calculateTeamStrength()` now mirrors `AIMatchResolver`, which floors static strength and never had the blow-out.

### Strength-ratio clamp (cross-league bound)

Because outcomes use `ratio ^ skill_dominance`, the xG formula is otherwise unbounded. Within a league the floored static ratio stays anchored to the calibrated band (~R), so the clamp does not bind there. It exists for genuine **cross-league** mismatches — e.g. a top-flight side vs a lower-league team in a cup tie — where the static ratio legitimately runs higher and a single match could otherwise run away. The home/away ratio is clamped symmetrically to `[1/max, max]` via `MatchOutcomeModel::clampStrengthRatio()` **before** exponentiation, in `expectedGoals()`, `calculateBaseExpectedGoals()`, and `applyTacticalModifiers()` — so every code path (full simulator, live resimulation, extra time, AI-vs-AI) inherits the same bound from one place. The knob is `max_strength_ratio` (default 2.2 → worst-case xG ≈ `2.2 ^ skill_dominance × base_goals`; `0`/`≤1.0` disables it as a rollback escape hatch).

## Formation & Mentality

Each formation has attack and defense modifiers (multiplicative on xG). A team's attack modifier scales their own xG; their defense modifier scales the opponent's. Available formations and their modifiers are defined in `Formation` enum.

Three mentalities — defensive, balanced, attacking — trade off own scoring vs conceding. Modifiers are in `config/match_simulation.php`.

AI teams select mentality based on reputation tier (bold/mid/cautious) crossed with venue (home/away) and relative strength. See `LineupService::selectAIMentality()`.

## Unified Energy System

Energy and fitness are unified into a **single energy bar**. Players start each match at their current energy level (not always 100%), and energy drains during the match based on overall ability, age, and tactical setup.

**Proportional drain**: Drain scales with starting energy (`drain × startingEnergy / 100`), so fatigued players lose less absolute energy per minute — this prevents death spirals in congested periods.

A typical outfielder (overall 70, age 25) starting at 100% ends a match at ~60%. Goalkeepers drain at half rate. High-rated players drain slower and recover faster.

As energy drops, player effectiveness decreases (from 1.0x down to a configured minimum of 0.50x), making late-game substitutions and squad rotation meaningful.

Energy parameters are in `config/match_simulation.php` under the `energy` key.

## Between-Match Recovery

Players recover energy between matches using a **nonlinear formula** that makes it harder to reach peak energy. Near 100, recovery is slow; at lower energy, it accelerates. This creates natural equilibria based on how often a player plays:

```
recoveryRate = baseRecovery × abilityModifier × (1 + scaling × (100 − energy) / 100)
```

**Key dynamics:**
- **Single-match weeks** (7-day gaps): Full recovery to 100. Players start every match fresh.
- **Congested periods** (2+ matches/week): Energy equilibrium drops to 75–85 starting energy, forcing squad rotation.
- **Overall ability matters**: High-rated players (90+) maintain ~92 start in congestion, while low-rated (50) drop to ~65.

**Modifiers:**
- **Age** affects energy loss per match — veterans (32+) lose ~12% more, young players (<24) lose ~8% less.
- **Overall ability** affects both drain rate AND recovery speed — high overall (≥80) recovers 10% faster, low overall (<60) recovers 10% slower.

AI teams use an energy rotation threshold (configurable, default 70) to bench fatigued players. All parameters are in `config/player.php` under the `condition` key.

## Match Performance Variance

Each player gets a random "form on the day" modifier using a normal distribution, shifted by morale. The tight variance range ensures the better squad reliably wins while still allowing occasional upsets. See `getMatchPerformance()`.

## Score Generation

Scores are Poisson-distributed (correlated via Dixon-Coles) from the final xG. Each simulation period (substitution window, red-card split, etc.) samples its own xG from the strength ratio scaled by that period's **match fraction**, so the periods sum back to the full-match xG rather than each adding a fresh full-match's worth of goals. With xG bounded at the source by the strength floor (applied to static ability) and the ratio clamp, the resulting totals stay realistic without any post-hoc goal cap — there is no `max_goals_cap` and no event-trimming step, so no code path can diverge by forgetting to apply one. A single sampled period is still naturally bounded by the Dixon-Coles table (`DIXON_COLES_MAX_GOALS = 8`).

## Match Events

Beyond the scoreline, the simulation generates:

- **Goals**: Attributed by position weight (forwards most likely) with a dampened quality multiplier (`sqrt` not linear) and within-match diminishing returns (halved weight per prior goal). See `pickGoalScorer()`.
- **Assists**: Each goal has a configurable chance of having an assist, attributed by separate position weights. See `pickAssistProvider()`.
- **Own goals**: Small configurable chance per goal, attributed by defensive position weights.
- **Cards**: Yellow cards Poisson-distributed per team. Direct red chance increases with goal deficit. A second yellow becomes a red. Attributed by position weight (defenders/DMs highest).
- **Injuries**: Configurable chance per player per match (and separate training injury chance for non-playing squad). Medical tier reduces chance. See [Injury System](injury-system.md).
- **Event reassignment**: If a player is removed (injury/red card), subsequent events are reassigned to available teammates.

Position weights for all event types are defined in `MatchSimulator`.

## Extra Time & Penalties

**Extra time** uses the same xG formula scaled to 30 minutes with a fatigue reduction factor.

**Penalty shootouts** use a kicker-vs-goalkeeper duel: base conversion rate adjusted by kicker overall/morale bonus minus goalkeeper overall penalty, plus luck. Standard 5 kicks, then sudden death. Implementation guarantees resolution.

## Live Match

Users interact with matches through:
- **Substitutions**: Up to 5 subs in 3 windows. Subs enter with their current energy level (not always 100%).
- **Tactical changes**: Formation and mentality changes mid-match, taking effect via `simulateRemainder()`.
- **Energy visibility**: Energy bars are shown for both on-pitch players and bench substitutes, helping inform substitution decisions.

## Season Simulation

Other flat-league competitions in the game (the leagues the player isn't entered in — Premier League, Bundesliga, Serie A, Ligue 1 from a Spanish career, plus other Spanish tiers) are simulated **lazily on demand** by `SyntheticLeagueResolver`:

- The first time the user opens a non-user league page, fixtures are drawn (via `LeagueFixtureGenerator`) and standings are zero-initialized.
- Every match with `scheduled_date <= game.current_date` is then resolved via independent home/away **Poisson scoreline draws**. λ is matchup-aware: each team's expected goals start from a 1.35 baseline and shift by the gap between its squad strength and the opponent's (mean of the top 16 `overall_score` values per team), plus a +0.3 home boost. Scores are capped at 7 per side.
- Standings are updated via the regular `StandingsCalculator`. **No `MatchEvent`, lineup, MVP, or commentary data is generated** — top-scorer leaderboards for non-user leagues are intentionally empty.
- A Postgres advisory lock keyed on `(game_id, competition_id)` serializes initialization and resolution so concurrent requests don't double-draw fixtures.
- Subsequent visits resolve only newly-due matches and are otherwise pure reads.

At season close, `FinalizeOtherLeaguesProcessor` (priority 74, in `SeasonClosingPipeline`) calls the resolver for every flat league the user never opened, ensuring final standings exist before promotion/relegation, UEFA qualifier selection, and season summaries run. `SeasonSimulationProcessor` (priority 75) keeps its skip-when-real-standings-exist guard and silently no-ops for finalized leagues, but remains as a defensive fallback (still produces `SimulatedSeason` rows for any league the resolver couldn't process — odd team counts, missing schedule.json, etc.).

Cup competitions (`knockout_cup`), Swiss-format competitions (`swiss_format`, `group_stage_cup`), and the World Cup are not handled by the resolver — they keep their existing path.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Match/Services/MatchSimulator.php` | Core simulation: xG, strength, events, extra time, penalties |
| `app/Modules/Match/Support/MatchOutcomeModel.php` | Shared xG + Dixon-Coles math and the strength-floor rescale |
| `app/Modules/Match/Services/CompetitionStrengthFloorResolver.php` | Per-competition / global strength floor from the rating band |
| `app/Modules/Match/Services/EnergyCalculator.php` | Energy drain and effectiveness calculations |
| `app/Modules/Match/Services/SyntheticLeagueResolver.php` | Lazy Poisson simulation of non-user flat leagues |
| `app/Modules/Player/Services/PlayerConditionService.php` | Between-match recovery and energy updates |
| `app/Modules/Finance/Services/SeasonSimulationService.php` | Reputation-jitter fallback for unresolved non-user leagues |
| `app/Modules/Season/Processors/FinalizeOtherLeaguesProcessor.php` | Defensive season-close pass for non-user leagues |
| `config/match_simulation.php` | Energy drain and match tunable parameters |
| `config/player.php` | Recovery rate and AI rotation parameters |
