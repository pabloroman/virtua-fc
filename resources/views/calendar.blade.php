@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12 grid grid-cols-3 gap-12">
                    {{-- Left Column (2/3) - Calendar --}}
                    <div class="col-span-2">
                        <h3 class="font-semibold text-xl text-slate-900 mb-6">Season Calendar</h3>

                        @foreach($calendar as $month => $matches)
                            <div class="mb-8">
                                <h4 class="text-md font-semibold mb-3 border-b pb-2">{{ $month }}</h4>
                                <div class="space-y-2">
                                    @foreach($matches as $match)
                                        <x-fixture-row :match="$match" :game="$game" />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Right Column (1/3) - Season Stats --}}
                    <div class="space-y-6">
                        <h3 class="font-semibold text-xl text-slate-900">Season {{ $game->season }}</h3>

                        {{-- Record --}}
                        <div>
                            <div class="flex items-center justify-between text-2xl font-bold mb-2">
                                <span class="text-green-600">{{ $seasonStats['wins'] }}W</span>
                                <span class="text-slate-400">{{ $seasonStats['draws'] }}D</span>
                                <span class="text-red-500">{{ $seasonStats['losses'] }}L</span>
                            </div>
                            @if($seasonStats['played'] > 0)
                            <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                @php
                                    $winWidth = ($seasonStats['wins'] / $seasonStats['played']) * 100;
                                    $drawWidth = ($seasonStats['draws'] / $seasonStats['played']) * 100;
                                @endphp
                                <div class="h-2 flex">
                                    <div class="bg-green-500" style="width: {{ $winWidth }}%"></div>
                                    <div class="bg-slate-400" style="width: {{ $drawWidth }}%"></div>
                                </div>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 text-right">{{ $seasonStats['winPercent'] }}% win rate</div>
                            @endif
                        </div>

                        {{-- Form --}}
                        @if(count($seasonStats['form']) > 0)
                        <div>
                            <div class="text-sm font-medium text-slate-600 mb-2">Form</div>
                            <div class="flex gap-1">
                                @foreach($seasonStats['form'] as $result)
                                    <span class="w-8 h-8 rounded text-sm font-bold flex items-center justify-center
                                        @if($result === 'W') bg-green-500 text-white
                                        @elseif($result === 'D') bg-slate-400 text-white
                                        @else bg-red-500 text-white @endif">
                                        {{ $result }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Goals --}}
                        <div class="pt-4 border-t">
                            <div class="text-sm font-medium text-slate-600 mb-3">Goals</div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center p-3 bg-slate-50 rounded-lg">
                                    <div class="text-2xl font-bold text-slate-900">{{ $seasonStats['goalsFor'] }}</div>
                                    <div class="text-xs text-slate-500">Scored</div>
                                </div>
                                <div class="text-center p-3 bg-slate-50 rounded-lg">
                                    <div class="text-2xl font-bold text-slate-900">{{ $seasonStats['goalsAgainst'] }}</div>
                                    <div class="text-xs text-slate-500">Conceded</div>
                                </div>
                            </div>
                        </div>

                        {{-- Home/Away Breakdown --}}
                        <div class="pt-4 border-t">
                            <div class="text-sm font-medium text-slate-600 mb-3">Home vs Away</div>
                            <div class="space-y-3">
                                {{-- Home --}}
                                <div class="p-3 bg-green-50 rounded-lg">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-semibold text-green-700">Home</span>
                                        <span class="text-sm font-bold text-green-700">{{ $seasonStats['home']['points'] }} pts</span>
                                    </div>
                                    <div class="text-xs text-slate-600">
                                        {{ $seasonStats['home']['wins'] }}W {{ $seasonStats['home']['draws'] }}D {{ $seasonStats['home']['losses'] }}L
                                        <span class="text-slate-400 mx-1">&middot;</span>
                                        {{ $seasonStats['home']['goalsFor'] }}-{{ $seasonStats['home']['goalsAgainst'] }}
                                    </div>
                                </div>
                                {{-- Away --}}
                                <div class="p-3 bg-slate-100 rounded-lg">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-semibold text-slate-700">Away</span>
                                        <span class="text-sm font-bold text-slate-700">{{ $seasonStats['away']['points'] }} pts</span>
                                    </div>
                                    <div class="text-xs text-slate-600">
                                        {{ $seasonStats['away']['wins'] }}W {{ $seasonStats['away']['draws'] }}D {{ $seasonStats['away']['losses'] }}L
                                        <span class="text-slate-400 mx-1">&middot;</span>
                                        {{ $seasonStats['away']['goalsFor'] }}-{{ $seasonStats['away']['goalsAgainst'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
