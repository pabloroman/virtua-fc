<section id="navigation" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Navigation</h2>
    <p class="text-slate-500 mb-8">Navigation components for top bars, tabs, and menus. Sky-500 is the active indicator color throughout.</p>

    {{-- Section Nav (Tabs) --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Section Nav (Tabs)</h3>
        <p class="text-sm text-slate-500 mb-4">Horizontal scrollable tab navigation for sub-sections within a page. Supports optional badge counts.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <x-section-nav :items="[
                ['href' => '#', 'label' => 'Squad', 'active' => true],
                ['href' => '#', 'label' => 'Development', 'active' => false],
                ['href' => '#', 'label' => 'Stats', 'active' => false],
                ['href' => '#', 'label' => 'Academy', 'active' => false, 'badge' => 3],
            ]" />
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-section-nav :items="[
    ['href' =&gt; route('squad'), 'label' =&gt; 'Squad', 'active' =&gt; true],
    ['href' =&gt; route('stats'), 'label' =&gt; 'Stats', 'active' =&gt; false],
    ['href' =&gt; route('academy'), 'label' =&gt; 'Academy', 'active' =&gt; false, 'badge' =&gt; 3],
]" /&gt;</code></pre>
        </div>

        <div class="overflow-x-auto">
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
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">items</td>
                        <td class="py-2 pr-4 text-slate-500">array</td>
                        <td class="py-2 text-slate-500">Array of {href, label, active, badge?}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Nav Link --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Nav Link (Desktop)</h3>
        <p class="text-sm text-slate-500 mb-4">Desktop navigation links with bottom border active indicator.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex gap-4 border-b border-slate-200 pb-1">
                <x-nav-link href="#" :active="true">Dashboard</x-nav-link>
                <x-nav-link href="#" :active="false">Squad</x-nav-link>
                <x-nav-link href="#" :active="false">Finances</x-nav-link>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-nav-link :href="route('dashboard')" :active="request()-&gt;routeIs('dashboard')"&gt;
    Dashboard
&lt;/x-nav-link&gt;</code></pre>
        </div>
    </div>

    {{-- Responsive Nav Link --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Responsive Nav Link (Mobile)</h3>
        <p class="text-sm text-slate-500 mb-4">Mobile-oriented navigation links with left border active indicator. Used inside the mobile drawer.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="max-w-xs border border-slate-200 rounded-lg overflow-hidden">
                <x-responsive-nav-link href="#" :active="true">Dashboard</x-responsive-nav-link>
                <x-responsive-nav-link href="#" :active="false">Squad</x-responsive-nav-link>
                <x-responsive-nav-link href="#" :active="false">Finances</x-responsive-nav-link>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-responsive-nav-link :href="route('dashboard')" :active="true"&gt;
    Dashboard
&lt;/x-responsive-nav-link&gt;</code></pre>
        </div>
    </div>

    {{-- Dropdown --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Dropdown</h3>
        <p class="text-sm text-slate-500 mb-4">Alpine.js powered dropdown menu with click-outside close and smooth scale transitions.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <x-dropdown align="left" width="48">
                <x-slot name="trigger">
                    <x-secondary-button type="button">
                        Options
                        <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </x-secondary-button>
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
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-dropdown align="left" width="48"&gt;
    &lt;x-slot name="trigger"&gt;
        &lt;x-secondary-button&gt;Options&lt;/x-secondary-button&gt;
    &lt;/x-slot&gt;
    &lt;x-slot name="content"&gt;
        &lt;x-dropdown-link href="#"&gt;Profile&lt;/x-dropdown-link&gt;
        &lt;x-dropdown-link href="#"&gt;Settings&lt;/x-dropdown-link&gt;
    &lt;/x-slot&gt;
&lt;/x-dropdown&gt;</code></pre>
        </div>
    </div>

    {{-- Context Menu Pattern --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Context Menu (Three-Dot)</h3>
        <p class="text-sm text-slate-500 mb-4">Inline action menu used in table rows. Uses Alpine.js for toggle state.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div x-data="{ open: false }" @click.outside="open = false" class="relative inline-block">
                <button @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                </button>
                <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="absolute left-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 py-1" style="display: none;">
                    <button class="w-full text-left px-4 py-2 text-sm text-sky-600 hover:bg-slate-100">List for sale</button>
                    <button class="w-full text-left px-4 py-2 text-sm text-amber-600 hover:bg-slate-100">Loan out</button>
                    <button class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-slate-100">Remove</button>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div x-data="{ open: false }" @click.outside="open = false" class="relative inline-block"&gt;
    &lt;button @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100"&gt;
        &lt;!-- three-dot SVG icon --&gt;
    &lt;/button&gt;
    &lt;div x-show="open" x-transition class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 py-1"&gt;
        &lt;button class="w-full text-left px-4 py-2 text-sm text-sky-600 hover:bg-slate-100"&gt;Action&lt;/button&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
