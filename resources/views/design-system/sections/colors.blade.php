<section id="colors" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Colors</h2>
    <p class="text-sm text-text-secondary mb-10">The color system is built on deep, dark surfaces with vibrant accents. Opacity-based borders and backgrounds create depth without competing for attention.</p>

    {{-- Surface Palette --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Surface Palette</h3>
        <p class="text-sm text-text-secondary mb-4">The foundational layers of the interface. Surface-900 is the page background; surface-800 is the primary card/panel color; surface-700 and surface-600 provide elevated and interactive surfaces.</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach([
                ['bg' => 'bg-surface-900', 'name' => 'surface-900', 'hex' => '#0B1120', 'usage' => 'Page background'],
                ['bg' => 'bg-surface-800', 'name' => 'surface-800', 'hex' => '#0F172A', 'usage' => 'Cards, panels'],
                ['bg' => 'bg-surface-700', 'name' => 'surface-700', 'hex' => '#1E293B', 'usage' => 'Code blocks, inputs'],
                ['bg' => 'bg-surface-600', 'name' => 'surface-600', 'hex' => '#334155', 'usage' => 'Elevated elements'],
            ] as $color)
            <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                <div class="w-full h-16 rounded-lg {{ $color['bg'] }} border border-border-strong mb-3"></div>
                <div class="font-heading font-semibold text-sm text-text-primary uppercase tracking-wide">{{ $color['name'] }}</div>
                <div class="text-[10px] text-text-muted uppercase tracking-widest mt-0.5">{{ $color['hex'] }}</div>
                <div class="text-xs text-text-secondary mt-1">{{ $color['usage'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Accent Palette --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Accent Palette</h3>
        <p class="text-sm text-text-secondary mb-4">Vibrant colors that pop against the dark surfaces. Each accent has a specific role in the UI.</p>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @foreach([
                ['bg' => 'bg-accent-blue', 'name' => 'accent-blue', 'hex' => '#3B82F6', 'usage' => 'Interactive, links, focus'],
                ['bg' => 'bg-accent-gold', 'name' => 'accent-gold', 'hex' => '#F59E0B', 'usage' => 'Highlights, awards'],
                ['bg' => 'bg-accent-green', 'name' => 'accent-green', 'hex' => '#22C55E', 'usage' => 'Success, positive'],
                ['bg' => 'bg-accent-red', 'name' => 'accent-red', 'hex' => '#EF4444', 'usage' => 'Danger, primary CTA'],
                ['bg' => 'bg-accent-orange', 'name' => 'accent-orange', 'hex' => '#F97316', 'usage' => 'Warnings, energy'],
            ] as $color)
            <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                <div class="w-full h-16 rounded-lg {{ $color['bg'] }} mb-3"></div>
                <div class="font-heading font-semibold text-sm text-text-primary uppercase tracking-wide">{{ $color['name'] }}</div>
                <div class="text-[10px] text-text-muted uppercase tracking-widest mt-0.5">{{ $color['hex'] }}</div>
                <div class="text-xs text-text-secondary mt-1">{{ $color['usage'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Pitch Palette --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Pitch Palette</h3>
        <p class="text-sm text-text-secondary mb-4">Football-specific greens used for pitch visualizations, formation displays, and match-related UI elements.</p>
        <div class="grid grid-cols-3 gap-4">
            @foreach([
                ['bg' => 'bg-pitch-dark', 'name' => 'pitch-dark', 'hex' => '#1a5c2a', 'usage' => 'Pitch shadows, edges'],
                ['bg' => 'bg-pitch-base', 'name' => 'pitch-base', 'hex' => '#1e6b31', 'usage' => 'Primary pitch surface'],
                ['bg' => 'bg-pitch-light', 'name' => 'pitch-light', 'hex' => '#22783a', 'usage' => 'Pitch highlights, stripes'],
            ] as $color)
            <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                <div class="w-full h-16 rounded-lg {{ $color['bg'] }} mb-3"></div>
                <div class="font-heading font-semibold text-sm text-text-primary uppercase tracking-wide">{{ $color['name'] }}</div>
                <div class="text-[10px] text-text-muted uppercase tracking-widest mt-0.5">{{ $color['hex'] }}</div>
                <div class="text-xs text-text-secondary mt-1">{{ $color['usage'] }}</div>
            </div>
            @endforeach
        </div>

        {{-- Pitch preview --}}
        <div class="mt-4 bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-[10px] text-text-muted uppercase tracking-widest mb-3">Pitch Preview</div>
            <div class="h-32 rounded-lg overflow-hidden flex">
                <div class="flex-1 bg-pitch-dark"></div>
                <div class="flex-1 bg-pitch-base"></div>
                <div class="flex-1 bg-pitch-light"></div>
                <div class="flex-1 bg-pitch-base"></div>
                <div class="flex-1 bg-pitch-dark"></div>
                <div class="flex-1 bg-pitch-base"></div>
                <div class="flex-1 bg-pitch-light"></div>
                <div class="flex-1 bg-pitch-base"></div>
            </div>
        </div>
    </div>

    {{-- Border Opacity Patterns --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Border Opacity Patterns</h3>
        <p class="text-sm text-text-secondary mb-4">Borders use white at low opacity instead of solid colors. This creates subtle separation that adapts to any dark surface beneath it.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-heading font-semibold text-sm text-text-primary uppercase tracking-wide">border-border-default</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-widest">Very subtle</div>
                </div>
                <div class="bg-surface-700/50 border border-border-default rounded-lg p-4">
                    <p class="text-sm text-text-secondary">Used for cards, panels, and containers. Barely visible separation that maintains visual cohesion.</p>
                </div>
                <div x-data="{ copied: false }" class="relative mt-3">
                    <button @click="navigator.clipboard.writeText($refs.code5.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                            class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
                    </button>
                    <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code5">border border-border-default</code></pre>
                </div>
            </div>

            <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-heading font-semibold text-sm text-text-primary uppercase tracking-wide">border-border-strong</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-widest">Subtle</div>
                </div>
                <div class="bg-surface-700/50 border border-border-strong rounded-lg p-4">
                    <p class="text-sm text-text-secondary">Used for dividers, table borders, and elements that need slightly more definition.</p>
                </div>
                <div x-data="{ copied: false }" class="relative mt-3">
                    <button @click="navigator.clipboard.writeText($refs.code10.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                            class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
                    </button>
                    <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code10">border border-border-strong</code></pre>
                </div>
            </div>
        </div>

        <div class="bg-surface-800 border border-border-default rounded-xl p-5">
            <div class="text-[10px] text-text-muted uppercase tracking-widest mb-3">Comparison</div>
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1 bg-surface-700/50 border border-border-default rounded-lg p-3 text-center">
                    <span class="text-xs text-text-secondary">white/5</span>
                </div>
                <div class="flex-1 bg-surface-700/50 border border-border-strong rounded-lg p-3 text-center">
                    <span class="text-xs text-text-secondary">white/10</span>
                </div>
                <div class="flex-1 bg-surface-700/50 border border-white/20 rounded-lg p-3 text-center">
                    <span class="text-xs text-text-secondary">white/20 (avoid)</span>
                </div>
                <div class="flex-1 bg-surface-700/50 border border-slate-200 rounded-lg p-3 text-center">
                    <span class="text-xs text-accent-red">slate-200 (never)</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Semantic Colors --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Semantic Colors</h3>
        <p class="text-sm text-text-secondary mb-4">Colors communicate meaning consistently throughout the application. Each semantic role maps to a specific accent color.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Success --}}
            <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-accent-green/20 border border-accent-green/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-accent-green" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <div class="font-heading font-bold text-sm text-text-primary uppercase tracking-wide">Success / Positive</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">accent-green #22C55E</div>
                    </div>
                </div>
                <p class="text-sm text-text-secondary mb-3">Completed transfers, match wins, positive growth, confirmed actions, budget surplus.</p>
                <div class="flex flex-wrap gap-2">
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-green/10 text-accent-green border border-accent-green/20">text-accent-green</span>
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-green/10 text-accent-green border border-accent-green/20">bg-accent-green/10</span>
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-green/10 text-accent-green border border-accent-green/20">border-accent-green/20</span>
                </div>
            </div>

            {{-- Danger --}}
            <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-accent-red/20 border border-accent-red/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-accent-red" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div>
                        <div class="font-heading font-bold text-sm text-text-primary uppercase tracking-wide">Danger / Destructive</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">accent-red #EF4444</div>
                    </div>
                </div>
                <p class="text-sm text-text-secondary mb-3">Match losses, injuries, red cards, budget deficit, relegation warnings, destructive actions.</p>
                <div class="flex flex-wrap gap-2">
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-red/10 text-accent-red border border-accent-red/20">text-accent-red</span>
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-red/10 text-accent-red border border-accent-red/20">bg-accent-red/10</span>
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-red/10 text-accent-red border border-accent-red/20">border-accent-red/20</span>
                </div>
            </div>

            {{-- Warning --}}
            <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-accent-gold/20 border border-accent-gold/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-accent-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <div>
                        <div class="font-heading font-bold text-sm text-text-primary uppercase tracking-wide">Warning / Caution</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">accent-gold #F59E0B</div>
                    </div>
                </div>
                <p class="text-sm text-text-secondary mb-3">Yellow cards, expiring contracts, low fitness, approaching budget limits, pending decisions.</p>
                <div class="flex flex-wrap gap-2">
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-gold/10 text-accent-gold border border-accent-gold/20">text-accent-gold</span>
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-gold/10 text-accent-gold border border-accent-gold/20">bg-accent-gold/10</span>
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-gold/10 text-accent-gold border border-accent-gold/20">border-accent-gold/20</span>
                </div>
            </div>

            {{-- Interactive --}}
            <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-accent-blue/20 border border-accent-blue/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-accent-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                    </div>
                    <div>
                        <div class="font-heading font-bold text-sm text-text-primary uppercase tracking-wide">Interactive / Info</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">accent-blue #3B82F6</div>
                    </div>
                </div>
                <p class="text-sm text-text-secondary mb-3">Links, focus rings, selected states, informational badges, navigation highlights, active tabs.</p>
                <div class="flex flex-wrap gap-2">
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-blue/10 text-accent-blue border border-accent-blue/20">text-accent-blue</span>
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-blue/10 text-accent-blue border border-accent-blue/20">bg-accent-blue/10</span>
                    <span class="px-2 py-1 text-[10px] rounded-sm bg-accent-blue/10 text-accent-blue border border-accent-blue/20">border-accent-blue/20</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Text Color Hierarchy --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Text Color Hierarchy</h3>
        <p class="text-sm text-text-secondary mb-4">Text colors create a clear visual hierarchy on dark surfaces. Use these consistently.</p>

        <div class="bg-surface-800 border border-border-default rounded-xl p-5">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-32 shrink-0">
                        <span class="text-[10px] text-text-muted uppercase tracking-widest">text-text-primary</span>
                    </div>
                    <span class="text-text-primary font-semibold">Headings, emphasis, primary values</span>
                </div>
                <div class="border-t border-border-default"></div>
                <div class="flex items-center gap-4">
                    <div class="w-32 shrink-0">
                        <span class="text-[10px] text-text-muted uppercase tracking-widest">text-text-body</span>
                    </div>
                    <span class="text-text-body">Code blocks, secondary emphasis</span>
                </div>
                <div class="border-t border-border-default"></div>
                <div class="flex items-center gap-4">
                    <div class="w-32 shrink-0">
                        <span class="text-[10px] text-text-muted uppercase tracking-widest">text-text-secondary</span>
                    </div>
                    <span class="text-text-secondary">Body text, descriptions, table data</span>
                </div>
                <div class="border-t border-border-default"></div>
                <div class="flex items-center gap-4">
                    <div class="w-32 shrink-0">
                        <span class="text-[10px] text-text-muted uppercase tracking-widest">text-text-muted</span>
                    </div>
                    <span class="text-text-muted">Micro-labels, timestamps, tertiary info</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card Pattern --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Standard Card Pattern</h3>
        <p class="text-sm text-text-secondary mb-4">The foundational card pattern used across the application.</p>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.codeCard.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="codeCard">&lt;div class="bg-surface-800 border border-border-default rounded-xl p-5"&gt;
    &lt;!-- Card content --&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
