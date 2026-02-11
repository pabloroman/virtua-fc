@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ __('squad.squad_statistics') }}</h3>

                    <x-section-nav :items="[
                        ['href' => route('game.squad', $game->id), 'label' => __('squad.squad'), 'active' => false],
                        ['href' => route('game.squad.development', $game->id), 'label' => __('squad.development'), 'active' => false],
                        ['href' => route('game.squad.stats', $game->id), 'label' => __('squad.stats'), 'active' => true],
                        ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false, 'badge' => $academyCount > 0 ? $academyCount : null],
                    ]" />

                    <div class="mt-6"></div>

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
                                            {{ __('app.player') }}
                                            <span x-show="sortColumn === 'name'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'age' ? !sortAsc : true; sortColumn = 'age'; sortTable('age', sortAsc)">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.age') }}
                                            <span x-show="sortColumn === 'age'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'appearances' ? !sortAsc : false; sortColumn = 'appearances'; sortTable('appearances', sortAsc)" title="{{ __('squad.appearances') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.apps') }}
                                            <span x-show="sortColumn === 'appearances'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'goals' ? !sortAsc : false; sortColumn = 'goals'; sortTable('goals', sortAsc)" title="{{ __('squad.legend_goals') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goals') }}
                                            <span x-show="sortColumn === 'goals'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'assists' ? !sortAsc : false; sortColumn = 'assists'; sortTable('assists', sortAsc)" title="{{ __('squad.legend_assists') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.assists') }}
                                            <span x-show="sortColumn === 'assists'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'contributions' ? !sortAsc : false; sortColumn = 'contributions'; sortTable('contributions', sortAsc)" title="{{ __('squad.legend_contributions') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goal_contributions') }}
                                            <span x-show="sortColumn === 'contributions'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-14 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'gpg' ? !sortAsc : false; sortColumn = 'gpg'; sortTable('gpg', sortAsc)" title="{{ __('squad.goals_per_game') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goals_per_game') }}
                                            <span x-show="sortColumn === 'gpg'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'own_goals' ? !sortAsc : false; sortColumn = 'own_goals'; sortTable('own_goals', sortAsc)" title="{{ __('squad.legend_own_goals') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.own_goals') }}
                                            <span x-show="sortColumn === 'own_goals'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'yellow' ? !sortAsc : false; sortColumn = 'yellow'; sortTable('yellow', sortAsc)" title="{{ __('squad.yellow_cards') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            <span class="w-3 h-4 bg-yellow-400 rounded-sm"></span>
                                            <span x-show="sortColumn === 'yellow'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'red' ? !sortAsc : false; sortColumn = 'red'; sortTable('red', sortAsc)" title="{{ __('squad.red_cards') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            <span class="w-3 h-4 bg-red-500 rounded-sm"></span>
                                            <span x-show="sortColumn === 'red'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortAsc = sortColumn === 'clean_sheets' ? !sortAsc : false; sortColumn = 'clean_sheets'; sortTable('clean_sheets', sortAsc)" title="{{ __('squad.legend_clean_sheets') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.clean_sheets') }}
                                            <span x-show="sortColumn === 'clean_sheets'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-slate-200 border-b-2 border-slate-300">
                                <tr>
                                    <td class="py-2"></td>
                                    <td class="py-2 font-semibold text-slate-700">{{ __('squad.squad_totals') }}</td>
                                    <td class="py-2"></td>
                                    <td class="py-2 text-center font-semibold"></td>
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
                                            <x-position-badge :position="$player->position" :tooltip="$player->position" />
                                        </td>
                                        {{-- Name --}}
                                        <td class="py-2">
                                            <div class="flex items-center gap-2">
                                                @if($player->number)
                                                    <span class="text-xs text-slate-400 w-4 text-right">{{ $player->number }}</span>
                                                @endif
                                                <span class="font-medium text-slate-900">{{ $player->name }}</span>
                                            </div>
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
                            <div><span class="font-medium">{{ __('squad.apps') }}</span> = {{ __('squad.legend_apps') }}</div>
                            <div><span class="font-medium">{{ __('squad.goals') }}</span> = {{ __('squad.legend_goals') }}</div>
                            <div><span class="font-medium">{{ __('squad.assists') }}</span> = {{ __('squad.legend_assists') }}</div>
                            <div><span class="font-medium">{{ __('squad.goal_contributions') }}</span> = {{ __('squad.legend_contributions') }}</div>
                            <div><span class="font-medium">{{ __('squad.own_goals') }}</span> = {{ __('squad.legend_own_goals') }}</div>
                            <div><span class="font-medium">{{ __('squad.clean_sheets') }}</span> = {{ __('squad.legend_clean_sheets') }}</div>
                            <div class="text-slate-400">{{ __('squad.click_to_sort') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
