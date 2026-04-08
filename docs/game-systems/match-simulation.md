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

**Team strength** is calculated from the 11-player lineup with ability-dominant weights (technical 57.5%, physical 37.5%, morale 5%), each modified by a per-player energy effectiveness modifier and a random daily performance variance (normal distribution, tight range). See `calculateTeamStrength()` in `MatchSimulator`.

**Striker bonus**: The best forward in the lineup above a quality threshold adds bonus xG. See `calculateStrikerBonus()`.

All base values and exponents are configurable in `config/match_simulation.php`.

## Formation & Mentality

Each formation has attack and defense modifiers (multiplicative on xG). A team's attack modifier scales their own xG; their defense modifier scales the opponent's. Available formations and their modifiers are defined in `Formation` enum.

Three mentalities — defensive, balanced, attacking — trade off own scoring vs conceding. Modifiers are in `config/match_simulation.php`.

AI teams select mentality based on reputation tier (bold/mid/cautious) crossed with venue (home/away) and relative strength. See `LineupService::selectAIMentality()`.

## Unified Energy System

Energy and fitness are unified into a **single energy bar**. Players start each match at their current energy level (not always 100%), and energy drains during the match based on physical ability, age, and tactical setup.

**Proportional drain**: Drain scales with starting energy (`drain × startingEnergy / 100`), so fatigued players lose less absolute energy per minute — this prevents death spirals in congested periods.

A typical outfielder (physical 70, age 25) starting at 100% ends a match at ~60%. Goalkeepers drain at half rate. High-physical players drain slower and recover faster.

As energy drops, player effectiveness decreases (from 1.0x down to a configured minimum of 0.50x), making late-game substitutions and squad rotation meaningful.

Energy parameters are in `config/match_simulation.php` under the `energy` key.

## Between-Match Recovery

Players recover energy between matches using a **nonlinear formula** that makes it harder to reach peak energy. Near 100, recovery is slow; at lower energy, it accelerates. This creates natural equilibria based on how often a player plays:

```
recoveryRate = baseRecovery × physicalModifier × (1 + scaling × (100 − energy) / 100)
```

**Key dynamics:**
- **Single-match weeks** (7-day gaps): Full recovery to 100. Players start every match fresh.
- **Congested periods** (2+ matches/week): Energy equilibrium drops to 75–85 starting energy, forcing squad rotation.
- **Physical ability matters more**: High-physical players (90+) maintain ~92 start in congestion, while low-physical (50) drop to ~65.

**Modifiers:**
- **Age** affects energy loss per match — veterans (32+) lose ~12% more, young players (<24) lose ~8% less.
- **Physical ability** affects both drain rate AND recovery speed — high physical (≥80) recovers 10% faster, low physical (<60) recovers 10% slower.

AI teams use an energy rotation threshold (configurable, default 70) to bench fatigued players. All parameters are in `config/player.php` under the `condition` key.

## Match Performance Variance

Each player gets a random "form on the day" modifier using a normal distribution, shifted by morale. The tight variance range ensures the better squad reliably wins while still allowing occasional upsets. See `getMatchPerformance()`.

## Score Generation

Scores are Poisson-distributed from the final xG, capped at a maximum per team to prevent unrealistic scorelines.

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

**Penalty shootouts** use a kicker-vs-goalkeeper duel: base conversion rate adjusted by kicker technical/morale bonus minus goalkeeper technical penalty, plus luck. Standard 5 kicks, then sudden death. Implementation guarantees resolution.

## Live Match

Users interact with matches through:
- **Substitutions**: Up to 5 subs in 3 windows. Subs enter with their current energy level (not always 100%).
- **Tactical changes**: Formation and mentality changes mid-match, taking effect via `simulateRemainder()`.
- **Energy visibility**: Energy bars are shown for both on-pitch players and bench substitutes, helping inform substitution decisions.

## Season Simulation

Non-played leagues are simulated match-by-match using the same ratio-based xG formula. Squad strength is calculated from best 18 players. Results are sorted by points → goal difference → goals for. See `SeasonSimulationService`.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Match/Services/MatchSimulator.php` | Core simulation: xG, strength, events, extra time, penalties |
| `app/Modules/Match/Services/EnergyCalculator.php` | Energy drain and effectiveness calculations |
| `app/Modules/Player/Services/PlayerConditionService.php` | Between-match recovery and energy updates |
| `app/Modules/Finance/Services/SeasonSimulationService.php` | Full league season simulation |
| `config/match_simulation.php` | Energy drain and match tunable parameters |
| `config/player.php` | Recovery rate and AI rotation parameters |
