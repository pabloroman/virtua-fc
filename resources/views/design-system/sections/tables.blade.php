<section id="tables" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Tables</h2>
    <p class="text-sm text-text-secondary mb-8">Data tables with responsive column hiding, group headers, hover states, and mobile-friendly layouts. Tables use surface-800 containers with white/5 borders.</p>

    {{-- Player List Table --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Player List Table</h3>
        <p class="text-sm text-text-secondary mb-4">The primary player data table. On mobile, rows collapse into card-like layouts with avatar, name, position badge, and rating. On desktop, a multi-column grid shows full details.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                {{-- Table Header --}}
                <div class="hidden lg:grid grid-cols-[2fr_0.8fr_0.6fr_0.6fr_1fr_0.6fr] gap-2 px-4 py-2.5 border-b border-border-default">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">Player</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">Position</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-wider text-center">Age</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-wider text-center">NAT</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-wider text-right">Value</div>
                    <div class="text-[10px] text-text-muted uppercase tracking-wider text-center">OVR</div>
                </div>

                {{-- Group Header --}}
                <div class="bg-surface-700/30 border-b border-border-default px-4 py-2">
                    <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">Midfielders</span>
                </div>

                {{-- Player Rows --}}
                @foreach([
                    ['name' => 'Pedri', 'pos' => 'CM', 'nat' => 'ESP', 'age' => 23, 'value' => '85.0M', 'ovr' => 88, 'initials' => 'PE'],
                    ['name' => 'Gavi', 'pos' => 'CM', 'nat' => 'ESP', 'age' => 21, 'value' => '72.0M', 'ovr' => 82, 'initials' => 'GA'],
                    ['name' => 'Dani Olmo', 'pos' => 'AM', 'nat' => 'ESP', 'age' => 27, 'value' => '60.0M', 'ovr' => 84, 'initials' => 'DO'],
                ] as $player)
                <div class="player-row border-b border-border-default transition-colors hover:bg-[rgba(59,130,246,0.05)]">
                    {{-- Mobile Row --}}
                    <div class="lg:hidden px-4 py-3 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-surface-700 border border-border-strong flex items-center justify-center shrink-0">
                            <span class="text-[10px] font-bold text-text-secondary">{{ $player['initials'] }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-text-primary truncate">{{ $player['name'] }}</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <x-position-badge abbreviation="{{ $player['pos'] }}" size="sm" />
                                <span class="text-[10px] text-text-muted">{{ $player['age'] }} yrs</span>
                                <span class="text-[10px] text-text-muted">&euro;{{ $player['value'] }}</span>
                            </div>
                        </div>
                        <div class="shrink-0">
                            @php
                                $ratingClass = $player['ovr'] >= 85 ? 'rating-elite' : ($player['ovr'] >= 75 ? 'rating-good' : 'rating-average');
                                $ratingBg = $player['ovr'] >= 85 ? 'bg-[#22C55E]' : ($player['ovr'] >= 75 ? 'bg-[#84CC16]' : 'bg-[#F59E0B]');
                            @endphp
                            <div class="w-9 h-9 {{ $ratingBg }} rounded-lg flex items-center justify-center">
                                <span class="font-heading text-sm font-bold text-white">{{ $player['ovr'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Desktop Row --}}
                    <div class="hidden lg:grid grid-cols-[2fr_0.8fr_0.6fr_0.6fr_1fr_0.6fr] gap-2 px-4 py-2.5 items-center">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-surface-700 border border-border-strong flex items-center justify-center shrink-0">
                                <span class="text-[10px] font-bold text-text-secondary">{{ $player['initials'] }}</span>
                            </div>
                            <span class="text-sm font-semibold text-text-primary truncate">{{ $player['name'] }}</span>
                        </div>
                        <div><x-position-badge abbreviation="{{ $player['pos'] }}" size="sm" /></div>
                        <div class="text-sm text-text-secondary text-center">{{ $player['age'] }}</div>
                        <div class="text-xs text-text-muted text-center">{{ $player['nat'] }}</div>
                        <div class="text-sm text-text-body text-right tabular-nums">&euro;{{ $player['value'] }}</div>
                        <div class="flex justify-center">
                            <div class="w-8 h-8 {{ $ratingBg }} rounded-lg flex items-center justify-center">
                                <span class="font-heading text-xs font-bold text-white">{{ $player['ovr'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                {{-- Group Header --}}
                <div class="bg-surface-700/30 border-b border-border-default px-4 py-2">
                    <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">Forwards</span>
                </div>

                @php
                    $fwd = ['name' => 'Lamine Yamal', 'pos' => 'RW', 'nat' => 'ESP', 'age' => 18, 'value' => '120.0M', 'ovr' => 85, 'initials' => 'LY'];
                @endphp
                <div class="player-row border-b border-border-default transition-colors hover:bg-[rgba(59,130,246,0.05)]">
                    <div class="lg:hidden px-4 py-3 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-surface-700 border border-border-strong flex items-center justify-center shrink-0">
                            <span class="text-[10px] font-bold text-text-secondary">{{ $fwd['initials'] }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-text-primary truncate">{{ $fwd['name'] }}</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <x-position-badge abbreviation="{{ $fwd['pos'] }}" size="sm" />
                                <span class="text-[10px] text-text-muted">{{ $fwd['age'] }} yrs</span>
                                <span class="text-[10px] text-text-muted">&euro;{{ $fwd['value'] }}</span>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <div class="w-9 h-9 bg-[#22C55E] rounded-lg flex items-center justify-center">
                                <span class="font-heading text-sm font-bold text-white">{{ $fwd['ovr'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="hidden lg:grid grid-cols-[2fr_0.8fr_0.6fr_0.6fr_1fr_0.6fr] gap-2 px-4 py-2.5 items-center">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-surface-700 border border-border-strong flex items-center justify-center shrink-0">
                                <span class="text-[10px] font-bold text-text-secondary">{{ $fwd['initials'] }}</span>
                            </div>
                            <span class="text-sm font-semibold text-text-primary truncate">{{ $fwd['name'] }}</span>
                        </div>
                        <div><x-position-badge abbreviation="{{ $fwd['pos'] }}" size="sm" /></div>
                        <div class="text-sm text-text-secondary text-center">{{ $fwd['age'] }}</div>
                        <div class="text-xs text-text-muted text-center">{{ $fwd['nat'] }}</div>
                        <div class="text-sm text-text-body text-right tabular-nums">&euro;{{ $fwd['value'] }}</div>
                        <div class="flex justify-center">
                            <div class="w-8 h-8 bg-[#22C55E] rounded-lg flex items-center justify-center">
                                <span class="font-heading text-xs font-bold text-white">{{ $fwd['ovr'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.playerTableCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="playerTableCode">&lt;div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden"&gt;
    &lt;!-- Table Header (desktop only) --&gt;
    &lt;div class="hidden lg:grid grid-cols-[2fr_0.8fr_0.6fr_0.6fr_1fr_0.6fr] gap-2 px-4 py-2.5 border-b border-border-default"&gt;
        &lt;div class="text-[10px] text-text-muted uppercase tracking-wider"&gt;Player&lt;/div&gt;
        &lt;!-- ...more columns --&gt;
    &lt;/div&gt;

    &lt;!-- Group Header --&gt;
    &lt;div class="bg-surface-700/30 border-b border-border-default px-4 py-2"&gt;
        &lt;span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted"&gt;Position Group&lt;/span&gt;
    &lt;/div&gt;

    &lt;!-- Player Row --&gt;
    &lt;div class="player-row border-b border-border-default hover:bg-[rgba(59,130,246,0.05)]"&gt;
        &lt;!-- Mobile layout (lg:hidden) --&gt;
        &lt;div class="lg:hidden px-4 py-3 flex items-center gap-3"&gt;
            &lt;!-- Avatar + Info + Rating badge --&gt;
        &lt;/div&gt;
        &lt;!-- Desktop layout (hidden lg:grid) --&gt;
        &lt;div class="hidden lg:grid grid-cols-[...] gap-2 px-4 py-2.5 items-center"&gt;
            &lt;!-- Full column data --&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Group Headers --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Group Headers</h3>
        <p class="text-sm text-text-secondary mb-4">Surface-700/30 background rows used to group table rows by category (e.g., position groups in squad). Uses <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">font-heading</code> with extra tracking.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                <div class="bg-surface-700/30 border-b border-border-default px-4 py-2">
                    <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">Goalkeepers</span>
                </div>
                <div class="border-b border-border-default px-4 py-2.5 flex items-center justify-between hover:bg-[rgba(59,130,246,0.05)] transition-colors">
                    <div class="flex items-center gap-3">
                        <x-position-badge abbreviation="GK" size="sm" />
                        <span class="text-sm font-semibold text-text-primary">Ter Stegen</span>
                    </div>
                    <span class="text-xs text-text-muted">32</span>
                </div>
                <div class="bg-surface-700/30 border-b border-border-default px-4 py-2">
                    <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">Midfielders</span>
                </div>
                <div class="border-b border-border-default px-4 py-2.5 flex items-center justify-between hover:bg-[rgba(59,130,246,0.05)] transition-colors">
                    <div class="flex items-center gap-3">
                        <x-position-badge abbreviation="CM" size="sm" />
                        <span class="text-sm font-semibold text-text-primary">Pedri</span>
                    </div>
                    <span class="text-xs text-text-muted">23</span>
                </div>
                <div class="border-b border-border-default px-4 py-2.5 flex items-center justify-between hover:bg-[rgba(59,130,246,0.05)] transition-colors">
                    <div class="flex items-center gap-3">
                        <x-position-badge abbreviation="CM" size="sm" />
                        <span class="text-sm font-semibold text-text-primary">Gavi</span>
                    </div>
                    <span class="text-xs text-text-muted">21</span>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.groupCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="groupCode">&lt;div class="bg-surface-700/30 border-b border-border-default px-4 py-2"&gt;
    &lt;span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted"&gt;
        Group Name
    &lt;/span&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>

    {{-- Financial Table --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Financial Table</h3>
        <p class="text-sm text-text-secondary mb-4">Line-item table with <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-accent-green</code> for income and <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">text-accent-red</code> for expenses. Used in the finances page budget flow.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-border-default">
                    <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">Revenue</span>
                </div>
                <div class="px-5 py-1 space-y-0 text-sm">
                    <div class="flex items-center justify-between py-2">
                        <span class="text-text-secondary pl-3">TV Rights</span>
                        <span class="text-accent-green tabular-nums">+&euro;18.5M</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-text-secondary pl-3">Commercial</span>
                        <span class="text-accent-green tabular-nums">+&euro;12.3M</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-text-secondary pl-3">Matchday</span>
                        <span class="text-accent-green tabular-nums">+&euro;8.7M</span>
                    </div>
                    <div class="border-t border-border-default pt-2 mt-1">
                        <div class="flex items-center justify-between py-1">
                            <span class="font-semibold text-text-primary pl-3">Total Revenue</span>
                            <span class="font-semibold text-accent-green tabular-nums">+&euro;39.5M</span>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 border-t border-border-default border-b border-border-default">
                    <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">Expenses</span>
                </div>
                <div class="px-5 py-1 space-y-0 text-sm">
                    <div class="flex items-center justify-between py-2">
                        <span class="text-text-secondary pl-3">Wages</span>
                        <span class="text-accent-red tabular-nums">-&euro;24.8M</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-text-secondary pl-3">Operating Expenses</span>
                        <span class="text-accent-red tabular-nums">-&euro;3.2M</span>
                    </div>
                    <div class="border-t-2 border-border-strong pt-2 mt-1">
                        <div class="flex items-center justify-between py-2">
                            <span class="font-heading font-bold text-lg text-text-primary pl-3">= Transfer Budget</span>
                            <span class="font-heading font-bold text-lg text-accent-gold tabular-nums">&euro;11.5M</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.financeCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="financeCode">&lt;div class="flex items-center justify-between py-2"&gt;
    &lt;span class="text-text-secondary pl-3"&gt;TV Rights&lt;/span&gt;
    &lt;span class="text-accent-green tabular-nums"&gt;+&amp;euro;18.5M&lt;/span&gt;
&lt;/div&gt;
&lt;div class="flex items-center justify-between py-2"&gt;
    &lt;span class="text-text-secondary pl-3"&gt;Wages&lt;/span&gt;
    &lt;span class="text-accent-red tabular-nums"&gt;-&amp;euro;24.8M&lt;/span&gt;
&lt;/div&gt;
&lt;!-- Total row --&gt;
&lt;div class="border-t-2 border-border-strong pt-2 mt-1"&gt;
    &lt;div class="flex items-center justify-between py-2"&gt;
        &lt;span class="font-heading font-bold text-lg text-text-primary"&gt;= Transfer Budget&lt;/span&gt;
        &lt;span class="font-heading font-bold text-lg text-accent-gold tabular-nums"&gt;&amp;euro;11.5M&lt;/span&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </div>
</section>
