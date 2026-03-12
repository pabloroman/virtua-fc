<section id="data-viz" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-white mb-2">Data Visualization</h2>
    <p class="text-sm text-slate-400 mb-8">Progress bars, stat indicators, and interactive sliders for player attributes and financial data.</p>

    {{-- Ability Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Ability Bar</h3>
        <p class="text-sm text-slate-400 mb-4">Displays a player stat value with a colored progress bar. Color-coded by threshold: green (80+), lime (70+), amber (60+), slate (below 60). Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">x-ability-bar</code> component.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 space-y-4 mb-3">
            <div>
                <div class="text-xs text-slate-500 mb-2">Size: md (default)</div>
                <div class="space-y-2">
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-500 w-20">Excellent</span>
                        <x-ability-bar :value="88" size="md" class="text-xs font-medium text-emerald-400" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-500 w-20">Good</span>
                        <x-ability-bar :value="74" size="md" class="text-xs font-medium text-lime-400" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-500 w-20">Average</span>
                        <x-ability-bar :value="63" size="md" class="text-xs font-medium text-amber-400" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-500 w-20">Low</span>
                        <x-ability-bar :value="45" size="md" class="text-xs font-medium text-slate-400" />
                    </div>
                </div>
            </div>
            <div>
                <div class="text-xs text-slate-500 mb-2">Size: sm (compact, for tables)</div>
                <div class="space-y-2">
                    <div class="flex items-center gap-4">
                        <x-ability-bar :value="85" size="sm" class="text-xs font-medium text-emerald-400" />
                        <x-ability-bar :value="72" size="sm" class="text-xs font-medium text-lime-400" />
                        <x-ability-bar :value="58" size="sm" class="text-xs font-medium text-slate-400" />
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-ability-bar :value="$player-&gt;technical_ability" size="sm" /&gt;
&lt;x-ability-bar :value="85" size="md" class="text-xs font-medium text-emerald-400" /&gt;</code></pre>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-white/10">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Prop</th>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Type</th>
                        <th class="font-semibold py-2 pr-4 text-slate-300">Default</th>
                        <th class="font-semibold py-2 text-slate-300">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">value</td>
                        <td class="py-2 pr-4 text-slate-400">int</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-400">required</td>
                        <td class="py-2 text-slate-400">Current ability value</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">max</td>
                        <td class="py-2 pr-4 text-slate-400">int</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-400">99</td>
                        <td class="py-2 text-slate-400">Maximum value for percentage calculation</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">showValue</td>
                        <td class="py-2 pr-4 text-slate-400">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-400">true</td>
                        <td class="py-2 text-slate-400">Show numeric value beside bar</td>
                    </tr>
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">size</td>
                        <td class="py-2 pr-4 text-slate-400">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-slate-400">'md'</td>
                        <td class="py-2 text-slate-400">sm | md</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Stat Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Stat Bar</h3>
        <p class="text-sm text-slate-400 mb-4">Thin 3px stat bar using <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">.stat-bar-track</code> and <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">.stat-bar-fill</code> CSS classes. Track background is <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">rgba(255,255,255,0.06)</code> for minimal visibility on dark surfaces.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 space-y-4 mb-3">
            <div class="max-w-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 w-16">Pace</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-blue" style="width: 82%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-white w-6 text-right">82</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 w-16">Shooting</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-green" style="width: 75%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-white w-6 text-right">75</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 w-16">Passing</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-gold" style="width: 68%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-white w-6 text-right">68</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 w-16">Defense</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-red" style="width: 42%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-white w-6 text-right">42</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 w-16">Physical</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-orange" style="width: 58%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-white w-6 text-right">58</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="stat-bar-track"&gt;
    &lt;div class="stat-bar-fill bg-accent-blue" style="width: 82%"&gt;&lt;/div&gt;
&lt;/div&gt;

{{-- .stat-bar-track: height 3px, bg rgba(255,255,255,0.06) --}}
{{-- .stat-bar-fill: height 100%, animated width transition --}}</code></pre>
        </div>
    </div>

    {{-- Fitness Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Fitness Bar</h3>
        <p class="text-sm text-slate-400 mb-4">Thin bar for player fitness levels. Uses <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">bg-surface-600</code> track with color-coded fill: green for healthy, amber for caution, red for low fitness.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 space-y-4 mb-3">
            <div class="max-w-xs space-y-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 w-16">Healthy</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-accent-green fitness-bar" style="width: 92%"></div>
                    </div>
                    <span class="text-xs font-semibold text-accent-green w-8 text-right">92%</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 w-16">Caution</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-amber-500 fitness-bar" style="width: 65%"></div>
                    </div>
                    <span class="text-xs font-semibold text-amber-500 w-8 text-right">65%</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 w-16">Low</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-accent-red fitness-bar" style="width: 28%"></div>
                    </div>
                    <span class="text-xs font-semibold text-accent-red w-8 text-right">28%</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="h-1.5 bg-surface-600 rounded-full overflow-hidden"&gt;
    &lt;div class="h-full rounded-full bg-accent-green fitness-bar" style="width: 92%"&gt;&lt;/div&gt;
&lt;/div&gt;

{{-- Color thresholds --}}
{{-- 70%+  : bg-accent-green (healthy) --}}
{{-- 40-69%: bg-amber-500 (caution) --}}
{{-- &lt;40% : bg-accent-red (low) --}}</code></pre>
        </div>
    </div>

    {{-- Progress Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Progress Bar</h3>
        <p class="text-sm text-slate-400 mb-4">Generic percentage bar for wage/revenue ratios and other metrics. Same structure as fitness bar but used for non-player data.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 space-y-4 mb-3">
            <div class="max-w-xs space-y-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 w-24">Wage ratio</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-accent-green" style="width: 45%"></div>
                    </div>
                    <span class="text-xs font-semibold text-white w-8 text-right">45%</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 w-24">Budget used</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-amber-500" style="width: 62%"></div>
                    </div>
                    <span class="text-xs font-semibold text-amber-400 w-8 text-right">62%</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 w-24">Squad cap</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-accent-red" style="width: 95%"></div>
                    </div>
                    <span class="text-xs font-semibold text-accent-red w-8 text-right">95%</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="h-1.5 bg-surface-600 rounded-full overflow-hidden"&gt;
    &lt;div class="h-full rounded-full bg-accent-green" style="width: 45%"&gt;&lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Tier Dots --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Tier Dots</h3>
        <p class="text-sm text-slate-400 mb-4">Used for infrastructure investment levels (1-4 tiers). Filled dots use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">bg-accent-green</code>, empty dots use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">bg-surface-600</code>.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 space-y-4 mb-3">
            @foreach([
                ['label' => 'Youth Academy', 'tier' => 3, 'cost' => '1.8M'],
                ['label' => 'Medical', 'tier' => 2, 'cost' => '1.2M'],
                ['label' => 'Scouting', 'tier' => 4, 'cost' => '2.4M'],
                ['label' => 'Facilities', 'tier' => 1, 'cost' => '0.6M'],
            ] as $item)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-white">{{ $item['label'] }}</span>
                    <span class="text-xs text-slate-500">&euro;{{ $item['cost'] }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    @for($i = 1; $i <= 4; $i++)
                        <span class="w-2.5 h-2.5 rounded-full {{ $i <= $item['tier'] ? 'bg-accent-green' : 'bg-surface-600' }}"></span>
                    @endfor
                    <span class="text-xs text-slate-500 ml-1">Tier {{ $item['tier'] }}</span>
                </div>
            </div>
            @endforeach
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="flex items-center gap-1.5"&gt;
    @@for($i = 1; $i &lt;= 4; $i++)
        &lt;span class="w-2.5 h-2.5 rounded-full &#123;&#123; $i &lt;= $tier ? 'bg-accent-green' : 'bg-surface-600' &#125;&#125;"&gt;&lt;/span&gt;
    @@endfor
    &lt;span class="text-xs text-slate-500 ml-1"&gt;Tier &#123;&#123; $tier &#125;&#125;&lt;/span&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Range Slider --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-white mb-2">Range Slider</h3>
        <p class="text-sm text-slate-400 mb-4">Custom-styled range input using the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">.tier-range</code> CSS class. Accent-blue thumb with <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">bg-surface-600</code> track.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-3" x-data="{ value: 2 }">
            <div class="max-w-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-white">Investment Level</span>
                    <span class="text-sm font-semibold text-accent-blue" x-text="'Tier ' + value">Tier 2</span>
                </div>
                <div class="tier-range">
                    <div class="track"></div>
                    <div class="track-fill" :style="'width: ' + ((value / 4) * 100) + '%'"></div>
                    <input type="range" min="0" max="4" step="1" x-model="value">
                </div>
                <div class="flex justify-between text-[10px] text-slate-500 mt-1">
                    <span>0</span><span>1</span><span>2</span><span>3</span><span>4</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="tier-range"&gt;
    &lt;div class="track"&gt;&lt;/div&gt;
    &lt;div class="track-fill" :style="'width: ' + pct + '%'"&gt;&lt;/div&gt;
    &lt;input type="range" min="0" max="4" step="1" x-model="value"&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Morale Dot --}}
    <div>
        <h3 class="text-lg font-semibold text-white mb-2">Morale Dot</h3>
        <p class="text-sm text-slate-400 mb-4">8px colored circle indicator using the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded text-slate-300">.morale-dot</code> CSS class. Used inline to show player morale at a glance.</p>

        <div class="bg-surface-700/30 border border-white/5 rounded-xl p-6 mb-3">
            <div class="flex flex-wrap items-center gap-6">
                <div class="flex items-center gap-2">
                    <span class="morale-dot bg-accent-green"></span>
                    <span class="text-xs text-slate-400">High</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="morale-dot bg-lime-500"></span>
                    <span class="text-xs text-slate-400">Good</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="morale-dot bg-accent-gold"></span>
                    <span class="text-xs text-slate-400">Neutral</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="morale-dot bg-accent-orange"></span>
                    <span class="text-xs text-slate-400">Low</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="morale-dot bg-accent-red"></span>
                    <span class="text-xs text-slate-400">Very Low</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-slate-400 hover:text-slate-200 bg-surface-600 rounded transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-slate-300 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;span class="morale-dot bg-accent-green"&gt;&lt;/span&gt;

{{-- .morale-dot: 8px circle (width, height, border-radius: 50%) --}}
{{-- Colors: bg-accent-green, bg-lime-500, bg-accent-gold, bg-accent-orange, bg-accent-red --}}</code></pre>
        </div>
    </div>
</section>
