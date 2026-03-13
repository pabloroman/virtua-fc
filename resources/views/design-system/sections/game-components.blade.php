<section id="game-components" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Game Components</h2>
    <p class="text-sm text-text-secondary mb-8">Complex components that require Eloquent model data to render. Documented here with props, usage patterns, and descriptions — no rendered previews since they depend on live data.</p>

    {{-- Player Avatar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Player Avatar</h3>
        <p class="text-sm text-text-secondary mb-4">Position-colored gradient circle with player initials. Optional position sub-badge. Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-player-avatar</code> component. Colors: GK = amber, DEF = blue, MID = green, FWD = rose.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            {{-- Size variants --}}
            <div class="mb-6">
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">Sizes</div>
                <div class="flex items-end gap-6">
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" position-abbrev="GK" size="sm" />
                        <div class="text-[10px] text-text-muted mt-2">sm</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" position-abbrev="GK" size="md" />
                        <div class="text-[10px] text-text-muted mt-2">md</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" position-abbrev="GK" size="lg" />
                        <div class="text-[10px] text-text-muted mt-2">lg</div>
                    </div>
                </div>
            </div>

            {{-- Position colors --}}
            <div>
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">Position Groups</div>
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" position-abbrev="GK" />
                        <div class="text-[10px] text-text-muted mt-2">GK</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Pablo García" position-group="Defender" position-abbrev="CB" />
                        <div class="text-[10px] text-text-muted mt-2">DEF</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Luis Fernández" position-group="Midfielder" position-abbrev="CM" />
                        <div class="text-[10px] text-text-muted mt-2">MID</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Carlos Torres" position-group="Forward" position-abbrev="CF" />
                        <div class="text-[10px] text-text-muted mt-2">FWD</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Ana López" position-group="Defender" />
                        <div class="text-[10px] text-text-muted mt-2">No badge</div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.avatarCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="avatarCode">&lt;!-- With position sub-badge --&gt;
&lt;x-player-avatar :name="$player->name" :position-group="$group" :position-abbrev="$abbrev" /&gt;

&lt;!-- Without sub-badge --&gt;
&lt;x-player-avatar :name="$player->name" position-group="Defender" /&gt;

&lt;!-- Small size (for table rows) --&gt;
&lt;x-player-avatar :name="$player->name" :position-group="$group" size="sm" /&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto mt-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="text-[10px] text-text-muted uppercase tracking-wider font-semibold py-2 pr-4">Prop</th>
                        <th class="text-[10px] text-text-muted uppercase tracking-wider font-semibold py-2 pr-4">Type</th>
                        <th class="text-[10px] text-text-muted uppercase tracking-wider font-semibold py-2 pr-4">Default</th>
                        <th class="text-[10px] text-text-muted uppercase tracking-wider font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">name</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Player name (initials computed automatically)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">positionGroup</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Goalkeeper | Defender | Midfielder | Forward</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">positionAbbrev</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">null</td>
                        <td class="py-2 text-text-secondary">Position abbreviation for sub-badge (GK, CB, etc.)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">size</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">'md'</td>
                        <td class="py-2 text-text-secondary">sm | md | lg</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Summary Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Summary Card</h3>
        <p class="text-sm text-text-secondary mb-4">Compact stat card with micro-label and bold value. Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-summary-card</code> component. Designed for horizontal scroll rows.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex gap-2.5 overflow-x-auto scrollbar-hide pb-1">
                <x-summary-card label="SQUAD" value="24" />
                <x-summary-card label="AVG AGE" value="26.3" />
                <x-summary-card label="FITNESS" value="87%" value-class="text-accent-green" />
                <x-summary-card label="MORALE" value="78" />
                <x-summary-card label="BUDGET" value="€12.5M" class="min-w-[130px]" />
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.summaryCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="summaryCode">&lt;!-- Basic usage --&gt;
&lt;x-summary-card label="SQUAD" value="24" /&gt;

&lt;!-- With colored value --&gt;
&lt;x-summary-card label="FITNESS" value="87%" value-class="text-accent-green" /&gt;

&lt;!-- With custom width --&gt;
&lt;x-summary-card label="BUDGET" value="€12.5M" class="min-w-[130px]" /&gt;

&lt;!-- With slot content --&gt;
&lt;x-summary-card label="STATUS" value="Active"&gt;
    &lt;span class="text-xs text-text-muted"&gt;Extra info&lt;/span&gt;
&lt;/x-summary-card&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto mt-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="text-[10px] text-text-muted uppercase tracking-wider font-semibold py-2 pr-4">Prop</th>
                        <th class="text-[10px] text-text-muted uppercase tracking-wider font-semibold py-2 pr-4">Type</th>
                        <th class="text-[10px] text-text-muted uppercase tracking-wider font-semibold py-2 pr-4">Default</th>
                        <th class="text-[10px] text-text-muted uppercase tracking-wider font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">label</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Micro-label text displayed above the value</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">value</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Main bold value</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">valueClass</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">'text-white'</td>
                        <td class="py-2 text-text-secondary">CSS class for value color (e.g. text-accent-green)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Game Header --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Game Header</h3>
        <p class="text-sm text-text-secondary mb-4">The primary navigation header for all game pages. Features a dual layout: desktop top bar with team badge, navigation links, budget display, and notification bell, plus a mobile hamburger menu with a slide-out drawer containing full navigation.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/game-header.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-text-muted">Game</td>
                        <td class="py-2 text-text-secondary">The game model instance with team relationship</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">nextMatch</td>
                        <td class="py-2 pr-4 text-text-muted">GameMatch|null</td>
                        <td class="py-2 text-text-secondary">Next upcoming match (determines "Continue" button vs "Season End")</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.gameHeaderCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="gameHeaderCode">&lt;x-game-header :game="$game" :next-match="$nextMatch" /&gt;</code></pre>
        </div>
    </div>

    {{-- Fixture Row --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Fixture Row</h3>
        <p class="text-sm text-text-secondary mb-4">Displays a single match fixture with date, competition badge, opponent, and result. Supports visual states for next match (gold highlight), played matches (surface background), and upcoming matches.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/fixture-row.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Default</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">match</td>
                        <td class="py-2 pr-4 text-text-muted">GameMatch</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">required</td>
                        <td class="py-2 text-text-secondary">The match model with homeTeam/awayTeam relationships</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-text-muted">Game</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">required</td>
                        <td class="py-2 text-text-secondary">The game model to determine user's team</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">showScore</td>
                        <td class="py-2 pr-4 text-text-muted">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">true</td>
                        <td class="py-2 text-text-secondary">Whether to show the score or "vs"</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">highlightNext</td>
                        <td class="py-2 pr-4 text-text-muted">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">true</td>
                        <td class="py-2 text-text-secondary">Highlight if this is the next unplayed match</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.fixtureRowCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="fixtureRowCode">&lt;x-fixture-row :match="$fixture" :game="$game" :show-score="true" :highlight-next="true" /&gt;</code></pre>
        </div>
    </div>

    {{-- Cup Tie Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Cup Tie Card</h3>
        <p class="text-sm text-text-secondary mb-4">Displays a cup match pairing with both teams, scores, and resolution info (aggregate, penalties, extra time). User's team gets a blue highlight. Winner gets green background, loser gets reduced opacity.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/cup-tie-card.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tie</td>
                        <td class="py-2 pr-4 text-text-muted">CupTie</td>
                        <td class="py-2 text-text-secondary">The cup tie model with team relationships</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">playerTeamId</td>
                        <td class="py-2 pr-4 text-text-muted">string</td>
                        <td class="py-2 text-text-secondary">The user's team ID for highlighting</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.cupTieCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="cupTieCode">&lt;x-cup-tie-card :tie="$tie" :player-team-id="$game-&gt;team_id" /&gt;</code></pre>
        </div>
    </div>

    {{-- Budget Allocation --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Budget Allocation</h3>
        <p class="text-sm text-text-secondary mb-4">Interactive Alpine.js component for allocating season budget across 4 infrastructure areas using tier-based sliders (0-4). Calculates transfer budget as the remainder. Shows real-time cost calculations with dynamic color feedback per tier level.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/budget-allocation.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-text-muted">Game</td>
                        <td class="py-2 text-text-secondary">The game model</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">availableSurplus</td>
                        <td class="py-2 pr-4 text-text-muted">int</td>
                        <td class="py-2 text-text-secondary">Total budget available to allocate</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tiers</td>
                        <td class="py-2 pr-4 text-text-muted">array</td>
                        <td class="py-2 text-text-secondary">Current tier values for each area</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tierThresholds</td>
                        <td class="py-2 pr-4 text-text-muted">array</td>
                        <td class="py-2 text-text-secondary">Cost thresholds for each tier level per area</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">locked</td>
                        <td class="py-2 pr-4 text-text-muted">bool</td>
                        <td class="py-2 text-text-secondary">Whether budget is locked (read-only mode)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.budgetCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="budgetCode">&lt;x-budget-allocation
    :game="$game"
    :available-surplus="$availableSurplus"
    :tiers="$tiers"
    :tier-thresholds="$tierThresholds"
    :locked="$locked"
/&gt;</code></pre>
        </div>
    </div>

    {{-- Contract Banner --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Contract Banner</h3>
        <p class="text-sm text-text-secondary mb-4">Expandable banner on the squad page showing contract-related alerts: pre-contract offers (amber), agreed pre-contracts (red), expiring contracts (slate), and pending renewals (green). Uses Alpine.js for expand/collapse.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/squad.blade.php <span class="text-text-muted">(inline)</span></code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-text-muted">Game</td>
                        <td class="py-2 text-text-secondary">The game model</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">preContractOffers</td>
                        <td class="py-2 pr-4 text-text-muted">Collection</td>
                        <td class="py-2 text-text-secondary">Players being targeted by other clubs</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">agreedPreContracts</td>
                        <td class="py-2 pr-4 text-text-muted">Collection</td>
                        <td class="py-2 text-text-secondary">Players who agreed to leave on free</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">pendingRenewals</td>
                        <td class="py-2 pr-4 text-text-muted">Collection</td>
                        <td class="py-2 text-text-secondary">Players with agreed renewal offers</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">renewalEligiblePlayers</td>
                        <td class="py-2 pr-4 text-text-muted">Collection</td>
                        <td class="py-2 text-text-secondary">Players eligible for contract renewal</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">renewalDemands</td>
                        <td class="py-2 pr-4 text-text-muted">array</td>
                        <td class="py-2 text-text-secondary">Wage demands for each eligible player</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.contractCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="contractCode">&lt;x-contract-banner
    :game="$game"
    :pre-contract-offers="$preContractOffers"
    :agreed-pre-contracts="$agreedPreContracts"
    :pending-renewals="$pendingRenewals"
    :renewal-eligible-players="$renewalEligiblePlayers"
    :renewal-demands="$renewalDemands"
/&gt;</code></pre>
        </div>
    </div>
</section>
