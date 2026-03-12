<section id="layout-patterns" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-2">Layout Patterns</h2>
    <p class="text-sm text-slate-400 mb-8">Reusable page structures, responsive grids, section dividers, and empty states used throughout VirtuaFC.</p>

    {{-- Page Shell --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Page Shell</h3>
        <p class="text-sm text-slate-400 mb-4">Every game page follows this structure: app layout with header slot, max-width container, and dark content area.</p>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.pageShellCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="pageShellCode">&lt;x-app-layout&gt;
    &lt;x-slot name="header"&gt;
        &lt;x-game-header :game="$game" :next-match="$nextMatch" /&gt;
    &lt;/x-slot&gt;

    &lt;div class="max-w-7xl mx-auto sm:px-6 lg:px-8"&gt;
        &lt;div class="bg-surface-800 overflow-hidden shadow-xs sm:rounded-lg border border-white/5"&gt;
            &lt;div class="p-4 sm:p-6 md:p-8"&gt;
                &lt;!-- Page content --&gt;
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/x-app-layout&gt;</code></pre>
        </div>
    </div>

    {{-- 2/3 + 1/3 Grid --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Two-Column Layout (2/3 + 1/3)</h3>
        <p class="text-sm text-slate-400 mb-4">Main content area with sidebar. Stacks vertically on mobile.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                <div class="md:col-span-2 bg-accent-blue/10 border border-accent-blue/20 rounded-lg p-4 text-center text-sm text-accent-blue">
                    Main content (md:col-span-2)
                </div>
                <div class="bg-surface-700 border border-white/5 rounded-lg p-4 text-center text-sm text-slate-400">
                    Sidebar
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.twoColCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="twoColCode">&lt;div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8"&gt;
    &lt;div class="md:col-span-2"&gt;Main content&lt;/div&gt;
    &lt;div&gt;Sidebar&lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Responsive Grids --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Responsive Grids</h3>
        <p class="text-sm text-slate-400 mb-4">Always start with <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">grid-cols-1</code> as the mobile base. Never use bare <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">grid-cols-N</code>.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 space-y-6 mb-3">
            <div>
                <div class="text-xs text-slate-500 mb-2">grid-cols-1 md:grid-cols-2</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">1</div>
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">2</div>
                </div>
            </div>
            <div>
                <div class="text-xs text-slate-500 mb-2">grid-cols-1 md:grid-cols-3</div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">1</div>
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">2</div>
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">3</div>
                </div>
            </div>
            <div>
                <div class="text-xs text-slate-500 mb-2">grid-cols-2 md:grid-cols-4</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">1</div>
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">2</div>
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">3</div>
                    <div class="bg-surface-700 border border-white/5 rounded-sm p-3 text-center text-xs text-slate-400">4</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Flex Stacking --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Flex Stacking</h3>
        <p class="text-sm text-slate-400 mb-4">Header bars and action rows that stack vertically on mobile and align horizontally on desktop.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-3">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 p-4 bg-surface-800 border border-white/5 rounded-lg">
                <div>
                    <h3 class="font-semibold text-sm text-white">Section Title</h3>
                    <p class="text-xs text-slate-500">Supporting description text</p>
                </div>
                <button type="button" class="bg-accent-blue hover:bg-blue-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors min-h-[44px]">Action</button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.flexCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="flexCode">&lt;div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2"&gt;
    &lt;div&gt;Title&lt;/div&gt;
    &lt;button&gt;Action&lt;/button&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Section Dividers --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Section Dividers</h3>
        <p class="text-sm text-slate-400 mb-4">Top border with padding to separate content sections. Use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-slate-300">border-white/5</code> instead of border-slate-100 for dark backgrounds.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-3">
            <div class="space-y-0">
                <div class="pb-6">
                    <div class="text-sm text-slate-400">First section content</div>
                </div>
                <div class="pt-6 border-t border-white/5">
                    <div class="text-sm text-slate-400">Second section, separated by border-t border-white/5</div>
                </div>
                <div class="pt-8 border-t border-white/5 mt-8">
                    <div class="text-sm text-slate-400">Third section, with more spacing (pt-8)</div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.dividerCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="dividerCode">&lt;div class="pt-6 border-t border-white/5"&gt;
    &lt;!-- New section --&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Empty States --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Empty States</h3>
        <p class="text-sm text-slate-400 mb-4">Centered placeholder content when no data is available. Dark-themed with subtle borders.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 space-y-6 mb-3">
            {{-- Simple empty state --}}
            <div class="text-center py-8 border border-white/5 rounded-xl bg-surface-800">
                <svg class="w-10 h-10 mx-auto mb-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-slate-500">No notifications yet</p>
            </div>

            {{-- Empty state with action --}}
            <div class="text-center py-8 border border-white/5 rounded-xl bg-surface-800">
                <svg class="w-10 h-10 mx-auto mb-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p class="text-sm text-slate-500 mb-3">No scout reports available</p>
                <button class="text-sm text-accent-blue hover:text-blue-400 font-medium transition-colors">Start a search &rarr;</button>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.emptyCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="emptyCode">&lt;div class="text-center py-8 border border-white/5 rounded-xl bg-surface-800"&gt;
    &lt;svg class="w-10 h-10 mx-auto mb-2 text-slate-600"&gt;...&lt;/svg&gt;
    &lt;p class="text-sm text-slate-500"&gt;No data available&lt;/p&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Tooltip Info Icon --}}
    <div>
        <h3 class="text-lg font-semibold text-white mb-2">Tooltip Info Icons</h3>
        <p class="text-sm text-slate-400 mb-4">Small question-mark icons next to labels that show explanatory tooltips on hover. Used throughout the finances page.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-3">
            <div class="flex items-center gap-1.5">
                <span class="text-sm text-slate-400">TV Rights</span>
                <svg x-data="" x-tooltip.raw="Revenue from television broadcasting rights based on league position" class="w-3.5 h-3.5 text-slate-600 hover:text-slate-400 cursor-help shrink-0 transition-colors" fill="currentColor" viewBox="0 0 512 512">
                    <path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/>
                </svg>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.tooltipCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="tooltipCode">&lt;span class="text-sm text-slate-400 flex items-center gap-1.5"&gt;
    Label
    &lt;svg x-data="" x-tooltip.raw="Explanation text"
         class="w-3.5 h-3.5 text-slate-600 hover:text-slate-400 cursor-help shrink-0"
         fill="currentColor" viewBox="0 0 512 512"&gt;
        &lt;!-- question mark circle icon --&gt;
    &lt;/svg&gt;
&lt;/span&gt;</code></pre>
        </div>
    </div>
</section>
