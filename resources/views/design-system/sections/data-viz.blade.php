<section id="data-viz" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Data Visualization</h2>
    <p class="text-sm text-text-secondary mb-8">Progress bars, stat indicators, and interactive sliders for player attributes and financial data.</p>

    {{-- Ability Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Ability Bar</h3>
        <p class="text-sm text-text-secondary mb-4">Displays a player stat value with a colored progress bar. Color-coded by threshold: green (80+), lime (70+), amber (60+), slate (below 60). Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-ability-bar</code> component.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-4 mb-3">
            <div>
                <div class="text-xs text-text-muted mb-2">Size: md (default)</div>
                <div class="space-y-2">
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-text-muted w-20">Excellent</span>
                        <x-ability-bar :value="88" size="md" class="text-xs font-medium text-emerald-400" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-text-muted w-20">Good</span>
                        <x-ability-bar :value="74" size="md" class="text-xs font-medium text-lime-400" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-text-muted w-20">Average</span>
                        <x-ability-bar :value="63" size="md" class="text-xs font-medium text-amber-400" />
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-text-muted w-20">Low</span>
                        <x-ability-bar :value="45" size="md" class="text-xs font-medium text-text-secondary" />
                    </div>
                </div>
            </div>
            <div>
                <div class="text-xs text-text-muted mb-2">Size: sm (compact, for tables)</div>
                <div class="space-y-2">
                    <div class="flex items-center gap-4">
                        <x-ability-bar :value="85" size="sm" class="text-xs font-medium text-emerald-400" />
                        <x-ability-bar :value="72" size="sm" class="text-xs font-medium text-lime-400" />
                        <x-ability-bar :value="58" size="sm" class="text-xs font-medium text-text-secondary" />
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative mb-4">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;x-ability-bar :value="$player-&gt;technical_ability" size="sm" /&gt;
&lt;x-ability-bar :value="85" size="md" class="text-xs font-medium text-emerald-400" /&gt;</code></pre>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-strong">
                    <tr>
                        <th class="font-semibold py-2 pr-4 text-text-body">Prop</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Type</th>
                        <th class="font-semibold py-2 pr-4 text-text-body">Default</th>
                        <th class="font-semibold py-2 text-text-body">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">value</td>
                        <td class="py-2 pr-4 text-text-secondary">int</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">required</td>
                        <td class="py-2 text-text-secondary">Current ability value</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">max</td>
                        <td class="py-2 pr-4 text-text-secondary">int</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">99</td>
                        <td class="py-2 text-text-secondary">Maximum value for percentage calculation</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">showValue</td>
                        <td class="py-2 pr-4 text-text-secondary">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">true</td>
                        <td class="py-2 text-text-secondary">Show numeric value beside bar</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">size</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-secondary">'md'</td>
                        <td class="py-2 text-text-secondary">sm | md</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Stat Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Stat Bar</h3>
        <p class="text-sm text-text-secondary mb-4">Thin 3px stat bar using <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">.stat-bar-track</code> and <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">.stat-bar-fill</code> CSS classes. Track background is <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">rgba(255,255,255,0.06)</code> for minimal visibility on dark surfaces.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-4 mb-3">
            <div class="max-w-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text-secondary w-16">Pace</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-blue" style="width: 82%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-text-primary w-6 text-right">82</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text-secondary w-16">Shooting</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-green" style="width: 75%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-text-primary w-6 text-right">75</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text-secondary w-16">Passing</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-gold" style="width: 68%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-text-primary w-6 text-right">68</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text-secondary w-16">Defense</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-red" style="width: 42%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-text-primary w-6 text-right">42</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text-secondary w-16">Physical</span>
                    <div class="flex-1 mx-3">
                        <div class="stat-bar-track">
                            <div class="stat-bar-fill bg-accent-orange" style="width: 58%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-text-primary w-6 text-right">58</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="stat-bar-track"&gt;
    &lt;div class="stat-bar-fill bg-accent-blue" style="width: 82%"&gt;&lt;/div&gt;
&lt;/div&gt;

{{-- .stat-bar-track: height 3px, bg rgba(255,255,255,0.06) --}}
{{-- .stat-bar-fill: height 100%, animated width transition --}}</code></pre>
        </div>
    </div>

    {{-- Fitness Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Fitness Bar</h3>
        <p class="text-sm text-text-secondary mb-4">Inline bar for player fitness levels. Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-fitness-bar</code> component with automatic color thresholds: green (80+), gold (60+), orange (40+), red (&lt;40).</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-4 mb-3">
            <div class="max-w-xs space-y-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary w-16">Healthy</span>
                    <x-fitness-bar :value="92" />
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary w-16">Caution</span>
                    <x-fitness-bar :value="65" />
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary w-16">Tired</span>
                    <x-fitness-bar :value="45" />
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary w-16">Low</span>
                    <x-fitness-bar :value="28" />
                </div>
            </div>

            {{-- Variants --}}
            <div class="mt-6 pt-4 border-t border-border-default">
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">Variants</div>
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-text-secondary w-28">With label</span>
                        <x-fitness-bar :value="85" :show-label="true" />
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-text-secondary w-28">No percentage</span>
                        <x-fitness-bar :value="85" :show-percentage="false" />
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-text-secondary w-28">Small size</span>
                        <x-fitness-bar :value="85" size="sm" :show-label="true" :show-percentage="false" />
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.fitCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="fitCode">&lt;!-- Basic usage --&gt;
&lt;x-fitness-bar :value="$player->fitness" /&gt;

&lt;!-- With FIT label, no percentage --&gt;
&lt;x-fitness-bar :value="85" :show-label="true" :show-percentage="false" /&gt;

&lt;!-- Small size (for compact tables) --&gt;
&lt;x-fitness-bar :value="85" size="sm" /&gt;</code></pre>
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
                        <td class="py-2 text-text-secondary">Fitness percentage (0-100)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">showLabel</td>
                        <td class="py-2 pr-4 text-text-secondary">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">false</td>
                        <td class="py-2 text-text-secondary">Show "FIT" prefix label</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">showPercentage</td>
                        <td class="py-2 pr-4 text-text-secondary">bool</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">true</td>
                        <td class="py-2 text-text-secondary">Show percentage number</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">size</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">'md'</td>
                        <td class="py-2 text-text-secondary">sm | md</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Progress Bar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Progress Bar</h3>
        <p class="text-sm text-text-secondary mb-4">Generic percentage bar for wage/revenue ratios and other metrics. Same structure as fitness bar but used for non-player data.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-4 mb-3">
            <div class="max-w-xs space-y-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary w-24">Wage ratio</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-accent-green" style="width: 45%"></div>
                    </div>
                    <span class="text-xs font-semibold text-text-primary w-8 text-right">45%</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary w-24">Budget used</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-amber-500" style="width: 62%"></div>
                    </div>
                    <span class="text-xs font-semibold text-amber-400 w-8 text-right">62%</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary w-24">Squad cap</span>
                    <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-accent-red" style="width: 95%"></div>
                    </div>
                    <span class="text-xs font-semibold text-accent-red w-8 text-right">95%</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="h-1.5 bg-surface-600 rounded-full overflow-hidden"&gt;
    &lt;div class="h-full rounded-full bg-accent-green" style="width: 45%"&gt;&lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Tier Dots --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Tier Dots</h3>
        <p class="text-sm text-text-secondary mb-4">Used for infrastructure investment levels (1-4 tiers). Filled dots use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-accent-green</code>, empty dots use <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-surface-600</code>.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 space-y-4 mb-3">
            @foreach([
                ['label' => 'Youth Academy', 'tier' => 3, 'cost' => '1.8M'],
                ['label' => 'Medical', 'tier' => 2, 'cost' => '1.2M'],
                ['label' => 'Scouting', 'tier' => 4, 'cost' => '2.4M'],
                ['label' => 'Facilities', 'tier' => 1, 'cost' => '0.6M'],
            ] as $item)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-text-primary">{{ $item['label'] }}</span>
                    <span class="text-xs text-text-muted">&euro;{{ $item['cost'] }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    @for($i = 1; $i <= 4; $i++)
                        <span class="w-2.5 h-2.5 rounded-full {{ $i <= $item['tier'] ? 'bg-accent-green' : 'bg-surface-600' }}"></span>
                    @endfor
                    <span class="text-xs text-text-muted ml-1">Tier {{ $item['tier'] }}</span>
                </div>
            </div>
            @endforeach
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="flex items-center gap-1.5"&gt;
    @@for($i = 1; $i &lt;= 4; $i++)
        &lt;span class="w-2.5 h-2.5 rounded-full &#123;&#123; $i &lt;= $tier ? 'bg-accent-green' : 'bg-surface-600' &#125;&#125;"&gt;&lt;/span&gt;
    @@endfor
    &lt;span class="text-xs text-text-muted ml-1"&gt;Tier &#123;&#123; $tier &#125;&#125;&lt;/span&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Range Slider --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Range Slider</h3>
        <p class="text-sm text-text-secondary mb-4">Custom-styled range input using the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">.tier-range</code> CSS class. Accent-blue thumb with <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">bg-surface-600</code> track.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3" x-data="{ value: 2 }">
            <div class="max-w-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-text-primary">Investment Level</span>
                    <span class="text-sm font-semibold text-accent-blue" x-text="'Tier ' + value">Tier 2</span>
                </div>
                <div class="tier-range">
                    <div class="track"></div>
                    <div class="track-fill" :style="'width: ' + ((value / 4) * 100) + '%'"></div>
                    <input type="range" min="0" max="4" step="1" x-model="value">
                </div>
                <div class="flex justify-between text-[10px] text-text-muted mt-1">
                    <span>0</span><span>1</span><span>2</span><span>3</span><span>4</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="code">&lt;div class="tier-range"&gt;
    &lt;div class="track"&gt;&lt;/div&gt;
    &lt;div class="track-fill" :style="'width: ' + pct + '%'"&gt;&lt;/div&gt;
    &lt;input type="range" min="0" max="4" step="1" x-model="value"&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Morale Indicator --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Morale Indicator</h3>
        <p class="text-sm text-text-secondary mb-4">Colored dot with text label using the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-morale-indicator</code> component. Automatically maps morale value to color and label.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex flex-wrap items-center gap-6">
                <x-morale-indicator :value="95" />
                <x-morale-indicator :value="80" />
                <x-morale-indicator :value="65" />
                <x-morale-indicator :value="45" />
                <x-morale-indicator :value="25" />
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.moraleCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="moraleCode">&lt;!-- Basic usage --&gt;
&lt;x-morale-indicator :value="$player->morale" /&gt;

&lt;!-- Thresholds (automatic) --&gt;
&lt;!-- 90+  Ecstatic   (green)  --&gt;
&lt;!-- 75+  Happy      (green)  --&gt;
&lt;!-- 60+  Content    (gold)   --&gt;
&lt;!-- 40+  Frustrated (orange) --&gt;
&lt;!-- &lt;40  Unhappy    (red)    --&gt;</code></pre>
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
                        <td class="py-2 text-text-secondary">Morale value (0-100). Color and label are determined automatically.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
