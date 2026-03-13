<section id="cards" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Cards & Containers</h2>
    <p class="text-sm text-text-secondary mb-8">Container patterns for grouping content. Cards use opacity-layered surfaces and subtle borders to create visual hierarchy on the dark background.</p>

    {{-- Section Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Section Card <code class="text-xs text-accent-blue font-mono">&lt;x-section-card&gt;</code></h3>
        <p class="text-sm text-text-secondary mb-4">The primary card pattern for content sections. Features a header with uppercase tracking-widest title and optional right-aligned badge, separated from the body by a border. Used for budget flow, transaction history, infrastructure, and all major content panels.</p>

        {{-- Props table --}}
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] text-text-muted uppercase tracking-widest border-b border-border-default">
                        <th class="px-3 py-2 font-semibold">Prop</th>
                        <th class="px-3 py-2 font-semibold">Type</th>
                        <th class="px-3 py-2 font-semibold">Default</th>
                        <th class="px-3 py-2 font-semibold">Description</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">title</td>
                        <td class="px-3 py-2">string</td>
                        <td class="px-3 py-2 text-text-muted">null</td>
                        <td class="px-3 py-2">Header title text. When set, renders the header bar with border separator.</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">badge</td>
                        <td class="px-3 py-2">string | slot</td>
                        <td class="px-3 py-2 text-text-muted">null</td>
                        <td class="px-3 py-2">Right-aligned header metadata. Pass as prop for simple text, or as a named slot for rich HTML content.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Example: with title and badge --}}
        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3 space-y-4">
            <x-section-card title="Budget Flow" badge="Season 2025/26">
                <div class="px-5 py-4">
                    <p class="text-sm text-text-secondary">Card content goes here. This is the standard section card pattern used for budget flow, transaction history, infrastructure, and all major content panels.</p>
                </div>
            </x-section-card>

            <x-section-card title="Infrastructure">
                <div class="px-5 py-4">
                    <p class="text-sm text-text-secondary">A section card without a badge — only the title is shown in the header.</p>
                </div>
            </x-section-card>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.cardCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="cardCode">{{-- With title and simple badge --}}
&lt;x-section-card title="Budget Flow" badge="Season 2025/26"&gt;
    &lt;div class="px-5 py-4"&gt;
        &lt;!-- Content --&gt;
    &lt;/div&gt;
&lt;/x-section-card&gt;

{{-- With title only (no badge) --}}
&lt;x-section-card title="Infrastructure"&gt;
    &lt;div class="px-5 py-4"&gt;
        &lt;!-- Content --&gt;
    &lt;/div&gt;
&lt;/x-section-card&gt;

{{-- With rich badge slot --}}
&lt;x-section-card title="Transactions"&gt;
    &lt;x-slot name="badge"&gt;
        &lt;div class="flex items-center gap-4 text-xs"&gt;
            &lt;span class="text-accent-green font-medium"&gt;+€12.5M Income&lt;/span&gt;
            &lt;span class="text-accent-red font-medium"&gt;-€8.2M Expenses&lt;/span&gt;
        &lt;/div&gt;
    &lt;/x-slot&gt;
    &lt;div class="px-5 py-4"&gt;
        &lt;!-- Content --&gt;
    &lt;/div&gt;
&lt;/x-section-card&gt;</code></pre>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Summary Cards</h3>
        <p class="text-sm text-text-secondary mb-4">Compact metric cards with micro-labels and bold colored values. Used in dashboards for at-a-glance stats.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-surface-700/50 border border-border-default rounded-lg px-3.5 py-2.5">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">Squad Value</div>
                    <div class="font-heading text-xl font-bold text-text-primary mt-0.5">&euro;285.4M</div>
                </div>
                <div class="bg-surface-700/50 border border-border-default rounded-lg px-3.5 py-2.5">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">Transfer Budget</div>
                    <div class="font-heading text-xl font-bold text-accent-green mt-0.5">&euro;12.5M</div>
                </div>
                <div class="bg-surface-700/50 border border-border-default rounded-lg px-3.5 py-2.5">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">Wage Bill</div>
                    <div class="font-heading text-xl font-bold text-accent-red mt-0.5">&euro;68.2M</div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.summaryCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="summaryCode">&lt;div class="bg-surface-700/50 border border-border-default rounded-lg px-3.5 py-2.5"&gt;
    &lt;div class="text-[10px] text-text-muted uppercase tracking-wider"&gt;Label&lt;/div&gt;
    &lt;div class="font-heading text-xl font-bold text-text-primary mt-0.5"&gt;Value&lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Stat Row Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Stat Row Card</h3>
        <p class="text-sm text-text-secondary mb-4">Key-value pair rows for sidebar data. Used for squad overview, financial summaries, and club info.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden divide-y divide-white/5">
                <div class="px-4 py-3 flex items-center justify-between">
                    <span class="text-xs text-text-muted">Squad Value</span>
                    <span class="font-semibold text-text-primary">&euro;285.4M</span>
                </div>
                <div class="px-4 py-3 flex items-center justify-between">
                    <span class="text-xs text-text-muted">Avg. Age</span>
                    <span class="font-semibold text-text-primary">26.4</span>
                </div>
                <div class="px-4 py-3 flex items-center justify-between">
                    <span class="text-xs text-text-muted">Squad Size</span>
                    <span class="font-semibold text-text-primary">23</span>
                </div>
                <div class="px-4 py-3 flex items-center justify-between">
                    <span class="text-xs text-text-muted">Wage Capacity</span>
                    <span class="font-semibold text-accent-gold">82%</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.statRowCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="statRowCode">&lt;div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden divide-y divide-white/5"&gt;
    &lt;div class="px-4 py-3 flex items-center justify-between"&gt;
        &lt;span class="text-xs text-text-muted"&gt;Label&lt;/span&gt;
        &lt;span class="font-semibold text-text-primary"&gt;Value&lt;/span&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Accent Border Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Accent Border Card</h3>
        <p class="text-sm text-text-secondary mb-4">Left border accent pattern used for next match preview and competition grouping. Color indicates competition type.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-4 mb-3">
            <div class="border-l-4 border-l-accent-gold bg-surface-800 border border-border-default rounded-r-xl pl-5 pr-4 py-3">
                <span class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">Domestic League</span>
                <div class="text-sm text-text-body mt-1">La Liga &middot; Matchday 12</div>
            </div>
            <div class="border-l-4 border-l-accent-green bg-surface-800 border border-border-default rounded-r-xl pl-5 pr-4 py-3">
                <span class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">Domestic Cup</span>
                <div class="text-sm text-text-body mt-1">Copa del Rey &middot; Round of 16</div>
            </div>
            <div class="border-l-4 border-l-accent-blue bg-surface-800 border border-border-default rounded-r-xl pl-5 pr-4 py-3">
                <span class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">European</span>
                <div class="text-sm text-text-body mt-1">Champions League &middot; League Phase</div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.accentCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="accentCode">&lt;div class="border-l-4 border-l-accent-gold bg-surface-800 border border-border-default rounded-r-xl pl-5 pr-4 py-3"&gt;
    &lt;!-- Domestic league content --&gt;
&lt;/div&gt;
&lt;div class="border-l-4 border-l-accent-green bg-surface-800 border border-border-default rounded-r-xl pl-5 pr-4 py-3"&gt;
    &lt;!-- Domestic cup content --&gt;
&lt;/div&gt;
&lt;div class="border-l-4 border-l-accent-blue bg-surface-800 border border-border-default rounded-r-xl pl-5 pr-4 py-3"&gt;
    &lt;!-- European content --&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Dark Gradient Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Dark Gradient Card</h3>
        <p class="text-sm text-text-secondary mb-4">Used for featured values like squad value in the finances sidebar. Gradient from surface-700 to surface-800.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="rounded-xl overflow-hidden border border-border-default max-w-sm">
                <div class="bg-linear-to-br from-surface-700 to-surface-800 px-4 py-5">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider mb-1">Squad Value</div>
                    <div class="font-heading text-2xl font-bold text-text-primary">&euro;245.8M</div>
                </div>
                <div class="divide-y divide-white/5 bg-surface-800">
                    <div class="px-4 py-3 flex items-center justify-between">
                        <span class="text-xs text-text-muted">Wage Bill</span>
                        <span class="text-sm font-semibold text-text-primary">&euro;68.2M/yr</span>
                    </div>
                    <div class="px-4 py-3 flex items-center justify-between">
                        <span class="text-xs text-text-muted">Transfer Budget</span>
                        <span class="text-sm font-semibold text-accent-green">&euro;12.5M</span>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.gradientCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="gradientCode">&lt;div class="rounded-xl overflow-hidden border border-border-default"&gt;
    &lt;div class="bg-linear-to-br from-surface-700 to-surface-800 px-4 py-5"&gt;
        &lt;div class="text-[10px] text-text-muted uppercase tracking-wider mb-1"&gt;Label&lt;/div&gt;
        &lt;div class="font-heading text-2xl font-bold text-text-primary"&gt;Value&lt;/div&gt;
    &lt;/div&gt;
    &lt;div class="divide-y divide-white/5 bg-surface-800"&gt;
        &lt;div class="px-4 py-3 flex items-center justify-between"&gt;
            &lt;span class="text-xs text-text-muted"&gt;Sub-label&lt;/span&gt;
            &lt;span class="text-sm font-semibold text-text-primary"&gt;Sub-value&lt;/span&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Tip Card --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Tip Card</h3>
        <p class="text-sm text-text-secondary mb-4">Informational callout card using a translucent accent-blue background. Used for contextual tips, help text, and onboarding hints.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="tip-card bg-accent-blue/8 border border-accent-blue/15 rounded-xl px-4 py-3.5">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-accent-blue shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <div>
                        <div class="text-sm font-semibold text-accent-blue mb-0.5">Pro Tip</div>
                        <p class="text-sm text-text-secondary leading-relaxed">Investing in youth academy infrastructure early pays dividends in later seasons. Higher-tier academies produce better prospects with stronger potential ceilings.</p>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.tipCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="tipCode">&lt;div class="tip-card bg-accent-blue/8 border border-accent-blue/15 rounded-xl px-4 py-3.5"&gt;
    &lt;div class="flex items-start gap-3"&gt;
        &lt;svg class="w-5 h-5 text-accent-blue shrink-0 mt-0.5" ...&gt;...&lt;/svg&gt;
        &lt;div&gt;
            &lt;div class="text-sm font-semibold text-accent-blue mb-0.5"&gt;Tip Title&lt;/div&gt;
            &lt;p class="text-sm text-text-secondary leading-relaxed"&gt;Tip content...&lt;/p&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
