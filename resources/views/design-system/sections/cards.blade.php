<section id="cards" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Cards & Containers</h2>
    <p class="text-slate-500 mb-8">Container patterns for grouping content. Cards use subtle borders and optional headers to create visual hierarchy.</p>

    {{-- Standard Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Standard Card</h3>
        <p class="text-sm text-slate-500 mb-4">The most common card pattern. A bordered container with a slate-50 header and white body.</p>

        <div class="border border-slate-200 rounded-lg p-6 bg-slate-50 mb-3">
            <div class="border rounded-lg overflow-hidden bg-white">
                <div class="px-5 py-3 bg-slate-50 border-b flex items-center justify-between">
                    <h4 class="font-semibold text-sm text-slate-900">Card Title</h4>
                    <span class="text-xs text-slate-400">Metadata</span>
                </div>
                <div class="px-5 py-4">
                    <p class="text-sm text-slate-500">Card content goes here. This is the standard card pattern used for budget flow, transaction history, and data sections.</p>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="border rounded-lg overflow-hidden"&gt;
    &lt;div class="px-5 py-3 bg-slate-50 border-b flex items-center justify-between"&gt;
        &lt;h4 class="font-semibold text-sm text-slate-900"&gt;Title&lt;/h4&gt;
        &lt;span class="text-xs text-slate-400"&gt;Metadata&lt;/span&gt;
    &lt;/div&gt;
    &lt;div class="px-5 py-4"&gt;
        &lt;!-- Content --&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Page Panel --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Page Panel</h3>
        <p class="text-sm text-slate-500 mb-4">The outer white container used on every page. Has shadow and rounded corners on larger screens.</p>

        <div class="rounded-lg p-6 bg-gradient-to-bl from-slate-900 via-cyan-950 to-teal-950 mb-3">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <p class="text-sm text-slate-500">Page content within the white panel, against the dark gradient background.</p>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="bg-white overflow-hidden shadow-sm sm:rounded-lg"&gt;
    &lt;div class="p-6 sm:p-8"&gt;
        &lt;!-- Page content --&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Bordered Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Simple Bordered Card</h3>
        <p class="text-sm text-slate-500 mb-4">A minimal card with just a border and padding. Used for infrastructure items and simple groupings.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="border rounded-lg p-4">
                    <div class="font-semibold text-sm text-slate-900">Squad Size</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">23</div>
                    <div class="text-xs text-slate-400 mt-1">Players registered</div>
                </div>
                <div class="border rounded-lg p-4">
                    <div class="font-semibold text-sm text-slate-900">Avg. Age</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">26.4</div>
                    <div class="text-xs text-slate-400 mt-1">Years</div>
                </div>
                <div class="border rounded-lg p-4">
                    <div class="font-semibold text-sm text-slate-900">Overall Rating</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">74</div>
                    <div class="text-xs text-slate-400 mt-1">Average ability</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dark Gradient Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Dark Gradient Card</h3>
        <p class="text-sm text-slate-500 mb-4">Used for featured values like squad value in the finances sidebar.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="rounded-lg overflow-hidden border border-slate-200 max-w-sm">
                <div class="bg-gradient-to-br from-slate-800 to-slate-900 px-4 py-5">
                    <div class="text-xs text-slate-400 uppercase mb-1">Squad Value</div>
                    <div class="text-2xl font-bold text-white">&euro;245.8M</div>
                </div>
                <div class="divide-y divide-slate-100">
                    <div class="px-4 py-3 flex items-center justify-between">
                        <span class="text-sm text-slate-500">Wage Bill</span>
                        <span class="text-sm font-semibold text-slate-900">&euro;68.2M/yr</span>
                    </div>
                    <div class="px-4 py-3 flex items-center justify-between">
                        <span class="text-sm text-slate-500">Transfer Budget</span>
                        <span class="text-sm font-semibold text-slate-900">&euro;12.5M</span>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="rounded-lg overflow-hidden border border-slate-200"&gt;
    &lt;div class="bg-gradient-to-br from-slate-800 to-slate-900 px-4 py-5"&gt;
        &lt;div class="text-xs text-slate-400 uppercase mb-1"&gt;Label&lt;/div&gt;
        &lt;div class="text-2xl font-bold text-white"&gt;Value&lt;/div&gt;
    &lt;/div&gt;
    &lt;div class="divide-y divide-slate-100"&gt;
        &lt;div class="px-4 py-3 flex items-center justify-between"&gt;...&lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Accent Border Card --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Accent Border Card</h3>
        <p class="text-sm text-slate-500 mb-4">Left border accent pattern used for next match preview and competition grouping. Color indicates competition role.</p>

        <div class="border border-slate-200 rounded-lg p-6 space-y-4 mb-3">
            <div class="border-l-4 border-l-amber-500 pl-6 py-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Domestic League</span>
                <div class="text-sm text-slate-700 mt-1">La Liga &middot; Matchday 12</div>
            </div>
            <div class="border-l-4 border-l-emerald-500 pl-6 py-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Domestic Cup</span>
                <div class="text-sm text-slate-700 mt-1">Copa del Rey &middot; Round of 16</div>
            </div>
            <div class="border-l-4 border-l-blue-600 pl-6 py-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">European</span>
                <div class="text-sm text-slate-700 mt-1">Champions League &middot; Group Stage</div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="border-l-4 border-l-amber-500 pl-6"&gt;
    &lt;!-- Domestic league content --&gt;
&lt;/div&gt;
&lt;div class="border-l-4 border-l-emerald-500 pl-6"&gt;
    &lt;!-- Domestic cup content --&gt;
&lt;/div&gt;
&lt;div class="border-l-4 border-l-blue-600 pl-6"&gt;
    &lt;!-- European content --&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
