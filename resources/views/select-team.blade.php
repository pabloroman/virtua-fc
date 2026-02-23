<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight text-center">
            {{ __('app.new_game') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @php
                    $allCompetitions = collect($countries)->flatMap(fn ($c) => collect($c['tiers']))->values();
                    $firstId = $allCompetitions->first()?->id;
                    $groupKeys = $wcGroups->keys()->sort()->values();
                    $firstGroup = $groupKeys->first() ?? '';
                @endphp
                <div class="p-6 sm:p-8"
                     x-data="{
                         mode: 'career',
                         openTab: '{{ $firstId }}',
                         openGroup: '{{ $firstGroup }}',
                         loading: false,
                     }">
                    <form method="post" action="{{ route('init-game') }}" @submit="loading = true" class="space-y-6">
                        @csrf

                        {{-- Manager name --}}
                        <label class="block">
                            <x-text-input id="name" class="block mt-1 w-full text-lg"
                                          type="text"
                                          name="name"
                                          autofocus
                                          placeholder="{{ __('game.manager_name') }}"
                                          required/>
                        </label>
                        <x-input-error :messages="$errors->get('name')" class="mt-2"/>
                        <x-input-error :messages="$errors->get('team_id')" class="mt-2"/>

                        {{-- Hidden game_mode field --}}
                        <input type="hidden" name="game_mode" :value="mode">

                        {{-- Mode selector --}}
                        @if($hasTournamentMode)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Career mode card --}}
                                <button type="button"
                                        @click="mode = 'career'"
                                        :class="mode === 'career'
                                            ? 'ring-2 ring-red-500 bg-red-50 border-red-200'
                                            : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                                        class="relative flex items-center gap-4 p-4 md:p-5 rounded-xl border-2 transition-all duration-200 text-left">
                                    <div class="flex-shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-xl flex items-center justify-center"
                                         :class="mode === 'career' ? 'bg-red-600' : 'bg-slate-200'">
                                        <svg class="w-6 h-6 md:w-7 md:h-7" :class="mode === 'career' ? 'text-white' : 'text-slate-500'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-bold text-lg" :class="mode === 'career' ? 'text-red-900' : 'text-slate-700'">
                                            {{ __('game.mode_career') }}
                                        </h3>
                                        <p class="text-sm mt-0.5 truncate" :class="mode === 'career' ? 'text-red-700' : 'text-slate-500'">
                                            {{ __('game.mode_career_desc') }}
                                        </p>
                                    </div>
                                    <div x-show="mode === 'career'" class="flex-shrink-0">
                                        <svg class="w-6 h-6 text-red-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>

                                {{-- Tournament mode card --}}
                                <button type="button"
                                        @click="mode = 'tournament'"
                                        :class="mode === 'tournament'
                                            ? 'ring-2 ring-amber-500 bg-amber-50 border-amber-200'
                                            : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                                        class="relative flex items-center gap-4 p-4 md:p-5 rounded-xl border-2 transition-all duration-200 text-left">
                                    <div class="flex-shrink-0 w-12 h-12 md:w-14 md:h-14 rounded-xl flex items-center justify-center"
                                         :class="mode === 'tournament' ? 'bg-amber-500' : 'bg-slate-200'">
                                        <svg class="w-6 h-6 md:w-7 md:h-7" :class="mode === 'tournament' ? 'text-white' : 'text-slate-500'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.893 13.393-1.135-1.135a2.252 2.252 0 0 1-.421-.585l-1.08-2.16a.414.414 0 0 0-.663-.107.827.827 0 0 1-.812.21l-1.273-.363a.89.89 0 0 0-.738 1.595l.587.39c.59.395.674 1.23.172 1.732l-.2.2c-.212.212-.33.498-.33.796v.41c0 .409-.11.809-.32 1.158l-1.315 2.191a2.11 2.11 0 0 1-1.81 1.025 1.055 1.055 0 0 1-1.055-1.055v-1.172c0-.92-.56-1.747-1.414-2.089l-.655-.261a2.25 2.25 0 0 1-1.383-2.46l.007-.042a2.25 2.25 0 0 1 .29-.787l.09-.15a2.25 2.25 0 0 1 2.37-1.048l1.178.236a1.125 1.125 0 0 0 1.302-.795l.208-.73a1.125 1.125 0 0 0-.578-1.315l-.665-.332-.091.091a2.25 2.25 0 0 1-1.591.659h-.18c-.249 0-.487.1-.662.274a.931.931 0 0 1-1.458-1.137l1.411-2.353a2.25 2.25 0 0 0 .286-.76m11.928 9.869A9 9 0 0 0 8.965 3.525m11.928 9.868A9 9 0 1 1 8.965 3.525" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-bold text-lg" :class="mode === 'tournament' ? 'text-amber-900' : 'text-slate-700'">
                                            {{ __('game.mode_tournament') }}
                                        </h3>
                                        <p class="text-sm mt-0.5 truncate" :class="mode === 'tournament' ? 'text-amber-700' : 'text-slate-500'">
                                            {{ __('game.mode_tournament_desc') }}
                                        </p>
                                    </div>
                                    <div x-show="mode === 'tournament'" class="flex-shrink-0">
                                        <svg class="w-6 h-6 text-amber-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </div>
                        @endif

                        {{-- ===================== CAREER MODE: Club teams ===================== --}}
                        <div x-show="mode === 'career'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                            {{-- Competition tabs --}}
                            <div class="flex space-x-2 overflow-x-auto scrollbar-hide">
                                @foreach($countries as $countryCode => $country)
                                    @foreach($country['tiers'] as $tier => $competition)
                                        <a x-on:click="openTab = '{{ $competition->id }}'" :class="{ 'bg-red-600 text-white': openTab === '{{ $competition->id }}' }" class="flex items-center space-x-2 py-2 px-4 rounded-md focus:outline-none text-lg transition-all duration-300 cursor-pointer shrink-0">
                                            <img class="w-5 h-4 rounded shadow" src="/flags/{{ $country['flag'] }}.svg">
                                            <span>{{ __($competition->name) }}</span>
                                        </a>
                                    @endforeach
                                @endforeach
                            </div>

                            {{-- Team grids per competition --}}
                            <div class="space-y-6">
                                @foreach($countries as $countryCode => $country)
                                    @foreach($country['tiers'] as $tier => $competition)
                                        <div x-show="openTab === '{{ $competition->id }}'">
                                            <div class="grid lg:grid-cols-4 md:grid-cols-2 gap-2 mt-4">
                                                @foreach($competition->teams as $team)
                                                    <label class="border text-slate-700 has-[:checked]:ring-sky-200 has-[:checked]:text-sky-900 has-[:checked]:bg-sky-100 grid grid-cols-[40px_1fr_auto] items-center gap-4 rounded-lg p-4 ring-1 ring-transparent hover:bg-sky-50">
                                                        <x-team-crest :team="$team" class="w-10 h-10" />
                                                        <span class="text-[20px]">{{ $team->name }}</span>
                                                        <input x-bind:required="mode === 'career'" x-bind:disabled="mode !== 'career'" type="radio" name="team_id" value="{{ $team->id }}" class="hidden appearance-none rounded-full border-[5px] border-white bg-white bg-clip-padding outline-none ring-1 ring-gray-950/10 checked:border-sky-600 checked:ring-sky-600 focus:outline-none">
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>

                        {{-- ===================== TOURNAMENT MODE: National teams ===================== --}}
                        @if($hasTournamentMode)
                            <div x-show="mode === 'tournament'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                                {{-- Group tabs --}}
                                <div class="flex space-x-2 overflow-x-auto scrollbar-hide">
                                    @foreach($wcGroups as $groupLabel => $teams)
                                        <a x-on:click="openGroup = '{{ $groupLabel }}'" :class="{ 'bg-amber-500 text-white': openGroup === '{{ $groupLabel }}' }" class="py-2 px-4 rounded-md focus:outline-none text-lg transition-all duration-300 cursor-pointer shrink-0">
                                            {{ __('game.group') }} {{ $groupLabel }}
                                        </a>
                                    @endforeach
                                </div>

                                {{-- Team grids per group --}}
                                <div class="space-y-6">
                                    @foreach($wcGroups as $groupLabel => $teams)
                                        <div x-show="openGroup === '{{ $groupLabel }}'">
                                            <div class="grid lg:grid-cols-4 md:grid-cols-2 gap-2 mt-4">
                                                @foreach($teams as $team)
                                                    <label class="border text-slate-700 has-[:checked]:ring-amber-200 has-[:checked]:text-amber-900 has-[:checked]:bg-amber-50 grid grid-cols-[40px_1fr_auto] items-center gap-4 rounded-lg p-4 ring-1 ring-transparent hover:bg-amber-50">
                                                        <x-team-crest :team="$team" class="w-10 h-7 rounded shadow object-cover" onerror="this.style.display='none'" />
                                                        <span class="text-[20px]">{{ $team->name }}</span>
                                                        <input x-bind:required="mode === 'tournament'" x-bind:disabled="mode !== 'tournament'" type="radio" name="team_id" value="{{ $team->id }}" class="hidden appearance-none rounded-full border-[5px] border-white bg-white bg-clip-padding outline-none ring-1 ring-gray-950/10 checked:border-amber-600 checked:ring-amber-600 focus:outline-none">
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="grid">
                            <div x-show="mode === 'career'" class="place-self-center">
                                <x-primary-button-spin class="text-lg">
                                    {{ __('game.start_game') }}
                                </x-primary-button-spin>
                            </div>
                            <div x-show="mode === 'tournament'" class="place-self-center">
                                <x-primary-button-spin class="text-lg" color="amber">
                                    {{ __('game.start_tournament') }}
                                </x-primary-button-spin>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
