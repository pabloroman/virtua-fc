@php
/** @var App\Models\Game $game */
/** @var array $candidatesByGroup */

$tabs = [
    'goalkeepers' => __('squad.goalkeepers'),
    'defenders' => __('squad.defenders'),
    'midfielders' => __('squad.midfielders'),
    'forwards' => __('squad.forwards'),
];
@endphp

<x-app-layout>
    <div x-data="{
        selectedIds: [],
        activeTab: 'goalkeepers',
        players: @json($candidatesByGroup),
        maxPlayers: 26,

        togglePlayer(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx > -1) {
                this.selectedIds.splice(idx, 1);
            } else if (this.selectedIds.length < this.maxPlayers) {
                this.selectedIds.push(id);
            }
        },

        isSelected(id) {
            return this.selectedIds.includes(id);
        },

        get totalSelected() {
            return this.selectedIds.length;
        },

        countByGroup(group) {
            return this.players[group].filter(p => this.selectedIds.includes(p.transfermarkt_id)).length;
        },

        get canConfirm() {
            return this.totalSelected === this.maxPlayers;
        },

        get isMaxed() {
            return this.totalSelected >= this.maxPlayers;
        },
    }" class="min-h-screen pb-32 md:pb-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

            {{-- Welcome Header --}}
            <div class="text-center mb-6 md:mb-8">
                <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}" class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-3 md:mb-4">
                <h1 class="text-2xl md:text-3xl font-bold text-white mb-1">
                    {{ __('game.welcome_to_team', ['team' => $game->team->name]) }}, {{ $game->player_name }}
                </h1>
                <p class="text-slate-500">{{ __('game.season_n', ['season' => $game->formatted_season]) }}</p>
            </div>

            {{-- Flash Messages --}}
            @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                {{ session('error') }}
            </div>
            @endif

            {{-- Main Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

                {{-- Title Bar --}}
                <div class="p-4 md:p-6 border-b border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base md:text-lg font-semibold text-slate-900">{{ __('squad.squad_selection_title') }}</h2>
                            <p class="text-xs md:text-sm text-slate-500 mt-0.5">{{ __('squad.squad_selection_subtitle') }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xl md:text-2xl font-bold transition-colors"
                                  :class="canConfirm ? 'text-emerald-600' : 'text-slate-400'"
                                  x-text="totalSelected"></span>
                            <span class="text-sm md:text-base text-slate-400">/</span>
                            <span class="text-sm md:text-base text-slate-400" x-text="maxPlayers"></span>
                        </div>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="border-b border-slate-200 overflow-x-auto scrollbar-hide">
                    <nav class="flex">
                        @foreach ($tabs as $key => $label)
                        <button type="button"
                                @click="activeTab = '{{ $key }}'"
                                :class="activeTab === '{{ $key }}'
                                    ? 'border-b-2 border-slate-900 text-slate-900 font-semibold'
                                    : 'text-slate-500 hover:text-slate-700'"
                                class="flex-1 shrink-0 px-3 md:px-4 py-3 text-xs md:text-sm text-center whitespace-nowrap transition-colors min-h-[44px] flex items-center justify-center gap-1.5">
                            <span>{{ $label }}</span>
                            <span class="inline-flex items-center justify-center rounded-full text-[10px] md:text-xs font-semibold min-w-[20px] h-5 px-1 transition-colors"
                                  :class="countByGroup('{{ $key }}') > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                                  x-text="countByGroup('{{ $key }}')"></span>
                        </button>
                        @endforeach
                    </nav>
                </div>

                {{-- Player Lists --}}
                @foreach ($tabs as $groupKey => $label)
                <div x-show="activeTab === '{{ $groupKey }}'" x-cloak class="divide-y divide-slate-100">
                    @foreach ($candidatesByGroup[$groupKey] as $candidate)
                    <button type="button"
                            @click="togglePlayer('{{ $candidate['transfermarkt_id'] }}')"
                            :class="{
                                'bg-emerald-50 border-l-4 border-l-emerald-500': isSelected('{{ $candidate['transfermarkt_id'] }}'),
                                'border-l-4 border-l-transparent hover:bg-slate-50': !isSelected('{{ $candidate['transfermarkt_id'] }}'),
                                'opacity-40 cursor-not-allowed': isMaxed && !isSelected('{{ $candidate['transfermarkt_id'] }}'),
                            }"
                            :disabled="isMaxed && !isSelected('{{ $candidate['transfermarkt_id'] }}')"
                            class="w-full flex items-center gap-3 px-3 md:px-5 py-3 md:py-3.5 text-left transition-all min-h-[56px]">

                        {{-- Checkbox --}}
                        <div class="shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                             :class="isSelected('{{ $candidate['transfermarkt_id'] }}')
                                 ? 'bg-emerald-500 border-emerald-500'
                                 : 'border-slate-300'">
                            <svg x-show="isSelected('{{ $candidate['transfermarkt_id'] }}')" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>

                        {{-- Position Badge --}}
                        <x-position-badge :position="$candidate['position']" size="md" />

                        {{-- Name + Meta --}}
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm md:text-base text-slate-900 truncate">{{ $candidate['name'] }}</div>
                            <div class="flex items-center gap-2 text-xs text-slate-400 mt-0.5">
                                <span>{{ $candidate['age'] }} {{ __('squad.years_abbr') }}</span>
                                @if($candidate['height'])
                                <span>&middot;</span>
                                <span>{{ $candidate['height'] }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Abilities --}}
                        <div class="shrink-0 flex items-center gap-2 md:gap-3">
                            <div class="hidden md:flex items-center gap-1.5">
                                <span class="text-xs text-slate-400">{{ __('squad.technical_abbr') }}</span>
                                <span class="text-xs font-semibold text-slate-600">{{ $candidate['technical'] }}</span>
                            </div>
                            <div class="hidden md:flex items-center gap-1.5">
                                <span class="text-xs text-slate-400">{{ __('squad.physical_abbr') }}</span>
                                <span class="text-xs font-semibold text-slate-600">{{ $candidate['physical'] }}</span>
                            </div>
                            <div class="flex items-center justify-center w-10 h-10 md:w-11 md:h-11 rounded-lg transition-colors"
                                 :class="isSelected('{{ $candidate['transfermarkt_id'] }}') ? 'bg-emerald-100' : 'bg-slate-100'">
                                <span class="text-sm md:text-base font-bold"
                                      :class="isSelected('{{ $candidate['transfermarkt_id'] }}') ? 'text-emerald-700' : 'text-slate-700'">{{ $candidate['overall'] }}</span>
                            </div>
                        </div>
                    </button>
                    @endforeach
                </div>
                @endforeach

            </div>
        </div>

        {{-- Sticky Bottom Bar --}}
        <div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-slate-200 shadow-lg z-30">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-3 md:py-4">
                <div class="flex items-center gap-3 md:gap-4">
                    {{-- Position Breakdown --}}
                    <div class="hidden md:flex items-center gap-3 text-xs text-slate-500 flex-1">
                        <span><span class="font-semibold text-slate-700" x-text="countByGroup('goalkeepers')"></span> {{ __('squad.goalkeepers_short') }}</span>
                        <span class="text-slate-300">&middot;</span>
                        <span><span class="font-semibold text-slate-700" x-text="countByGroup('defenders')"></span> {{ __('squad.defenders_short') }}</span>
                        <span class="text-slate-300">&middot;</span>
                        <span><span class="font-semibold text-slate-700" x-text="countByGroup('midfielders')"></span> {{ __('squad.midfielders_short') }}</span>
                        <span class="text-slate-300">&middot;</span>
                        <span><span class="font-semibold text-slate-700" x-text="countByGroup('forwards')"></span> {{ __('squad.forwards_short') }}</span>
                    </div>

                    {{-- Mobile: compact counter --}}
                    <div class="flex md:hidden items-center gap-1.5 text-sm">
                        <span class="font-bold transition-colors"
                              :class="canConfirm ? 'text-emerald-600' : 'text-slate-700'"
                              x-text="totalSelected"></span>
                        <span class="text-slate-400">/ 26</span>
                    </div>

                    {{-- Submit --}}
                    <form method="POST" action="{{ route('game.squad-selection.save', $game->id) }}" class="flex-1 md:flex-none">
                        @csrf
                        <template x-for="id in selectedIds" :key="id">
                            <input type="hidden" name="player_ids[]" :value="id">
                        </template>
                        <button type="submit"
                                :disabled="!canConfirm"
                                :class="canConfirm
                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm'
                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed'"
                                class="w-full md:w-auto px-6 py-2.5 rounded-lg text-sm font-semibold transition-all min-h-[44px]">
                            {{ __('squad.confirm_squad') }}
                            <span x-show="!canConfirm" class="ml-1" x-text="'(' + totalSelected + '/26)'"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
