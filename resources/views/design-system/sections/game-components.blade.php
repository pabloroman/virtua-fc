<section id="game-components" class="mb-20">
    <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-2">Game Components</h2>
    <p class="text-sm text-text-secondary mb-8">Complex components that require Eloquent model data to render. Documented here with props, usage patterns, and descriptions — no rendered previews since they depend on live data.</p>

    {{-- Player Avatar --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Player Avatar</h3>
        <p class="text-sm text-text-secondary mb-4">Position-colored gradient circle with player initials. Optional position sub-badge. Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-player-avatar</code> component. Colors: GK = amber, DEF = blue, MID = green, FWD = rose.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            {{-- Size variants --}}
            <div class="mb-6">
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">Sizes</div>
                <div class="flex items-end gap-6">
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" position-abbrev="GK" size="sm" />
                        <div class="text-[10px] text-text-muted mt-2">sm</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" position-abbrev="GK" size="md" />
                        <div class="text-[10px] text-text-muted mt-2">md</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" position-abbrev="GK" size="lg" />
                        <div class="text-[10px] text-text-muted mt-2">lg</div>
                    </div>
                </div>
            </div>

            {{-- Position colors --}}
            <div>
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-3">Position Groups</div>
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" position-abbrev="GK" />
                        <div class="text-[10px] text-text-muted mt-2">GK</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Pablo García" position-group="Defender" position-abbrev="CB" />
                        <div class="text-[10px] text-text-muted mt-2">DEF</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Luis Fernández" position-group="Midfielder" position-abbrev="CM" />
                        <div class="text-[10px] text-text-muted mt-2">MID</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Carlos Torres" position-group="Forward" position-abbrev="CF" />
                        <div class="text-[10px] text-text-muted mt-2">FWD</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Ana López" position-group="Defender" />
                        <div class="text-[10px] text-text-muted mt-2">No badge</div>
                    </div>
                    <div class="text-center">
                        <x-player-avatar name="Marc Rodríguez" position-group="Goalkeeper" :number="1" />
                        <div class="text-[10px] text-text-muted mt-2">With #</div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.avatarCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="avatarCode">&lt;!-- With number and position sub-badge --&gt;
&lt;x-player-avatar :name="$player->name" :position-group="$group" :number="$player->number" :position-abbrev="$abbrev" /&gt;

&lt;!-- Without number (falls back to initials) --&gt;
&lt;x-player-avatar :name="$player->name" position-group="Defender" /&gt;

&lt;!-- Small size (for table rows) --&gt;
&lt;x-player-avatar :name="$player->name" :position-group="$group" :number="$player->number" size="sm" /&gt;</code></pre>
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
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">name</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Player name (initials computed automatically)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">positionGroup</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Goalkeeper | Defender | Midfielder | Forward</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">number</td>
                        <td class="py-2 pr-4 text-text-secondary">int|null</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">null</td>
                        <td class="py-2 text-text-secondary">Squad number (shown instead of initials when present)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">positionAbbrev</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">null</td>
                        <td class="py-2 text-text-secondary">Position abbreviation for sub-badge (GK, CB, etc.)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">size</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">'md'</td>
                        <td class="py-2 text-text-secondary">sm | md | lg</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Summary Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Summary Card</h3>
        <p class="text-sm text-text-secondary mb-4">Compact stat card with micro-label and bold value. Uses the <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">x-summary-card</code> component. Designed for horizontal scroll rows.</p>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex gap-2.5 overflow-x-auto scrollbar-hide pb-1">
                <x-summary-card label="SQUAD" value="24" />
                <x-summary-card label="AVG AGE" value="26.3" />
                <x-summary-card label="FITNESS" value="87%" value-class="text-accent-green" />
                <x-summary-card label="MORALE" value="78" />
                <x-summary-card label="BUDGET" value="€12.5M" class="min-w-[130px]" />
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.summaryCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="summaryCode">&lt;!-- Basic usage --&gt;
&lt;x-summary-card label="SQUAD" value="24" /&gt;

&lt;!-- With colored value --&gt;
&lt;x-summary-card label="FITNESS" value="87%" value-class="text-accent-green" /&gt;

&lt;!-- With custom width --&gt;
&lt;x-summary-card label="BUDGET" value="€12.5M" class="min-w-[130px]" /&gt;

&lt;!-- With slot content --&gt;
&lt;x-summary-card label="STATUS" value="Active"&gt;
    &lt;span class="text-xs text-text-muted"&gt;Extra info&lt;/span&gt;
&lt;/x-summary-card&gt;</code></pre>
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
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">label</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Micro-label text displayed above the value</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">value</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">—</td>
                        <td class="py-2 text-text-secondary">Main bold value</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">valueClass</td>
                        <td class="py-2 pr-4 text-text-secondary">string</td>
                        <td class="py-2 pr-4 font-mono text-xs text-text-muted">'text-white'</td>
                        <td class="py-2 text-text-secondary">CSS class for value color (e.g. text-accent-green)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Game Header --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Game Header</h3>
        <p class="text-sm text-text-secondary mb-4">The primary navigation header for all game pages. Features a dual layout: desktop top bar with team badge, navigation links, budget display, and notification bell, plus a mobile hamburger menu with a slide-out drawer containing full navigation.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/game-header.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-text-muted">Game</td>
                        <td class="py-2 text-text-secondary">The game model instance with team relationship</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">nextMatch</td>
                        <td class="py-2 pr-4 text-text-muted">GameMatch|null</td>
                        <td class="py-2 text-text-secondary">Next upcoming match (determines "Continue" button vs "Season End")</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.gameHeaderCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="gameHeaderCode">&lt;x-game-header :game="$game" :next-match="$nextMatch" /&gt;</code></pre>
        </div>
    </div>

    {{-- Fixture Row --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Fixture Row <code class="text-xs text-accent-blue font-mono">&lt;x-fixture-row&gt;</code></h3>
        <p class="text-sm text-text-secondary mb-4">A single match fixture in a calendar or upcoming fixtures list. Features a stacked date block, competition-colored home/away pill, opponent with crest, and result with a color-coded dot. The next match gets a blue accent highlight matching the standing-row pattern. Rows are stacked inside a <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">divide-y</code> container.</p>

        {{-- Props table --}}
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] text-text-muted uppercase tracking-widest border-b border-border-default">
                        <th class="px-3 py-2 font-semibold">Prop</th>
                        <th class="px-3 py-2 font-semibold">Type</th>
                        <th class="px-3 py-2 font-semibold">Default</th>
                        <th class="px-3 py-2 font-semibold">Description</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">match</td>
                        <td class="px-3 py-2">GameMatch</td>
                        <td class="px-3 py-2 text-text-muted">—</td>
                        <td class="px-3 py-2">The match model with homeTeam/awayTeam and competition loaded</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">game</td>
                        <td class="px-3 py-2">Game</td>
                        <td class="px-3 py-2 text-text-muted">—</td>
                        <td class="px-3 py-2">The game model (determines user's team for home/away)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">show-score</td>
                        <td class="px-3 py-2">bool</td>
                        <td class="px-3 py-2 text-text-muted">true</td>
                        <td class="px-3 py-2">Show score for played matches</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">highlight-next</td>
                        <td class="px-3 py-2">bool</td>
                        <td class="px-3 py-2 text-text-muted">true</td>
                        <td class="px-3 py-2">Highlight with blue accent if this is the next unplayed match</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">next-match-id</td>
                        <td class="px-3 py-2">string|null</td>
                        <td class="px-3 py-2 text-text-muted">null</td>
                        <td class="px-3 py-2">ID of the next match to compare against for highlighting</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Visual anatomy --}}
        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden max-w-md">
                <div class="divide-y divide-border-default">
                    {{-- Played match — win --}}
                    <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-700/30 transition-colors">
                        <div class="w-10 shrink-0 text-center">
                            <div class="text-[11px] font-medium text-text-body leading-tight">15</div>
                            <div class="text-[9px] text-text-faint uppercase">Sep</div>
                            <div class="w-3 h-0.5 rounded-full bg-accent-gold mx-auto mt-1"></div>
                        </div>
                        <span class="inline-flex px-2 py-0.5 text-[9px] font-semibold rounded-full bg-accent-green/10 text-accent-green shrink-0 uppercase tracking-wider">H</span>
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                            <div class="w-5 h-5 rounded bg-surface-600 shrink-0"></div>
                            <span class="text-xs text-text-body truncate">Real Sociedad</span>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-accent-green shrink-0"></div>
                                <span class="text-[11px] font-semibold text-accent-green">3 - 1</span>
                            </div>
                        </div>
                    </div>
                    {{-- Played match — loss --}}
                    <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-700/30 transition-colors">
                        <div class="w-10 shrink-0 text-center">
                            <div class="text-[11px] font-medium text-text-body leading-tight">22</div>
                            <div class="text-[9px] text-text-faint uppercase">Sep</div>
                            <div class="w-3 h-0.5 rounded-full bg-accent-green mx-auto mt-1"></div>
                        </div>
                        <span class="inline-flex px-2 py-0.5 text-[9px] font-semibold rounded-full bg-surface-600 text-text-secondary shrink-0 uppercase tracking-wider">A</span>
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                            <div class="w-5 h-5 rounded bg-surface-600 shrink-0"></div>
                            <span class="text-xs text-text-body truncate">Real Madrid</span>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-accent-red shrink-0"></div>
                                <span class="text-[11px] font-semibold text-accent-red">0 - 2</span>
                            </div>
                        </div>
                    </div>
                    {{-- Next match (highlighted) --}}
                    <div class="flex items-center gap-3 px-4 py-2.5 bg-accent-blue/[0.06] border-l-2 border-l-accent-blue hover:bg-surface-700/30 transition-colors">
                        <div class="w-10 shrink-0 text-center">
                            <div class="text-[11px] font-medium text-text-body leading-tight">29</div>
                            <div class="text-[9px] text-text-faint uppercase">Sep</div>
                            <div class="w-3 h-0.5 rounded-full bg-accent-gold mx-auto mt-1"></div>
                        </div>
                        <span class="inline-flex px-2 py-0.5 text-[9px] font-semibold rounded-full bg-accent-green/10 text-accent-green shrink-0 uppercase tracking-wider">H</span>
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                            <div class="w-5 h-5 rounded bg-surface-600 shrink-0"></div>
                            <span class="text-xs text-text-primary font-medium truncate">FC Barcelona</span>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[9px] font-semibold text-accent-blue uppercase tracking-wider">Next</span>
                        </div>
                    </div>
                    {{-- Future match --}}
                    <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-700/30 transition-colors">
                        <div class="w-10 shrink-0 text-center">
                            <div class="text-[11px] font-medium text-text-body leading-tight">06</div>
                            <div class="text-[9px] text-text-faint uppercase">Oct</div>
                            <div class="w-3 h-0.5 rounded-full bg-accent-blue mx-auto mt-1"></div>
                        </div>
                        <span class="inline-flex px-2 py-0.5 text-[9px] font-semibold rounded-full bg-surface-600 text-text-secondary shrink-0 uppercase tracking-wider">A</span>
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                            <div class="w-5 h-5 rounded bg-surface-600 shrink-0"></div>
                            <span class="text-xs text-text-body truncate">Sevilla FC</span>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="text-[11px] text-text-faint">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.fixtureRowCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="fixtureRowCode">&lt;!-- Inside a section card --&gt;
&lt;x-section-card :title="$month"&gt;
    &lt;div class="divide-y divide-border-default"&gt;
        @@foreach($matches as $match)
            &lt;x-fixture-row :match="$match" :game="$game" :next-match-id="$nextMatchId" /&gt;
        @@endforeach
    &lt;/div&gt;
&lt;/x-section-card&gt;</code></pre>
        </div>
    </div>

    {{-- Cup Tie Card --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Cup Tie Card</h3>
        <p class="text-sm text-text-secondary mb-4">Displays a cup match pairing with both teams, scores, and resolution info (aggregate, penalties, extra time). User's team gets a blue highlight. Winner gets green background, loser gets reduced opacity.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/cup-tie-card.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tie</td>
                        <td class="py-2 pr-4 text-text-muted">CupTie</td>
                        <td class="py-2 text-text-secondary">The cup tie model with team relationships</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">playerTeamId</td>
                        <td class="py-2 pr-4 text-text-muted">string</td>
                        <td class="py-2 text-text-secondary">The user's team ID for highlighting</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.cupTieCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="cupTieCode">&lt;x-cup-tie-card :tie="$tie" :player-team-id="$game-&gt;team_id" /&gt;</code></pre>
        </div>
    </div>

    {{-- Budget Allocation --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Budget Allocation</h3>
        <p class="text-sm text-text-secondary mb-4">Interactive Alpine.js component for allocating season budget across 4 infrastructure areas using tier-based sliders (0-4). Calculates transfer budget as the remainder. Shows real-time cost calculations with dynamic color feedback per tier level.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/components/budget-allocation.blade.php</code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-text-muted">Game</td>
                        <td class="py-2 text-text-secondary">The game model</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">availableSurplus</td>
                        <td class="py-2 pr-4 text-text-muted">int</td>
                        <td class="py-2 text-text-secondary">Total budget available to allocate</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tiers</td>
                        <td class="py-2 pr-4 text-text-muted">array</td>
                        <td class="py-2 text-text-secondary">Current tier values for each area</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">tierThresholds</td>
                        <td class="py-2 pr-4 text-text-muted">array</td>
                        <td class="py-2 text-text-secondary">Cost thresholds for each tier level per area</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">locked</td>
                        <td class="py-2 pr-4 text-text-muted">bool</td>
                        <td class="py-2 text-text-secondary">Whether budget is locked (read-only mode)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.budgetCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="budgetCode">&lt;x-budget-allocation
    :game="$game"
    :available-surplus="$availableSurplus"
    :tiers="$tiers"
    :tier-thresholds="$tierThresholds"
    :locked="$locked"
/&gt;</code></pre>
        </div>
    </div>

    {{-- Contract Banner --}}
    <div>
        <h3 class="text-lg font-semibold text-text-primary mb-2">Contract Banner</h3>
        <p class="text-sm text-text-secondary mb-4">Expandable banner on the squad page showing contract-related alerts: pre-contract offers (amber), agreed pre-contracts (red), expiring contracts (slate), and pending renewals (green). Uses Alpine.js for expand/collapse.</p>

        <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3 mb-4">
            <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wide mb-1">Component</div>
            <code class="text-xs font-mono text-accent-blue">resources/views/squad.blade.php <span class="text-text-muted">(inline)</span></code>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left border-b border-border-default">
                    <tr>
                        <th class="font-semibold text-text-body py-2 pr-4">Prop</th>
                        <th class="font-semibold text-text-body py-2 pr-4">Type</th>
                        <th class="font-semibold text-text-body py-2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">game</td>
                        <td class="py-2 pr-4 text-text-muted">Game</td>
                        <td class="py-2 text-text-secondary">The game model</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">preContractOffers</td>
                        <td class="py-2 pr-4 text-text-muted">Collection</td>
                        <td class="py-2 text-text-secondary">Players being targeted by other clubs</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">agreedPreContracts</td>
                        <td class="py-2 pr-4 text-text-muted">Collection</td>
                        <td class="py-2 text-text-secondary">Players who agreed to leave on free</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">pendingRenewals</td>
                        <td class="py-2 pr-4 text-text-muted">Collection</td>
                        <td class="py-2 text-text-secondary">Players with agreed renewal offers</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">renewalEligiblePlayers</td>
                        <td class="py-2 pr-4 text-text-muted">Collection</td>
                        <td class="py-2 text-text-secondary">Players eligible for contract renewal</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="py-2 pr-4 font-mono text-xs text-accent-blue">renewalDemands</td>
                        <td class="py-2 pr-4 text-text-muted">array</td>
                        <td class="py-2 text-text-secondary">Wage demands for each eligible player</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.contractCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-slate-200 bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="contractCode">&lt;x-contract-banner
    :game="$game"
    :pre-contract-offers="$preContractOffers"
    :agreed-pre-contracts="$agreedPreContracts"
    :pending-renewals="$pendingRenewals"
    :renewal-eligible-players="$renewalEligiblePlayers"
    :renewal-demands="$renewalDemands"
/&gt;</code></pre>
        </div>
    </div>

    {{-- Notification Icon --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Notification Icon <code class="text-xs text-accent-blue font-mono">&lt;x-notification-icon&gt;</code></h3>
        <p class="text-sm text-text-secondary mb-4">Icon badge used in notification rows. Renders a type-specific SVG icon inside a translucent colored rounded-lg container. Icon types match notification event types (injury, transfer, scout, contract, etc.).</p>

        {{-- Props table --}}
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] text-text-muted uppercase tracking-widest border-b border-border-default">
                        <th class="px-3 py-2 font-semibold">Prop</th>
                        <th class="px-3 py-2 font-semibold">Type</th>
                        <th class="px-3 py-2 font-semibold">Default</th>
                        <th class="px-3 py-2 font-semibold">Description</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">icon</td>
                        <td class="px-3 py-2">string</td>
                        <td class="px-3 py-2 text-text-muted">—</td>
                        <td class="px-3 py-2">Icon key: injury, suspended, recovered, fitness, transfer, transfer_complete, clock, scout, contract, loan, loan_destination, loan_failed, trophy, eliminated, academy</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">icon-bg</td>
                        <td class="px-3 py-2">string</td>
                        <td class="px-3 py-2 text-text-muted">bg-surface-700/50</td>
                        <td class="px-3 py-2">Tailwind background class for the icon container (translucent, e.g. <code class="text-xs">bg-blue-500/10</code>)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">icon-text</td>
                        <td class="px-3 py-2">string</td>
                        <td class="px-3 py-2 text-text-muted">text-text-secondary</td>
                        <td class="px-3 py-2">Tailwind text color class for the SVG icon</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="flex items-center gap-4">
                <div class="text-center">
                    <x-notification-icon icon="injury" icon-bg="bg-red-500/10" icon-text="text-red-500" />
                    <div class="text-[9px] text-text-muted mt-1.5">injury</div>
                </div>
                <div class="text-center">
                    <x-notification-icon icon="transfer" icon-bg="bg-blue-500/10" icon-text="text-blue-500" />
                    <div class="text-[9px] text-text-muted mt-1.5">transfer</div>
                </div>
                <div class="text-center">
                    <x-notification-icon icon="scout" icon-bg="bg-teal-500/10" icon-text="text-teal-500" />
                    <div class="text-[9px] text-text-muted mt-1.5">scout</div>
                </div>
                <div class="text-center">
                    <x-notification-icon icon="contract" icon-bg="bg-zinc-500/10" icon-text="text-zinc-400" />
                    <div class="text-[9px] text-text-muted mt-1.5">contract</div>
                </div>
                <div class="text-center">
                    <x-notification-icon icon="trophy" icon-bg="bg-amber-500/10" icon-text="text-amber-500" />
                    <div class="text-[9px] text-text-muted mt-1.5">trophy</div>
                </div>
                <div class="text-center">
                    <x-notification-icon icon="academy" icon-bg="bg-lime-500/10" icon-text="text-lime-500" />
                    <div class="text-[9px] text-text-muted mt-1.5">academy</div>
                </div>
                <div class="text-center">
                    <x-notification-icon icon="loan" icon-bg="bg-violet-500/10" icon-text="text-violet-500" />
                    <div class="text-[9px] text-text-muted mt-1.5">loan</div>
                </div>
                <div class="text-center">
                    <x-notification-icon icon="recovered" icon-bg="bg-emerald-500/10" icon-text="text-emerald-500" />
                    <div class="text-[9px] text-text-muted mt-1.5">recovered</div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.notifIconCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="notifIconCode">&lt;x-notification-icon
    icon="transfer"
    icon-bg="bg-blue-500/10"
    icon-text="text-blue-500"
/&gt;</code></pre>
        </div>
    </div>

    {{-- Notification Row --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Notification Row <code class="text-xs text-accent-blue font-mono">&lt;x-notification-row&gt;</code></h3>
        <p class="text-sm text-text-secondary mb-4">A single notification entry in the inbox. Renders as a clickable row with a translucent icon, title, message, timestamp, priority badge, and an unread dot indicator. Rows are stacked inside a <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">divide-y</code> container within a section card. Clicking marks the notification as read.</p>

        {{-- Props table --}}
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] text-text-muted uppercase tracking-widest border-b border-border-default">
                        <th class="px-3 py-2 font-semibold">Prop</th>
                        <th class="px-3 py-2 font-semibold">Type</th>
                        <th class="px-3 py-2 font-semibold">Description</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">notification</td>
                        <td class="px-3 py-2">GameNotification</td>
                        <td class="px-3 py-2">The notification model instance</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">game</td>
                        <td class="px-3 py-2">Game</td>
                        <td class="px-3 py-2">The game model instance (for route generation)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Visual anatomy --}}
        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden max-w-md">
                <div class="divide-y divide-border-default">
                    {{-- Simulated unread notification --}}
                    <div class="px-4 py-3 hover:bg-surface-700/30 transition-colors cursor-pointer">
                        <div class="flex items-start gap-3">
                            <x-notification-icon icon="transfer" icon-bg="bg-blue-500/10" icon-text="text-blue-500" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-text-primary">Transfer Offer Received</p>
                                <p class="text-[11px] text-text-muted mt-0.5 leading-relaxed">Manchester City have offered <span class="text-accent-gold font-medium">&euro;75M</span> for Pedri.</p>
                                <span class="text-[9px] text-text-faint mt-1 block">12 Aug</span>
                            </div>
                            <div class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mt-1.5"></div>
                        </div>
                    </div>
                    {{-- Simulated unread with priority badge --}}
                    <div class="px-4 py-3 hover:bg-surface-700/30 transition-colors cursor-pointer">
                        <div class="flex items-start gap-3">
                            <x-notification-icon icon="contract" icon-bg="bg-zinc-500/10" icon-text="text-zinc-400" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-text-primary">Contract Expiring</p>
                                <p class="text-[11px] text-text-muted mt-0.5 leading-relaxed">Lewandowski's contract expires at end of season.</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[9px] text-text-faint">10 Aug</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full bg-amber-500 text-white">Attention</span>
                                </div>
                            </div>
                            <div class="w-2 h-2 rounded-full bg-zinc-400 shrink-0 mt-1.5"></div>
                        </div>
                    </div>
                    {{-- Simulated read notification --}}
                    <div class="px-4 py-3 hover:bg-surface-700/30 transition-colors cursor-pointer opacity-50">
                        <div class="flex items-start gap-3">
                            <x-notification-icon icon="academy" icon-bg="bg-lime-500/10" icon-text="text-lime-500" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-text-primary">Academy Report</p>
                                <p class="text-[11px] text-text-muted mt-0.5 leading-relaxed">Marc Guiu (ST, 18) has been recommended for first team.</p>
                                <span class="text-[9px] text-text-faint mt-1 block">8 Aug</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.notifRowCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="notifRowCode">&lt;!-- Inside a section card --&gt;
&lt;x-section-card :title="__('notifications.inbox')"&gt;
    &lt;div class="divide-y divide-border-default"&gt;
        @@foreach($notifications as $notification)
            &lt;x-notification-row :notification="$notification" :game="$game" /&gt;
        @@endforeach
    &lt;/div&gt;
&lt;/x-section-card&gt;</code></pre>
        </div>
    </div>

    {{-- Standing Row --}}
    <div class="mb-12">
        <h3 class="text-lg font-semibold text-text-primary mb-2">Standing Row <code class="text-xs text-accent-blue font-mono">&lt;x-standing-row&gt;</code></h3>
        <p class="text-sm text-text-secondary mb-4">A single row in a league standings table. Uses a CSS Grid layout with fixed column widths for alignment. The player's team row gets a subtle blue highlight with a left accent border. Rows are stacked inside a <code class="text-xs bg-surface-700 px-1.5 py-0.5 rounded-sm text-text-body">divide-y</code> container beneath a grid column header.</p>

        {{-- Props table --}}
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] text-text-muted uppercase tracking-widest border-b border-border-default">
                        <th class="px-3 py-2 font-semibold">Prop</th>
                        <th class="px-3 py-2 font-semibold">Type</th>
                        <th class="px-3 py-2 font-semibold">Default</th>
                        <th class="px-3 py-2 font-semibold">Description</th>
                    </tr>
                </thead>
                <tbody class="text-text-secondary">
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">standing</td>
                        <td class="px-3 py-2">GameStanding</td>
                        <td class="px-3 py-2 text-text-muted">—</td>
                        <td class="px-3 py-2">The standing model instance (with team relation loaded)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">is-player</td>
                        <td class="px-3 py-2">bool</td>
                        <td class="px-3 py-2 text-text-muted">false</td>
                        <td class="px-3 py-2">Highlights the row with blue accent (left border + tinted background)</td>
                    </tr>
                    <tr class="border-b border-border-default">
                        <td class="px-3 py-2 font-mono text-accent-blue text-xs">show-gap</td>
                        <td class="px-3 py-2">bool</td>
                        <td class="px-3 py-2 text-text-muted">false</td>
                        <td class="px-3 py-2">Shows an ellipsis separator above the row (for abridged tables with skipped positions)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Visual anatomy --}}
        <div class="bg-surface-700/30 border border-border-default rounded-xl p-6 mb-3">
            <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden max-w-md">
                {{-- Column headers --}}
                <div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2 text-[9px] text-text-faint uppercase tracking-wider border-b border-border-default">
                    <span>#</span>
                    <span>Team</span>
                    <span class="text-center">W</span>
                    <span class="text-center">D</span>
                    <span class="text-center">L</span>
                    <span class="text-center">GD</span>
                    <span class="text-right">Pts</span>
                </div>
                <div class="divide-y divide-border-default">
                    {{-- Normal row --}}
                    <div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2 items-center">
                        <span class="text-[11px] font-heading font-semibold text-text-muted">1</span>
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-5 h-5 rounded bg-surface-600 shrink-0"></div>
                            <span class="text-xs text-text-body truncate">Real Madrid</span>
                        </div>
                        <span class="text-[11px] text-text-muted text-center">20</span>
                        <span class="text-[11px] text-text-muted text-center">4</span>
                        <span class="text-[11px] text-text-muted text-center">3</span>
                        <span class="text-[11px] text-text-muted text-center">+38</span>
                        <span class="text-[11px] text-text-primary font-semibold text-right">64</span>
                    </div>
                    {{-- Normal row --}}
                    <div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2 items-center">
                        <span class="text-[11px] font-heading font-semibold text-text-muted">2</span>
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-5 h-5 rounded bg-surface-600 shrink-0"></div>
                            <span class="text-xs text-text-body truncate">Atl&eacute;tico Madrid</span>
                        </div>
                        <span class="text-[11px] text-text-muted text-center">18</span>
                        <span class="text-[11px] text-text-muted text-center">5</span>
                        <span class="text-[11px] text-text-muted text-center">4</span>
                        <span class="text-[11px] text-text-muted text-center">+29</span>
                        <span class="text-[11px] text-text-primary font-semibold text-right">59</span>
                    </div>
                    {{-- Player row (highlighted) --}}
                    <div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2.5 items-center bg-accent-blue/[0.06] border-l-2 border-l-accent-blue">
                        <span class="text-[11px] font-heading font-semibold text-accent-blue">3</span>
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-5 h-5 rounded bg-surface-600 shrink-0"></div>
                            <span class="text-xs text-text-primary font-semibold truncate">Your Team FC</span>
                        </div>
                        <span class="text-[11px] text-text-primary font-medium text-center">17</span>
                        <span class="text-[11px] text-text-primary font-medium text-center">4</span>
                        <span class="text-[11px] text-text-primary font-medium text-center">6</span>
                        <span class="text-[11px] text-text-primary font-medium text-center">+24</span>
                        <span class="text-[11px] text-accent-blue font-bold text-right">55</span>
                    </div>
                    {{-- Normal row --}}
                    <div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2 items-center">
                        <span class="text-[11px] font-heading font-semibold text-text-muted">4</span>
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-5 h-5 rounded bg-surface-600 shrink-0"></div>
                            <span class="text-xs text-text-body truncate">FC Barcelona</span>
                        </div>
                        <span class="text-[11px] text-text-muted text-center">16</span>
                        <span class="text-[11px] text-text-muted text-center">5</span>
                        <span class="text-[11px] text-text-muted text-center">6</span>
                        <span class="text-[11px] text-text-muted text-center">+21</span>
                        <span class="text-[11px] text-text-primary font-semibold text-right">53</span>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ copied: false }" class="relative">
            <button @click="navigator.clipboard.writeText($refs.standingRowCode.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    class="absolute top-3 right-3 px-2 py-1 text-[10px] font-medium text-text-secondary hover:text-text-body bg-surface-600 rounded-sm transition-colors">
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak class="text-accent-green">Copied!</span>
            </button>
            <pre class="bg-surface-700 text-text-body rounded-lg p-4 overflow-x-auto text-xs leading-relaxed"><code x-ref="standingRowCode">&lt;x-section-card :title="$standingsTitle"&gt;
    {{-- Column headers --}}
    &lt;div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2
                text-[9px] text-text-faint uppercase tracking-wider border-b border-border-default"&gt;
        &lt;span&gt;#&lt;/span&gt;
        &lt;span&gt;Team&lt;/span&gt;
        &lt;span class="text-center"&gt;W&lt;/span&gt;
        &lt;span class="text-center"&gt;D&lt;/span&gt;
        &lt;span class="text-center"&gt;L&lt;/span&gt;
        &lt;span class="text-center"&gt;GD&lt;/span&gt;
        &lt;span class="text-right"&gt;Pts&lt;/span&gt;
    &lt;/div&gt;

    &lt;!-- Rows --&gt;
    &lt;div class="divide-y divide-border-default"&gt;
        @@foreach($standings as $standing)
            &lt;x-standing-row
                :standing="$standing"
                :is-player="$standing-&gt;team_id === $game-&gt;team_id"
            /&gt;
        @@endforeach
    &lt;/div&gt;
&lt;/x-section-card&gt;</code></pre>
        </div>
    </div>
</section>
