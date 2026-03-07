# Club Reputation System

Club reputation is a dynamic tier that evolves based on sustained on-pitch performance. It influences finances, season goals, transfer attractiveness, loan destinations, and AI tactical behaviour.

## Reputation Tiers

Five tiers, ordered lowest to highest:

| Index | Tier | Example Clubs (seed) |
|-------|------|---------------------|
| 0 | Local | Small/unknown clubs |
| 1 | Modest | Rayo Vallecano, Girona FC |
| 2 | Established | Espanyol, Celta de Vigo |
| 3 | Continental | Real Betis, Sevilla FC, Atletico |
| 4 | Elite | Real Madrid, FC Barcelona |

## Points-Based Progression

Each team has a **reputation points** score that determines their current tier. Points thresholds mirror the tier index (0, 100, 200, 300, 400). Teams start at the midpoint of their seeded tier.

At the end of each season, points are adjusted based on:

1. **Final league position** -- top finishes award positive points, relegation-zone finishes deduct points. Top-division deltas are larger than second-division ones.
2. **Regression toward base** -- a small pull (configurable) drags points back toward the team's seeded base tier each season. This prevents runaway inflation and ensures sustained excellence is required.

After points are updated, the effective tier is recalculated from the new total.

### Approximate Pace

With the default config, a modest team (100 pts) consistently finishing in Champions League places (~+30 pts/season, minus ~5 regression) reaches established (200 pts) in roughly **4 seasons**. A dominant run with title finishes (+40 pts/season) can accelerate this to ~3 seasons.

Decline works similarly: an elite team that finishes mid-table (+5 pts but -5 regression) will plateau, while a team in the relegation zone (-10 pts -5 regression) will drop a tier in about 7 seasons.

## Floor Mechanism

Teams cannot drop more than **2 tiers** below their seeded base reputation. This prevents historically significant clubs from falling to unrealistically low levels. For example, Real Madrid (seeded elite, index 4) can never drop below established (index 2).

## Where Reputation Is Used

| System | How reputation affects it |
|--------|------------------------|
| **Finances** | Operating expenses, commercial revenue per seat, and matchday revenue per seat are all keyed by reputation tier in `config/finances.php`. Higher reputation = more revenue but also higher operating costs. |
| **Season Goals** | Each competition config maps reputation to an expected season goal (elite -> title, continental -> CL, etc.). The goal determines what the board considers success. |
| **Transfers** | Players at higher-reputation clubs are harder to sign. The `ScoutingService` applies a reputation gap modifier that reduces transfer acceptance probability when bidding from a lower-tier club. |
| **Loans** | `LoanService` scores loan destinations partly on reputation match, ensuring players go to clubs appropriate for their ability level. |
| **AI Tactics** | `LineupService` uses reputation to select AI team mentality and tactical instructions (bigger clubs play more attacking football). |
| **AI Transfers** | `AITransferMarketService` uses reputation to prioritise which teams are most active in the transfer market. |

## Architecture

### Data Model

- `team_reputations` table: game-scoped records with `game_id`, `team_id`, `reputation_level`, `base_reputation_level`, `reputation_points`
- `TeamReputation` model with `resolveLevel(gameId, teamId)` static helper (falls back to `ClubProfile` for backwards compatibility)
- `ClubProfile` remains the static seed source; `TeamReputation` is the game-scoped override

### Season End Processor

`ReputationUpdateProcessor` (priority 27) runs after `PromotionRelegationProcessor` (26) and before `LeagueFixtureProcessor` (30) / `BudgetProjectionProcessor` (50). It:

1. Iterates all league competitions in the game
2. Reads final positions from `GameStanding` (played leagues) or `SimulatedSeason` (AI leagues)
3. Awards/deducts points per the config-driven position delta table
4. Applies regression toward base tier
5. Recalculates effective tiers
6. Notifies the user if their club's tier changed

### Initialization

During `SetupNewGame`, reputation records are created for all teams by copying their `ClubProfile.reputation_level` as both the starting tier and the base tier.

## Key Files

| Purpose | Location |
|---------|----------|
| Model | `app/Models/TeamReputation.php` |
| Season processor | `app/Modules/Season/Processors/ReputationUpdateProcessor.php` |
| Config (deltas, regression) | `config/reputation.php` |
| Initialization | `app/Modules/Season/Jobs/SetupNewGame.php` |
| Migration | `database/migrations/2026_03_07_000001_create_team_reputations_table.php` |
