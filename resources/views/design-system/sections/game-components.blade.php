<section id="game-components" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-2">Game Components</h2>
    <p class="text-sm text-slate-400 mb-8">Complex components that require Eloquent model data to render. Documented here with props, usage patterns, and descriptions — no rendered previews since they depend on live data.</p>

    {{-- Game Header --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Game Header</h3>
        <p class="text-sm text-slate-400 mb-4">The primary navigation header for all game pages. Features a dual layout: desktop top bar with team badge, navigation links, budget display, and notification bell, plus a mobile hamburger menu with a slide-out drawer containing full navigation.</p>

        <div class="bg-surface-700/50 border border-white/5 rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/game-header.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/5">
                    <tr>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Prop</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Type</th>
                        <th class="font-semibold text-slate-300 py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-slate-500">Game</td>
                        <td class="py-2 text-slate-400">The game model instance with team relationship</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">nextMatch</td>
                        <td class="py-2 pr-4 text-slate-500">GameMatch|null</td>
                        <td class="py-2 text-slate-400">Next upcoming match (determines "Continue" button vs "Season End")</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.gameHeaderCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="gameHeaderCode">&lt;x-game-header :game="$game" :next-match="$nextMatch" /&gt;</code></pre>
        </div>
    </div>

    {{-- Fixture Row --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Fixture Row</h3>
        <p class="text-sm text-slate-400 mb-4">Displays a single match fixture with date, competition badge, opponent, and result. Supports visual states for next match (gold highlight), played matches (surface background), and upcoming matches.</p>

        <div class="bg-surface-700/50 border border-white/5 rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/fixture-row.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/5">
                    <tr>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Prop</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Type</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Default</th>
                        <th class="font-semibold text-slate-300 py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">match</td>
                        <td class="py-2 pr-4 text-slate-500">GameMatch</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-500">required</td>
                        <td class="py-2 text-slate-400">The match model with homeTeam/awayTeam relationships</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-slate-500">Game</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-500">required</td>
                        <td class="py-2 text-slate-400">The game model to determine user's team</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">showScore</td>
                        <td class="py-2 pr-4 text-slate-500">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-500">true</td>
                        <td class="py-2 text-slate-400">Whether to show the score or "vs"</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">highlightNext</td>
                        <td class="py-2 pr-4 text-slate-500">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-500">true</td>
                        <td class="py-2 text-slate-400">Highlight if this is the next unplayed match</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.fixtureRowCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="fixtureRowCode">&lt;x-fixture-row :match="$fixture" :game="$game" :show-score="true" :highlight-next="true" /&gt;</code></pre>
        </div>
    </div>

    {{-- Cup Tie Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Cup Tie Card</h3>
        <p class="text-sm text-slate-400 mb-4">Displays a cup match pairing with both teams, scores, and resolution info (aggregate, penalties, extra time). User's team gets a blue highlight. Winner gets green background, loser gets reduced opacity.</p>

        <div class="bg-surface-700/50 border border-white/5 rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/cup-tie-card.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/5">
                    <tr>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Prop</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Type</th>
                        <th class="font-semibold text-slate-300 py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tie</td>
                        <td class="py-2 pr-4 text-slate-500">CupTie</td>
                        <td class="py-2 text-slate-400">The cup tie model with team relationships</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">playerTeamId</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 text-slate-400">The user's team ID for highlighting</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.cupTieCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="cupTieCode">&lt;x-cup-tie-card :tie="$tie" :player-team-id="$game-&gt;team_id" /&gt;</code></pre>
        </div>
    </div>

    {{-- Budget Allocation --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Budget Allocation</h3>
        <p class="text-sm text-slate-400 mb-4">Interactive Alpine.js component for allocating season budget across 4 infrastructure areas using tier-based sliders (0-4). Calculates transfer budget as the remainder. Shows real-time cost calculations with dynamic color feedback per tier level.</p>

        <div class="bg-surface-700/50 border border-white/5 rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/budget-allocation.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/5">
                    <tr>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Prop</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Type</th>
                        <th class="font-semibold text-slate-300 py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-slate-500">Game</td>
                        <td class="py-2 text-slate-400">The game model</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">availableSurplus</td>
                        <td class="py-2 pr-4 text-slate-500">int</td>
                        <td class="py-2 text-slate-400">Total budget available to allocate</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tiers</td>
                        <td class="py-2 pr-4 text-slate-500">array</td>
                        <td class="py-2 text-slate-400">Current tier values for each area</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tierThresholds</td>
                        <td class="py-2 pr-4 text-slate-500">array</td>
                        <td class="py-2 text-slate-400">Cost thresholds for each tier level per area</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">locked</td>
                        <td class="py-2 pr-4 text-slate-500">bool</td>
                        <td class="py-2 text-slate-400">Whether budget is locked (read-only mode)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.budgetCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="budgetCode">&lt;x-budget-allocation
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
        <h3 class="text-lg font-semibold text-white mb-2">Contract Banner</h3>
        <p class="text-sm text-slate-400 mb-4">Expandable banner on the squad page showing contract-related alerts: pre-contract offers (amber), agreed pre-contracts (red), expiring contracts (slate), and pending renewals (green). Uses Alpine.js for expand/collapse.</p>

        <div class="bg-surface-700/50 border border-white/5 rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/squad.blade.php <span class="text-slate-500">(inline)</span></code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/5">
                    <tr>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Prop</th>
                        <th class="font-semibold text-slate-300 py-2 pr-4">Type</th>
                        <th class="font-semibold text-slate-300 py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-slate-500">Game</td>
                        <td class="py-2 text-slate-400">The game model</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">preContractOffers</td>
                        <td class="py-2 pr-4 text-slate-500">Collection</td>
                        <td class="py-2 text-slate-400">Players being targeted by other clubs</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">agreedPreContracts</td>
                        <td class="py-2 pr-4 text-slate-500">Collection</td>
                        <td class="py-2 text-slate-400">Players who agreed to leave on free</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">pendingRenewals</td>
                        <td class="py-2 pr-4 text-slate-500">Collection</td>
                        <td class="py-2 text-slate-400">Players with agreed renewal offers</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">renewalEligiblePlayers</td>
                        <td class="py-2 pr-4 text-slate-500">Collection</td>
                        <td class="py-2 text-slate-400">Players eligible for contract renewal</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">renewalDemands</td>
                        <td class="py-2 pr-4 text-slate-500">array</td>
                        <td class="py-2 text-slate-400">Wage demands for each eligible player</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.contractCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="contractCode">&lt;x-contract-banner
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
