@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">Squad Statistics</h3>
                        <a href="{{ route('game.squad', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                            &larr; Back to Squad
                        </a>
                    </div>

                    <div x-data="{
                        sortColumn: 'appearances',
                        sortAsc: false,
                        sortTable(column, asc) {
                            const tbody = document.getElementById('stats-body');
                            const rows = Array.from(tbody.querySelectorAll('.player-row'));

                            rows.sort((a, b) => {
                                let aVal = a.dataset[column];
                                let bVal = b.dataset[column];

                                if (!isNaN(parseFloat(aVal)) && !isNaN(parseFloat(bVal))) {
                                    aVal = parseFloat(aVal);
                                    bVal = parseFloat(bVal);
                                }

                                if (aVal < bVal) return asc ? -1 : 1;
                                if (aVal > bVal) return asc ? 1 : -1;
                                return 0;
                            });

                            rows.forEach(row => tbody.appendChild(row));
                        }
                    }">
                        <table class="w-full text-sm" id="stats-table">
                            <thead class="text-left border-b">
                                <tr>
                                    <th class="font-semibold py-2 w-10"></th>
                                    <th class="font-semibold py-2 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'name' ? !sortAsc : true; sortColumn = 'name'; sortTable('name', sortAsc)">
                                        <span class="flex items-center gap-1">
                                            Player
                                            <span x-show="sortColumn === 'name'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'age' ? !sortAsc : true; sortColumn = 'age'; sortTable('age', sortAsc)">
                                        <span class="flex items-center justify-center gap-1">
                                            Age
                                            <span x-show="sortColumn === 'age'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'appearances' ? !sortAsc : false; sortColumn = 'appearances'; sortTable('appearances', sortAsc)" title="Appearances">
                                        <span class="flex items-center justify-center gap-1">
                                            Apps
                                            <span x-show="sortColumn === 'appearances'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'goals' ? !sortAsc : false; sortColumn = 'goals'; sortTable('goals', sortAsc)" title="Goals">
                                        <span class="flex items-center justify-center gap-1">
                                            G
                                            <span x-show="sortColumn === 'goals'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'assists' ? !sortAsc : false; sortColumn = 'assists'; sortTable('assists', sortAsc)" title="Assists">
                                        <span class="flex items-center justify-center gap-1">
                                            A
                                            <span x-show="sortColumn === 'assists'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'contributions' ? !sortAsc : false; sortColumn = 'contributions'; sortTable('contributions', sortAsc)" title="Goal Contributions (Goals + Assists)">
                                        <span class="flex items-center justify-center gap-1">
                                            G+A
                                            <span x-show="sortColumn === 'contributions'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-14 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'gpg' ? !sortAsc : false; sortColumn = 'gpg'; sortTable('gpg', sortAsc)" title="Goals per Game">
                                        <span class="flex items-center justify-center gap-1">
                                            G/Gm
                                            <span x-show="sortColumn === 'gpg'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'own_goals' ? !sortAsc : false; sortColumn = 'own_goals'; sortTable('own_goals', sortAsc)" title="Own Goals">
                                        <span class="flex items-center justify-center gap-1">
                                            OG
                                            <span x-show="sortColumn === 'own_goals'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'yellow' ? !sortAsc : false; sortColumn = 'yellow'; sortTable('yellow', sortAsc)" title="Yellow Cards">
                                        <span class="flex items-center justify-center gap-1">
                                            <span class="w-3 h-4 bg-yellow-400 rounded-sm"></span>
                                            <span x-show="sortColumn === 'yellow'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'red' ? !sortAsc : false; sortColumn = 'red'; sortTable('red', sortAsc)" title="Red Cards">
                                        <span class="flex items-center justify-center gap-1">
                                            <span class="w-3 h-4 bg-red-500 rounded-sm"></span>
                                            <span x-show="sortColumn === 'red'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'clean_sheets' ? !sortAsc : false; sortColumn = 'clean_sheets'; sortTable('clean_sheets', sortAsc)" title="Clean Sheets (Goalkeepers)">
                                        <span class="flex items-center justify-center gap-1">
                                            CS
                                            <span x-show="sortColumn === 'clean_sheets'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-slate-200 border-b-2 border-slate-300">
                                <tr>
                                    <td class="py-2"></td>
                                    <td class="py-2 font-semibold text-slate-700">Squad Totals</td>
                                    <td class="py-2"></td>
                                    <td class="py-2 text-center font-semibold">{{ $totals['appearances'] }}</td>
                                    <td class="py-2 text-center font-semibold text-green-600">{{ $totals['goals'] }}</td>
                                    <td class="py-2 text-center font-semibold text-sky-600">{{ $totals['assists'] }}</td>
                                    <td class="py-2 text-center font-semibold">{{ $totals['goals'] + $totals['assists'] }}</td>
                                    <td class="py-2"></td>
                                    <td class="py-2 text-center font-semibold text-red-500">{{ $totals['own_goals'] }}</td>
                                    <td class="py-2 text-center font-semibold text-yellow-600">{{ $totals['yellow_cards'] }}</td>
                                    <td class="py-2 text-center font-semibold text-red-600">{{ $totals['red_cards'] }}</td>
                                    <td class="py-2 text-center font-semibold text-green-600">{{ $totals['clean_sheets'] }}</td>
                                </tr>
                            </tbody>
                            <tbody id="stats-body">
                                @foreach($players->sortByDesc('appearances') as $player)
                                    @php
                                        $positionDisplay = $player->position_display;
                                        $goalsPerGame = $player->appearances > 0 ? round($player->goals / $player->appearances, 2) : 0;
                                        $contributions = $player->goals + $player->assists;
                                    @endphp
                                    <tr class="border-b border-slate-200 hover:bg-slate-50 player-row"
                                        data-name="{{ strtolower($player->name) }}"
                                        data-age="{{ $player->age }}"
                                        data-appearances="{{ $player->appearances }}"
                                        data-goals="{{ $player->goals }}"
                                        data-assists="{{ $player->assists }}"
                                        data-contributions="{{ $contributions }}"
                                        data-gpg="{{ $goalsPerGame }}"
                                        data-own_goals="{{ $player->own_goals }}"
                                        data-yellow="{{ $player->yellow_cards }}"
                                        data-red="{{ $player->red_cards }}"
                                        data-clean_sheets="{{ $player->clean_sheets }}">
                                        {{-- Position --}}
                                        <td class="py-2 text-center">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded text-xs font-bold {{ $positionDisplay['bg'] }} {{ $positionDisplay['text'] }}" title="{{ $player->position }}">
                                                {{ $positionDisplay['abbreviation'] }}
                                            </span>
                                        </td>
                                        {{-- Name --}}
                                        <td class="py-2">
                                            <div class="font-medium text-slate-900">{{ $player->name }}</div>
                                        </td>
                                        {{-- Age --}}
                                        <td class="py-2 text-center text-slate-600">{{ $player->age }}</td>
                                        {{-- Appearances --}}
                                        <td class="py-2 text-center">
                                            <span class="{{ $player->appearances > 0 ? 'font-medium text-slate-900' : 'text-slate-400' }}">{{ $player->appearances }}</span>
                                        </td>
                                        {{-- Goals --}}
                                        <td class="py-2 text-center">
                                            <span class="{{ $player->goals > 0 ? 'font-semibold text-green-600' : 'text-slate-400' }}">{{ $player->goals }}</span>
                                        </td>
                                        {{-- Assists --}}
                                        <td class="py-2 text-center">
                                            <span class="{{ $player->assists > 0 ? 'font-medium text-sky-600' : 'text-slate-400' }}">{{ $player->assists }}</span>
                                        </td>
                                        {{-- Goal Contributions --}}
                                        <td class="py-2 text-center">
                                            <span class="{{ $contributions > 0 ? 'font-semibold text-slate-900' : 'text-slate-400' }}">{{ $contributions }}</span>
                                        </td>
                                        {{-- Goals per Game --}}
                                        <td class="py-2 text-center text-slate-500 text-xs">{{ number_format($goalsPerGame, 2) }}</td>
                                        {{-- Own Goals --}}
                                        <td class="py-2 text-center">
                                            <span class="{{ $player->own_goals > 0 ? 'text-red-500' : 'text-slate-400' }}">{{ $player->own_goals }}</span>
                                        </td>
                                        {{-- Yellow Cards --}}
                                        <td class="py-2 text-center">
                                            <span class="{{ $player->yellow_cards > 0 ? 'text-yellow-600 font-medium' : 'text-slate-400' }}">{{ $player->yellow_cards }}</span>
                                        </td>
                                        {{-- Red Cards --}}
                                        <td class="py-2 text-center">
                                            <span class="{{ $player->red_cards > 0 ? 'text-red-600 font-medium' : 'text-slate-400' }}">{{ $player->red_cards }}</span>
                                        </td>
                                        {{-- Clean Sheets --}}
                                        <td class="py-2 text-center">
                                            @if($player->position === 'Goalkeeper')
                                                <span class="{{ $player->clean_sheets > 0 ? 'text-green-600 font-medium' : 'text-slate-400' }}">{{ $player->clean_sheets }}</span>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Legend --}}
                    <div class="pt-6 border-t">
                        <div class="flex flex-wrap gap-6 text-xs text-slate-500">
                            <div><span class="font-medium">Apps</span> = Appearances</div>
                            <div><span class="font-medium">G</span> = Goals</div>
                            <div><span class="font-medium">A</span> = Assists</div>
                            <div><span class="font-medium">G+A</span> = Goal Contributions</div>
                            <div><span class="font-medium">OG</span> = Own Goals</div>
                            <div><span class="font-medium">CS</span> = Clean Sheets (GK only)</div>
                            <div class="text-slate-400">Click column headers to sort</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
