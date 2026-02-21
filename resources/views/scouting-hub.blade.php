@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8">
                    @include('partials.transfers-header')

                    {{-- Tab Navigation --}}
                    <x-section-nav :items="[
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => false, 'badge' => $counterOfferCount > 0 ? $counterOfferCount : null],
                        ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => true],
                    ]" />

                    {{-- How it works toggle --}}
                    <div x-data="{ open: false }" class="mt-4">
                        <button @click="open = !open" class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-slate-400 shrink-0">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ __('transfers.scouting_help_toggle') }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-show="open" x-transition class="mt-3 bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm">
                            <p class="text-slate-600 mb-4">{{ __('transfers.scouting_help_intro') }}</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                {{-- Scout searches --}}
                                <div>
                                    <p class="font-semibold text-slate-700 mb-2">{{ __('transfers.scouting_help_search_title') }}</p>
                                    <ul class="space-y-2">
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-sky-200 text-sky-700 text-xs font-bold">1</span>
                                            <span class="text-slate-600">{{ __('transfers.scouting_help_search_filters') }}</span>
                                        </li>
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-sky-200 text-sky-700 text-xs font-bold">2</span>
                                            <span class="text-slate-600">{{ __('transfers.scouting_help_search_time') }}</span>
                                        </li>
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-sky-200 text-sky-700 text-xs font-bold">3</span>
                                            <span class="text-slate-600">{{ __('transfers.scouting_help_search_scope') }}</span>
                                        </li>
                                    </ul>
                                </div>

                                {{-- Shortlist & Offers --}}
                                <div>
                                    <p class="font-semibold text-slate-700 mb-2">{{ __('transfers.scouting_help_shortlist_title') }}</p>
                                    <ul class="space-y-1 text-slate-600">
                                        <li class="flex gap-2"><span class="text-amber-500 shrink-0">&#9733;</span> {{ __('transfers.scouting_help_shortlist_star') }}</li>
                                        <li class="flex gap-2"><span class="text-sky-500 shrink-0">&#8594;</span> {{ __('transfers.scouting_help_shortlist_bid') }}</li>
                                        <li class="flex gap-2"><span class="text-emerald-500 shrink-0">&#8644;</span> {{ __('transfers.scouting_help_shortlist_loan') }}</li>
                                        <li class="flex gap-2"><span class="text-slate-400 shrink-0">&#10003;</span> {{ __('transfers.scouting_help_shortlist_precontract') }}</li>
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
                                expandedId: null,
                                confirmRemoveId: null,
                                isPreContractPeriod: {{ $isPreContractPeriod ? 'true' : 'false' }},
                                csrfToken: '{{ csrf_token() }}',
                                handleToggle(detail) {
                                    if (detail.action === 'added' && detail.player) {
                                        if (!this.players.find(p => p.id === detail.player.id)) {
                                            this.players.unshift(detail.player);
                                        }
                                    } else if (detail.action === 'removed') {
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
                                        this.players = this.players.filter(p => p.id !== player.id);
                                        if (this.expandedId === player.id) this.expandedId = null;
                                        this.confirmRemoveId = null;
                                        window.dispatchEvent(new CustomEvent('shortlist-toggled', { detail: { action: 'removed', playerId: player.id } }));
                                    }).catch(() => { this.confirmRemoveId = null; });
                                },
                                toggleExpand(player) {
                                    this.expandedId = this.expandedId === player.id ? null : player.id;
                                    this.confirmRemoveId = null;
                                },
                                bidRoute(id) { return '{{ route('game.scouting.bid', [$game->id, '__ID__']) }}'.replace('__ID__', id); },
                                loanRoute(id) { return '{{ route('game.scouting.loan', [$game->id, '__ID__']) }}'.replace('__ID__', id); },
                                preContractRoute(id) { return '{{ route('game.scouting.pre-contract', [$game->id, '__ID__']) }}'.replace('__ID__', id); },
                            }" @shortlist-toggled.window="handleToggle($event.detail)">

                                {{-- Filled state --}}
                                <div x-show="players.length > 0" class="border rounded-lg overflow-hidden">
                                    <div class="px-5 py-3 bg-amber-50 border-b border-amber-200">
                                        <h4 class="font-semibold text-sm text-slate-900 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            {{ __('transfers.shortlist') }}
                                            <span class="text-xs font-normal text-slate-400" x-text="'(' + players.length + ')'"></span>
                                        </h4>
                                    </div>
                                    <div class="divide-y divide-slate-100">
                                        <template x-for="player in players" :key="player.id">
                                            <div class="px-4 md:px-5 py-3 hover:bg-slate-50/50">
                                                {{-- Player Summary Row --}}
                                                <div class="flex items-center gap-3 cursor-pointer" @click="toggleExpand(player)">
                                                    {{-- Position badge --}}
                                                    <span :class="player.positionBg + ' ' + player.positionText + ' inline-flex items-center justify-center w-7 h-7 text-xs -skew-x-12 font-semibold'">
                                                        <span class="skew-x-12" x-text="player.positionAbbr"></span>
                                                    </span>
                                                    {{-- Name, age, team --}}
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <span class="font-semibold text-slate-900 truncate" x-text="player.name"></span>
                                                            <span class="text-xs text-slate-400" x-text="player.age + ' {{ __('app.years') }}'"></span>
                                                            <template x-if="player.isExpiring">
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700">{{ __('transfers.expiring_contract') }}</span>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center gap-2 text-xs text-slate-500 mt-0.5">
                                                            <template x-if="player.teamImage">
                                                                <img :src="player.teamImage" class="w-4 h-4 shrink-0">
                                                            </template>
                                                            <span class="truncate" x-text="player.teamName"></span>
                                                        </div>
                                                    </div>
                                                    {{-- Ability range --}}
                                                    <div class="text-right hidden sm:block shrink-0">
                                                        <div class="text-xs text-slate-400">{{ __('transfers.ability') }}</div>
                                                        <div class="text-sm font-semibold text-slate-700 tabular-nums" x-text="player.techRange[0] + '-' + player.techRange[1]"></div>
                                                    </div>
                                                    {{-- Asking price --}}
                                                    <div class="text-right shrink-0">
                                                        <div class="text-xs text-slate-400">{{ __('transfers.asking_price') }}</div>
                                                        <div class="text-sm font-semibold" :class="player.canAffordFee ? 'text-slate-900' : 'text-red-600'" x-text="player.formattedAskingPrice"></div>
                                                    </div>
                                                    {{-- Expand + Remove buttons --}}
                                                    <div class="flex items-center gap-1 shrink-0">
                                                        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="expandedId === player.id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                        <template x-if="confirmRemoveId !== player.id">
                                                            <button @click.stop="confirmRemoveId = player.id" class="p-1.5 text-slate-300 hover:text-red-500 rounded hover:bg-red-50 transition-colors min-h-[44px] sm:min-h-0" title="{{ __('transfers.remove_from_shortlist') }}">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                            </button>
                                                        </template>
                                                        <template x-if="confirmRemoveId === player.id">
                                                            <button @click.stop="removePlayer(player)" class="px-2 py-1 text-xs font-semibold text-red-600 border border-red-200 rounded hover:bg-red-50 transition-colors min-h-[44px] sm:min-h-0">
                                                                {{ __('transfers.remove_from_shortlist') }}
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>

                                                {{-- Expanded Offer Section --}}
                                                <div x-show="expandedId === player.id" x-cloak class="mt-3"
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                                     x-transition:enter-end="opacity-100 translate-y-0"
                                                     x-transition:leave="transition ease-in duration-150"
                                                     x-transition:leave-start="opacity-100 translate-y-0"
                                                     x-transition:leave-end="opacity-0 -translate-y-1">
                                                    <div class="bg-slate-50 rounded-lg p-4">
                                                        {{-- Financial summary --}}
                                                        <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs mb-3">
                                                            <div>
                                                                <span class="text-slate-500">{{ __('transfers.estimated_asking_price') }}:</span>
                                                                <span class="font-semibold" :class="player.canAffordFee ? 'text-slate-900' : 'text-red-600'" x-text="player.formattedAskingPrice"></span>
                                                            </div>
                                                            <div>
                                                                <span class="text-slate-500">{{ __('transfers.wage_demand') }}:</span>
                                                                <span class="font-semibold text-slate-700" x-text="player.formattedWageDemand + '{{ __('squad.per_year') }}'"></span>
                                                            </div>
                                                        </div>

                                                        {{-- Action: Offer awaiting response (pending, no counter) --}}
                                                        <template x-if="player.hasExistingOffer && player.offerStatus === 'pending' && !player.offerIsCounter">
                                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-50 text-amber-700 border border-amber-200">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                {{ __('transfers.bid_awaiting_response') }}
                                                            </div>
                                                        </template>

                                                        {{-- Action: Counter-offer received (pending with counter) --}}
                                                        <template x-if="player.hasExistingOffer && player.offerStatus === 'pending' && player.offerIsCounter">
                                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 text-blue-700 border border-blue-200">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                                                {{ __('transfers.counter_offer_received') }}
                                                            </div>
                                                        </template>

                                                        {{-- Action: Transfer agreed, waiting for window --}}
                                                        <template x-if="player.hasExistingOffer && player.offerStatus === 'agreed'">
                                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-green-50 text-green-700 border border-green-200">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                {{ __('transfers.transfer_agreed') }}
                                                            </div>
                                                        </template>

                                                        {{-- Action: Pre-contract --}}
                                                        <template x-if="!player.hasExistingOffer && player.isExpiring && isPreContractPeriod">
                                                            <form :action="preContractRoute(player.id)" method="POST" class="space-y-2">
                                                                <input type="hidden" name="_token" :value="csrfToken">
                                                                <label class="block text-xs font-medium text-slate-600">{{ __('transfers.offered_wage_euros') }}</label>
                                                                <div class="flex items-center gap-2" x-data="{
                                                                    holdTimer: null, holdInterval: null,
                                                                    get step() { return player.wageEuros >= 1000000 ? 100000 : 10000 },
                                                                    get display() { return '€ ' + new Intl.NumberFormat('es-ES').format(player.wageEuros) },
                                                                    get atMin() { return player.wageEuros <= 0 },
                                                                    increment() { player.wageEuros += this.step },
                                                                    decrement() { player.wageEuros = Math.max(player.wageEuros - this.step, 0) },
                                                                    startHold(fn) { fn(); this.holdTimer = setTimeout(() => { this.holdInterval = setInterval(() => fn(), 80) }, 400) },
                                                                    stopHold() { clearTimeout(this.holdTimer); clearInterval(this.holdInterval) }
                                                                }">
                                                                    <div class="inline-flex items-stretch border border-slate-300 rounded-lg overflow-hidden h-[36px]">
                                                                        <input type="hidden" name="offered_wage" :value="player.wageEuros">
                                                                        <button type="button" :disabled="atMin" :class="atMin ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 active:bg-slate-200'" class="min-h-[32px] sm:min-h-0 min-w-[32px] text-sm flex items-center justify-center bg-slate-50 text-slate-700 font-bold select-none transition-colors" @mousedown.prevent="startHold(() => decrement())" @mouseup="stopHold()" @mouseleave="stopHold()" @touchstart.prevent="startHold(() => decrement())" @touchend="stopHold()">&minus;</button>
                                                                        <input type="text" readonly :value="display" class="min-h-[32px] sm:min-h-0 w-28 text-xs text-center font-semibold text-slate-800 bg-white border-x border-y-0 border-slate-300 outline-none cursor-default focus:outline-none focus:ring-0 focus:border-slate-300">
                                                                        <button type="button" class="min-h-[32px] sm:min-h-0 min-w-[32px] text-sm flex items-center justify-center bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-700 font-bold select-none transition-colors" @mousedown.prevent="startHold(() => increment())" @mouseup="stopHold()" @mouseleave="stopHold()" @touchstart.prevent="startHold(() => increment())" @touchend="stopHold()">+</button>
                                                                    </div>
                                                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                                                                        {{ __('transfers.submit_pre_contract') }}
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </template>

                                                        {{-- Action: Can't afford --}}
                                                        <template x-if="!player.hasExistingOffer && !(player.isExpiring && isPreContractPeriod) && !player.canAffordFee">
                                                            <div class="text-xs text-red-600 font-medium">
                                                                {{ __('transfers.transfer_fee_exceeds_budget') }}
                                                            </div>
                                                        </template>

                                                        {{-- Action: Bid + Loan --}}
                                                        <template x-if="!player.hasExistingOffer && !(player.isExpiring && isPreContractPeriod) && player.canAffordFee">
                                                            <div class="flex flex-col sm:flex-row gap-2" x-data="{
                                                                holdTimer: null, holdInterval: null,
                                                                get step() { return player.bidEuros >= 1000000 ? 100000 : 10000 },
                                                                get display() { return '€ ' + new Intl.NumberFormat('es-ES').format(player.bidEuros) },
                                                                get atMin() { return player.bidEuros <= 0 },
                                                                increment() { player.bidEuros += this.step },
                                                                decrement() { player.bidEuros = Math.max(player.bidEuros - this.step, 0) },
                                                                startHold(fn) { fn(); this.holdTimer = setTimeout(() => { this.holdInterval = setInterval(() => fn(), 80) }, 400) },
                                                                stopHold() { clearTimeout(this.holdTimer); clearInterval(this.holdInterval) }
                                                            }">
                                                                <form :action="bidRoute(player.id)" method="POST" class="flex items-center gap-2 flex-1">
                                                                    <input type="hidden" name="_token" :value="csrfToken">
                                                                    <div class="inline-flex items-stretch border border-slate-300 rounded-lg overflow-hidden h-[36px]">
                                                                        <input type="hidden" name="bid_amount" :value="player.bidEuros">
                                                                        <button type="button" :disabled="atMin" :class="atMin ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 active:bg-slate-200'" class="min-h-[32px] sm:min-h-0 min-w-[32px] text-sm flex items-center justify-center bg-slate-50 text-slate-700 font-bold select-none transition-colors" @mousedown.prevent="startHold(() => decrement())" @mouseup="stopHold()" @mouseleave="stopHold()" @touchstart.prevent="startHold(() => decrement())" @touchend="stopHold()">&minus;</button>
                                                                        <input type="text" readonly :value="display" class="min-h-[32px] sm:min-h-0 w-28 text-xs text-center font-semibold text-slate-800 bg-white border-x border-y-0 border-slate-300 outline-none cursor-default focus:outline-none focus:ring-0 focus:border-slate-300">
                                                                        <button type="button" class="min-h-[32px] sm:min-h-0 min-w-[32px] text-sm flex items-center justify-center bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-700 font-bold select-none transition-colors" @mousedown.prevent="startHold(() => increment())" @mouseup="stopHold()" @mouseleave="stopHold()" @touchstart.prevent="startHold(() => increment())" @touchend="stopHold()">+</button>
                                                                    </div>
                                                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg transition-colors whitespace-nowrap">
                                                                        {{ __('transfers.submit_bid') }}
                                                                    </button>
                                                                </form>
                                                                <form :action="loanRoute(player.id)" method="POST">
                                                                    <input type="hidden" name="_token" :value="csrfToken">
                                                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors whitespace-nowrap">
                                                                        {{ __('transfers.request_loan') }}
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- Empty state --}}
                                <div x-show="players.length === 0" x-cloak class="border border-dashed border-slate-200 rounded-lg p-6 text-center text-slate-400">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                    <p class="text-sm">{{ __('transfers.shortlist_empty') }}</p>
                                </div>

                            </div>

                            {{-- Search History --}}
                            @if($searchHistory->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-5 py-3 bg-slate-50 border-b">
                                    <h4 class="font-semibold text-sm text-slate-900 flex items-center gap-2">
                                        {{ __('transfers.search_history') }}
                                        <span class="text-xs font-normal text-slate-400">({{ $searchHistory->count() }})</span>
                                    </h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="text-left bg-slate-50/50 border-b border-slate-100">
                                            <tr>
                                                <th class="font-medium py-2 pl-4 text-slate-500">{{ __('transfers.position_required', ['*' => '']) }}</th>
                                                <th class="font-medium py-2 text-slate-500 hidden md:table-cell">{{ __('transfers.scope') }}</th>
                                                <th class="font-medium py-2 text-slate-500 hidden md:table-cell">{{ __('transfers.age_range') }}</th>
                                                <th class="font-medium py-2 text-center text-slate-500">{{ __('transfers.scout_results') }}</th>
                                                <th class="font-medium py-2 pr-4 text-right text-slate-500"></th>
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
                                                <tr class="border-t border-slate-100 hover:bg-slate-50/50">
                                                    <td class="py-3 pl-4">
                                                        <span class="font-medium text-slate-900">{{ isset($filters['position']) ? \App\Support\PositionMapper::filterToDisplayName($filters['position']) : '-' }}</span>
                                                        <div class="text-xs text-slate-400 md:hidden">{{ $histScopeLabel }}</div>
                                                    </td>
                                                    <td class="py-3 text-slate-600 hidden md:table-cell">{{ $histScopeLabel }}</td>
                                                    <td class="py-3 text-slate-600 hidden md:table-cell">{{ $ageLabel ?? __('transfers.all_ages') }}</td>
                                                    <td class="py-3 text-center text-slate-600 tabular-nums">{{ __('transfers.results_count', ['count' => $resultCount]) }}</td>
                                                    <td class="py-3 text-right pr-4">
                                                        <div class="flex items-center justify-end gap-2" x-data="{ confirmDelete: false }">
                                                            <button x-data @click="$dispatch('show-scout-results', '{{ route('game.scouting.results', [$game->id, $historyReport->id]) }}')"
                                                               class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-sky-600 hover:text-sky-800 border border-sky-200 hover:bg-sky-50 rounded-lg transition-colors min-h-[44px] sm:min-h-0">
                                                                {{ __('transfers.view_results') }}
                                                            </button>
                                                            <template x-if="!confirmDelete">
                                                                <button @click="confirmDelete = true" class="p-1.5 text-slate-300 hover:text-red-500 rounded hover:bg-red-50 transition-colors min-h-[44px] sm:min-h-0" title="{{ __('transfers.delete_search') }}">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                    </svg>
                                                                </button>
                                                            </template>
                                                            <template x-if="confirmDelete">
                                                                <form method="POST" action="{{ route('game.scouting.delete', [$game->id, $historyReport->id]) }}" class="inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="inline-flex items-center px-2 py-1.5 text-xs font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors min-h-[44px] sm:min-h-0">
                                                                        {{ __('transfers.delete_search') }}
                                                                    </button>
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
                            <div class="text-center py-12 text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <div class="border rounded-lg p-5 bg-sky-50">
                                    <div class="text-center">
                                        <svg class="w-10 h-10 mx-auto mb-3 text-sky-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <h4 class="font-semibold text-slate-900 mb-1">{{ __('transfers.scout_searching') }}</h4>
                                        <p class="text-sm text-slate-600 mb-1">
                                            {{ trans_choice('game.weeks_remaining', $searchingReport->weeks_remaining, ['count' => $searchingReport->weeks_remaining]) }}
                                        </p>
                                        <p class="text-xs text-slate-500 mb-4">
                                            {{ __('transfers.looking_for') }}: <span class="font-medium">{{ \App\Support\PositionMapper::filterToDisplayName($searchingReport->filters['position']) }}</span>
                                            @if(isset($searchingReport->filters['scope']) && count($searchingReport->filters['scope']) === 1)
                                                — <span class="font-medium">{{ in_array('domestic', $searchingReport->filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international') }}</span>
                                            @endif
                                        </p>
                                        <div class="w-full bg-slate-200 rounded-full h-2 mb-4">
                                            @php $progress = (($searchingReport->weeks_total - $searchingReport->weeks_remaining) / $searchingReport->weeks_total) * 100; @endphp
                                            <div class="bg-sky-500 h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <form method="post" action="{{ route('game.scouting.cancel', $game->id) }}">
                                            @csrf
                                            <x-ghost-button type="submit" class="text-sm text-center">
                                                {{ __('transfers.cancel_search') }}
                                            </x-ghost-button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- New Search Button --}}
                                <div x-data>
                                    <button @click="$dispatch('open-modal', 'scout-search')"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg transition-colors min-h-[44px]">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        {{ __('transfers.new_scout_search') }}
                                    </button>
                                </div>
                            @endif

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <x-scout-search-modal :game="$game" :can-search-internationally="$canSearchInternationally" />
    <x-scout-results-modal />

</x-app-layout>
