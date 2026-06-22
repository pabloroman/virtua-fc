@php
/** @var App\Models\Game $game */
/** @var \Illuminate\Support\Collection $teams */
/** @var array $slots */
$assetUrl = rtrim(Storage::disk('assets')->url(''), '/');
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen py-12 md:py-16"
         x-data="preseasonSetup({
            teams: @js($teams),
            assetUrl: @js($assetUrl),
            slotCount: @js(count($slots)),
         })">
        <div class="max-w-2xl mx-auto px-4 sm:px-6">

            {{-- Hero --}}
            <div class="text-center mb-8">
                <x-team-crest :team="$game->team" class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-4 drop-shadow-lg" />
                <h1 class="font-heading text-3xl md:text-4xl font-bold text-text-primary">{{ __('game.preseason_setup_title') }}</h1>
                <p class="text-base md:text-lg text-text-secondary mt-3">{{ __('game.preseason_setup_subtitle') }}</p>
            </div>

            <x-flash-message type="error" :message="session('error')" class="mb-6" />

            <form action="{{ route('game.preseason-setup.save', $game->id) }}" method="POST">
                @csrf

                @if($teams->isEmpty())
                    <x-status-banner color="blue" :title="__('game.preseason_setup_title')" :description="__('game.preseason_setup_no_opponents')" class="mb-6" />
                @else
                    <div class="space-y-4 mb-8">
                        @foreach($slots as $index => $date)
                            <div class="bg-surface-800 border border-border-default rounded-xl p-4 md:p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">
                                        {{ __('game.preseason_setup_fixture', ['number' => $index + 1]) }}
                                    </span>
                                    <span class="text-sm font-semibold text-text-secondary">
                                        {{ $date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    {{-- Opponent picker --}}
                                    <div>
                                        {{-- Empty: open the picker modal --}}
                                        <button type="button" x-show="!selections[{{ $index }}].teamId"
                                                @click="openSlot = {{ $index }}"
                                                class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border border-dashed border-border-strong bg-surface-700 text-sm font-medium text-text-secondary hover:border-accent-blue/50 hover:text-text-primary transition-colors min-h-[44px]">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            {{ __('game.preseason_setup_choose_opponent') }}
                                        </button>

                                        {{-- Chosen: show crest + name, click to change, × to clear --}}
                                        <div x-show="selections[{{ $index }}].teamId" x-cloak
                                             class="w-full flex items-center gap-3 px-3 py-2 rounded-lg border border-border-default bg-surface-700 min-h-[44px]">
                                            <img :src="selections[{{ $index }}].teamImage" :alt="selections[{{ $index }}].teamName" class="w-7 h-7 shrink-0 object-contain">
                                            <button type="button" @click="openSlot = {{ $index }}"
                                                    class="flex-1 text-left text-sm font-medium text-text-primary truncate"
                                                    x-text="selections[{{ $index }}].teamName"></button>
                                            <button type="button" @click="clear({{ $index }})"
                                                    class="shrink-0 text-text-muted hover:text-text-primary" aria-label="{{ __('game.preseason_setup_clear') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Home / away toggle --}}
                                    <div class="flex items-center gap-2" x-show="selections[{{ $index }}].teamId" x-cloak>
                                        <button type="button" @click="selections[{{ $index }}].isHome = true"
                                            class="flex-1 px-3 py-2 rounded-lg text-sm font-semibold border transition-colors"
                                            :class="selections[{{ $index }}].isHome ? 'bg-accent-blue/15 border-accent-blue/50 text-accent-blue' : 'bg-surface-700 border-border-default text-text-secondary'">
                                            {{ __('game.preseason_setup_home') }}
                                        </button>
                                        <button type="button" @click="selections[{{ $index }}].isHome = false"
                                            class="flex-1 px-3 py-2 rounded-lg text-sm font-semibold border transition-colors"
                                            :class="!selections[{{ $index }}].isHome ? 'bg-accent-blue/15 border-accent-blue/50 text-accent-blue' : 'bg-surface-700 border-border-default text-text-secondary'">
                                            {{ __('game.preseason_setup_away') }}
                                        </button>
                                    </div>
                                </div>

                                {{-- Serialized state --}}
                                <input type="hidden" name="slots[{{ $index }}][team_id]" :value="selections[{{ $index }}].teamId || ''">
                                <input type="hidden" name="slots[{{ $index }}][is_home]" :value="selections[{{ $index }}].isHome ? '1' : '0'">
                            </div>
                        @endforeach
                    </div>
                @endif

                <x-primary-button class="w-full">{{ __('game.preseason_setup_confirm') }}</x-primary-button>
            </form>
        </div>

        {{-- Opponent picker modal: all game teams with a squad, grouped by country --}}
        <div x-show="openSlot !== null" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
             @keydown.escape.window="closeModal()">
            <div class="fixed inset-0 bg-black/80" @click="closeModal()"></div>

            <div class="relative z-10 mx-auto sm:max-w-2xl bg-surface-800 border border-border-strong rounded-xl shadow-xl flex flex-col max-h-[85vh]">
                {{-- Header --}}
                <div class="flex items-center gap-3 p-4 border-b border-border-default">
                    <h3 class="flex-1 text-base font-semibold text-text-primary">{{ __('game.preseason_setup_modal_title') }}</h3>
                    <button type="button" @click="closeModal()" class="shrink-0 text-text-muted hover:text-text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Search --}}
                <div class="p-4 pb-2">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input type="text" x-model="searchQuery"
                               placeholder="{{ __('game.preseason_setup_search_placeholder') }}"
                               class="w-full pl-10 pr-3 py-2.5 bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary placeholder-text-muted focus:outline-none focus:border-accent-blue/50 focus:ring-1 focus:ring-accent-blue/30 min-h-[44px]">
                    </div>
                </div>

                {{-- Grouped teams --}}
                <div class="flex-1 overflow-y-auto px-4 pb-4">
                    <template x-for="group in filteredGroups" :key="group.code">
                        <div class="mb-4">
                            <div class="flex items-center gap-2 px-1 py-1.5 mb-1">
                                <img :src="assetUrl + '/flags/' + group.flag + '.svg'" class="w-5 h-3.5 rounded-xs shadow-xs" :alt="group.name">
                                <span class="text-xs font-semibold uppercase tracking-wider text-text-muted" x-text="group.name"></span>
                                <span class="text-xs text-text-muted" x-text="'(' + group.teams.length + ')'"></span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                <template x-for="team in group.teams" :key="team.id">
                                    <button type="button" @click="choose(team)"
                                            class="flex items-center gap-2 p-2 rounded-lg border border-border-default bg-surface-700 hover:bg-surface-600/60 hover:border-accent-blue/40 transition-colors text-left min-h-[44px]">
                                        <img :src="team.image" :alt="team.name" class="w-7 h-7 shrink-0 object-contain">
                                        <span class="text-sm font-medium text-text-primary truncate" x-text="team.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="filteredGroups.length === 0">
                        <p class="text-sm text-text-secondary text-center py-8">{{ __('game.preseason_setup_no_results') }}</p>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
