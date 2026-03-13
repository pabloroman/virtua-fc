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

    {{-- Mobile Hamburger + Drawer --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Mobile Hamburger + Drawer</h3>
        <p class="text-sm text-text-secondary mb-4">On mobile (<code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">lg:hidden</code>), the desktop nav collapses into a hamburger button that opens a slide-out drawer from the left. The drawer is implemented in <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">game-header.blade.php</code> using Alpine.js.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            {{-- Simulated mobile header --}}
            <div class="max-w-sm">
                <div class="bg-surface-900/95 backdrop-blur-md border border-border-default rounded-lg px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        {{-- Hamburger icon --}}
                        <button class="text-text-secondary hover:text-text-primary min-h-[44px] min-w-[44px] flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                            </svg>
                        </button>
                        <div>
                            <span class="font-heading font-semibold text-sm text-text-primary uppercase tracking-wide">Real Madrid</span>
                            <p class="text-[10px] text-text-muted uppercase tracking-widest">Season 2025/26</p>
                        </div>
                    </div>
                    <button class="inline-flex items-center px-3 py-1.5 bg-accent-blue hover:bg-blue-600 rounded-lg font-semibold text-xs text-white uppercase tracking-wider transition-all">Continue</button>
                </div>
            </div>

            {{-- Simulated drawer panel --}}
            <div class="max-w-[288px] mt-4">
                <div class="bg-surface-800 border border-border-default rounded-lg shadow-xl overflow-hidden">
                    <div class="flex items-center justify-between p-4 border-b border-border-strong">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-surface-600 rounded-lg shrink-0"></div>
                            <div>
                                <h3 class="font-heading font-semibold text-sm text-text-primary uppercase tracking-wide">Real Madrid</h3>
                                <p class="text-[10px] text-text-muted uppercase tracking-widest">Season 2025/26</p>
                            </div>
                        </div>
                        <button class="p-2 text-text-muted hover:text-text-primary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <nav class="py-2">
                        <a href="#" class="block w-full ps-3 pe-4 py-2 border-l-4 border-accent-blue text-start text-base font-medium text-text-primary bg-accent-blue/10">Dashboard</a>
                        <a href="#" class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700">Squad</a>
                        <a href="#" class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700">Starting XI</a>
                        <a href="#" class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700">Finances</a>
                        <a href="#" class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700">Transfers</a>
                    </nav>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;!-- Hamburger button (visible on mobile only) --&gt;
&lt;button @click="mobileMenuOpen = true" class="lg:hidden text-text-secondary hover:text-white min-h-[44px] min-w-[44px]"&gt;
    &lt;!-- hamburger SVG --&gt;
&lt;/button&gt;

&lt;!-- Drawer uses x-show="mobileMenuOpen" with translate-x transitions --&gt;
&lt;!-- Panel: bg-surface-800 border-r border-border-default w-72 --&gt;
&lt;!-- Active links use &lt;x-responsive-nav-link :active="true"&gt; --&gt;
&lt;!-- See game-header.blade.php for the full implementation --&gt;</code></pre>
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
