<section id="badges" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Badges & Pills</h2>
    <p class="text-slate-500 mb-8">Compact indicators for status, categories, position, and scores. All use rounded shapes and small text sizes.</p>

    {{-- Status Pills --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Status Pills</h3>
        <p class="text-sm text-slate-500 mb-4">Rounded-full pills for categorization and status labels.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800">Active</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-800">Pending</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-700">Rejected</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-sky-100 text-sky-800">Info</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-700">Default</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">On Loan</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800"&gt;
    Active
&lt;/span&gt;</code></pre>
        </div>
    </div>

    {{-- Competition Badges --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Competition Badges</h3>
        <p class="text-sm text-slate-500 mb-4">Color-coded by competition role: domestic league, domestic cup, or European.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex flex-wrap gap-3">
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">La Liga</span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">Copa del Rey</span>
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Champions League</span>
            </div>
        </div>
    </div>

    {{-- Form Result Badges --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Form Result Badges</h3>
        <p class="text-sm text-slate-500 mb-4">Win/Draw/Loss indicators for recent match form.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex gap-1">
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center bg-green-500 text-white">W</span>
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center bg-green-500 text-white">W</span>
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center bg-slate-400 text-white">D</span>
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center bg-red-500 text-white">L</span>
                <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center bg-green-500 text-white">W</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center bg-green-500 text-white"&gt;W&lt;/span&gt;
&lt;span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center bg-slate-400 text-white"&gt;D&lt;/span&gt;
&lt;span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center bg-red-500 text-white"&gt;L&lt;/span&gt;</code></pre>
        </div>
    </div>

    {{-- Overall Score Circles --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Overall Score Circles</h3>
        <p class="text-sm text-slate-500 mb-4">Color-coded by value threshold: emerald (80+), lime (70+), amber (60+), slate (below 60).</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex flex-wrap gap-3 items-center">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-emerald-500 text-white">88</span>
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-lime-500 text-white">74</span>
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-amber-500 text-white">63</span>
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-slate-300 text-slate-700">52</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-emerald-500 text-white"&gt;88&lt;/span&gt;</code></pre>
        </div>
    </div>

    {{-- Position Badges --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Position Badges</h3>
        <p class="text-sm text-slate-500 mb-4">Skewed badge design with position-specific colors. Uses the <code class="text-xs bg-slate-100 px-1 py-0.5 rounded text-slate-700">x-position-badge</code> component with <code class="text-xs bg-slate-100 px-1 py-0.5 rounded text-slate-700">PositionMapper</code> for colors.</p>

        <div class="border border-slate-200 rounded-lg p-6 space-y-4 mb-3">
            {{-- Size variants --}}
            <div>
                <div class="text-xs text-slate-400 mb-2">Sizes</div>
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <x-position-badge abbreviation="GK" size="sm" />
                        <div class="text-[10px] text-slate-400 mt-1">sm</div>
                    </div>
                    <div class="text-center">
                        <x-position-badge abbreviation="GK" size="md" />
                        <div class="text-[10px] text-slate-400 mt-1">md</div>
                    </div>
                    <div class="text-center">
                        <x-position-badge abbreviation="GK" size="lg" />
                        <div class="text-[10px] text-slate-400 mt-1">lg</div>
                    </div>
                </div>
            </div>

            {{-- Position variants --}}
            <div>
                <div class="text-xs text-slate-400 mb-2">All positions</div>
                <div class="flex flex-wrap gap-2">
                    @foreach(['GK', 'CB', 'LB', 'RB', 'DM', 'CM', 'AM', 'LW', 'RW', 'CF'] as $pos)
                        <x-position-badge abbreviation="{{ $pos }}" size="md" />
                    @endforeach
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-position-badge abbreviation="GK" size="md" /&gt;
&lt;x-position-badge abbreviation="CB" size="sm" /&gt;
&lt;x-position-badge position="Goalkeeper" size="lg" tooltip="Goalkeeper" /&gt;</code></pre>
        </div>

        {{-- Props table --}}
        <div class="overflow-x-auto mt-4">
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
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">abbreviation</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">null</td>
                        <td class="py-2 text-slate-500">Position abbreviation (GK, CB, etc.)</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">position</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">null</td>
                        <td class="py-2 text-slate-500">Full position name (uses PositionMapper)</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">size</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">'md'</td>
                        <td class="py-2 text-slate-500">sm | md | lg</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">tooltip</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">null</td>
                        <td class="py-2 text-slate-500">Tooltip text shown on hover</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Notification Count --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Notification Count Badge</h3>
        <p class="text-sm text-slate-500 mb-4">Small red circle for unread notification counts.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3">
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-700">Inbox</span>
                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">3</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-700">Updates</span>
                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">9+</span>
                </div>
            </div>
        </div>
    </div>
</section>
