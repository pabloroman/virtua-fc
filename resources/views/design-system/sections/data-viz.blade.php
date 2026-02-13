<section id="data-viz" class="mb-20">
    <h2 class="text-3xl font-bold text-slate-900 mb-2">Data Visualization</h2>
    <p class="text-slate-500 mb-8">Progress bars, stat indicators, and interactive sliders for player attributes and financial data.</p>

    {{-- Ability Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Ability Bar</h3>
        <p class="text-sm text-slate-500 mb-4">Displays a player stat value with a colored progress bar. Color-coded by threshold: green (80+), lime (70+), amber (60+), slate (below 60).</p>

        <div class="border border-slate-200 rounded-lg p-6 space-y-4 mb-3">
            <div>
                <div class="text-xs text-slate-400 mb-2">Size: md (default)</div>
                <div class="space-y-2">
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-500 w-20">Excellent</span>
                        <x-ability-bar :value="88" size="md" class="text-xs font-medium text-green-600" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-500 w-20">Good</span>
                        <x-ability-bar :value="74" size="md" class="text-xs font-medium text-lime-600" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-500 w-20">Average</span>
                        <x-ability-bar :value="63" size="md" class="text-xs font-medium" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-500 w-20">Low</span>
                        <x-ability-bar :value="45" size="md" class="text-xs font-medium text-slate-400" />
                    </div>
                </div>
            </div>
            <div>
                <div class="text-xs text-slate-400 mb-2">Size: sm (compact, for tables)</div>
                <div class="space-y-2">
                    <div class="flex items-center gap-4">
                        <x-ability-bar :value="85" size="sm" class="text-xs font-medium text-green-600" />
                        <x-ability-bar :value="72" size="sm" class="text-xs font-medium text-lime-600" />
                        <x-ability-bar :value="58" size="sm" class="text-xs font-medium text-slate-400" />
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-ability-bar :value="$player-&gt;technical_ability" size="sm" /&gt;
&lt;x-ability-bar :value="85" size="md" class="text-xs font-medium text-green-600" /&gt;</code></pre>
        </div>

        <div class="overflow-x-auto">
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
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">value</td>
                        <td class="py-2 pr-4 text-slate-500">int</td>
                        <td class="py-2 pr-4 font-mono text-xs">required</td>
                        <td class="py-2 text-slate-500">Current ability value</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">max</td>
                        <td class="py-2 pr-4 text-slate-500">int</td>
                        <td class="py-2 pr-4 font-mono text-xs">99</td>
                        <td class="py-2 text-slate-500">Maximum value for percentage calculation</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">showValue</td>
                        <td class="py-2 pr-4 text-slate-500">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs">true</td>
                        <td class="py-2 text-slate-500">Show numeric value beside bar</td>
                    </tr>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-mono text-xs text-sky-600">size</td>
                        <td class="py-2 pr-4 text-slate-500">string</td>
                        <td class="py-2 pr-4 font-mono text-xs">'md'</td>
                        <td class="py-2 text-slate-500">sm | md</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Progress Bar Pattern --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Progress Bar Pattern</h3>
        <p class="text-sm text-slate-500 mb-4">Generic progress bar pattern used for wage/revenue ratios and other percentage metrics.</p>

        <div class="border border-slate-200 rounded-lg p-6 space-y-4 mb-3">
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500 w-20">Healthy</span>
                <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500" style="width: 45%"></div>
                </div>
                <span class="text-xs font-semibold text-slate-900">45%</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500 w-20">Caution</span>
                <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-amber-500" style="width: 62%"></div>
                </div>
                <span class="text-xs font-semibold text-amber-600">62%</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500 w-20">Critical</span>
                <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-red-500" style="width: 78%"></div>
                </div>
                <span class="text-xs font-semibold text-red-600">78%</span>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden"&gt;
    &lt;div class="h-full rounded-full bg-emerald-500" style="width: 45%"&gt;&lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Tier Dots --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Tier Dots</h3>
        <p class="text-sm text-slate-500 mb-4">Used for infrastructure investment levels (1-4 tiers).</p>

        <div class="border border-slate-200 rounded-lg p-6 space-y-4 mb-3">
            @foreach([
                ['label' => 'Youth Academy', 'tier' => 3],
                ['label' => 'Medical', 'tier' => 2],
                ['label' => 'Scouting', 'tier' => 4],
                ['label' => 'Facilities', 'tier' => 1],
            ] as $item)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ $item['label'] }}</span>
                    <span class="text-xs text-slate-400">&euro;1.2M</span>
                </div>
                <div class="flex items-center gap-1.5">
                    @for($i = 1; $i <= 4; $i++)
                        <span class="w-2.5 h-2.5 rounded-full {{ $i <= $item['tier'] ? 'bg-emerald-500' : 'bg-slate-200' }}"></span>
                    @endfor
                    <span class="text-xs text-slate-500 ml-1">Tier {{ $item['tier'] }}</span>
                </div>
            </div>
            @endforeach
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="flex items-center gap-1.5"&gt;
    @for($i = 1; $i <= 4; $i++)
        &lt;span class="w-2.5 h-2.5 rounded-full &#123;&#123; $i &lt;= $tier ? 'bg-emerald-500' : 'bg-slate-200' &#125;&#125;"&gt;&lt;/span&gt;
    @endfor
    &lt;span class="text-xs text-slate-500 ml-1"&gt;Tier &#123;&#123; $tier &#125;&#125;&lt;/span&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Range Sliders --}}
    <div>
        <h3 class="text-lg font-semibold text-slate-900 mb-2">Range Sliders</h3>
        <p class="text-sm text-slate-500 mb-4">Custom-styled range inputs using the <code class="text-xs bg-slate-100 px-1 py-0.5 rounded text-slate-700">.tier-range</code> CSS class. Sky-500 thumb with slate-200 track.</p>

        <div class="border border-slate-200 rounded-lg p-6 mb-3" x-data="{ value: 2 }">
            <div class="max-w-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-slate-700">Investment Level</span>
                    <span class="text-sm font-semibold text-slate-900" x-text="'Tier ' + value">Tier 2</span>
                </div>
                <div class="tier-range">
                    <div class="track"></div>
                    <div class="track-fill" :style="'width: ' + ((value / 4) * 100) + '%'"></div>
                    <input type="range" min="0" max="4" step="1" x-model="value">
                </div>
                <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                    <span>0</span><span>1</span><span>2</span><span>3</span><span>4</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-slate-700 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
            </button>
            <pre class="bg-slate-800 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="tier-range"&gt;
    &lt;div class="track"&gt;&lt;/div&gt;
    &lt;div class="track-fill" :style="'width: ' + pct + '%'"&gt;&lt;/div&gt;
    &lt;input type="range" min="0" max="4" step="1" x-model="value"&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
