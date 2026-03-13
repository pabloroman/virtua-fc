<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 pb-8">
        {{-- Page Title --}}
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('app.new_game') }}</h2>
        </div>

        @php
            $allCompetitions = collect($countries)->flatMap(fn ($c) => collect($c['tiers']))->values();
            $firstId = $allCompetitions->first()?->id;
        @endphp

        <div x-data="{
                mode: 'career',
                openTab: '{{ $firstId }}',
                loading: false,
            }">
            <form method="post" action="{{ route('init-game') }}" @submit="loading = true" class="space-y-6">
                @csrf

                <x-input-error :messages="$errors->get('team_id')" class="mt-2"/>

                {{-- Hidden game_mode field --}}
                <input type="hidden" name="game_mode" :value="mode">

                {{-- Mode selector --}}
                @if($hasTournamentMode)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                        {{-- Career mode card --}}
                        <button type="button"
                                @click="mode = 'career'"
                                :class="mode === 'career'
                                    ? 'ring-2 ring-accent-red border-accent-red/30 bg-accent-red/5'
                                    : 'border-border-strong hover:bg-surface-700/50'"
                                class="relative flex items-center gap-4 p-4 md:p-5 rounded-xl border transition-all duration-200 text-left">
                            <div class="shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-xl flex items-center justify-center"
                                 :class="mode === 'career' ? 'bg-accent-red' : 'bg-surface-600'">
                                <svg class="w-6 h-6 md:w-7 md:h-7" :class="mode === 'career' ? 'text-white' : 'text-text-muted'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-heading font-bold text-base md:text-lg uppercase tracking-wide" :class="mode === 'career' ? 'text-accent-red' : 'text-text-body'">
                                    {{ __('game.mode_career') }}
                                </h3>
                                <p class="text-xs md:text-sm mt-0.5" :class="mode === 'career' ? 'text-accent-red/80' : 'text-text-muted'">
                                    {{ __('game.mode_career_desc') }}
                                </p>
                            </div>
                            <div x-show="mode === 'career'" x-cloak class="shrink-0">
                                <svg class="w-6 h-6 text-accent-red" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>

                        {{-- Tournament mode card --}}
                        <button type="button"
                                @click="mode = 'tournament'"
                                :class="mode === 'tournament'
                                    ? 'ring-2 ring-accent-gold border-accent-gold/30 bg-accent-gold/5'
                                    : 'border-border-strong hover:bg-surface-700/50'"
                                class="relative flex items-center gap-4 p-4 md:p-5 rounded-xl border transition-all duration-200 text-left">
                            <div class="shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-xl flex items-center justify-center"
                                 :class="mode === 'tournament' ? 'bg-accent-gold' : 'bg-surface-600'">
                                <svg class="w-6 h-6 md:w-7 md:h-7" :class="mode === 'tournament' ? 'text-white' : 'text-text-muted'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.893 13.393-1.135-1.135a2.252 2.252 0 0 1-.421-.585l-1.08-2.16a.414.414 0 0 0-.663-.107.827.827 0 0 1-.812.21l-1.273-.363a.89.89 0 0 0-.738 1.595l.587.39c.59.395.674 1.23.172 1.732l-.2.2c-.212.212-.33.498-.33.796v.41c0 .409-.11.809-.32 1.158l-1.315 2.191a2.11 2.11 0 0 1-1.81 1.025 1.055 1.055 0 0 1-1.055-1.055v-1.172c0-.92-.56-1.747-1.414-2.089l-.655-.261a2.25 2.25 0 0 1-1.383-2.46l.007-.042a2.25 2.25 0 0 1 .29-.787l.09-.15a2.25 2.25 0 0 1 2.37-1.048l1.178.236a1.125 1.125 0 0 0 1.302-.795l.208-.73a1.125 1.125 0 0 0-.578-1.315l-.665-.332-.091.091a2.25 2.25 0 0 1-1.591.659h-.18c-.249 0-.487.1-.662.274a.931.931 0 0 1-1.458-1.137l1.411-2.353a2.25 2.25 0 0 0 .286-.76m11.928 9.869A9 9 0 0 0 8.965 3.525m11.928 9.868A9 9 0 1 1 8.965 3.525" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-heading font-bold text-base md:text-lg uppercase tracking-wide" :class="mode === 'tournament' ? 'text-accent-gold' : 'text-text-body'">
                                    {{ __('game.mode_tournament') }}
                                </h3>
                                <p class="text-xs md:text-sm mt-0.5" :class="mode === 'tournament' ? 'text-accent-gold/80' : 'text-text-muted'">
                                    {{ __('game.mode_tournament_desc') }}
                                </p>
                            </div>
                            <div x-show="mode === 'tournament'" x-cloak class="shrink-0">
                                <svg class="w-6 h-6 text-accent-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </div>
                @endif

                {{-- ===================== CAREER MODE: Club teams ===================== --}}
                <div x-show="mode === 'career'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    {{-- Competition tabs --}}
                    <div class="flex gap-2 overflow-x-auto scrollbar-hide mb-4">
                        @foreach($countries as $countryCode => $country)
                            @foreach($country['tiers'] as $tier => $competition)
                                <x-pill-button
                                    @click="openTab = '{{ $competition->id }}'"
                                    x-bind:class="openTab === '{{ $competition->id }}'
                                        ? 'bg-accent-red text-white'
                                        : 'bg-surface-700 text-text-secondary hover:text-text-body hover:bg-surface-600'"
                                    class="gap-2 shrink-0">
                                    <img class="w-5 h-4 rounded-sm shadow-sm" src="/flags/{{ $competition->flag }}.svg" alt="">
                                    <span>{{ __($competition->name) }}</span>
                                </x-pill-button>
                            @endforeach
                        @endforeach
                    </div>

                    {{-- Team grids per competition --}}
                    @foreach($countries as $countryCode => $country)
                        @foreach($country['tiers'] as $tier => $competition)
                            <div x-show="openTab === '{{ $competition->id }}'" x-cloak>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                                    @foreach($competition->teams as $team)
                                        <label class="flex items-center gap-3 rounded-lg border border-border-default p-3 md:p-4 cursor-pointer transition-all
                                                       hover:bg-accent-blue/5 hover:border-accent-blue/30
                                                       has-checked:ring-2 has-checked:ring-accent-blue has-checked:border-accent-blue/30 has-checked:bg-accent-blue/5">
                                            <x-team-crest :team="$team" class="w-10 h-10 shrink-0" />
                                            <span class="text-sm md:text-base font-medium text-text-body truncate">{{ $team->name }}</span>
                                            <input x-bind:required="mode === 'career'" x-bind:disabled="mode !== 'career'" type="radio" name="team_id" value="{{ $team->id }}" class="hidden">
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>

                {{-- ===================== TOURNAMENT MODE: National teams ===================== --}}
                @if($hasTournamentMode)
                    <div x-show="mode === 'tournament'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-6">

                        {{-- Featured teams (larger cards) --}}
                        @if($wcFeaturedTeams->isNotEmpty())
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach($wcFeaturedTeams as $team)
                                <label class="flex flex-col items-center gap-2 rounded-xl border border-border-default p-4 md:p-5 cursor-pointer transition-all
                                               hover:bg-accent-gold/5 hover:border-accent-gold/30
                                               has-checked:ring-2 has-checked:ring-accent-gold has-checked:border-accent-gold/30 has-checked:bg-accent-gold/5">
                                    <x-team-crest :team="$team" class="w-14 h-14 md:w-16 md:h-16" />
                                    <span class="text-sm md:text-base font-semibold text-text-body text-center truncate w-full">{{ $team->name }}</span>
                                    <input x-bind:required="mode === 'tournament'" x-bind:disabled="mode !== 'tournament'" type="radio" name="team_id" value="{{ $team->id }}" class="hidden">
                                </label>
                            @endforeach
                        </div>
                        @endif

                        {{-- Divider --}}
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-border-strong"></div></div>
                            <div class="relative flex justify-center">
                                <span class="bg-surface-900 px-3 text-[10px] text-text-muted uppercase tracking-widest">{{ __('app.all_teams') }}</span>
                            </div>
                        </div>

                        {{-- All other teams (compact cards) --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                            @foreach($wcTeams as $team)
                                <label class="flex items-center gap-2.5 rounded-lg border border-border-default p-3 cursor-pointer transition-all
                                               hover:bg-accent-gold/5 hover:border-accent-gold/30
                                               has-checked:ring-2 has-checked:ring-accent-gold has-checked:border-accent-gold/30 has-checked:bg-accent-gold/5">
                                    <x-team-crest :team="$team" class="w-8 h-8 shrink-0" />
                                    <span class="text-sm font-medium text-text-body truncate">{{ $team->name }}</span>
                                    <input x-bind:required="mode === 'tournament'" x-bind:disabled="mode !== 'tournament'" type="radio" name="team_id" value="{{ $team->id }}" class="hidden">
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Submit --}}
                <div class="flex justify-center pt-2">
                    <div x-show="mode === 'career'">
                        <x-primary-button-spin>
                            {{ __('game.start_game') }}
                        </x-primary-button-spin>
                    </div>
                    <div x-show="mode === 'tournament'" x-cloak>
                        <x-primary-button-spin color="amber">
                            {{ __('game.start_tournament') }}
                        </x-primary-button-spin>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
