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
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 0 1-2.77.896m5.25-6.624V2.721" />
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
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 3.03v.568c0 .334.148.65.405.864l1.068.89c.442.369.535 1.01.216 1.49l-.51.766a2.25 2.25 0 0 1-1.161.886l-.143.048a1.107 1.107 0 0 0-.57 1.664c.369.555.169 1.307-.427 1.605L9 13.125l.423 1.059a.956.956 0 0 1-1.652.928l-.679-.906a1.125 1.125 0 0 0-1.906.172L4.5 15.75l-.612.153M12.75 3.031a9 9 0 0 1 6.69 14.036m0 0-.177-.529A2.25 2.25 0 0 0 17.128 15H16.5l-.324-.324a1.453 1.453 0 0 0-2.328.377l-.036.073a1.586 1.586 0 0 1-.982.816l-.99.282c-.55.157-.894.702-.8 1.267l.073.438c.08.474.49.821.97.821.846 0 1.598.542 1.865 1.345l.215.643m-3.414 1.06A9 9 0 0 1 3.75 12c0-1.26.26-2.46.727-3.55" />
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
                                            <span>{{ $competition->name }}</span>
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
                                                        <img src="{{ $team->image }}" class="w-10 h-10">
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
                                                        <img src="{{ $team->image }}" class="w-10 h-7 rounded shadow object-cover" onerror="this.style.display='none'">
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
