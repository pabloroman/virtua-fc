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

**Team strength** is calculated from the 11-player lineup with ability-dominant weights (technical 55%, physical 35%, fitness 5%, morale 5%), each modified by a per-player energy effectiveness modifier and a random daily performance variance (normal distribution, tight range). See `calculateTeamStrength()` in `MatchSimulator`.

**Striker bonus**: The best forward in the lineup above a quality threshold adds bonus xG. See `calculateStrikerBonus()`.

All base values and exponents are configurable in `config/match_simulation.php`.

## Formation & Mentality

Each formation has attack and defense modifiers (multiplicative on xG). A team's attack modifier scales their own xG; their defense modifier scales the opponent's. Available formations and their modifiers are defined in `Formation` enum.

Three mentalities — defensive, balanced, attacking — trade off own scoring vs conceding. Modifiers are in `config/match_simulation.php`.

AI teams select mentality based on reputation tier (bold/mid/cautious) crossed with venue (home/away) and relative strength. See `LineupService::selectAIMentality()`.

## Energy System

Players lose energy per minute based on physical ability and age. Goalkeepers drain slower. As energy drops, player effectiveness decreases (from 1.0x down to a configured minimum). This makes late-game substitutions and squad rotation meaningful.

Energy parameters are in `config/match_simulation.php` under the `energy` key.

## Match Performance Variance

Each player gets a random "form on the day" modifier using a normal distribution, shifted by morale and fitness. The tight variance range ensures the better squad reliably wins while still allowing occasional upsets. See `getMatchPerformance()`.

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
- **Substitutions**: Up to 5 subs in 3 windows. Fresh subs have full energy.
- **Tactical changes**: Formation and mentality changes mid-match, taking effect via `simulateRemainder()`.

## Season Simulation

Non-played leagues are simulated match-by-match using the same ratio-based xG formula. Squad strength is calculated from best 18 players. Results are sorted by points → goal difference → goals for. See `SeasonSimulationService`.

## Key Files

| File | Purpose |
|------|---------|
| `app/Modules/Match/Services/MatchSimulator.php` | Core simulation: xG, strength, events, extra time, penalties |
| `app/Modules/Finance/Services/SeasonSimulationService.php` | Full league season simulation |
| `config/match_simulation.php` | All tunable parameters |
