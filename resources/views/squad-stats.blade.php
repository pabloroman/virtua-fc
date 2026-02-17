@php
    /** @var App\Models\Game $game **/
    $sortedPlayers = $players->sortByDesc('appearances');

    // Compute top performers for highlighting
    $maxGoals = $players->max('goals');
    $maxAssists = $players->max('assists');
    $maxContributions = $players->max('goal_contributions');
    $maxAppearances = $players->max('appearances');
    $maxCleanSheets = $players->where('position', 'Goalkeeper')->max('clean_sheets');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    @php
                        $squadNavItems = [
                            ['href' => route('game.squad', $game->id), 'label' => __('squad.squad'), 'active' => false],
                            ['href' => route('game.squad.development', $game->id), 'label' => __('squad.development'), 'active' => false],
                            ['href' => route('game.squad.stats', $game->id), 'label' => __('squad.stats'), 'active' => true],
                        ];
                        if ($game->isCareerMode()) {
                            $squadNavItems[] = ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false, 'badge' => $academyCount > 0 ? $academyCount : null];
                        }
                    @endphp
                    <x-section-nav :items="$squadNavItems" />

                    <div class="mt-6"></div>

                    {{-- Season summary card --}}
                    <div class="flex flex-wrap items-stretch gap-4 mb-6">
                        <div class="flex items-center gap-3 px-5 py-3 bg-slate-50 rounded-lg border border-slate-200">
                            <div class="text-2xl font-bold text-green-600">{{ $totals['goals'] }}</div>
                            <div class="text-xs text-slate-500 leading-tight">{{ __('squad.legend_goals') }}</div>
                        </div>
                        <div class="flex items-center gap-3 px-5 py-3 bg-slate-50 rounded-lg border border-slate-200">
                            <div class="text-2xl font-bold text-sky-600">{{ $totals['assists'] }}</div>
                            <div class="text-xs text-slate-500 leading-tight">{{ __('squad.legend_assists') }}</div>
                        </div>
                        <div class="flex items-center gap-3 px-5 py-3 bg-slate-50 rounded-lg border border-slate-200">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-4 bg-yellow-400 rounded-sm"></span>
                                <span class="text-2xl font-bold text-yellow-600">{{ $totals['yellow_cards'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-4 bg-red-500 rounded-sm"></span>
                                <span class="text-2xl font-bold text-red-600">{{ $totals['red_cards'] }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 px-5 py-3 bg-slate-50 rounded-lg border border-slate-200">
                            <div class="text-2xl font-bold text-green-600">{{ $totals['clean_sheets'] }}</div>
                            <div class="text-xs text-slate-500 leading-tight">{{ __('squad.legend_clean_sheets') }}</div>
                        </div>
                        @if($totals['own_goals'] > 0)
                        <div class="flex items-center gap-3 px-5 py-3 bg-slate-50 rounded-lg border border-slate-200">
                            <div class="text-2xl font-bold text-red-500">{{ $totals['own_goals'] }}</div>
                            <div class="text-xs text-slate-500 leading-tight">{{ __('squad.legend_own_goals') }}</div>
                        </div>
                        @endif
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
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="stats-table">
                            <thead class="text-left border-b border-slate-300">
                                <tr>
                                    <th class="font-semibold py-2 w-10 sticky left-0 bg-white z-10"></th>
                                    <th class="font-semibold py-2 cursor-pointer hover:text-sky-600 select-none sticky left-10 bg-white z-10" @click="sortAsc = sortColumn === 'name' ? !sortAsc : true; sortColumn = 'name'; sortTable('name', sortAsc)">
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
                            <tbody id="stats-body">
                                @foreach($sortedPlayers as $player)
                                    @php
                                        $goalsPerGame = $player->appearances > 0 ? round($player->goals / $player->appearances, 2) : 0;
                                        $contributions = $player->goals + $player->assists;
                                        $isTopScorer = $maxGoals > 0 && $player->goals === $maxGoals;
                                        $isTopAssister = $maxAssists > 0 && $player->assists === $maxAssists;
                                        $isTopContributor = $maxContributions > 0 && $contributions === $maxContributions;
                                        $isTopAppearances = $maxAppearances > 0 && $player->appearances === $maxAppearances;
                                        $isTopCleanSheets = $maxCleanSheets > 0 && $player->position === 'Goalkeeper' && $player->clean_sheets === $maxCleanSheets;
                                    @endphp
                                    <tr class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer player-row" @click="$dispatch('show-player-detail', @js($player->toModalData($game)))"
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
                                        <td class="py-2.5 text-center sticky left-0 bg-white z-10">
                                            <x-position-badge :position="$player->position" :tooltip="\App\Support\PositionMapper::toDisplayName($player->position)" />
                                        </td>
                                        {{-- Name --}}
                                        <td class="py-2.5 sticky left-10 bg-white z-10">
                                            <div class="flex items-center gap-2">
                                                @if($player->number)
                                                    <span class="text-xs text-slate-400 w-4 text-right">{{ $player->number }}</span>
                                                @endif
                                                <span class="font-medium text-slate-900">{{ $player->name }}</span>
                                            </div>
                                        </td>
                                        {{-- Age --}}
                                        <td class="py-2.5 text-center text-slate-600">{{ $player->age }}</td>
                                        {{-- Appearances --}}
                                        <td class="py-2.5 text-center {{ $isTopAppearances ? 'bg-amber-50' : '' }}">
                                            <span class="{{ $player->appearances > 0 ? 'font-medium text-slate-900' : 'text-slate-300' }}">{{ $player->appearances > 0 ? $player->appearances : '-' }}</span>
                                        </td>
                                        {{-- Goals --}}
                                        <td class="py-2.5 text-center {{ $isTopScorer ? 'bg-amber-50' : '' }}">
                                            <span class="{{ $player->goals > 0 ? 'font-semibold text-green-600' : 'text-slate-300' }}">{{ $player->goals > 0 ? $player->goals : '-' }}</span>
                                        </td>
                                        {{-- Assists --}}
                                        <td class="py-2.5 text-center {{ $isTopAssister ? 'bg-amber-50' : '' }}">
                                            <span class="{{ $player->assists > 0 ? 'font-medium text-sky-600' : 'text-slate-300' }}">{{ $player->assists > 0 ? $player->assists : '-' }}</span>
                                        </td>
                                        {{-- Goal Contributions --}}
                                        <td class="py-2.5 text-center {{ $isTopContributor ? 'bg-amber-50' : '' }}">
                                            <span class="{{ $contributions > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">{{ $contributions > 0 ? $contributions : '-' }}</span>
                                        </td>
                                        {{-- Goals per Game --}}
                                        <td class="py-2.5 text-center text-xs {{ $goalsPerGame > 0 ? 'text-slate-500' : 'text-slate-300' }}">{{ $goalsPerGame > 0 ? number_format($goalsPerGame, 2) : '-' }}</td>
                                        {{-- Own Goals --}}
                                        <td class="py-2.5 text-center">
                                            <span class="{{ $player->own_goals > 0 ? 'text-red-500 font-medium' : 'text-slate-300' }}">{{ $player->own_goals > 0 ? $player->own_goals : '-' }}</span>
                                        </td>
                                        {{-- Yellow Cards --}}
                                        <td class="py-2.5 text-center">
                                            @if($player->yellow_cards > 0)
                                                <span class="inline-flex items-center gap-1 text-yellow-600 font-medium">
                                                    <span class="w-2.5 h-3.5 bg-yellow-400 rounded-sm flex-shrink-0"></span>
                                                    {{ $player->yellow_cards }}
                                                </span>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                        {{-- Red Cards --}}
                                        <td class="py-2.5 text-center">
                                            @if($player->red_cards > 0)
                                                <span class="inline-flex items-center gap-1 text-red-600 font-medium">
                                                    <span class="w-2.5 h-3.5 bg-red-500 rounded-sm flex-shrink-0"></span>
                                                    {{ $player->red_cards }}
                                                </span>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                        {{-- Clean Sheets --}}
                                        <td class="py-2.5 text-center {{ $isTopCleanSheets ? 'bg-amber-50' : '' }}">
                                            @if($player->position === 'Goalkeeper')
                                                <span class="{{ $player->clean_sheets > 0 ? 'text-green-600 font-medium' : 'text-slate-300' }}">{{ $player->clean_sheets > 0 ? $player->clean_sheets : '0' }}</span>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>

                    {{-- Legend --}}
                    <div class="pt-6 border-t mt-2">
                        <div class="flex flex-wrap gap-6 text-xs text-slate-500">
                            <div><span class="font-medium">{{ __('squad.apps') }}</span> = {{ __('squad.legend_apps') }}</div>
                            <div><span class="font-medium">{{ __('squad.goals') }}</span> = {{ __('squad.legend_goals') }}</div>
                            <div><span class="font-medium">{{ __('squad.assists') }}</span> = {{ __('squad.legend_assists') }}</div>
                            <div><span class="font-medium">{{ __('squad.goal_contributions') }}</span> = {{ __('squad.legend_contributions') }}</div>
                            <div><span class="font-medium">{{ __('squad.own_goals') }}</span> = {{ __('squad.legend_own_goals') }}</div>
                            <div><span class="font-medium">{{ __('squad.clean_sheets') }}</span> = {{ __('squad.legend_clean_sheets') }}</div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-3 h-3 bg-amber-50 border border-amber-200 rounded"></div>
                                <span>{{ __('squad.top_in_squad') }}</span>
                            </div>
                            <div class="text-slate-400">{{ __('squad.click_to_sort') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-player-detail-modal />
</x-app-layout>
