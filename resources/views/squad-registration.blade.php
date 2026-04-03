@php
    /** @var App\Models\Game $game */
    /** @var \Illuminate\Support\Collection $goalkeepers */
    /** @var \Illuminate\Support\Collection $defenders */
    /** @var \Illuminate\Support\Collection $midfielders */
    /** @var \Illuminate\Support\Collection $forwards */
    /** @var \Illuminate\Support\Collection $allPlayers */
    /** @var \Illuminate\Support\Collection $playerData */
    /** @var array $suggestions */
    /** @var int $maxStandard */
    /** @var int $academyNumberStart */
    /** @var int $maxAcademyAge */
    /** @var int $minGk */
    /** @var int $minDef */
    /** @var int $minMid */
    /** @var int $minFwd */
    /** @var int $minTotal */
    /** @var bool $isReRegistration */

    $positionGroups = [
        ['key' => 'goalkeepers', 'label' => __('squad.goalkeepers'), 'players' => $goalkeepers, 'group' => 'Goalkeeper'],
        ['key' => 'defenders', 'label' => __('squad.defenders'), 'players' => $defenders, 'group' => 'Defender'],
        ['key' => 'midfielders', 'label' => __('squad.midfielders'), 'players' => $midfielders, 'group' => 'Midfielder'],
        ['key' => 'forwards', 'label' => __('squad.forwards'), 'players' => $forwards, 'group' => 'Forward'],
    ];
@endphp

<x-app-layout :hide-footer="true">
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div x-data="{
        players: @js($playerData),
        suggestions: @js($suggestions),
        assignments: {},
        activePanel: 'registered',
        posTab: 'all',
        maxStandard: {{ $maxStandard }},
        academyStart: {{ $academyNumberStart }},
        maxAcademyAge: {{ $maxAcademyAge }},
        minCounts: { Goalkeeper: {{ $minGk }}, Defender: {{ $minDef }}, Midfielder: {{ $minMid }}, Forward: {{ $minFwd }} },
        minTotal: {{ $minTotal }},
        submitting: false,

        // Drag state
        dragPlayerId: null,
        dragOverPanel: null,

        // Number conflict tracking
        numberConflicts: {},

        init() {
            for (const [id, p] of Object.entries(this.players)) {
                if (p.number) {
                    this.assignments[id] = p.number;
                }
            }
        },

        // ── Computed ──────────────────────────────────────
        get registeredList() {
            return Object.entries(this.assignments)
                .map(([id, num]) => ({ id, number: num, ...this.players[id] }))
                .sort((a, b) => a.number - b.number);
        },

        get availableList() {
            const registered = new Set(Object.keys(this.assignments));
            return Object.entries(this.players)
                .filter(([id]) => !registered.has(id))
                .map(([id, p]) => ({ id, ...p }))
                .filter(p => this.posTab === 'all' || p.position_group === this.posTab)
                .sort((a, b) => b.overall - a.overall);
        },

        get standardCount() {
            return Object.values(this.assignments).filter(n => n >= 1 && n <= this.maxStandard).length;
        },

        get academyCount() {
            return Object.values(this.assignments).filter(n => n >= this.academyStart).length;
        },

        get totalRegistered() {
            return Object.keys(this.assignments).length;
        },

        get slotsRemaining() {
            return this.maxStandard - this.standardCount;
        },

        positionCount(group) {
            return Object.keys(this.assignments).filter(id => this.players[id]?.position_group === group).length;
        },

        positionMet(group) {
            return this.positionCount(group) >= this.minCounts[group];
        },

        get canSubmit() {
            if (this.totalRegistered < this.minTotal) return false;
            if (this.standardCount > this.maxStandard) return false;
            for (const [group, min] of Object.entries(this.minCounts)) {
                if (this.positionCount(group) < min) return false;
            }
            const nums = Object.values(this.assignments);
            if (new Set(nums).size !== nums.length) return false;
            return true;
        },

        isRegistered(playerId) {
            return playerId in this.assignments;
        },

        isAcademyEligible(playerId) {
            const p = this.players[playerId];
            return p && p.age <= this.maxAcademyAge;
        },

        canAddToStandard(playerId) {
            if (this.slotsRemaining > 0) return true;
            return this.isAcademyEligible(playerId);
        },

        // ── Actions ──────────────────────────────────────
        registerPlayer(playerId) {
            if (this.isRegistered(playerId)) return;
            const p = this.players[playerId];
            if (!p) return;

            // If standard full and academy-eligible, assign 26+
            if (this.standardCount >= this.maxStandard && p.age <= this.maxAcademyAge) {
                const taken = new Set(Object.values(this.assignments));
                for (let n = this.academyStart; n <= 99; n++) {
                    if (!taken.has(n)) {
                        this.assignments[playerId] = n;
                        this.assignments = { ...this.assignments };
                        return;
                    }
                }
                return;
            }

            if (this.standardCount >= this.maxStandard) return;

            const taken = new Set(Object.values(this.assignments));
            for (let n = 1; n <= this.maxStandard; n++) {
                if (!taken.has(n)) {
                    this.assignments[playerId] = n;
                    this.assignments = { ...this.assignments };
                    return;
                }
            }
        },

        unregisterPlayer(playerId) {
            if (!this.isRegistered(playerId)) return;
            delete this.assignments[playerId];
            delete this.numberConflicts[playerId];
            this.assignments = { ...this.assignments };
        },

        updateNumber(playerId, rawValue) {
            const val = parseInt(rawValue, 10);
            if (isNaN(val) || val < 1 || val > 99) return;

            // Check for conflicts
            const conflictId = Object.entries(this.assignments).find(([id, n]) => id !== playerId && n === val)?.[0];
            if (conflictId) {
                this.numberConflicts[playerId] = val;
                this.numberConflicts = { ...this.numberConflicts };
                return;
            }

            // Check academy age eligibility for numbers 26+
            if (val >= this.academyStart) {
                const p = this.players[playerId];
                if (p && p.age > this.maxAcademyAge) {
                    this.numberConflicts[playerId] = val;
                    return;
                }
            }

            delete this.numberConflicts[playerId];
            this.assignments[playerId] = val;
            this.assignments = { ...this.assignments };
        },

        autoAssign() {
            this.assignments = {};
            this.numberConflicts = {};
            for (const [id, num] of Object.entries(this.suggestions)) {
                if (this.players[id]) {
                    this.assignments[id] = num;
                }
            }
            this.assignments = { ...this.assignments };
        },

        clearAll() {
            this.assignments = {};
            this.numberConflicts = {};
        },

        // ── Drag and Drop ────────────────────────────────
        onDragStart(e, playerId) {
            this.dragPlayerId = playerId;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', playerId);
        },

        onDragEnd() {
            this.dragPlayerId = null;
            this.dragOverPanel = null;
        },

        onDragOverPanel(e, panel) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.dragOverPanel = panel;
        },

        onDragLeavePanel(panel) {
            if (this.dragOverPanel === panel) {
                this.dragOverPanel = null;
            }
        },

        onDropRegistered(e) {
            e.preventDefault();
            const playerId = e.dataTransfer.getData('text/plain') || this.dragPlayerId;
            if (playerId && !this.isRegistered(playerId)) {
                this.registerPlayer(playerId);
            }
            this.dragPlayerId = null;
            this.dragOverPanel = null;
        },

        onDropAvailable(e) {
            e.preventDefault();
            const playerId = e.dataTransfer.getData('text/plain') || this.dragPlayerId;
            if (playerId && this.isRegistered(playerId)) {
                this.unregisterPlayer(playerId);
            }
            this.dragPlayerId = null;
            this.dragOverPanel = null;
        },
    }" class="min-h-screen pb-32 md:pb-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

            {{-- Header --}}
            <div class="text-center mb-6 md:mb-8">
                <x-team-crest :team="$game->team" class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-3 md:mb-4" />
                <h1 class="text-2xl md:text-3xl font-bold text-text-primary mb-1">
                    {{ __('squad.registration_title') }}
                </h1>
                <p class="text-sm text-text-muted max-w-md mx-auto">
                    {{ __('squad.registration_subtitle', ['max' => $maxStandard]) }}
                </p>
            </div>

            {{-- Flash Messages --}}
            <x-flash-message type="error" :message="session('error')" class="mb-4" />
            <x-flash-message type="success" :message="session('success')" class="mb-4" />

            {{-- Info Banner --}}
            <x-status-banner color="blue" :title="__('squad.registration_info_title')" :description="__('squad.registration_info_description', ['max' => $maxStandard, 'academy_age' => $maxAcademyAge + 1])" class="mb-4">
                <x-slot name="icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                </x-slot>
            </x-status-banner>

            {{-- Action Bar --}}
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-3">
                    {{-- Counter --}}
                    <div class="flex items-center gap-1.5">
                        <span class="text-xl md:text-2xl font-bold transition-colors"
                              :class="standardCount === maxStandard ? 'text-accent-green' : 'text-text-secondary'"
                              x-text="standardCount"></span>
                        <span class="text-sm md:text-base text-text-secondary">/</span>
                        <span class="text-sm md:text-base text-text-secondary">{{ $maxStandard }}</span>
                    </div>
                    <span class="text-xs text-text-muted hidden md:inline" x-show="academyCount > 0">
                        + <span x-text="academyCount"></span> {{ __('squad.academy_short') }}
                    </span>
                </div>

                {{-- Position Minimums --}}
                <div class="hidden md:flex items-center gap-3 text-xs">
                    @foreach ([
                        ['group' => 'Goalkeeper', 'label' => __('squad.goalkeepers_short'), 'min' => $minGk],
                        ['group' => 'Defender', 'label' => __('squad.defenders_short'), 'min' => $minDef],
                        ['group' => 'Midfielder', 'label' => __('squad.midfielders_short'), 'min' => $minMid],
                        ['group' => 'Forward', 'label' => __('squad.forwards_short'), 'min' => $minFwd],
                    ] as $req)
                    <div class="flex items-center gap-1 transition-colors"
                         :class="positionMet('{{ $req['group'] }}') ? 'text-accent-green' : 'text-accent-gold'">
                        <svg x-show="positionMet('{{ $req['group'] }}')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <svg x-show="!positionMet('{{ $req['group'] }}')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                        <span>{{ $req['label'] }}: <span x-text="positionCount('{{ $req['group'] }}')"></span>/{{ $req['min'] }}</span>
                    </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <x-ghost-button type="button" @click="clearAll()" class="text-xs">
                        {{ __('squad.clear_all') }}
                    </x-ghost-button>
                    <x-secondary-button type="button" @click="autoAssign()" class="text-xs">
                        {{ __('squad.auto_assign') }}
                    </x-secondary-button>
                </div>
            </div>

            {{-- Mobile Panel Toggle --}}
            <div class="md:hidden border-b border-border-strong mb-4">
                <nav class="flex">
                    <button @click="activePanel = 'registered'" type="button"
                            :class="activePanel === 'registered'
                                ? 'border-accent-blue text-accent-blue font-semibold'
                                : 'border-transparent text-text-muted'"
                            class="flex-1 px-4 py-3 text-sm text-center border-b-2 min-h-[44px] flex items-center justify-center gap-1.5 transition-colors">
                        <span>{{ __('squad.registered_squad') }}</span>
                        <span class="inline-flex items-center justify-center rounded-full text-[10px] font-semibold min-w-[20px] h-5 px-1"
                              :class="totalRegistered > 0 ? 'bg-accent-green/10 text-accent-green' : 'bg-surface-700 text-text-secondary'"
                              x-text="totalRegistered"></span>
                    </button>
                    <button @click="activePanel = 'available'" type="button"
                            :class="activePanel === 'available'
                                ? 'border-accent-blue text-accent-blue font-semibold'
                                : 'border-transparent text-text-muted'"
                            class="flex-1 px-4 py-3 text-sm text-center border-b-2 min-h-[44px] flex items-center justify-center gap-1.5 transition-colors">
                        <span>{{ __('squad.available_players') }}</span>
                        <span class="inline-flex items-center justify-center rounded-full text-[10px] font-semibold min-w-[20px] h-5 px-1 bg-surface-700 text-text-secondary"
                              x-text="availableList.length"></span>
                    </button>
                </nav>
            </div>

            {{-- ═══ Two-Panel Layout ═══ --}}
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 md:gap-5">

                {{-- ── LEFT: Registered Squad ── --}}
                <div class="md:col-span-7"
                     x-show="activePanel === 'registered'"
                     x-cloak
                     class="md:!block"
                     @dragover.prevent="onDragOverPanel($event, 'registered')"
                     @dragleave="onDragLeavePanel('registered')"
                     @drop="onDropRegistered($event)">

                    <div class="bg-surface-800 rounded-xl border transition-colors overflow-hidden"
                         :class="dragOverPanel === 'registered' && dragPlayerId && !isRegistered(dragPlayerId)
                             ? 'border-accent-green/50 bg-accent-green/5'
                             : 'border-border-strong'">

                        {{-- Panel Header --}}
                        <div class="px-4 md:px-5 py-3 border-b border-border-strong flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary">
                                    {{ __('squad.registered_squad') }}
                                </h3>
                                <span class="text-xs text-text-muted" x-text="'(' + standardCount + '/' + maxStandard + ')'"></span>
                            </div>
                        </div>

                        {{-- Registered Player List --}}
                        <div class="divide-y divide-border-default min-h-[200px]">
                            {{-- Empty state --}}
                            <template x-if="registeredList.length === 0">
                                <div class="flex flex-col items-center justify-center py-12 text-text-muted">
                                    <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                                    <p class="text-sm">{{ __('squad.drag_to_register') }}</p>
                                </div>
                            </template>

                            {{-- Registered rows --}}
                            <template x-for="rp in registeredList" :key="rp.id">
                                <div class="flex items-center gap-2 px-3 md:px-4 py-2.5 transition-all min-h-[52px] group"
                                     :class="{
                                         'opacity-30': dragPlayerId === rp.id,
                                         'bg-accent-blue/5': rp.number >= academyStart,
                                     }"
                                     draggable="true"
                                     @dragstart="onDragStart($event, rp.id)"
                                     @dragend="onDragEnd()">

                                    {{-- Number input --}}
                                    <div class="shrink-0" @click.stop>
                                        <input type="number" min="1" max="99"
                                            :value="rp.number"
                                            @blur="updateNumber(rp.id, $el.value)"
                                            @keydown.enter.prevent="$el.blur()"
                                            class="w-12 h-8 text-sm font-bold text-center tabular-nums bg-surface-700 border rounded-md focus:ring-2 focus:ring-accent-blue focus:border-accent-blue [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                            :class="numberConflicts[rp.id]
                                                ? 'border-accent-red bg-accent-red/10 text-accent-red'
                                                : rp.number >= academyStart
                                                    ? 'border-accent-blue/30 text-accent-blue'
                                                    : 'border-border-strong text-accent-green'">
                                    </div>

                                    {{-- Position badge (rendered via Alpine since these are dynamic) --}}
                                    <div class="shrink-0 flex items-center justify-center w-7 h-7 rounded text-[10px] font-bold -skew-x-12"
                                         :class="{
                                             'bg-amber-500/20 text-amber-400': rp.position_group === 'Goalkeeper',
                                             'bg-blue-500/20 text-blue-400': rp.position_group === 'Defender',
                                             'bg-emerald-500/20 text-emerald-400': rp.position_group === 'Midfielder',
                                             'bg-rose-500/20 text-rose-400': rp.position_group === 'Forward',
                                         }">
                                        <span class="skew-x-12" x-text="rp.position_abbreviation"></span>
                                    </div>

                                    {{-- Name + Age --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-sm text-text-primary truncate" x-text="rp.name"></div>
                                        <div class="text-[11px] text-text-secondary" x-text="rp.age + ' {{ __('squad.years_abbr') }}'"></div>
                                    </div>

                                    {{-- Overall --}}
                                    <div class="shrink-0 flex items-center justify-center w-9 h-9 rounded-lg bg-accent-green/10">
                                        <span class="text-sm font-bold text-accent-green" x-text="rp.overall"></span>
                                    </div>

                                    {{-- Remove button --}}
                                    <button type="button" @click="unregisterPlayer(rp.id)"
                                            class="shrink-0 w-8 h-8 flex items-center justify-center rounded-md text-text-muted hover:text-accent-red hover:bg-accent-red/10 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
                                            :title="'{{ __('app.remove') }}'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        {{-- Conflict warning --}}
                        <template x-if="Object.keys(numberConflicts).length > 0">
                            <div class="px-4 py-2 bg-accent-red/10 border-t border-accent-red/20 text-accent-red text-xs flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                                <span>{{ __('squad.number_conflict') }}</span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- ── RIGHT: Available Players ── --}}
                <div class="md:col-span-5"
                     x-show="activePanel === 'available'"
                     x-cloak
                     class="md:!block"
                     @dragover.prevent="onDragOverPanel($event, 'available')"
                     @dragleave="onDragLeavePanel('available')"
                     @drop="onDropAvailable($event)">

                    <div class="bg-surface-800 rounded-xl border transition-colors overflow-hidden"
                         :class="dragOverPanel === 'available' && dragPlayerId && isRegistered(dragPlayerId)
                             ? 'border-accent-gold/50 bg-accent-gold/5'
                             : 'border-border-strong'">

                        {{-- Panel Header --}}
                        <div class="px-4 md:px-5 py-3 border-b border-border-strong">
                            <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary">
                                {{ __('squad.available_players') }}
                            </h3>
                        </div>

                        {{-- Position Tabs --}}
                        <div class="border-b border-border-strong overflow-x-auto scrollbar-hide">
                            <nav class="flex">
                                <button @click="posTab = 'all'" type="button"
                                        :class="posTab === 'all' ? 'border-accent-blue text-accent-blue font-semibold' : 'border-transparent text-text-muted hover:text-text-body'"
                                        class="shrink-0 px-3 py-2.5 text-xs text-center border-b-2 min-h-[40px] transition-colors">
                                    {{ __('squad.all') }}
                                </button>
                                @foreach ($positionGroups as $pg)
                                <button @click="posTab = '{{ $pg['group'] }}'" type="button"
                                        :class="posTab === '{{ $pg['group'] }}' ? 'border-accent-blue text-accent-blue font-semibold' : 'border-transparent text-text-muted hover:text-text-body'"
                                        class="shrink-0 px-3 py-2.5 text-xs text-center border-b-2 min-h-[40px] transition-colors">
                                    {{ $pg['label'] }}
                                </button>
                                @endforeach
                            </nav>
                        </div>

                        {{-- Available Player List --}}
                        <div class="divide-y divide-border-default max-h-[60vh] md:max-h-[65vh] overflow-y-auto">
                            {{-- Empty state --}}
                            <template x-if="availableList.length === 0 && totalRegistered > 0">
                                <div class="flex flex-col items-center justify-center py-10 text-text-muted">
                                    <svg class="w-8 h-8 mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p class="text-xs">{{ __('squad.all_registered') }}</p>
                                </div>
                            </template>

                            {{-- Available player rows --}}
                            <template x-for="ap in availableList" :key="ap.id">
                                <button type="button"
                                        @click="registerPlayer(ap.id)"
                                        :disabled="!canAddToStandard(ap.id)"
                                        :class="{
                                            'opacity-30': dragPlayerId === ap.id,
                                            'opacity-40 cursor-not-allowed': !canAddToStandard(ap.id),
                                            'hover:bg-surface-700/50 cursor-pointer': canAddToStandard(ap.id),
                                        }"
                                        class="w-full flex items-center gap-2 px-3 md:px-4 py-2.5 text-left transition-all min-h-[52px]"
                                        draggable="true"
                                        @dragstart="onDragStart($event, ap.id)"
                                        @dragend="onDragEnd()">

                                    {{-- Add icon --}}
                                    <div class="shrink-0 w-8 h-8 rounded-md border border-dashed border-border-strong flex items-center justify-center text-text-muted">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                    </div>

                                    {{-- Position badge --}}
                                    <div class="shrink-0 flex items-center justify-center w-7 h-7 rounded text-[10px] font-bold -skew-x-12"
                                         :class="{
                                             'bg-amber-500/20 text-amber-400': ap.position_group === 'Goalkeeper',
                                             'bg-blue-500/20 text-blue-400': ap.position_group === 'Defender',
                                             'bg-emerald-500/20 text-emerald-400': ap.position_group === 'Midfielder',
                                             'bg-rose-500/20 text-rose-400': ap.position_group === 'Forward',
                                         }">
                                        <span class="skew-x-12" x-text="ap.position_abbreviation"></span>
                                    </div>

                                    {{-- Name + Meta --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-sm text-text-primary truncate" x-text="ap.name"></div>
                                        <div class="flex items-center gap-2 text-[11px] text-text-secondary">
                                            <span x-text="ap.age + ' {{ __('squad.years_abbr') }}'"></span>
                                            <span x-show="ap.age <= maxAcademyAge" class="text-accent-blue">{{ __('squad.academy_eligible') }}</span>
                                        </div>
                                    </div>

                                    {{-- Overall --}}
                                    <div class="shrink-0 flex items-center justify-center w-9 h-9 rounded-lg bg-surface-700">
                                        <span class="text-sm font-bold text-text-body" x-text="ap.overall"></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ═══ Sticky Bottom Bar ═══ --}}
        <div class="fixed bottom-0 left-0 right-0 bg-surface-800/95 backdrop-blur-xs border-t border-border-strong shadow-lg z-30">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-3 md:py-4">
                <div class="flex items-center gap-3 md:gap-4">
                    {{-- Position Breakdown (desktop) --}}
                    <div class="hidden md:flex items-center gap-3 text-xs text-text-muted flex-1">
                        @foreach ([
                            ['key' => 'Goalkeeper', 'label' => __('squad.goalkeepers_short')],
                            ['key' => 'Defender', 'label' => __('squad.defenders_short')],
                            ['key' => 'Midfielder', 'label' => __('squad.midfielders_short')],
                            ['key' => 'Forward', 'label' => __('squad.forwards_short')],
                        ] as $i => $pos)
                            @if($i > 0)<span class="text-text-body">&middot;</span>@endif
                            <span><span class="font-semibold text-text-body" x-text="positionCount('{{ $pos['key'] }}')"></span> {{ $pos['label'] }}</span>
                        @endforeach
                    </div>

                    {{-- Mobile: compact counter --}}
                    <div class="flex md:hidden items-center gap-1.5 text-sm flex-1">
                        <span class="font-bold transition-colors"
                              :class="canSubmit ? 'text-accent-green' : 'text-text-body'"
                              x-text="totalRegistered"></span>
                        <span class="text-text-secondary">{{ __('squad.registered_label') }}</span>
                    </div>

                    {{-- Mobile: position minimums --}}
                    <div class="flex md:hidden items-center gap-2 text-[10px]">
                        @foreach ([
                            ['group' => 'Goalkeeper', 'label' => __('squad.goalkeepers_short'), 'min' => $minGk],
                            ['group' => 'Defender', 'label' => __('squad.defenders_short'), 'min' => $minDef],
                            ['group' => 'Midfielder', 'label' => __('squad.midfielders_short'), 'min' => $minMid],
                            ['group' => 'Forward', 'label' => __('squad.forwards_short'), 'min' => $minFwd],
                        ] as $req)
                        <span :class="positionMet('{{ $req['group'] }}') ? 'text-accent-green' : 'text-accent-gold'"
                              x-text="positionCount('{{ $req['group'] }}') + '/{{ $req['min'] }}'"></span>
                        @endforeach
                    </div>

                    {{-- Submit --}}
                    <form method="POST" action="{{ route('game.squad.registration.save', $game->id) }}" class="shrink-0"
                          @submit="submitting = true">
                        @csrf
                        <template x-for="[playerId, number] in Object.entries(assignments)" :key="playerId">
                            <input type="hidden" :name="'assignments[' + playerId + ']'" :value="number">
                        </template>
                        <x-primary-button color="emerald" x-bind:disabled="!canSubmit || submitting" class="w-full md:w-auto">
                            <span x-show="!submitting">{{ __('squad.confirm_registration') }}</span>
                            <span x-show="submitting" x-cloak>{{ __('app.saving') }}...</span>
                        </x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
