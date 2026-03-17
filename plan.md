# Free Agent Improvements: Reputation Gate + Free Agent Pool in Explore UI

## Overview

Two improvements to the free agent system:
1. **Reputation gate** — free agents use their player tier to determine willingness to sign, preventing world-class players from joining low-reputation teams
2. **Free agent pool in Explore UI** — a new "Free Agents" option in the explore page to browse available free agents

---

## Feature 1: Reputation Gate for Free Agents (using Player Tiers)

### Concept

When a user tries to sign a free agent, the player's tier (1-5, based on market value) determines the minimum team reputation they'll accept. This mirrors the existing `TransferService::MIN_TIER_BY_REPUTATION` pattern but inverted — instead of "which tier can this team buy", it's "which team can this player accept".

**Mapping (player tier → minimum team reputation to accept):**

| Player Tier | Min Value | Minimum Team Reputation | Example |
|------------|-----------|------------------------|---------|
| 5 (World Class) | €50M+ | Continental | Won't join modest/local clubs |
| 4 (Excellent) | €20M+ | Established | Won't join local clubs |
| 3 (Good) | €5M+ | Modest | Available to most teams |
| 2 (Average) | €1M+ | Local | Available to all teams |
| 1 (Developing) | <€1M | Local | Available to all teams |

If the team's reputation is below the threshold, the signing is rejected with a message. This is deterministic (not probabilistic) to keep it predictable and clear.

### Files to Change

**1. `app/Modules/Transfer/Services/ScoutingService.php`**
- Add constant `MIN_REPUTATION_BY_PLAYER_TIER` mapping tier → minimum reputation level
- Add method `canSignFreeAgent(GamePlayer $player, string $gameId, string $teamId): bool`
  - Gets player tier from `$player->tier`
  - Gets team reputation via `TeamReputation::resolveLevel($gameId, $teamId)`
  - Compares team reputation index against minimum required index
- Add method `getFreeAgentWillingnessLevel(GamePlayer $player, string $gameId, string $teamId): string`
  - Returns 'willing', 'reluctant', or 'unwilling' for UI display
  - 'willing': team reputation >= player's minimum (will sign)
  - 'reluctant': team reputation is exactly 1 below minimum (would need a step up)
  - 'unwilling': team reputation is 2+ below minimum (won't consider)

**2. `app/Http/Actions/SignFreeAgent.php`**
- After existing validations, add reputation gate check:
  ```php
  if (!$this->scoutingService->canSignFreeAgent($player, $game->id, $game->team_id)) {
      return redirect()->route('game.transfers', $gameId)
          ->with('error', __('messages.free_agent_reputation_too_low'));
  }
  ```

**3. `lang/es/messages.php` and `lang/en/messages.php`**
- Add `free_agent_reputation_too_low` translation key
  - EN: "This player has no interest in joining a club of your reputation level."
  - ES: "Este jugador no tiene interés en fichar por un club de tu nivel de reputación."

**4. `resources/views/partials/scout-report-results.blade.php`** (if free agents show willingness there)
- Show willingness indicator next to "Sign Free Agent" button based on tier vs reputation comparison
- If unwilling, disable the sign button and show a tooltip explaining why

**5. `resources/views/partials/explore-free-agents.blade.php`** (new partial, see Feature 2)
- Show willingness badge per player in the free agent pool

### No migration needed
Player tier is already stored on `game_players.tier`. Team reputation is already resolved via `TeamReputation::resolveLevel()`.

---

## Feature 2: Free Agent Pool in Explore UI

### Concept

Add a "Free Agents" pill/tab alongside the competition pills in the Explore page. When selected, it shows all current free agents in a list similar to the squad view, with position grouping and shortlist toggle. No ability info shown (consistent with explore not revealing abilities — scouting is needed for that).

### UI Design

The free agent pool appears as an additional pill button in the competition selector row, visually distinct (e.g., different icon or color). When selected:
- The teams list (left column) is replaced by position filter tabs: All / GK / DEF / MID / FWD
- The squad view (right column) shows the free agent list in table format matching `explore-squad.blade.php` style
- Each player row shows: position badge, name + nationality, age, market value, willingness badge, shortlist star
- The willingness badge uses the reputation gate (Feature 1) to show green/yellow/red status

### Files to Change

**1. `app/Http/Views/ShowExplore.php`**
- Add free agent count to the view data:
  ```php
  $freeAgentCount = GamePlayer::where('game_id', $gameId)
      ->whereNull('team_id')
      ->count();
  ```
- Pass `$freeAgentCount` and `$teamReputationLevel` (user's team) to the view

**2. `app/Http/Views/ExploreFreeAgents.php`** (new file)
- New AJAX endpoint similar to `ExploreSquad`
- Returns server-rendered HTML partial of free agents
- Accepts optional `position` query parameter for filtering (all/gk/def/mid/fwd)
- Query: `GamePlayer::where('game_id', $game->id)->whereNull('team_id')->with('player')->get()`
- Sort by position group, then market value descending (same as explore-squad)
- Load shortlist status
- Calculate willingness per player using `ScoutingService::getFreeAgentWillingnessLevel()`
- Returns `partials.explore-free-agents` partial

**3. `resources/views/partials/explore-free-agents.blade.php`** (new file)
- Similar structure to `explore-squad.blade.php` but with:
  - Header: "Free Agents" with count badge instead of team logo
  - Info box about free agents (replaces scouting nudge)
  - Same table columns: position, name+nationality+mobile details, age, value, willingness badge, shortlist star
  - Willingness badge: green "Interested" / yellow "Reluctant" / red "Not interested" using design system badge colors
  - If transfer window is open, show "Sign" button for willing free agents (links to existing sign action)
  - If window is closed, show "Window closed" indicator

**4. `resources/views/explore.blade.php`**
- Add "Free Agents" pill in the competition selector, after the competition pills:
  ```blade
  <x-pill-button @click="selectFreeAgents()"
      x-bind:class="viewMode === 'freeAgents' ? '...' : '...'"
      class="shrink-0 gap-2 rounded-lg border min-h-[44px]">
      <svg><!-- person icon --></svg>
      <span>{{ __('transfers.free_agents_pool') }}</span>
      <span class="text-xs ...">{{ $freeAgentCount }}</span>
  </x-pill-button>
  ```
- Add Alpine.js state: `viewMode` ('competition' or 'freeAgents'), `positionFilter` ('all'/'gk'/'def'/'mid'/'fwd')
- When in free agent mode:
  - Left column shows position filter buttons instead of teams list
  - Right column shows free agent list via AJAX (same pattern as squad loading)
- Add `selectFreeAgents()` and `selectPositionFilter(pos)` methods
- Add `loadFreeAgents(position)` async method that fetches from the new endpoint

**5. `routes/web.php`**
- Add route: `Route::get('/game/{gameId}/explore/free-agents', ExploreFreeAgents::class)->name('game.explore.free-agents');`

**6. `lang/es/transfers.php` and `lang/en/transfers.php`**
- Add keys:
  - `free_agents_pool` — "Free Agents" / "Agentes Libres"
  - `explore_free_agents_hint` — hint text about free agents
  - `free_agent_willing` — "Interested" / "Interesado"
  - `free_agent_reluctant` — "Difficult" / "Difícil"
  - `free_agent_unwilling` — "Not interested" / "Sin interés"
  - `explore_free_agents_empty` — "No free agents available" / "No hay agentes libres disponibles"
  - `explore_filter_all` — "All" / "Todos"
  - `explore_window_closed` — "Transfer window closed" / "Ventana de fichajes cerrada"

---

## Implementation Order

1. **Reputation gate logic** — `ScoutingService` methods + constants
2. **SignFreeAgent gate** — add check to the action
3. **Translations** — all new keys in both languages
4. **ExploreFreeAgents view + route** — new AJAX endpoint
5. **explore-free-agents partial** — new Blade template with willingness badges
6. **explore.blade.php changes** — Alpine.js state + free agents pill + position filter
7. **Tests** — unit test for reputation gate logic
