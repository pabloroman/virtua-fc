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
                    // Flatten all competitions for tab defaulting
                    $allCompetitions = collect($countries)->flatMap(fn ($c) => collect($c['tiers']))->values();
                    $firstId = $allCompetitions->first()?->id;
                @endphp
                <div class="p-6 sm:p-8" x-data="{ openTab: '{{ $firstId }}' }">
                    <form method="post" action="{{ route('init-game') }}" x-data="{ loading: false }" @submit="loading = true" class="space-y-6">
                        @csrf
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

                        <div class="space-y-6">
                            @foreach($countries as $countryCode => $country)
                                @foreach($country['tiers'] as $tier => $competition)
                                    <div x-show="openTab === '{{ $competition->id }}'">
                                        <div class="grid lg:grid-cols-4 md:grid-cols-2 gap-2 mt-4">
                                            @foreach($competition->teams as $team)
                                                <label class="border text-slate-700 has-[:checked]:ring-sky-200 has-[:checked]:text-sky-900 has-[:checked]:bg-sky-100 grid grid-cols-[40px_1fr_auto] items-center gap-4 rounded-lg p-4 ring-1 ring-transparent hover:bg-sky-50">
                                                    <img src="{{ $team->image }}" class="w-10 h-10">
                                                    <span class="text-[20px]">{{ $team->name }}</span>
                                                    <input required type="radio" name="team_id" value="{{ $team->id }}" class="hidden appearance-none rounded-full border-[5px] border-white bg-white bg-clip-padding outline-none ring-1 ring-gray-950/10 checked:border-sky-600 checked:ring-sky-600 focus:outline-none">
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        <div class="grid">
                            <x-primary-button-spin class="place-self-center text-lg">
                                {{ __('game.start_game') }}
                            </x-primary-button-spin>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
