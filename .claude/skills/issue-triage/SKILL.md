---
name: issue-triage
description: "Triage a Spanish bug report or feature request and turn it into an agent-ready English roadmap issue on `pabloroman/virtua-fc`. Use when the user pastes Spanish text describing a bug, feature request, suggestion (sugerencia), or feedback (`error`, `bug`, `no funciona`, `me gustaría`, `sería genial`, `mejora`, `sugerencia`, `idea`), OR when they reference an existing GitHub issue by number (`#123`, `issue 123`, `triage #123`, `procesa el ticket #123`). Skill produces a draft spec for the user to approve, then creates a new parent issue (labels: `roadmap` + `priority:P*` + area) and links the original Spanish report as a sub-issue. Pairs with the deepened-spec template, label taxonomy, priority rubric, and code-pointer cheatsheet defined inline below."
---

# Issue Triage — Spanish bug → English roadmap parent

Convert a Spanish-language bug report or feature request from a community user into a clean, agent-ready English **roadmap parent issue** on `pabloroman/virtua-fc`. Original Spanish issues stay untouched; the new parent gets the work, and the original is linked as a `sub_issue` for back-reference.

## When to invoke

Trigger when the user:

1. **Pastes raw Spanish text** that describes a bug or feature request (typical markers: `error`, `bug`, `no funciona`, `me gustaría`, `sería genial`, `sugerencia`, `idea`, `mejora`, `propuesta`).
2. **References a GitHub issue number** to triage (`#123`, `issue 123`, `triage #123`, `procesa el ticket #123`, `convierte el #123 en roadmap`).
3. Asks to **batch-triage** several reports at once. Run the playbook per report.

Do NOT invoke for:
- General codebase questions about an issue
- Closing or commenting on an existing issue (that's manual)
- Triaging already-closed issues
- Bulk re-triage of the entire backlog (that's a different, larger flow — see `/tmp/triage/` artifacts on the first run)

## Defaults locked in by the maintainer

- **Always stop at draft for approval** before any GitHub write. Never auto-create the issue.
- **Originals are sacred**: zero edits, comments, labels, or state changes on the original Spanish issue. The only acceptable interaction with the original is reading it (`issue_read`) and linking it as a sub-issue (`sub_issue_write`).
- **No GitHub Projects MCP available** — the user manually drags new parents into the Project board afterwards. Note this in the final report.
- **No code changes**. Triage is GitHub-only.
- **Be frugal with comments**. Do not post comments on the original or the new parent unless the user asks.

## Process (7 steps)

### 1. Acquire the report

If raw text: use it as `body`. Try to infer the reporter handle if mentioned; otherwise leave the source line as `submitted via chat`.

If `#NNN`: fetch via `mcp__github__issue_read` (`method: get`) and, if `comments > 0`, also `method: get_comments`. The comments often contain the actual disambiguation (see #1089, #784, #737, #1023, #1024).

### 2. Detect already-fixed reports

Cross-reference against recent commits where **the commit date postdates the issue date**:

```bash
git log --since="<issue created_at>" --pretty=format:"%h|%ad|%s" --date=short
```

Confidence levels:
- 🟢 **High** — commit body explicitly describes the same bug/feature → propose closure of the original instead of creating a new roadmap parent
- 🟡 **Medium** — plausibly the same root cause but commit message doesn't directly address it → ask the user to verify before creating
- 🟠 **Partial** — commit addresses part of the issue → narrow the roadmap spec's scope to the remaining piece, reference the prior fix as "related"

If the report's reporter has self-acknowledged in comments that the bug is fixed (e.g., #1024 — *"ya no me pasa creo que se ha solucionado"*), surface that and propose closure.

### 3. De-duplicate against existing roadmap parents

Search:

```
mcp__github__search_issues query: "repo:pabloroman/virtua-fc is:issue is:open label:roadmap <keyword>"
```

If a clear match: do NOT create a new parent. Instead, link the Spanish original as a **sub-issue of the existing roadmap parent**. Report this to the user and ask for confirmation before linking.

### 4. Split if multi-topic

Mega-feedback tickets bundle 4–8 unrelated suggestions in a single issue (canonical examples: #1049, #1037, #605, #633, #534). Detect by:
- Numbered or bulleted lists with more than 2 distinct topics
- Section headers like `Alineaciones:`, `Mercado:`, `Club:`
- Body length > ~1500 characters

For each atomic item, produce a separate draft spec. List them upfront and ask the user to confirm the split before deepening.

### 5. Draft the English spec using this exact template

```markdown
> **Roadmap parent** — auto-generated from triage. See all with [label:roadmap](https://github.com/pabloroman/virtua-fc/labels/roadmap).

### Problem
[2–4 sentences in English: what's broken or missing, who reported, user impact. Translate naturally — don't transliterate.]

### Acceptance criteria
- [ ] [Specific, testable outcome 1]
- [ ] [Specific, testable outcome 2]
- [ ] Mobile (375px) verified  ← if UI
- [ ] Dark + light mode verified  ← if UI
- [ ] Both `lang/es/` and `lang/en/` updated  ← if i18n
- [ ] Tests added (extend `tests/Feature/` or `tests/Unit/`)

### Code pointers
- [path 1] — one-line note on what to look at
- [path 2] — etc.

### Out of scope
- [Non-goal 1]
- [Non-goal 2]

### Sources
- #NNN (@reporter) — short note on why
```

Title format: `[Ref] Title in English` where `Ref` is a 2–3-character mnemonic for the theme (L=Live match, T=Tactics, F=Fichajes/Transfers, C=Cesiones/Loans, D=Development, S=Squad UI, I=Injuries, Y=Youth/Cantera, M=Money/Finances, K=Competition, G=Manager/Meta, U=UX polish). For a single-issue triage you can pick the next free number in that family by searching `[<letter>` in roadmap issues. If unclear, omit the ref and use a plain English title.

### 6. Assign priority + labels

**Priority rubric:**

| Tier | Criteria | Typical examples |
|------|----------|------------------|
| **P0** | Game-breaking bug, data corruption, blocks core flow for many users | Scoreboard shows wrong score; lineup save broken |
| **P1** | Bug affecting gameplay realism OR multi-reporter feature OR owner-flagged | Substitution bugs; high-traffic UI broken; balance exploit |
| **P2** | Quality-of-life improvement, UX request, mid-impact suggestion | New filters; new stats column; minor UX redesign |
| **P3** | Nice-to-have, speculative, polish, niche | Color tweaks; whimsical features; v3 territory |

**Label set:**
- **Always**: `roadmap` + exactly one of `priority:P0` / `priority:P1` / `priority:P2` / `priority:P3`
- **Type**: `bug` or `idea` (mutually exclusive)
- **Area** (zero or more, pick what fits): `fichajes`, `alineación`, `plantilla`, `cantera`, `UX/UI`, `simulación`, `v3`

Do NOT invent new labels — `mcp__github__` has no `create_label` tool. Stick to the taxonomy above. If a strong case exists for a new area label, surface that recommendation in the draft and the user can create it manually before issue creation.

### 7. Draft → approval → create + link

Present the draft spec(s), priority, labels, and any already-fixed/duplicate findings to the user. Wait for explicit approval. Then:

1. **Create the parent issue** via `mcp__github__issue_write` (`method: create`, `owner: pabloroman`, `repo: virtua-fc`, title, body, labels).
2. **Link the original** as a sub-issue via `mcp__github__sub_issue_write` (`method: add`, `issue_number`: new parent's number, `sub_issue_id`: the original's `node_id`).
3. **Report back** with the new parent URL and a reminder to drag it into the GitHub Project board manually.

### Getting the original's `node_id`

The `issue_read` MCP tool does **not** return the REST `id` / `node_id` needed for `sub_issue_write`. Two ways to get it:

- `mcp__github__search_issues` returns `node_id` in its output (search by the issue number).
- A `state: "open"` no-op `issue_write` update returns the `node_id` and doesn't actually modify the issue (verified — `updated_at` stays at the original timestamp). Use sparingly; prefer `search_issues`.

## Code-pointer cheatsheet (carry-over from initial triage)

When writing the "Code pointers" section, use this map. Verify paths still exist before referencing.

### Match simulation
- `app/Modules/Match/Services/MatchSimulator.php`
- `app/Modules/Match/Services/MatchFinalizationService.php`
- `app/Modules/Match/Services/MatchdayOrchestrator.php`
- `app/Modules/Match/Support/ScoreEventsAuditor.php`
- `config/match_simulation.php`

### Live match UI
- `resources/views/live-match.blade.php`
- `resources/js/live-match.js`
- `resources/js/modules/tactical-submission.js`
- `app/Support/LiveMatchLineupPresenter.php`
- `resources/views/partials/live-match/`

### Lineup & tactics
- `resources/views/lineup.blade.php` + `resources/js/lineup.js`
- `app/Http/Actions/SaveLineup.php`
- `app/Http/Views/ShowSquadSelection.php`
- `app/Modules/Lineup/Services/FormationRecommender.php`
- `app/Modules/Lineup/Services/TacticalChangeService.php`
- `app/Modules/Lineup/Services/SubstitutionService.php`
- `app/Modules/Match/Services/MatchLineupResolver.php`
- `app/Modules/Squad/Services/PlayerSquadRoleClassifier.php`

### Players & development
- `app/Models/GamePlayer.php` (has `secondary_positions`, `injury_date`, `injury_weeks`)
- `app/Modules/Player/Services/PlayerDevelopmentService.php`
- `app/Modules/Player/Services/PlayerConditionService.php`
- `app/Modules/Player/Services/InjuryService.php`
- `app/Modules/Player/Services/PlayerValuationService.php`

### Transfers & scouting
- `app/Modules/Transfer/Services/ExploreService.php`
- `app/Modules/Transfer/Services/ScoutingService.php`
- `app/Modules/Transfer/Services/LoanService.php`
- `app/Modules/Transfer/Services/TransferCompletionService.php`
- `app/Modules/Transfer/Services/ContractService.php`
- `app/Modules/Transfer/Services/DispositionService.php`
- `app/Http/Actions/NegotiateTransfer.php` / `AcceptTransferOffer.php` / `NegotiateLoan.php` / `AcceptLoanOffer.php`
- `app/Http/Actions/SubmitPreContractOffer.php`
- `app/Http/Views/ShowTransferMarket.php` / `ExploreFreeAgents.php`
- `resources/views/transfer-market.blade.php` / `scouting-hub.blade.php`
- `app/Models/FollowedPlayer.php`

### Squad UI
- `resources/views/squad.blade.php` / `squad-planner.blade.php` / `squad-academy.blade.php`
- `app/Http/Views/ShowSquad.php`
- `app/Modules/Squad/Services/SquadPlannerService.php`
- `resources/views/partials/squad/` / `partials/player-detail.blade.php` / `partials/academy-player-detail.blade.php`

### Finances & stadium
- `app/Modules/Finance/Services/BudgetProjectionService.php`
- `app/Modules/Finance/Services/BudgetAllocationService.php`
- `config/finances.php`
- `app/Modules/Stadium/Services/SeasonTicketPricingService.php`
- `app/Http/Actions/SaveSeasonTicketPricing.php` / `PreviewSeasonTicketPricing.php` / `CommitStadiumRebuild.php`
- `app/Models/Stadium.php`
- `app/Http/Views/ShowFinances.php`
- `resources/views/partials/match-summary.blade.php`
- `app/Modules/Match/Services/MatchSummaryPresenter.php`

### Season pipelines & competition structure
- `app/Modules/Season/Services/SeasonClosingPipeline.php`
- `app/Modules/Competition/Promotions/PromotionRelegationExecutor.php`
- `app/Modules/Competition/Promotions/CountryPromotionRelegationPlanner.php`
- `app/Modules/Match/Handlers/{League,KnockoutCup,GroupStageCup,SwissFormat}Handler.php`
- `app/Modules/Competition/Configs/` (per-competition configs)
- `app/Modules/Competition/Contracts/CompetitionConfig.php`
- `app/Modules/Squad/Services/SquadMinimumService.php`
- `app/Modules/Squad/Listeners/EnforceSquadRegistration.php` / `CheckRecoveredPlayers.php`

### Manager profile / dashboard / nav
- `app/Modules/Manager/Services/ManagerProfileService.php`
- `app/Modules/Manager/Services/LeaderboardService.php`
- `app/Modules/Manager/Services/PerformanceHistoryService.php`
- `resources/views/manager-career.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/components/game-header.blade.php`
- `app/Http/Actions/GetAutoLineup.php`

### Cantera / academy
- `app/Modules/Academy/Services/YouthAcademyService.php`
- `resources/views/squad-academy.blade.php`

### Notifications
- `app/Modules/Notification/Services/NotificationService.php`
- `app/Http/Actions/MarkNotificationRead.php`

### Reports
- `app/Modules/Report/Services/SeasonSummaryService.php`
- `app/Modules/Report/Services/CompetitionSummaryService.php`
- `app/Modules/Report/Services/AwardService.php`

### i18n
- `lang/es/<area>.php` + `lang/en/<area>.php` — areas include `app`, `game`, `squad`, `transfers`, `finances`, `messages`, `season`, `cup`, `notifications`, `commentary`

## Reminders enforced by CLAUDE.md

When writing acceptance criteria, surface the relevant constraint:

- **UUID PKs**: any new model needs `$table->uuid('id')->primary()`, not `$table->id()`. Models on UUID-keyed tables use `HasUuids`. Bulk `insert()` paths also need `DEFAULT gen_random_uuid()`.
- **No wall-clock timestamps on game models**: `public $timestamps = false`; rely on `Game.current_date` (forward-looking — represents next unplayed match).
- **`currentFinances` / `currentInvestment`** — lazy load only, never `with()`.
- **Both languages** — every new translation key must exist in `lang/es/` AND `lang/en/`.
- **Alpine + Blade** — use `@js($val)` to pass PHP into `x-data`, never raw `{{ }}`.
- **UI** — mobile-first (`grid-cols-1 md:grid-cols-N`); semantic tokens only (`bg-surface-*`, `text-text-*`); both dark and light themes must look correct.
- **No N+1** — eager load any relationship accessed in a loop.
- **Tests run in CI** — don't run them locally unless asked.

## Final report template

When the issue is created, return this:

```
✅ Created **[Title]** at #NNNN
   https://github.com/pabloroman/virtua-fc/issues/NNNN

   Labels: roadmap, priority:P*, <area>
   Sub-issue link: #<original> ← parent #NNNN

   Next: drag #NNNN into the GitHub Project board manually (no Projects MCP available).
```

If duplicate-detected, dropped, or merged:
```
↪ #<original> is a duplicate of existing roadmap parent #<existing>. Linked as additional sub-issue (or comment-noted if linking failed).
```

If already-fixed:
```
✓ #<original> appears already fixed by <commit> #<PR>. Propose closure (user action — I won't close it).
```

## Don't

- Don't translate the original Spanish issue's body — leave it Spanish forever.
- Don't post a thank-you comment to the reporter on the original. The sub-issue back-reference notifies them automatically.
- Don't invent priority tiers (only P0–P3) or area labels (only the taxonomy above).
- Don't run `git push`, open a PR, or modify any files in the repo.
- Don't skip the user-approval step — even on what looks like a trivial item.
