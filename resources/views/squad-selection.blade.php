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

<x-app-layout :hide-footer="true">
    <div x-data="squadSelection({
        players: @js($candidatesByGroup),
        groupLabels: @js($tabs),
        teamName: @js($game->team->name),
        teamCrestUrl: @js($game->team->image),
    })" class="min-h-screen pb-32 md:pb-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

            {{-- Welcome Header --}}
            <div class="text-center mb-6 md:mb-8">
                <x-team-crest :team="$game->team" class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-3 md:mb-4" />
                <h1 class="text-2xl md:text-3xl font-bold text-text-primary mb-1">
                    {{ __('game.welcome_team') }}
                </h1>
            </div>

            {{-- Flash Messages --}}
            <x-flash-message type="error" :message="session('error')" class="mb-4" />

            {{-- Main Card --}}
            <div class="bg-surface-800 rounded-xl shadow-xs border border-border-strong overflow-hidden">

                {{-- Title Bar --}}
                <div class="p-4 md:p-6 border-b border-border-strong">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base md:text-lg font-semibold text-text-primary">{{ __('squad.squad_selection_title') }}</h2>
                            <p class="text-xs md:text-sm text-text-muted mt-0.5">{{ __('squad.squad_selection_subtitle') }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xl md:text-2xl font-bold transition-colors"
                                  :class="canConfirm ? 'text-emerald-600' : 'text-text-secondary'"
                                  x-text="totalSelected"></span>
                            <span class="text-sm md:text-base text-text-secondary">/</span>
                            <span class="text-sm md:text-base text-text-secondary" x-text="maxPlayers"></span>
                        </div>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="border-b border-border-strong overflow-x-auto scrollbar-hide">
                    <nav class="flex">
                        @foreach ($tabs as $key => $label)
                        <x-tab-button @click="activeTab = '{{ $key }}'"
                                x-bind:class="activeTab === '{{ $key }}'
                                    ? 'border-accent-blue text-accent-blue font-semibold'
                                    : 'border-transparent text-text-muted hover:text-text-body'"
                                class="flex-1 shrink-0 px-3 md:px-4 py-3 text-xs md:text-sm text-center min-h-[44px] flex items-center justify-center gap-1.5">
                            <span>{{ $label }}</span>
                            <span class="inline-flex items-center justify-center rounded-full text-[10px] md:text-xs font-semibold min-w-[20px] h-5 px-1 transition-colors"
                                  :class="countByGroup('{{ $key }}') > 0 ? 'bg-accent-green/10 text-accent-green' : 'bg-surface-700 text-text-secondary'"
                                  x-text="countByGroup('{{ $key }}')"></span>
                        </x-tab-button>
                        @endforeach
                    </nav>
                </div>

                {{-- Player Lists --}}
                @foreach ($tabs as $groupKey => $label)
                <div x-show="activeTab === '{{ $groupKey }}'" x-cloak class="divide-y divide-border-default">
                    @foreach ($candidatesByGroup[$groupKey] as $candidate)
                    <button type="button"
                            @click="togglePlayer('{{ $candidate['transfermarkt_id'] }}')"
                            :class="{
                                'bg-accent-green/10 border-l-4 border-l-emerald-500': isSelected('{{ $candidate['transfermarkt_id'] }}'),
                                'border-l-4 border-l-transparent hover:bg-surface-700/50': !isSelected('{{ $candidate['transfermarkt_id'] }}'),
                                'opacity-40 cursor-not-allowed': isMaxed && !isSelected('{{ $candidate['transfermarkt_id'] }}'),
                            }"
                            :disabled="isMaxed && !isSelected('{{ $candidate['transfermarkt_id'] }}')"
                            class="w-full flex items-center gap-3 px-3 md:px-5 py-3 md:py-3.5 text-left transition-all min-h-[56px]">

                        {{-- Checkbox --}}
                        <div class="shrink-0 w-5 h-5 rounded-sm border-2 flex items-center justify-center transition-colors"
                             :class="isSelected('{{ $candidate['transfermarkt_id'] }}')
                                 ? 'bg-emerald-500 border-emerald-500'
                                 : 'border-border-strong'">
                            <svg x-show="isSelected('{{ $candidate['transfermarkt_id'] }}')" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>

                        {{-- Position Badge --}}
                        <x-position-badge :position="$candidate['position']" size="md" />

                        {{-- Name + Meta --}}
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm md:text-base text-text-primary truncate">{{ $candidate['name'] }}</div>
                            <div class="flex items-center gap-2 text-xs text-text-secondary mt-0.5">
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
                                <span class="text-xs text-text-secondary">{{ __('squad.technical_abbr') }}</span>
                                <span class="text-xs font-semibold text-text-secondary">{{ $candidate['technical'] }}</span>
                            </div>
                            <div class="hidden md:flex items-center gap-1.5">
                                <span class="text-xs text-text-secondary">{{ __('squad.physical_abbr') }}</span>
                                <span class="text-xs font-semibold text-text-secondary">{{ $candidate['physical'] }}</span>
                            </div>
                            <div class="flex items-center justify-center w-10 h-10 md:w-11 md:h-11 rounded-lg transition-colors"
                                 :class="isSelected('{{ $candidate['transfermarkt_id'] }}') ? 'bg-accent-green/10' : 'bg-surface-700'">
                                <span class="text-sm md:text-base font-bold"
                                      :class="isSelected('{{ $candidate['transfermarkt_id'] }}') ? 'text-accent-green' : 'text-text-body'">{{ $candidate['overall'] }}</span>
                            </div>
                        </div>
                    </button>
                    @endforeach
                </div>
                @endforeach

            </div>
        </div>

        {{-- Sticky Bottom Bar --}}
        <div class="fixed bottom-0 left-0 right-0 bg-surface-800/95 backdrop-blur-xs border-t border-border-strong shadow-lg z-30">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-3 md:py-4">
                <div class="flex items-center gap-3 md:gap-4">
                    {{-- Position Breakdown --}}
                    <div class="hidden md:flex items-center gap-3 text-xs text-text-muted flex-1">
                        <span><span class="font-semibold text-text-body" x-text="countByGroup('goalkeepers')"></span> {{ __('squad.goalkeepers_short') }}</span>
                        <span class="text-text-body">&middot;</span>
                        <span><span class="font-semibold text-text-body" x-text="countByGroup('defenders')"></span> {{ __('squad.defenders_short') }}</span>
                        <span class="text-text-body">&middot;</span>
                        <span><span class="font-semibold text-text-body" x-text="countByGroup('midfielders')"></span> {{ __('squad.midfielders_short') }}</span>
                        <span class="text-text-body">&middot;</span>
                        <span><span class="font-semibold text-text-body" x-text="countByGroup('forwards')"></span> {{ __('squad.forwards_short') }}</span>
                    </div>

                    {{-- Mobile: compact counter --}}
                    <div class="flex md:hidden items-center gap-1.5 text-sm">
                        <span class="font-bold transition-colors"
                              :class="canConfirm ? 'text-emerald-600' : 'text-text-body'"
                              x-text="totalSelected"></span>
                        <span class="text-text-secondary">/ 26</span>
                    </div>

                    {{-- Download --}}
                    <x-secondary-button
                        type="button"
                        x-bind:disabled="!canConfirm"
                        @click="downloadSquadImage()"
                        class="shrink-0"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        <span class="hidden md:inline ml-1.5">{{ __('squad.download_squad') }}</span>
                    </x-secondary-button>

                    {{-- Submit --}}
                    <form method="POST" action="{{ route('game.squad-selection.save', $game->id) }}" class="flex-1 md:flex-none">
                        @csrf
                        <template x-for="id in selectedIds" :key="id">
                            <input type="hidden" name="player_ids[]" :value="id">
                        </template>
                        <x-primary-button color="emerald" x-bind:disabled="!canConfirm" class="w-full md:w-auto">
                            {{ __('squad.confirm_squad') }}
                            <span x-show="!canConfirm" class="ml-1" x-text="'(' + totalSelected + '/26)'"></span>
                        </x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
