<section id="badges" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Badges & Pills</h2>
    <p class="text-sm text-text-secondary mb-8">Compact indicators for status, categories, positions, ratings, and scores. All designed for the dark surface background with translucent color fills.</p>

    {{-- Rating Badges --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Rating Badges</h3>
        <p class="text-sm text-text-secondary mb-4">Rounded-lg squares with color-coded backgrounds based on ability thresholds. Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-rating-badge</code> component.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex flex-wrap gap-4 items-end">
                @foreach([
                    ['label' => 'Elite', 'value' => 92, 'desc' => '80+'],
                    ['label' => 'Good', 'value' => 78, 'desc' => '70-79'],
                    ['label' => 'Average', 'value' => 68, 'desc' => '60-69'],
                    ['label' => 'Below', 'value' => 55, 'desc' => '50-59'],
                    ['label' => 'Poor', 'value' => 45, 'desc' => '<50'],
                ] as $rating)
                <div class="text-center">
                    <x-rating-badge :value="$rating['value']" class="mb-1.5" />
                    <div class="text-[10px] text-text-secondary font-medium">{{ $rating['label'] }}</div>
                    <div class="text-[10px] text-text-faint">{{ $rating['desc'] }}</div>
                </div>
                @endforeach
            </div>

            {{-- Size variants --}}
            <div class="mt-6 pt-4 border-t border-border-default">
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">Size Variants</div>
                <div class="flex items-end gap-4">
                    <div class="text-center">
                        <x-rating-badge :value="88" size="sm" />
                        <div class="text-[10px] text-text-muted mt-1">sm</div>
                    </div>
                    <div class="text-center">
                        <x-rating-badge :value="88" size="md" />
                        <div class="text-[10px] text-text-muted mt-1">md</div>
                    </div>
                    <div class="text-center">
                        <x-rating-badge :value="88" size="lg" />
                        <div class="text-[10px] text-text-muted mt-1">lg</div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.ratingCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="ratingCode">&lt;!-- Basic usage --&gt;
&lt;x-rating-badge :value="$player->overall_score" /&gt;

&lt;!-- With size --&gt;
&lt;x-rating-badge :value="88" size="sm" /&gt;
&lt;x-rating-badge :value="88" size="lg" /&gt;

&lt;!-- Color thresholds (automatic) --&gt;
&lt;!-- 80+  .rating-elite   (green)  --&gt;
&lt;!-- 70+  .rating-good    (lime)   --&gt;
&lt;!-- 60+  .rating-average (amber)  --&gt;
&lt;!-- 50+  .rating-below   (orange) --&gt;
&lt;!-- &lt;50  .rating-poor    (red)    --&gt;</code></pre>
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
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">value</td>
                        <td class="py-2 pr-4 text-text-secondary">int</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Ability score (determines color automatically)</td>
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

    {{-- Position Badges --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Position Badges</h3>
        <p class="text-sm text-text-secondary mb-4">Skewed badge design with position-specific colors. Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-position-badge</code> component. GK = amber, DEF = blue, MID = green, FWD = red.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-5 mb-3">
            {{-- Size variants --}}
            <div>
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">Sizes</div>
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <x-position-badge abbreviation="GK" size="sm" />
                        <div class="text-[10px] text-text-muted mt-1">sm</div>
                    </div>
                    <div class="text-center">
                        <x-position-badge abbreviation="GK" size="md" />
                        <div class="text-[10px] text-text-muted mt-1">md</div>
                    </div>
                    <div class="text-center">
                        <x-position-badge abbreviation="GK" size="lg" />
                        <div class="text-[10px] text-text-muted mt-1">lg</div>
                    </div>
                </div>
            </div>

            {{-- All positions --}}
            <div>
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">All Positions</div>
                <div class="flex flex-wrap gap-2">
                    @foreach(['GK', 'CB', 'LB', 'RB', 'DM', 'CM', 'AM', 'LW', 'RW', 'CF'] as $pos)
                        <x-position-badge abbreviation="{{ $pos }}" size="md" />
                    @endforeach
                </div>
            </div>

            {{-- Color groups --}}
            <div>
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">Color Groups</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-surface-800 border border-border-default rounded-lg px-3 py-2">
                        <div class="text-[10px] text-amber-400 font-semibold mb-1.5">Goalkeeper</div>
                        <x-position-badge abbreviation="GK" size="sm" />
                    </div>
                    <div class="bg-surface-800 border border-border-default rounded-lg px-3 py-2">
                        <div class="text-[10px] text-blue-400 font-semibold mb-1.5">Defence</div>
                        <div class="flex gap-1">
                            <x-position-badge abbreviation="CB" size="sm" />
                            <x-position-badge abbreviation="LB" size="sm" />
                            <x-position-badge abbreviation="RB" size="sm" />
                        </div>
                    </div>
                    <div class="bg-surface-800 border border-border-default rounded-lg px-3 py-2">
                        <div class="text-[10px] text-green-400 font-semibold mb-1.5">Midfield</div>
                        <div class="flex gap-1">
                            <x-position-badge abbreviation="DM" size="sm" />
                            <x-position-badge abbreviation="CM" size="sm" />
                            <x-position-badge abbreviation="AM" size="sm" />
                        </div>
                    </div>
                    <div class="bg-surface-800 border border-border-default rounded-lg px-3 py-2">
                        <div class="text-[10px] text-red-400 font-semibold mb-1.5">Forward</div>
                        <div class="flex gap-1">
                            <x-position-badge abbreviation="LW" size="sm" />
                            <x-position-badge abbreviation="RW" size="sm" />
                            <x-position-badge abbreviation="CF" size="sm" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.posCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="posCode">&lt;x-position-badge abbreviation="GK" size="md" /&gt;
&lt;x-position-badge abbreviation="CB" size="sm" /&gt;
&lt;x-position-badge position="Goalkeeper" size="lg" tooltip="Goalkeeper" /&gt;</code></pre>
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
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">abbreviation</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">null</td>
                        <td class="py-2 text-text-secondary">Position abbreviation (GK, CB, etc.)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">position</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">null</td>
                        <td class="py-2 text-text-secondary">Full position name (uses PositionMapper)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">size</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">'md'</td>
                        <td class="py-2 text-text-secondary">sm | md | lg</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tooltip</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">null</td>
                        <td class="py-2 text-text-secondary">Tooltip text shown on hover</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Status Pills --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Status Pills</h3>
        <p class="text-sm text-text-secondary mb-4">Dark-themed translucent pills for categorization and status labels. Use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-accent-*/20 text-accent-*</code> pattern.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-green/20 text-accent-green">Active</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-gold/20 text-accent-gold">Pending</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-red/20 text-accent-red">Rejected</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-blue/20 text-accent-blue">Info</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-orange/20 text-accent-orange">Warning</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-white/10 text-text-secondary">Default</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-500/20 text-emerald-400">On Loan</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-500/20 text-red-400">Injured</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.pillCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="pillCode">&lt;span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-green/20 text-accent-green"&gt;Active&lt;/span&gt;
&lt;span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-gold/20 text-accent-gold"&gt;Pending&lt;/span&gt;
&lt;span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-red/20 text-accent-red"&gt;Rejected&lt;/span&gt;
&lt;span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-blue/20 text-accent-blue"&gt;Info&lt;/span&gt;</code></pre>
        </div>
    </div>

    {{-- Competition Badges --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Competition Badges</h3>
        <p class="text-sm text-text-secondary mb-4">Color-coded by competition type: domestic league (amber), domestic cup (emerald), European (blue). Use the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-competition-pill</code> component which resolves colors automatically via <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">CompetitionColors::badge()</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-gold/20 text-accent-gold">La Liga</span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-gold/20 text-accent-gold">Segunda Division</span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-green/20 text-accent-green">Copa del Rey</span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-green/20 text-accent-green">Supercopa</span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-blue/20 text-accent-blue">Champions League</span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-blue/20 text-accent-blue">Europa League</span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-blue/20 text-accent-blue">Conference League</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.compCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="compCode">&lt;!-- Recommended: use the component (resolves colors automatically) --&gt;
&lt;x-competition-pill :competition="$competition" /&gt;
&lt;x-competition-pill :competition="$competition" :round-name="$match->round_name" /&gt;
&lt;x-competition-pill :competition="$competition" :round-number="$match->round_number" /&gt;

&lt;!-- Raw markup (for reference) --&gt;
&lt;span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-gold/20 text-accent-gold"&gt;League&lt;/span&gt;
&lt;span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-green/20 text-accent-green"&gt;Cup&lt;/span&gt;
&lt;span class="px-3 py-1 text-xs font-semibold rounded-full bg-accent-blue/20 text-accent-blue"&gt;European&lt;/span&gt;</code></pre>
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
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">competition</td>
                        <td class="py-2 pr-4 text-text-secondary">Competition</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Competition model (determines color automatically)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">round-name</td>
                        <td class="py-2 pr-4 text-text-secondary">string|null</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">null</td>
                        <td class="py-2 text-text-secondary">Optional round name shown after the pill</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">round-number</td>
                        <td class="py-2 pr-4 text-text-secondary">int|null</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">null</td>
                        <td class="py-2 text-text-secondary">Optional matchday number (used if round-name is null)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Form Result Badges --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Form Result Badges</h3>
        <p class="text-sm text-text-secondary mb-4">Win/Draw/Loss squares for recent match form display.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex gap-1">
                <span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center bg-accent-green text-white">W</span>
                <span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center bg-accent-green text-white">W</span>
                <span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center bg-slate-500 text-white">D</span>
                <span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center bg-accent-red text-white">L</span>
                <span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center bg-accent-green text-white">W</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.formCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="formCode">&lt;span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center bg-accent-green text-white"&gt;W&lt;/span&gt;
&lt;span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center bg-slate-500 text-white"&gt;D&lt;/span&gt;
&lt;span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center bg-accent-red text-white"&gt;L&lt;/span&gt;</code></pre>
        </div>
    </div>

    {{-- Notification Count Badge --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Notification Count Badge</h3>
        <p class="text-sm text-text-secondary mb-4">Small red circle for unread notification counts. Positioned absolutely relative to a parent element.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-text-body">Inbox</span>
                    <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-accent-red rounded-full">3</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-text-body">Updates</span>
                    <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-accent-red rounded-full">9+</span>
                </div>
                <div class="relative inline-flex">
                    <svg class="w-6 h-6 text-text-secondary" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <span class="absolute -top-1.5 -right-1.5 inline-flex items-center justify-center w-4 h-4 text-[9px] font-bold text-white bg-accent-red rounded-full">5</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.notifCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="notifCode">&lt;!-- Inline count --&gt;
&lt;span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-accent-red rounded-full"&gt;3&lt;/span&gt;

&lt;!-- Positioned over icon --&gt;
&lt;div class="relative inline-flex"&gt;
    &lt;svg class="w-6 h-6 text-text-secondary" ...&gt;...&lt;/svg&gt;
    &lt;span class="absolute -top-1.5 -right-1.5 inline-flex items-center justify-center w-4 h-4 text-[9px] font-bold text-white bg-accent-red rounded-full"&gt;5&lt;/span&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
