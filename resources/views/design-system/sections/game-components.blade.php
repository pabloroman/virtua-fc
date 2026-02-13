<section id="game-components" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Game Components</h2>
    <p class="text-slate-500 mb-8">Complex components that require Eloquent model data to render. Documented here with props, usage patterns, and descriptions.</p>

    {{-- Game Header --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Game Header</h3>
        <p class="text-sm text-slate-500 mb-4">The primary navigation header for all game pages. Features a dual layout: desktop top bar with team info and nav links, plus a mobile hamburger menu with a slide-out drawer containing full navigation.</p>

        <div class="border border-slate-200 rounded-lg overflow-hidden mb-4">
            <div class="px-5 py-3 bg-slate-50 border-b">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Component</div>
            </div>
            <div class="px-5 py-4">
                <code class="text-xs font-mono text-sky-600">resources/views/components/game-header.blade.php</code>
            </div>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-slate-200">
                    <tr>
                        <th class="font-semibold py-2 pr-4">Prop</th>
                        <th class="font-semibold py-2 pr-4">Type</th>
                        <th class="font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">game</td>
                        <td class="py-2 pr-4 text-slate-500">Game</td>
                        <td class="py-2 text-slate-500">The game model instance with team relationship</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">nextMatch</td>
                        <td class="py-2 pr-4 text-slate-500">GameMatch|null</td>
                        <td class="py-2 text-slate-500">Next upcoming match (determines "Continue" button vs "Season End")</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-game-header :game="$game" :next-match="$nextMatch" /&gt;</code></pre>
        </div>
    </div>

    {{-- Fixture Row --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Fixture Row</h3>
        <p class="text-sm text-slate-500 mb-4">Displays a single match fixture with date, competition badge, opponent, and result. Supports visual states for next match (yellow highlight), played matches (slate background), and upcoming matches (white).</p>

        <div class="border border-slate-200 rounded-lg overflow-hidden mb-4">
            <div class="px-5 py-3 bg-slate-50 border-b">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Component</div>
            </div>
            <div class="px-5 py-4">
                <code class="text-xs font-mono text-sky-600">resources/views/components/fixture-row.blade.php</code>
            </div>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-slate-200">
                    <tr>
                        <th class="font-semibold py-2 pr-4">Prop</th>
                        <th class="font-semibold py-2 pr-4">Type</th>
                        <th class="font-semibold py-2 pr-4">Default</th>
                        <th class="font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">match</td>
                        <td class="py-2 pr-4 text-slate-500">GameMatch</td>
                        <td class="py-2 pr-4 font-mono text-xs">required</td>
                        <td class="py-2 text-slate-500">The match model with homeTeam/awayTeam relationships</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">game</td>
                        <td class="py-2 pr-4 text-slate-500">Game</td>
                        <td class="py-2 pr-4 font-mono text-xs">required</td>
                        <td class="py-2 text-slate-500">The game model to determine user's team</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">showScore</td>
                        <td class="py-2 pr-4 text-slate-500">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs">true</td>
                        <td class="py-2 text-slate-500">Whether to show the score or "vs"</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">highlightNext</td>
                        <td class="py-2 pr-4 text-slate-500">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs">true</td>
                        <td class="py-2 text-slate-500">Highlight if this is the next unplayed match</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-fixture-row :match="$fixture" :game="$game" :show-score="true" :highlight-next="true" /&gt;</code></pre>
        </div>
    </div>

    {{-- Cup Tie Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Cup Tie Card</h3>
        <p class="text-sm text-slate-500 mb-4">Displays a cup match pairing with both teams, scores, and resolution info (aggregate, penalties, extra time). User's team gets a sky highlight. Winner gets green background, loser gets reduced opacity.</p>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-slate-200">
                    <tr>
                        <th class="font-semibold py-2 pr-4">Prop</th>
                        <th class="font-semibold py-2 pr-4">Type</th>
                        <th class="font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">tie</td>
                        <td class="py-2 pr-4 text-slate-500">CupTie</td>
                        <td class="py-2 text-slate-500">The cup tie model with team relationships</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">playerTeamId</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 text-slate-500">The user's team ID for highlighting</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-cup-tie-card :tie="$tie" :player-team-id="$game-&gt;team_id" /&gt;</code></pre>
        </div>
    </div>

    {{-- Contract Banner --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Contract Banner</h3>
        <p class="text-sm text-slate-500 mb-4">Expandable banner on the squad page showing contract-related alerts: pre-contract offers (amber), agreed pre-contracts (red), expiring contracts (slate), and pending renewals (green). Uses Alpine.js for expand/collapse.</p>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-slate-200">
                    <tr>
                        <th class="font-semibold py-2 pr-4">Prop</th>
                        <th class="font-semibold py-2 pr-4">Type</th>
                        <th class="font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">game</td>
                        <td class="py-2 pr-4 text-slate-500">Game</td>
                        <td class="py-2 text-slate-500">The game model</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">preContractOffers</td>
                        <td class="py-2 pr-4 text-slate-500">Collection</td>
                        <td class="py-2 text-slate-500">Players being targeted by other clubs</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">agreedPreContracts</td>
                        <td class="py-2 pr-4 text-slate-500">Collection</td>
                        <td class="py-2 text-slate-500">Players who agreed to leave on free</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">pendingRenewals</td>
                        <td class="py-2 pr-4 text-slate-500">Collection</td>
                        <td class="py-2 text-slate-500">Players with agreed renewal offers</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">renewalEligiblePlayers</td>
                        <td class="py-2 pr-4 text-slate-500">Collection</td>
                        <td class="py-2 text-slate-500">Players eligible for contract renewal</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">renewalDemands</td>
                        <td class="py-2 pr-4 text-slate-500">array</td>
                        <td class="py-2 text-slate-500">Wage demands for each eligible player</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-contract-banner
    :game="$game"
    :pre-contract-offers="$preContractOffers"
    :agreed-pre-contracts="$agreedPreContracts"
    :pending-renewals="$pendingRenewals"
    :renewal-eligible-players="$renewalEligiblePlayers"
    :renewal-demands="$renewalDemands"
/&gt;</code></pre>
        </div>
    </div>

    {{-- Budget Allocation --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Budget Allocation</h3>
        <p class="text-sm text-slate-500 mb-4">Interactive Alpine.js component for allocating season budget across 4 infrastructure areas using tier-based sliders (0-4). Calculates transfer budget as the remainder. Shows real-time cost calculations with dynamic color feedback per tier level.</p>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-slate-200">
                    <tr>
                        <th class="font-semibold py-2 pr-4">Prop</th>
                        <th class="font-semibold py-2 pr-4">Type</th>
                        <th class="font-semibold py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">game</td>
                        <td class="py-2 pr-4 text-slate-500">Game</td>
                        <td class="py-2 text-slate-500">The game model</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">availableSurplus</td>
                        <td class="py-2 pr-4 text-slate-500">int</td>
                        <td class="py-2 text-slate-500">Total budget available to allocate</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">tiers</td>
                        <td class="py-2 pr-4 text-slate-500">array</td>
                        <td class="py-2 text-slate-500">Current tier values for each area</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">tierThresholds</td>
                        <td class="py-2 pr-4 text-slate-500">array</td>
                        <td class="py-2 text-slate-500">Cost thresholds for each tier level per area</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">locked</td>
                        <td class="py-2 pr-4 text-slate-500">bool</td>
                        <td class="py-2 text-slate-500">Whether budget is locked (read-only mode)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-budget-allocation
    :game="$game"
    :available-surplus="$availableSurplus"
    :tiers="$tiers"
    :tier-thresholds="$tierThresholds"
    :locked="$locked"
/&gt;</code></pre>
        </div>
    </div>
</section>
