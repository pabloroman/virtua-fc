@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('app.transfers') }}</h2>
        </div>

        {{-- Flash Messages --}}
        <x-flash-message type="success" :message="session('success')" class="mb-4" />
        <x-flash-message type="error" :message="session('error')" class="mb-4" />

        @include('partials.transfers-header')

                    {{-- Tab Navigation + How it works --}}
                    <div x-data="{ helpOpen: false }">
                        <x-section-nav :items="[
                            ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => false],
                            ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
                            ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => true],
                            ['href' => route('game.explore', $game->id), 'label' => __('transfers.explore_tab'), 'active' => false],
                        ]">
                            <x-ghost-button color="slate" @click="helpOpen = !helpOpen" class="gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-text-secondary shrink-0">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                                </svg>
                                <span class="hidden md:inline">{{ __('transfers.scouting_help_toggle') }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform hidden md:block" :class="helpOpen ? 'rotate-180' : ''">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </x-ghost-button>
                        </x-section-nav>

                        <div x-show="helpOpen" x-transition class="mt-3 bg-surface-800 border border-border-default rounded-xl p-4 text-sm">
                            <p class="text-text-secondary mb-4">{{ __('transfers.scouting_help_intro') }}</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                {{-- Scout searches --}}
                                <div>
                                    <p class="font-semibold text-text-body mb-2">{{ __('transfers.scouting_help_search_title') }}</p>
                                    <ul class="space-y-2">
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent-blue/20 text-accent-blue text-xs font-bold">1</span>
                                            <span class="text-text-secondary">{{ __('transfers.scouting_help_search_filters') }}</span>
                                        </li>
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent-blue/20 text-accent-blue text-xs font-bold">2</span>
                                            <span class="text-text-secondary">{{ __('transfers.scouting_help_search_time') }}</span>
                                        </li>
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent-blue/20 text-accent-blue text-xs font-bold">3</span>
                                            <span class="text-text-secondary">{{ __('transfers.scouting_help_search_scope') }}</span>
                                        </li>
                                    </ul>
                                </div>

                                {{-- Shortlist & Offers --}}
                                <div>
                                    <p class="font-semibold text-text-body mb-2">{{ __('transfers.scouting_help_shortlist_title') }}</p>
                                    <ul class="space-y-1 text-text-secondary">
                                        <li class="flex gap-2"><span class="text-accent-gold shrink-0">&#9733;</span> {{ __('transfers.scouting_help_shortlist_star') }}</li>
                                        <li class="flex gap-2"><span class="text-accent-blue shrink-0">&#8594;</span> {{ __('transfers.scouting_help_shortlist_bid') }}</li>
                                        <li class="flex gap-2"><span class="text-accent-green shrink-0">&#8644;</span> {{ __('transfers.scouting_help_shortlist_loan') }}</li>
                                        <li class="flex gap-2"><span class="text-text-secondary shrink-0">&#10003;</span> {{ __('transfers.scouting_help_shortlist_precontract') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">

                        {{-- ============================== --}}
                        {{-- LEFT COLUMN (2/3) — Shortlist + Search History --}}
                        {{-- ============================== --}}
                        <div class="md:col-span-2 space-y-6">

                            {{-- Shortlist Section (Reactive Alpine.js) --}}
                            <div x-data="{
                                players: {{ Js::from($shortlistData) }},
                                shortlistMax: {{ \App\Modules\Transfer\Services\ScoutingService::MAX_SHORTLIST_SIZE }},
                                trackingMax: {{ $trackingCapacity['max_slots'] }},
                                trackingUsed: {{ $trackingCapacity['used_slots'] }},
                                sortBy: 'default',
                                sortDir: 'asc',
                                expandedId: null,
                                confirmRemoveId: null,
                                isPreContractPeriod: {{ $isPreContractPeriod ? 'true' : 'false' }},
                                isTransferWindow: {{ $isTransferWindow ? 'true' : 'false' }},
                                csrfToken: '{{ csrf_token() }}',
                                get trackingAvailable() { return Math.max(0, this.trackingMax - this.trackingUsed) },
                                get sortedPlayers() {
                                    if (this.sortBy === 'default') return this.players;
                                    const dir = this.sortDir === 'asc' ? 1 : -1;
                                    return [...this.players].sort((a, b) => {
                                        switch (this.sortBy) {
                                            case 'name': return dir * a.name.localeCompare(b.name);
                                            case 'age': return dir * (a.age - b.age);
                                            case 'position': return dir * a.positionAbbr.localeCompare(b.positionAbbr);
                                            case 'ability': {
                                                const aVal = a.techRange ? (a.techRange[0] + a.techRange[1]) : 0;
                                                const bVal = b.techRange ? (b.techRange[0] + b.techRange[1]) : 0;
                                                return dir * (aVal - bVal);
                                            }
                                            case 'price': {
                                                const aPrice = a.askingPrice ?? a.marketValue ?? 0;
                                                const bPrice = b.askingPrice ?? b.marketValue ?? 0;
                                                return dir * (aPrice - bPrice);
                                            }
                                            default: return 0;
                                        }
                                    });
                                },
                                toggleSort(field) {
                                    if (this.sortBy === field) {
                                        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                                    } else {
                                        this.sortBy = field;
                                        this.sortDir = field === 'name' || field === 'position' ? 'asc' : 'desc';
                                    }
                                },
                                handleToggle(detail) {
                                    if (detail.action === 'added' && detail.player) {
                                        if (!this.players.find(p => p.id === detail.player.id)) {
                                            this.players.unshift(detail.player);
                                        }
                                    } else if (detail.action === 'removed') {
                                        const removed = this.players.find(p => p.id === detail.playerId);
                                        if (removed && removed.isTracking) this.trackingUsed = Math.max(0, this.trackingUsed - 1);
                                        this.players = this.players.filter(p => p.id !== detail.playerId);
                                        if (this.expandedId === detail.playerId) this.expandedId = null;
                                    }
                                },
                                removePlayer(player) {
                                    const url = '{{ route('game.scouting.shortlist.remove', [$game->id, '__ID__']) }}'.replace('__ID__', player.id);
                                    fetch(url, {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': this.csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                                    }).then(r => r.json()).then(() => {
                                        if (player.isTracking) this.trackingUsed = Math.max(0, this.trackingUsed - 1);
                                        this.players = this.players.filter(p => p.id !== player.id);
                                        if (this.expandedId === player.id) this.expandedId = null;
                                        this.confirmRemoveId = null;
                                        window.dispatchEvent(new CustomEvent('shortlist-toggled', { detail: { action: 'removed', playerId: player.id } }));
                                    }).catch(() => { this.confirmRemoveId = null; });
                                },
                                startTracking(player) {
                                    if (this.trackingAvailable <= 0 || player.isTracking || player.intelLevel >= 2) return;
                                    const url = '{{ route('game.scouting.track.start', [$game->id, '__ID__']) }}'.replace('__ID__', player.id);
                                    fetch(url, {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': this.csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                                    }).then(r => r.json()).then(data => {
                                        if (data.success) {
                                            player.isTracking = true;
                                            this.trackingUsed = data.trackingCapacity.used_slots;
                                        }
                                    });
                                },
                                stopTracking(player) {
                                    if (!player.isTracking) return;
                                    const url = '{{ route('game.scouting.track.stop', [$game->id, '__ID__']) }}'.replace('__ID__', player.id);
                                    fetch(url, {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': this.csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                                    }).then(r => r.json()).then(data => {
                                        if (data.success) {
                                            player.isTracking = false;
                                            this.trackingUsed = data.trackingCapacity.used_slots;
                                        }
                                    });
                                },
                                toggleExpand(player) {
                                    this.expandedId = this.expandedId === player.id ? null : player.id;
                                    this.confirmRemoveId = null;
                                },
                                negotiateRoute(id) { return '{{ route('game.negotiate.transfer', [$game->id, '__ID__']) }}'.replace('__ID__', id); },
                                loanRoute(id) { return '{{ route('game.negotiate.loan', [$game->id, '__ID__']) }}'.replace('__ID__', id); },
                                preContractRoute(id) { return '{{ route('game.negotiate.pre-contract', [$game->id, '__ID__']) }}'.replace('__ID__', id); },
                                signFreeAgentRoute(id) { return '{{ route('game.scouting.sign-free-agent', [$game->id, '__ID__']) }}'.replace('__ID__', id); },
                            }" @shortlist-toggled.window="handleToggle($event.detail)">

                                {{-- Filled state --}}
                                <div x-show="players.length > 0" class="border border-border-default rounded-xl overflow-hidden">
                                    <div class="px-5 py-3 bg-accent-gold/10 border-b border-accent-gold/20">
                                        <div class="flex items-center justify-between gap-2">
                                            <h4 class="font-semibold text-sm text-text-primary flex items-center gap-2">
                                                <svg class="w-4 h-4 text-accent-gold" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                                {{ __('transfers.shortlist') }}
                                                <span class="text-xs font-normal text-text-secondary" x-text="'(' + players.length + '/' + shortlistMax + ')'"></span>
                                            </h4>
                                            <div class="flex items-center gap-3">
                                                {{-- Tracking slots indicator --}}
                                                <div x-show="trackingMax > 0" class="flex items-center gap-1.5 text-xs text-teal-400 bg-teal-500/10 px-2.5 py-1 rounded-full border border-teal-500/20">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    <span class="font-medium tabular-nums" x-text="trackingUsed + '/' + trackingMax"></span>
                                                </div>
                                                {{-- Sort controls --}}
                                                <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide" x-show="players.length > 1">
                                                    <span class="text-[10px] text-text-secondary shrink-0 hidden sm:inline">{{ __('transfers.sort_by') }}:</span>
                                                    <template x-for="col in [
                                                        { key: 'name', label: '{{ __('transfers.sort_name') }}' },
                                                        { key: 'age', label: '{{ __('transfers.sort_age') }}' },
                                                        { key: 'ability', label: '{{ __('transfers.sort_ability') }}' },
                                                        { key: 'price', label: '{{ __('transfers.sort_price') }}' },
                                                    ]" :key="col.key">
                                                        <x-pill-button size="xs" @click="toggleSort(col.key)"
                                                            class="rounded-full"
                                                            x-bind:class="sortBy === col.key ? 'bg-accent-gold/20 text-accent-gold' : 'bg-surface-800/70 text-text-muted hover:bg-surface-800 hover:text-text-body'">
                                                            <span x-text="col.label"></span>
                                                            <svg x-show="sortBy === col.key" class="w-3 h-3 transition-transform" :class="sortDir === 'desc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                                            </svg>
                                                        </x-pill-button>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="divide-y divide-border-default">
                                        <template x-for="player in sortedPlayers" :key="player.id">
                                            <div class="px-4 md:px-5 py-3 hover:bg-surface-700/50">
                                                {{-- Player Summary Row --}}
                                                <div class="flex items-center gap-3 cursor-pointer" @click="toggleExpand(player)">
                                                    {{-- Position badge --}}
                                                    <span :class="player.positionBg + ' ' + player.positionText + ' inline-flex items-center justify-center w-7 h-7 text-xs -skew-x-12 font-semibold'">
                                                        <span class="skew-x-12" x-text="player.positionAbbr"></span>
                                                    </span>
                                                    {{-- Name, age, team --}}
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <span class="font-semibold text-text-primary truncate" x-text="player.name"></span>
                                                            <span class="text-xs text-text-secondary" x-text="player.age + ' {{ __('app.years') }}'"></span>
                                                            <template x-if="!player.isFreeAgent && player.isExpiring">
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[10px] font-medium bg-accent-gold/10 text-accent-gold">{{ __('transfers.expiring_contract') }}</span>
                                                            </template>
                                                            {{-- Active tracking indicator --}}
                                                            <template x-if="player.isTracking && player.intelLevel < 2">
                                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-sm text-[10px] font-medium bg-teal-500/10 text-teal-400">
                                                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500 animate-pulse"></span>
                                                                    {{ __('transfers.tracking_in_progress') }}
                                                                </span>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center gap-2 text-xs text-text-muted mt-0.5">
                                                            <template x-if="player.isFreeAgent">
                                                                <span>{{ __('transfers.free_agent') }}</span>
                                                            </template>
                                                            <template x-if="!player.isFreeAgent">
                                                                <div class="flex items-center gap-2">
                                                                    <template x-if="player.teamImage">
                                                                        <img :src="player.teamImage" class="w-4 h-4 shrink-0">
                                                                    </template>
                                                                    <span class="truncate" x-text="player.teamName"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    {{-- Ability range (locked if level 0) --}}
                                                    <div class="text-right hidden sm:block shrink-0">
                                                        <template x-if="player.techRange">
                                                            <div>
                                                                <div class="text-xs text-text-secondary">{{ __('transfers.ability') }}</div>
                                                                <div class="text-sm font-semibold text-text-body tabular-nums" x-text="player.techRange[0] + '-' + player.techRange[1]"></div>
                                                            </div>
                                                        </template>
                                                        <template x-if="!player.techRange">
                                                            <div class="flex items-center gap-1 text-text-secondary text-xs">
                                                                <span>{{ __('transfers.ability') }}</span>
                                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    {{-- Asking price (locked if level 0, hidden for free agents) --}}
                                                    <div class="text-right shrink-0" x-show="!player.isFreeAgent">
                                                        <template x-if="player.formattedAskingPrice">
                                                            <div>
                                                                <div class="text-xs text-text-secondary">{{ __('transfers.asking_price') }}</div>
                                                                <div class="text-sm font-semibold" :class="player.canAffordFee ? 'text-text-primary' : 'text-accent-red'" x-text="player.formattedAskingPrice"></div>
                                                            </div>
                                                        </template>
                                                        <template x-if="!player.formattedAskingPrice">
                                                            <div class="flex items-center gap-1 text-text-secondary text-xs">
                                                                <span>{{ __('transfers.asking_price') }}</span>
                                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    {{-- Track / Expand / Remove buttons --}}
                                                    <div class="flex items-center gap-1 shrink-0">
                                                        {{-- Track button (for level < 2 players, not currently tracking) --}}
                                                        <template x-if="player.intelLevel < 2 && !player.isTracking && trackingMax > 0">
                                                            <x-icon-button size="sm" @click.stop="startTracking(player)"
                                                                x-bind:disabled="trackingAvailable <= 0"
                                                                x-bind:class="trackingAvailable > 0 ? 'text-teal-400 hover:text-teal-300 hover:bg-teal-500/10' : 'text-text-body cursor-not-allowed'"
                                                                class="min-h-[44px] sm:min-h-0"
                                                                x-bind:title="trackingAvailable > 0 ? {{ \Illuminate\Support\Js::from(__('transfers.start_tracking')) }} : {{ \Illuminate\Support\Js::from(__('transfers.no_tracking_slots')) }}">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                            </x-icon-button>
                                                        </template>
                                                        {{-- Stop tracking button (for currently tracking players) --}}
                                                        <template x-if="player.isTracking">
                                                            <x-icon-button size="sm" @click.stop="stopTracking(player)"
                                                                class="text-teal-400 hover:text-red-500 hover:bg-accent-red/10 min-h-[44px] sm:min-h-0"
                                                                title="{{ __('transfers.stop_tracking') }}">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                            </x-icon-button>
                                                        </template>
                                                        <svg class="w-4 h-4 text-text-secondary transition-transform" :class="expandedId === player.id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                        <template x-if="confirmRemoveId !== player.id">
                                                            <x-icon-button size="sm" @click.stop="confirmRemoveId = player.id" class="text-text-body hover:text-red-500 hover:bg-accent-red/10 min-h-[44px] sm:min-h-0" title="{{ __('transfers.remove_from_shortlist') }}">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                            </x-icon-button>
                                                        </template>
                                                        <template x-if="confirmRemoveId === player.id">
                                                            <x-ghost-button color="red" size="xs" @click.stop="removePlayer(player)" class="min-h-[44px] sm:min-h-0">
                                                                {{ __('transfers.remove_from_shortlist') }}
                                                            </x-ghost-button>
                                                        </template>
                                                    </div>
                                                </div>

                                                {{-- Expanded Section --}}
                                                <div x-show="expandedId === player.id" x-cloak class="mt-3"
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                                     x-transition:enter-end="opacity-100 translate-y-0"
                                                     x-transition:leave="transition ease-in duration-150"
                                                     x-transition:leave-start="opacity-100 translate-y-0"
                                                     x-transition:leave-end="opacity-0 -translate-y-1">

                                                    {{-- Level 0: Track to unlock prompt --}}
                                                    <template x-if="player.intelLevel === 0">
                                                        <div class="bg-surface-800 rounded-lg p-4">
                                                            <div class="flex flex-col items-center text-center py-2">
                                                                <div class="w-10 h-10 rounded-full bg-teal-500/10 flex items-center justify-center mb-3">
                                                                    <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                                </div>
                                                                <template x-if="!player.isTracking">
                                                                    <div>
                                                                        <p class="text-sm font-semibold text-text-body mb-1">{{ __('transfers.track_to_unlock') }}</p>
                                                                        <p class="text-xs text-text-muted mb-3 max-w-xs">{{ __('transfers.track_to_unlock_desc') }}</p>
                                                                    </div>
                                                                </template>
                                                                <template x-if="player.isTracking">
                                                                    <div>
                                                                        <p class="text-sm font-semibold text-text-body mb-1">{{ __('transfers.tracking_in_progress_title') }}</p>
                                                                        <p class="text-xs text-text-muted mb-3 max-w-xs">{{ __('transfers.tracking_in_progress_desc') }}</p>
                                                                    </div>
                                                                </template>
                                                                <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-text-muted mb-3">
                                                                    <span>{{ __('transfers.market_value') }}: <span class="font-semibold text-text-body" x-text="player.formattedMarketValue"></span></span>
                                                                    <template x-if="!player.isFreeAgent && player.contractYear">
                                                                        <span>{{ __('transfers.contract_until') }}: <span class="font-semibold text-text-body" x-text="player.contractYear"></span></span>
                                                                    </template>
                                                                </div>
                                                                <template x-if="!player.isTracking && trackingMax > 0">
                                                                    <x-primary-button color="teal" size="xs" @click="startTracking(player)"
                                                                        x-bind:disabled="trackingAvailable <= 0">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                                        {{ __('transfers.start_tracking') }}
                                                                    </x-primary-button>
                                                                </template>
                                                                <template x-if="player.isTracking">
                                                                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-teal-500/10 text-teal-400 border border-teal-500/20">
                                                                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500 animate-pulse"></span>
                                                                        {{ __('transfers.tracking_in_progress') }}
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>

                                                    {{-- Level 1+: Full scouting detail --}}
                                                    <template x-if="player.intelLevel >= 1">
                                                        <div class="bg-surface-800 rounded-lg p-4">
                                                            {{-- Financial summary --}}
                                                            <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs mb-3">
                                                                <div>
                                                                    <span class="text-text-muted">{{ __('transfers.estimated_asking_price') }}:</span>
                                                                    <span class="font-semibold" :class="player.canAffordFee ? 'text-text-primary' : 'text-accent-red'" x-text="player.formattedAskingPrice"></span>
                                                                </div>
                                                                <div>
                                                                    <span class="text-text-muted">{{ __('transfers.wage_demand') }}:</span>
                                                                    <span class="font-semibold text-text-body" x-text="player.formattedWageDemand + '{{ __('squad.per_year') }}'"></span>
                                                                </div>
                                                            </div>

                                                            {{-- Deep Intel: Willingness & Rival Interest (Level 2) --}}
                                                            <template x-if="player.intelLevel >= 2 && player.willingness">
                                                                <div class="flex flex-wrap gap-2 mb-3">
                                                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-medium border"
                                                                        :class="{
                                                                            'bg-accent-green/10 text-accent-green border-accent-green/20': player.willingness === 'very_interested' || player.willingness === 'open',
                                                                            'bg-accent-gold/10 text-accent-gold border-accent-gold/20': player.willingness === 'undecided',
                                                                            'bg-accent-red/10 text-accent-red border-accent-red/20': player.willingness === 'reluctant' || player.willingness === 'not_interested',
                                                                        }">
                                                                        {{ __('transfers.willingness') }}: <span x-text="player.willingnessLabel"></span>
                                                                    </span>
                                                                    <template x-if="player.rivalInterest">
                                                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-medium bg-accent-orange/10 text-accent-orange border border-accent-orange/20">
                                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                                                            {{ __('transfers.rival_interest') }}
                                                                        </span>
                                                                    </template>
                                                                </div>
                                                            </template>

                                                            {{-- Tracking in progress indicator for Level 1 --}}
                                                            <template x-if="player.intelLevel === 1 && player.isTracking">
                                                                <div class="flex items-center gap-1.5 mb-3 text-xs text-teal-400">
                                                                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500 animate-pulse"></span>
                                                                    {{ __('transfers.tracking_in_progress') }} — {{ __('transfers.intel_deep') }}
                                                                </div>
                                                            </template>

                                                            {{-- Action: Free agent signing --}}
                                                            <template x-if="player.isFreeAgent && !player.hasExistingOffer">
                                                                <form :action="signFreeAgentRoute(player.id)" method="POST">
                                                                    <input type="hidden" name="_token" :value="csrfToken">
                                                                    <x-primary-button color="green" size="xs">
                                                                        {{ __('transfers.sign_free_agent') }}
                                                                    </x-primary-button>
                                                                </form>
                                                            </template>

                                                            {{-- Action: Offer awaiting response (pending, no counter) --}}
                                                            <template x-if="!player.isFreeAgent && player.hasExistingOffer && player.offerStatus === 'pending' && !player.offerIsCounter">
                                                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-gold/10 text-accent-gold border border-accent-gold/20">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                    {{ __('transfers.bid_awaiting_response') }}
                                                                </div>
                                                            </template>

                                                            {{-- Action: Counter-offer received (pending with counter) --}}
                                                            <template x-if="!player.isFreeAgent && player.hasExistingOffer && player.offerStatus === 'pending' && player.offerIsCounter">
                                                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-blue/10 text-blue-400 border border-accent-blue/20">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                                                    {{ __('transfers.counter_offer_received') }}
                                                                </div>
                                                            </template>

                                                            {{-- Action: Transfer agreed, waiting for window --}}
                                                            <template x-if="!player.isFreeAgent && player.hasExistingOffer && player.offerStatus === 'agreed'">
                                                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-accent-green/10 text-accent-green border border-accent-green/20">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                    {{ __('transfers.transfer_agreed') }}
                                                                </div>
                                                            </template>

                                                            {{-- Action: Pre-contract --}}
                                                            <template x-if="!player.isFreeAgent && !player.hasExistingOffer && player.isExpiring && isPreContractPeriod">
                                                                <x-primary-button size="xs" color="green"
                                                                    @click="$dispatch('open-negotiation', {
                                                                        playerName: player.name,
                                                                        negotiateUrl: preContractRoute(player.id),
                                                                        mode: 'pre_contract',
                                                                        phase: 'personal_terms',
                                                                        chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_pre_contract_title')) }}
                                                                    })">
                                                                    {{ __('transfers.negotiate_pre_contract') }}
                                                                </x-primary-button>
                                                            </template>

                                                            {{-- Action: Can't afford --}}
                                                            <template x-if="!player.isFreeAgent && !player.hasExistingOffer && !(player.isExpiring && isPreContractPeriod) && !player.canAffordFee">
                                                                <div class="text-xs text-accent-red font-medium">
                                                                    {{ __('transfers.transfer_fee_exceeds_budget') }}
                                                                </div>
                                                            </template>

                                                            {{-- Action: Negotiate + Loan --}}
                                                            <template x-if="!player.isFreeAgent && !player.hasExistingOffer && !(player.isExpiring && isPreContractPeriod) && player.canAffordFee">
                                                                <div class="flex flex-col sm:flex-row gap-2">
                                                                    <x-primary-button size="xs"
                                                                        @click="$dispatch('open-negotiation', {
                                                                            playerName: player.name,
                                                                            negotiateUrl: negotiateRoute(player.id),
                                                                            mode: 'transfer_fee',
                                                                            phase: 'club_fee',
                                                                            chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_transfer_title')) }}
                                                                        })">
                                                                        {{ __('transfers.negotiate') }}
                                                                    </x-primary-button>
                                                                    <x-secondary-button size="xs"
                                                                        @click="$dispatch('open-negotiation', {
                                                                            playerName: player.name,
                                                                            negotiateUrl: loanRoute(player.id),
                                                                            mode: 'loan',
                                                                            phase: 'club_fee',
                                                                            chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_loan_title')) }}
                                                                        })">
                                                                        {{ __('transfers.request_loan') }}
                                                                    </x-secondary-button>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- Empty state --}}
                                <div x-show="players.length === 0" x-cloak class="border border-dashed border-border-default rounded-xl p-6 text-center text-text-secondary">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                    <p class="text-sm">{{ __('transfers.shortlist_empty') }}</p>
                                </div>

                            </div>

                            {{-- Search History --}}
                            @if($searchHistory->isNotEmpty())
                            <div class="border border-border-default rounded-xl overflow-hidden">
                                <div class="px-5 py-3 bg-surface-800 border-b border-border-default">
                                    <h4 class="font-semibold text-sm text-text-primary flex items-center gap-2">
                                        {{ __('transfers.search_history') }}
                                        <span class="text-xs font-normal text-text-secondary">({{ $searchHistory->count() }})</span>
                                    </h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="text-left bg-surface-800 border-b border-border-default">
                                            <tr>
                                                <th class="font-medium py-2 pl-4 text-text-muted">{{ __('transfers.position_required', ['*' => '']) }}</th>
                                                <th class="font-medium py-2 text-text-muted hidden md:table-cell">{{ __('transfers.scope') }}</th>
                                                <th class="font-medium py-2 text-text-muted hidden md:table-cell">{{ __('transfers.age_range') }}</th>
                                                <th class="font-medium py-2 text-center text-text-muted">{{ __('transfers.scout_results') }}</th>
                                                <th class="font-medium py-2 pr-4 text-right text-text-muted"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($searchHistory as $historyReport)
                                                @php
                                                    $filters = $historyReport->filters;
                                                    $histScopeLabel = isset($filters['scope']) && count($filters['scope']) === 1
                                                        ? (in_array('domestic', $filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international'))
                                                        : __('transfers.scope_domestic') . ' + ' . __('transfers.scope_international');
                                                    $resultCount = is_array($historyReport->player_ids) ? count($historyReport->player_ids) : 0;
                                                    $ageLabel = null;
                                                    if (isset($filters['age_min']) || isset($filters['age_max'])) {
                                                        $ageMin = $filters['age_min'] ?? '16';
                                                        $ageMax = $filters['age_max'] ?? '40';
                                                        $ageLabel = $ageMin . '-' . $ageMax;
                                                    }
                                                @endphp
                                                <tr class="border-t border-border-default hover:bg-surface-700/50">
                                                    <td class="py-3 pl-4">
                                                        <span class="font-medium text-text-primary">{{ isset($filters['position']) ? \App\Support\PositionMapper::filterToDisplayName($filters['position']) : '-' }}</span>
                                                        <div class="text-xs text-text-secondary md:hidden">{{ $histScopeLabel }}</div>
                                                    </td>
                                                    <td class="py-3 text-text-secondary hidden md:table-cell">{{ $histScopeLabel }}</td>
                                                    <td class="py-3 text-text-secondary hidden md:table-cell">{{ $ageLabel ?? __('transfers.all_ages') }}</td>
                                                    <td class="py-3 text-center text-text-secondary tabular-nums">{{ __('transfers.results_count', ['count' => $resultCount]) }}</td>
                                                    <td class="py-3 text-right pr-4">
                                                        <div class="flex items-center justify-end gap-2" x-data="{ confirmDelete: false }">
                                                            <x-action-button color="blue" type="button" x-data @click="$dispatch('show-scout-results', '{{ route('game.scouting.results', [$game->id, $historyReport->id]) }}')" class="sm:min-h-0">
                                                                {{ __('transfers.view_results') }}
                                                            </x-action-button>
                                                            <template x-if="!confirmDelete">
                                                                <x-icon-button size="sm" @click="confirmDelete = true" class="hover:text-red-500 hover:bg-accent-red/10 sm:min-h-0" title="{{ __('transfers.delete_search') }}">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                    </svg>
                                                                </x-icon-button>
                                                            </template>
                                                            <template x-if="confirmDelete">
                                                                <form method="POST" action="{{ route('game.scouting.delete', [$game->id, $historyReport->id]) }}" class="inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <x-action-button color="red" class="sm:min-h-0">
                                                                        {{ __('transfers.delete_search') }}
                                                                    </x-action-button>
                                                                </form>
                                                            </template>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @else
                            <div class="text-center py-12 text-text-secondary">
                                <svg class="w-12 h-12 mx-auto mb-3 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <p class="font-medium">{{ __('transfers.no_search_history') }}</p>
                                <p class="text-sm mt-1">{{ __('transfers.scout_search_desc') }}</p>
                            </div>
                            @endif

                        </div>

                        {{-- ============================== --}}
                        {{-- RIGHT COLUMN (1/3) — Search Panel --}}
                        {{-- ============================== --}}
                        <div class="space-y-6">

                            @if($searchingReport)
                                {{-- Searching State --}}
                                <div class="border border-accent-blue/20 rounded-xl p-5 bg-accent-blue/10">
                                    <div class="text-center">
                                        <svg class="w-10 h-10 mx-auto mb-3 text-accent-blue animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <h4 class="font-semibold text-text-primary mb-1">{{ __('transfers.scout_searching') }}</h4>
                                        <p class="text-sm text-text-secondary mb-1">
                                            {{ trans_choice('game.weeks_remaining', $searchingReport->weeks_remaining, ['count' => $searchingReport->weeks_remaining]) }}
                                        </p>
                                        <p class="text-xs text-text-muted mb-4">
                                            {{ __('transfers.looking_for') }}: <span class="font-medium">{{ \App\Support\PositionMapper::filterToDisplayName($searchingReport->filters['position']) }}</span>
                                            @if(isset($searchingReport->filters['scope']) && count($searchingReport->filters['scope']) === 1)
                                                — <span class="font-medium">{{ in_array('domestic', $searchingReport->filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international') }}</span>
                                            @endif
                                        </p>
                                        <div class="w-full bg-bar-track rounded-full h-2 mb-4">
                                            @php $progress = (($searchingReport->weeks_total - $searchingReport->weeks_remaining) / $searchingReport->weeks_total) * 100; @endphp
                                            <div class="bg-accent-blue h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <form method="post" action="{{ route('game.scouting.cancel', $game->id) }}">
                                            @csrf
                                            <x-ghost-button size="xs" type="submit" class="text-sm text-center">
                                                {{ __('transfers.cancel_search') }}
                                            </x-ghost-button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- New Search Button --}}
                                <div x-data>
                                    <x-primary-button type="button" @click="$dispatch('open-modal', 'scout-search')" class="w-full gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        {{ __('transfers.new_scout_search') }}
                                    </x-primary-button>
                                </div>
                            @endif

                        </div>
                    </div>

    </div>

    <x-scout-search-modal :game="$game" :can-search-internationally="$canSearchInternationally" />
    <x-scout-results-modal />
    <x-negotiation-chat-modal />

</x-app-layout>
