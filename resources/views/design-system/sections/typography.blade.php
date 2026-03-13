<section id="typography" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Typography</h2>
    <p class="text-sm text-text-secondary mb-10">Two typefaces drive the visual identity: <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">Barlow Condensed</code> (font-heading) for headings, labels, and numbers, and <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">Inter</code> (font-sans) for body text. Font sizes are scaled up from Tailwind defaults via <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">tailwind.config.js</code>. On mobile (&lt;768px), the root font-size drops to 14px, proportionally scaling all rem-based values.</p>

    {{-- ================================================================== --}}
    {{-- HEADINGS --}}
    {{-- ================================================================== --}}
    <h3 class="text-lg font-semibold text-text-primary mb-4">Headings</h3>

    <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-6 mb-4">
        {{-- Page title --}}
        <div>
            <span class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">Page Title</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary</code>
            </div>
            <p class="text-xs text-text-muted mt-1">Top-level page headings. Scales from text-2xl on mobile to text-3xl on desktop.</p>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Team / header name --}}
        <div>
            <span class="font-heading font-semibold text-base leading-none tracking-wide text-text-primary">Real Madrid CF</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">font-heading font-semibold text-base leading-none tracking-wide text-text-primary</code>
            </div>
            <p class="text-xs text-text-muted mt-1">Team names and header identifiers. Tight leading for compact layouts.</p>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Section heading --}}
        <div>
            <span class="text-lg font-semibold text-text-primary">Section Heading</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-lg font-semibold text-text-primary</code>
            </div>
            <p class="text-xs text-text-muted mt-1">Subsection headings within a page or card.</p>
        </div>
    </div>

    <div x-data="{ copied: false }" class="relative mb-12">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
        </button>
        <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary"&gt;Page Title&lt;/h1&gt;
&lt;span class="font-heading font-semibold text-base leading-none tracking-wide text-text-primary"&gt;Team Name&lt;/span&gt;
&lt;h2 class="text-lg font-semibold text-text-primary"&gt;Section Heading&lt;/h2&gt;</code></pre>
    </div>

    {{-- ================================================================== --}}
    {{-- LABELS --}}
    {{-- ================================================================== --}}
    <h3 class="text-lg font-semibold text-text-primary mb-4">Labels</h3>

    <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-6 mb-4">
        {{-- Micro-label --}}
        <div>
            <span class="text-[10px] text-text-muted uppercase tracking-widest">La Liga &middot; Season 2025/26</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-[10px] text-text-muted uppercase tracking-widest</code>
            </div>
            <p class="text-xs text-text-muted mt-1">Micro metadata labels. Used for competition context, dates, and secondary identifiers.</p>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Section group header --}}
        <div>
            <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">Goalkeepers</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted</code>
            </div>
            <p class="text-xs text-text-muted mt-1">Group headers for categorized lists (position groups, form sections).</p>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Nav item text --}}
        <div>
            <span class="text-xs font-medium uppercase tracking-wider text-text-secondary">Plantilla</span>
            <span class="ml-4 text-xs font-medium uppercase tracking-wider text-text-primary">Alineaci&oacute;n</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-xs font-medium uppercase tracking-wider</code>
            </div>
            <p class="text-xs text-text-muted mt-1">Navigation items and tab labels. Active state uses text-text-primary, inactive uses text-text-secondary.</p>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Stat label --}}
        <div>
            <span class="text-[10px] text-text-muted">Goals</span>
            <span class="ml-4 text-[9px] text-text-muted">xG per 90</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-[10px] text-text-muted</code>
                <span class="text-text-faint mx-1">or</span>
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-[9px] text-text-muted</code>
            </div>
            <p class="text-xs text-text-muted mt-1">Stat labels and very compact metadata. Use text-[9px] only in space-constrained contexts.</p>
        </div>
    </div>

    <div x-data="{ copied: false }" class="relative mb-12">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
        </button>
        <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;span class="text-[10px] text-text-muted uppercase tracking-widest"&gt;La Liga &middot; Season 2025/26&lt;/span&gt;
&lt;span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted"&gt;Goalkeepers&lt;/span&gt;
&lt;span class="text-xs font-medium uppercase tracking-wider text-text-secondary"&gt;Nav Item&lt;/span&gt;
&lt;span class="text-[10px] text-text-muted"&gt;Stat Label&lt;/span&gt;</code></pre>
    </div>

    {{-- ================================================================== --}}
    {{-- BODY TEXT --}}
    {{-- ================================================================== --}}
    <h3 class="text-lg font-semibold text-text-primary mb-4">Body Text</h3>

    <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-5 mb-4">
        {{-- Player name --}}
        <div>
            <span class="text-sm font-medium text-text-primary">Lamine Yamal</span>
            <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body ml-3">text-sm font-medium text-text-primary</code>
            <p class="text-xs text-text-muted mt-1">Player names in rows, lists, and cards.</p>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Body text --}}
        <div>
            <span class="text-sm text-text-secondary">Primary body text for descriptions, paragraphs, and general content.</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-sm text-text-secondary</code>
            </div>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Secondary text --}}
        <div>
            <span class="text-xs text-text-muted">Secondary information, metadata, and supporting details.</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-xs text-text-muted</code>
            </div>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Link text --}}
        <div>
            <a href="#" class="text-sm text-accent-blue hover:text-blue-400">View all players &rarr;</a>
            <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body ml-3">text-sm text-accent-blue hover:text-blue-400</code>
        </div>
    </div>

    <div x-data="{ copied: false }" class="relative mb-12">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
        </button>
        <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;span class="text-sm font-medium text-text-primary"&gt;Player Name&lt;/span&gt;
&lt;p class="text-sm text-text-secondary"&gt;Body text&lt;/p&gt;
&lt;span class="text-xs text-text-muted"&gt;Secondary text&lt;/span&gt;
&lt;a href="#" class="text-sm text-accent-blue hover:text-blue-400"&gt;Link&lt;/a&gt;</code></pre>
    </div>

    {{-- ================================================================== --}}
    {{-- NUMBERS & VALUES --}}
    {{-- ================================================================== --}}
    <h3 class="text-lg font-semibold text-text-primary mb-4">Numbers &amp; Values</h3>

    <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-6 mb-4">
        {{-- Rating number --}}
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-accent-blue/20 flex items-center justify-center">
                <span class="font-heading font-bold text-base text-accent-blue">78</span>
            </div>
            <div>
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">font-heading font-bold text-base</code>
                <p class="text-xs text-text-muted mt-1">Rating numbers inside badges. Color varies by context (blue, green, gold).</p>
            </div>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Summary card value --}}
        <div class="flex items-center gap-4">
            <div>
                <span class="font-heading text-xl font-bold text-accent-green">+€4.2M</span>
                <span class="mx-3 font-heading text-xl font-bold text-accent-red">-€8.1M</span>
                <span class="font-heading text-xl font-bold text-text-primary">24</span>
            </div>
            <div>
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">font-heading text-xl font-bold</code>
            </div>
        </div>
        <p class="text-xs text-text-muted -mt-3">Summary card hero values. Color by context: green for income, red for expenses, white for neutral counts.</p>

        <div class="border-t border-border-default"></div>

        {{-- Financial value --}}
        <div>
            <span class="text-xs font-semibold text-accent-gold font-heading tracking-wide">&euro;12.5M</span>
            <div class="mt-2">
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-xs font-semibold text-accent-gold font-heading tracking-wide</code>
            </div>
            <p class="text-xs text-text-muted mt-1">Inline financial values (market value, transfer fees, salaries).</p>
        </div>

        <div class="border-t border-border-default"></div>

        {{-- Match score --}}
        <div class="flex items-center gap-4">
            <span class="font-heading text-3xl md:text-5xl font-bold text-text-primary">2 - 1</span>
            <div>
                <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">font-heading text-3xl md:text-5xl font-bold text-text-primary</code>
                <p class="text-xs text-text-muted mt-1">Match scores. Scales responsively from 3xl to 5xl.</p>
            </div>
        </div>
    </div>

    <div x-data="{ copied: false }" class="relative mb-12">
        <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
        </button>
        <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">{{-- Rating badge --}}
&lt;span class="font-heading font-bold text-base text-accent-blue"&gt;78&lt;/span&gt;

{{-- Summary card value --}}
&lt;span class="font-heading text-xl font-bold text-accent-green"&gt;+&euro;4.2M&lt;/span&gt;

{{-- Financial inline value --}}
&lt;span class="text-xs font-semibold text-accent-gold font-heading tracking-wide"&gt;&euro;12.5M&lt;/span&gt;

{{-- Match score --}}
&lt;span class="font-heading text-3xl md:text-5xl font-bold text-text-primary"&gt;2 - 1&lt;/span&gt;</code></pre>
    </div>

    {{-- ================================================================== --}}
    {{-- FONT SIZE SCALE --}}
    {{-- ================================================================== --}}
    <h3 class="text-lg font-semibold text-text-primary mb-4">Font Size Scale</h3>

    <div class="bg-surface-700/30 border border-border-default rounded-xl overflow-hidden mb-12">
        @foreach([
            ['class' => 'text-xs', 'name' => 'text-xs', 'size' => '0.8rem', 'usage' => 'Metadata, timestamps, badges'],
            ['class' => 'text-sm', 'name' => 'text-sm', 'size' => '1rem', 'usage' => 'Body text, table cells, labels'],
            ['class' => 'text-base', 'name' => 'text-base', 'size' => '1.25rem', 'usage' => 'Default base size, rating numbers'],
            ['class' => 'text-xl', 'name' => 'text-xl', 'size' => '1.563rem', 'usage' => 'Summary card values'],
            ['class' => 'text-2xl', 'name' => 'text-2xl', 'size' => '1.953rem', 'usage' => 'Page titles (mobile)'],
            ['class' => 'text-3xl', 'name' => 'text-3xl', 'size' => '2.441rem', 'usage' => 'Page titles (desktop), match scores'],
            ['class' => 'text-4xl', 'name' => 'text-4xl', 'size' => '3.052rem', 'usage' => 'Hero text'],
            ['class' => 'text-5xl', 'name' => 'text-5xl', 'size' => '3.815rem', 'usage' => 'Match scores (desktop)'],
        ] as $size)
        <div class="px-5 py-3 flex items-baseline gap-4 {{ !$loop->last ? 'border-b border-border-default' : '' }}">
            <div class="w-28 shrink-0">
                <code class="text-[10px] text-accent-blue">{{ $size['name'] }}</code>
                <div class="text-[10px] text-text-faint">{{ $size['size'] }}</div>
            </div>
            <div class="{{ $size['class'] }} text-text-primary truncate flex-1">The quick brown fox</div>
            <div class="text-xs text-text-muted hidden md:block shrink-0">{{ $size['usage'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ================================================================== --}}
    {{-- MOBILE SCALING NOTE --}}
    {{-- ================================================================== --}}
    <div class="bg-accent-blue/10 border border-accent-blue/20 rounded-xl p-4 text-sm text-text-body">
        <span class="font-semibold text-text-primary">Mobile scaling:</span> The root font-size drops to 14px on screens narrower than 768px (from the default ~20px). All rem-based sizes scale proportionally. Never use fixed <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">px</code> values for font sizes &mdash; use Tailwind's <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-*</code> utilities. Arbitrary pixel sizes like <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-[10px]</code> and <code class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-[9px]</code> are exceptions used only for micro-labels that should remain fixed across breakpoints.
    </div>
</section>
