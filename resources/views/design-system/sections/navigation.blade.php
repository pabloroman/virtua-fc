<section id="navigation" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Navigation</h2>
    <p class="text-sm text-text-secondary mb-8">Navigation components for top bars, tabs, and menus. <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">accent-blue</code> is the active indicator color throughout. All navigation lives on dark surfaces.</p>

    {{-- Desktop Nav Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Desktop Nav Bar</h3>
        <p class="text-sm text-text-secondary mb-4">Top-level navigation using the <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">.nav-item</code> class pattern. Active items show a blue underline via <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">.active::after</code> pseudo-element and white text. Inactive items are <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-text-muted</code> with hover to <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-text-body</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-900/95 backdrop-blur-md border-b border-border-default rounded-lg px-4 py-3">
                <nav class="flex items-center gap-1">
                    <a href="#" class="nav-item active px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-primary">Dashboard</a>
                    <a href="#" class="nav-item px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-muted hover:text-text-body">Squad</a>
                    <a href="#" class="nav-item px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-muted hover:text-text-body">Starting XI</a>
                    <a href="#" class="nav-item px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-muted hover:text-text-body">Finances</a>
                    <a href="#" class="nav-item px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-muted hover:text-text-body">Transfers</a>
                    <a href="#" class="nav-item px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-muted hover:text-text-body">Calendar</a>
                </nav>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;nav class="flex items-center gap-1"&gt;
    &lt;a href="#" class="nav-item active px-3 py-2 text-xs font-medium uppercase tracking-wider text-white"&gt;
        Dashboard
    &lt;/a&gt;
    &lt;a href="#" class="nav-item px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-muted hover:text-text-body"&gt;
        Squad
    &lt;/a&gt;
&lt;/nav&gt;

&lt;!-- The .nav-item.active class adds a blue underline via CSS ::after pseudo-element --&gt;</code></pre>
        </div>
    </div>

    {{-- Section Nav (Tabs) --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Section Nav (Tabs)</h3>
        <p class="text-sm text-text-secondary mb-4">Horizontal scrollable tab navigation for sub-sections within a page. Active tab has an <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">accent-blue</code> bottom border and white text. Supports optional badge counts in <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">accent-red</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <x-section-nav :items="[
                ['href' => '#', 'label' => 'Squad', 'active' => true],
                ['href' => '#', 'label' => 'Development', 'active' => false],
                ['href' => '#', 'label' => 'Stats', 'active' => false],
                ['href' => '#', 'label' => 'Academy', 'active' => false, 'badge' => 3],
            ]" />
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-section-nav :items="[
    ['href' =&gt; route('squad'), 'label' =&gt; 'Squad', 'active' =&gt; true],
    ['href' =&gt; route('stats'), 'label' =&gt; 'Stats', 'active' =&gt; false],
    ['href' =&gt; route('academy'), 'label' =&gt; 'Academy', 'active' =&gt; false, 'badge' =&gt; 3],
]" /&gt;</code></pre>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">items</td>
                        <td class="py-2 pr-4">array</td>
                        <td class="py-2">Array of {href, label, active, badge?}</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">$slot</td>
                        <td class="py-2 pr-4">blade</td>
                        <td class="py-2">Optional right-aligned content (e.g. action button)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Bottom Tab Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Mobile Bottom Tab Bar</h3>
        <p class="text-sm text-text-secondary mb-4">On mobile (<code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">lg:hidden</code>), navigation uses a fixed bottom tab bar with 5 tabs: Dashboard, Squad, Starting XI, Calendar, and More. The "More" tab opens a slide-up panel with secondary items (Finances, Transfers, Competitions). Implemented in <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bottom-tab-bar.blade.php</code>, included via <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">game-header.blade.php</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            {{-- Simulated mobile layout --}}
            <div class="max-w-sm mx-auto">
                {{-- Simulated header --}}
                <div class="bg-surface-900/95 backdrop-blur-md border border-border-default rounded-t-lg px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-surface-600 rounded-lg shrink-0"></div>
                        <div>
                            <span class="font-heading font-semibold text-sm text-text-primary uppercase tracking-wide">Real Madrid</span>
                            <p class="text-[10px] text-text-muted uppercase tracking-widest">Season 2025/26</p>
                        </div>
                    </div>
                    <button class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-accent-blue text-white">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </button>
                </div>

                {{-- Simulated content --}}
                <div class="bg-surface-800 border-x border-border-default h-32 flex items-center justify-center text-text-muted text-xs">Page content</div>

                {{-- Simulated bottom tab bar --}}
                <div class="bg-surface-900/95 backdrop-blur-md border border-border-default rounded-b-lg px-2 py-2 flex items-center justify-around">
                    <div class="flex flex-col items-center gap-0.5 text-accent-blue">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                        <span class="text-[9px] font-medium uppercase tracking-wider">Home</span>
                    </div>
                    <div class="flex flex-col items-center gap-0.5 text-text-muted">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                        <span class="text-[9px] font-medium uppercase tracking-wider">Squad</span>
                    </div>
                    <div class="flex flex-col items-center gap-0.5 text-text-muted">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>
                        <span class="text-[9px] font-medium uppercase tracking-wider">XI</span>
                    </div>
                    <div class="flex flex-col items-center gap-0.5 text-text-muted">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                        <span class="text-[9px] font-medium uppercase tracking-wider">Cal</span>
                    </div>
                    <div class="flex flex-col items-center gap-0.5 text-text-muted">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
                        <span class="text-[9px] font-medium uppercase tracking-wider">More</span>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;!-- Bottom tab bar (mobile only, included via game-header) --&gt;
&lt;x-bottom-tab-bar :game="$game" :next-match="$nextMatch" :team-competitions="$teamCompetitions" /&gt;

&lt;!-- Fixed bottom, backdrop blur, 5 tabs with icons + labels --&gt;
&lt;!-- Active tab: text-accent-blue, Inactive: text-text-muted --&gt;
&lt;!-- "More" tab opens slide-up panel with secondary nav items --&gt;
&lt;!-- See bottom-tab-bar.blade.php for the full implementation --&gt;</code></pre>
        </div>
    </div>

    {{-- Dropdown --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Dropdown</h3>
        <p class="text-sm text-text-secondary mb-4">Alpine.js powered dropdown menu with click-outside close and smooth scale transitions. Panel uses <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-surface-800 border border-border-strong</code>. Links highlight with <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">hover:bg-surface-700</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <x-dropdown align="left" width="48">
                <x-slot name="trigger">
                    <button class="inline-flex items-center gap-1.5 px-3 py-2 bg-surface-700 border border-border-strong rounded-lg text-sm text-text-body hover:text-text-primary hover:bg-surface-600 transition-colors">
                        Options
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link href="#">Profile</x-dropdown-link>
                    <x-dropdown-link href="#">Settings</x-dropdown-link>
                    <x-dropdown-link href="#">Log Out</x-dropdown-link>
                </x-slot>
            </x-dropdown>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-dropdown align="left" width="48"&gt;
    &lt;x-slot name="trigger"&gt;
        &lt;button class="inline-flex items-center gap-1.5 px-3 py-2 bg-surface-700 border border-border-strong rounded-lg text-sm text-text-body"&gt;
            Options
            &lt;!-- chevron SVG --&gt;
        &lt;/button&gt;
    &lt;/x-slot&gt;
    &lt;x-slot name="content"&gt;
        &lt;x-dropdown-link href="#"&gt;Profile&lt;/x-dropdown-link&gt;
        &lt;x-dropdown-link href="#"&gt;Settings&lt;/x-dropdown-link&gt;
    &lt;/x-slot&gt;
&lt;/x-dropdown&gt;</code></pre>
        </div>
    </div>

    {{-- Context Menu (Three-Dot) --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Context Menu (Three-Dot)</h3>
        <p class="text-sm text-text-secondary mb-4">Inline action menu used in table rows and card actions. Dark dropdown panel with <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-surface-700 border border-border-default</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            {{-- Simulated table row context --}}
            <div class="flex items-center justify-between bg-surface-800 rounded-lg px-4 py-3 max-w-md">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-surface-600 rounded-full"></div>
                    <div>
                        <span class="text-sm font-medium text-text-primary">Jude Bellingham</span>
                        <p class="text-[10px] text-text-muted">MC &middot; 22 years</p>
                    </div>
                </div>
                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                    <button @click="open = !open" class="p-2 text-text-secondary hover:text-text-primary rounded-lg hover:bg-surface-600 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                    </button>
                    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         class="absolute right-0 mt-2 w-48 bg-surface-700 border border-border-default rounded-lg shadow-xl z-50 py-1" style="display: none;">
                        <button class="w-full text-left px-4 py-2 text-sm text-accent-blue hover:bg-surface-600 transition-colors">List for sale</button>
                        <button class="w-full text-left px-4 py-2 text-sm text-accent-gold hover:bg-surface-600 transition-colors">Loan out</button>
                        <button class="w-full text-left px-4 py-2 text-sm text-accent-red hover:bg-surface-600 transition-colors">Release player</button>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div x-data="{ open: false }" @click.outside="open = false" class="relative"&gt;
    &lt;button @click="open = !open" class="p-2 text-text-secondary hover:text-white rounded-lg hover:bg-surface-600 min-h-[44px] min-w-[44px]"&gt;
        &lt;!-- three-dot SVG --&gt;
    &lt;/button&gt;
    &lt;div x-show="open" x-transition
         class="absolute right-0 mt-2 w-48 bg-surface-700 border border-border-default rounded-lg shadow-xl z-50 py-1"&gt;
        &lt;button class="w-full text-left px-4 py-2 text-sm text-accent-blue hover:bg-surface-600"&gt;List for sale&lt;/button&gt;
        &lt;button class="w-full text-left px-4 py-2 text-sm text-accent-gold hover:bg-surface-600"&gt;Loan out&lt;/button&gt;
        &lt;button class="w-full text-left px-4 py-2 text-sm text-accent-red hover:bg-surface-600"&gt;Release&lt;/button&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Position Filter Pills --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Position Filter Pills</h3>
        <p class="text-sm text-text-secondary mb-4">Pill-style toggle buttons for filtering by position. Inactive pills use <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-surface-700 text-text-secondary</code>, active pills switch to <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-accent-blue text-white</code>. Uses Alpine.js for state management.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div x-data="{ active: 'all' }" class="flex flex-wrap gap-2">
                <button @click="active = 'all'" :class="active === 'all' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">All</button>
                <button @click="active = 'gk'" :class="active === 'gk' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">GK</button>
                <button @click="active = 'def'" :class="active === 'def' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">DEF</button>
                <button @click="active = 'mid'" :class="active === 'mid' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">MID</button>
                <button @click="active = 'fwd'" :class="active === 'fwd' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">FWD</button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div x-data="{ active: 'all' }" class="flex flex-wrap gap-2"&gt;
    &lt;button @click="active = 'all'"
            :class="active === 'all' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-slate-200'"
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"&gt;
        All
    &lt;/button&gt;
    &lt;button @click="active = 'gk'"
            :class="active === 'gk' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-slate-200'"
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"&gt;
        GK
    &lt;/button&gt;
    &lt;!-- ... more positions --&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Competition Dropdown (Nav Bar) --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Competition Dropdown (Nav Bar)</h3>
        <p class="text-sm text-text-secondary mb-4">Inline dropdown used in the desktop nav bar for competition selection. Combines the <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">.nav-item</code> styling with an Alpine.js dropdown. Panel uses <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-surface-800 border border-border-strong</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-900/95 backdrop-blur-md border-b border-border-default rounded-lg px-4 py-3">
                <nav class="flex items-center gap-1">
                    <a href="#" class="nav-item px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-muted hover:text-text-body">Dashboard</a>
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="nav-item active px-3 py-2 text-xs font-medium uppercase tracking-wider flex items-center gap-1 text-text-primary">
                            Competitions
                            <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             class="absolute left-0 z-50 mt-2 w-48 rounded-lg shadow-xl bg-surface-800 border border-border-strong" style="display: none;">
                            <div class="py-1">
                                <a href="#" class="block px-4 py-2 text-sm text-text-primary bg-surface-700 font-semibold">La Liga</a>
                                <a href="#" class="block px-4 py-2 text-sm text-text-body hover:bg-surface-700 hover:text-text-primary">Copa del Rey</a>
                                <a href="#" class="block px-4 py-2 text-sm text-text-body hover:bg-surface-700 hover:text-text-primary">Champions League</a>
                            </div>
                        </div>
                    </div>
                    <a href="#" class="nav-item px-3 py-2 text-xs font-medium uppercase tracking-wider text-text-muted hover:text-text-body">Calendar</a>
                </nav>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="relative" x-data="{ open: false }" @click.outside="open = false"&gt;
    &lt;button @click="open = !open"
            class="nav-item active px-3 py-2 text-xs font-medium uppercase tracking-wider flex items-center gap-1 text-white"&gt;
        Competitions
        &lt;svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }"&gt;...&lt;/svg&gt;
    &lt;/button&gt;
    &lt;div x-show="open" x-transition
         class="absolute left-0 z-50 mt-2 w-48 rounded-lg shadow-xl bg-surface-800 border border-border-strong"&gt;
        &lt;div class="py-1"&gt;
            &lt;a href="#" class="block px-4 py-2 text-sm text-text-body hover:bg-surface-700 hover:text-white"&gt;
                La Liga
            &lt;/a&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
