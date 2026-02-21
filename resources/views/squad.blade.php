@php
    /** @var App\Models\Game $game **/
    $isCareer = $game->isCareerMode();

    // Stats highlights
    $maxGoals = $players->max('goals');
    $maxAssists = $players->max('assists');
    $maxContributions = $players->max('goal_contributions');
    $maxAppearances = $players->max('appearances');
    $maxCleanSheets = $players->where('position', 'Goalkeeper')->max('clean_sheets');

    // Squad summary
    $avgFitness = $players->avg('fitness');
    $avgMorale = $players->avg('morale');
    $lowFitnessCount = $players->filter(fn($p) => $p->fitness < 70)->count();
    $lowMoraleCount = $players->filter(fn($p) => $p->morale < 65)->count();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 md:p-8">
                    @php
                        $squadNavItems = [
                            ['href' => route('game.squad', $game->id), 'label' => __('squad.squad'), 'active' => true],
                        ];
                        if ($isCareer) {
                            $squadNavItems[] = ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false, 'badge' => $academyCount > 0 ? $academyCount : null];
                        }
                    @endphp
                    <x-section-nav :items="$squadNavItems" />

                    <div class="mt-6"></div>

                    {{-- Flash Messages --}}
                    @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                        {{ session('success') }}
                    </div>
                    @endif
                    @if(session('error'))
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                        {{ session('error') }}
                    </div>
                    @endif

                    {{-- Alpine.js view mode controller --}}
                    <div x-data="{
                        viewMode: new URLSearchParams(window.location.search).get('view') || 'skills',
                        sortColumn: 'position',
                        sortAsc: true,
                        init() {
                            this.$watch('viewMode', (val) => {
                                const url = new URL(window.location);
                                if (val === 'skills') {
                                    url.searchParams.delete('view');
                                } else {
                                    url.searchParams.set('view', val);
                                }
                                history.replaceState({}, '', url);
                            });
                        },
                        sortTable(column) {
                            if (this.sortColumn === column) {
                                this.sortAsc = !this.sortAsc;
                            } else {
                                this.sortColumn = column;
                                this.sortAsc = column === 'name' || column === 'position';
                            }
                            const tbody = this.$refs.tbody;
                            const rows = Array.from(tbody.querySelectorAll('.player-row'));
                            rows.sort((a, b) => {
                                let aVal = a.dataset[column];
                                let bVal = b.dataset[column];
                                if (!isNaN(parseFloat(aVal)) && !isNaN(parseFloat(bVal))) {
                                    aVal = parseFloat(aVal);
                                    bVal = parseFloat(bVal);
                                }
                                if (aVal < bVal) return this.sortAsc ? -1 : 1;
                                if (aVal > bVal) return this.sortAsc ? 1 : -1;
                                return 0;
                            });
                            rows.forEach(row => tbody.appendChild(row));
                        }
                    }">

                        {{-- View mode toggle --}}
                        <div class="flex gap-1 mb-5 overflow-x-auto scrollbar-hide">
                            <button @click="viewMode = 'skills'"
                                :class="viewMode === 'skills' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="shrink-0 px-3 md:px-4 py-1.5 rounded-full text-xs md:text-sm font-medium transition-colors min-h-[36px]">
                                {{ __('squad.view_skills') }}
                            </button>
                            <button @click="viewMode = 'development'"
                                :class="viewMode === 'development' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="shrink-0 px-3 md:px-4 py-1.5 rounded-full text-xs md:text-sm font-medium transition-colors min-h-[36px]">
                                {{ __('squad.view_development') }}
                            </button>
                            @if($isCareer)
                            <button @click="viewMode = 'contract'"
                                :class="viewMode === 'contract' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="shrink-0 px-3 md:px-4 py-1.5 rounded-full text-xs md:text-sm font-medium transition-colors min-h-[36px]">
                                {{ __('squad.view_contract') }}
                            </button>
                            @endif
                            <button @click="viewMode = 'stats'"
                                :class="viewMode === 'stats' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="shrink-0 px-3 md:px-4 py-1.5 rounded-full text-xs md:text-sm font-medium transition-colors min-h-[36px]">
                                {{ __('squad.view_stats') }}
                            </button>
                        </div>

                        {{-- Stats summary cards (stats mode only) --}}
                        <div x-show="viewMode === 'stats'" x-cloak class="flex flex-wrap items-stretch gap-3 md:gap-4 mb-5">
                            <div class="flex items-center gap-3 px-4 md:px-5 py-2.5 md:py-3 bg-slate-50 rounded-lg border border-slate-200">
                                <div class="text-xl md:text-2xl font-bold text-green-600">{{ $totals['goals'] }}</div>
                                <div class="text-xs text-slate-500 leading-tight">{{ __('squad.legend_goals') }}</div>
                            </div>
                            <div class="flex items-center gap-3 px-4 md:px-5 py-2.5 md:py-3 bg-slate-50 rounded-lg border border-slate-200">
                                <div class="text-xl md:text-2xl font-bold text-sky-600">{{ $totals['assists'] }}</div>
                                <div class="text-xs text-slate-500 leading-tight">{{ __('squad.legend_assists') }}</div>
                            </div>
                            <div class="flex items-center gap-3 px-4 md:px-5 py-2.5 md:py-3 bg-slate-50 rounded-lg border border-slate-200">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-4 bg-yellow-400 rounded-sm"></span>
                                    <span class="text-xl md:text-2xl font-bold text-yellow-600">{{ $totals['yellow_cards'] }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-4 bg-red-500 rounded-sm"></span>
                                    <span class="text-xl md:text-2xl font-bold text-red-600">{{ $totals['red_cards'] }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 px-4 md:px-5 py-2.5 md:py-3 bg-slate-50 rounded-lg border border-slate-200">
                                <div class="text-xl md:text-2xl font-bold text-green-600">{{ $totals['clean_sheets'] }}</div>
                                <div class="text-xs text-slate-500 leading-tight">{{ __('squad.legend_clean_sheets') }}</div>
                            </div>
                            @if($totals['own_goals'] > 0)
                            <div class="flex items-center gap-3 px-4 md:px-5 py-2.5 md:py-3 bg-slate-50 rounded-lg border border-slate-200">
                                <div class="text-xl md:text-2xl font-bold text-red-500">{{ $totals['own_goals'] }}</div>
                                <div class="text-xs text-slate-500 leading-tight">{{ __('squad.legend_own_goals') }}</div>
                            </div>
                            @endif
                        </div>

                        {{-- Data table --}}
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left border-b border-slate-300">
                                <tr>
                                    {{-- Common columns: Position, Name, Age --}}
                                    <th class="font-semibold py-2 w-10 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('position')">
                                        <span class="flex items-center justify-center gap-1">
                                            <span x-show="sortColumn === 'position'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('name')">
                                        <span class="flex items-center gap-1">
                                            {{ __('app.name') }}
                                            <span x-show="sortColumn === 'name'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('age')" x-show="viewMode !== 'skills'">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.age') }}
                                            <span x-show="sortColumn === 'age'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>

                                    {{-- Skills columns --}}
                                    <th x-show="viewMode === 'skills'" class="font-semibold py-2 text-center w-8 text-slate-400 hidden md:table-cell">#</th>
                                    <th x-show="viewMode === 'skills'" class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.country') }}</th>
                                    <th x-show="viewMode === 'skills'" class="font-semibold py-2 text-center w-12 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('age')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.age') }}
                                            <span x-show="sortColumn === 'age'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'skills'" class="font-semibold py-2 pl-3 text-center w-10 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('technical')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.technical') }}
                                            <span x-show="sortColumn === 'technical'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'skills'" class="font-semibold py-2 text-center w-10 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('physical')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.physical') }}
                                            <span x-show="sortColumn === 'physical'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'skills'" class="font-semibold py-2 text-center w-10 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('fitness')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.fitness') }}
                                            <span x-show="sortColumn === 'fitness'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'skills'" class="font-semibold py-2 text-center w-10 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('morale')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.morale') }}
                                            <span x-show="sortColumn === 'morale'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'skills'" class="font-semibold py-2 text-center w-10 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('overall')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.overall') }}
                                            <span x-show="sortColumn === 'overall'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>

                                    {{-- Development columns --}}
                                    <th x-show="viewMode === 'development'" class="font-semibold py-2 pl-2 hidden md:table-cell" style="min-width: 160px">{{ __('squad.ability') }}</th>
                                    <th x-show="viewMode === 'development'" class="font-semibold py-2 text-center w-24 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('devstatus')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.status') }}
                                            <span x-show="sortColumn === 'devstatus'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'development'" class="font-semibold py-2 text-center w-24 hidden md:table-cell">{{ __('squad.playing_time') }}</th>
                                    <th x-show="viewMode === 'development'" class="font-semibold py-2 text-center cursor-pointer hover:text-sky-600 select-none" style="min-width: 110px" @click="sortTable('projection')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.projection') }}
                                            <span x-show="sortColumn === 'projection'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>

                                    {{-- Contract columns (career only) --}}
                                    @if($isCareer)
                                    <th x-show="viewMode === 'contract'" class="font-semibold py-2 pl-3 pr-4 text-right w-24 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('marketvalue')">
                                        <span class="flex items-center justify-end gap-1">
                                            {{ __('app.value') }}
                                            <span x-show="sortColumn === 'marketvalue'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'contract'" class="font-semibold py-2 pr-4 text-right w-24 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('wage')">
                                        <span class="flex items-center justify-end gap-1">
                                            {{ __('app.wage') }}
                                            <span x-show="sortColumn === 'wage'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'contract'" class="font-semibold py-2 text-center w-20 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('contract')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.contract') }}
                                            <span x-show="sortColumn === 'contract'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'contract'" class="font-semibold py-2 text-center w-10 hidden md:table-cell">{{ __('app.status') }}</th>
                                    @endif

                                    {{-- Stats columns --}}
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('appearances')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.apps') }}
                                            <span x-show="sortColumn === 'appearances'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('goals')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goals') }}
                                            <span x-show="sortColumn === 'goals'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sortTable('assists')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.assists') }}
                                            <span x-show="sortColumn === 'assists'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-12 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('contributions')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goal_contributions') }}
                                            <span x-show="sortColumn === 'contributions'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-14 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('gpg')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goals_per_game') }}
                                            <span x-show="sortColumn === 'gpg'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-12 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('own_goals')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.own_goals') }}
                                            <span x-show="sortColumn === 'own_goals'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-12 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('yellow')">
                                        <span class="flex items-center justify-center gap-1">
                                            <span class="w-3 h-4 bg-yellow-400 rounded-sm"></span>
                                            <span x-show="sortColumn === 'yellow'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-12 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('red')">
                                        <span class="flex items-center justify-center gap-1">
                                            <span class="w-3 h-4 bg-red-500 rounded-sm"></span>
                                            <span x-show="sortColumn === 'red'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th x-show="viewMode === 'stats'" class="font-semibold py-2 text-center w-12 hidden md:table-cell cursor-pointer hover:text-sky-600 select-none" @click="sortTable('clean_sheets')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.clean_sheets') }}
                                            <span x-show="sortColumn === 'clean_sheets'" x-text="sortAsc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>

                                    {{-- Detail column --}}
                                    <th class="py-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody x-ref="tbody">
                                @foreach($players as $gamePlayer)
                                    @php
                                        $nextMatchday = $game->current_matchday + 1;
                                        $isUnavailable = !$gamePlayer->isAvailable($game->current_date, $nextMatchday);
                                        $unavailabilityReason = $gamePlayer->getUnavailabilityReason($game->current_date, $nextMatchday);

                                        // Development
                                        $currentAbility = (int) round(($gamePlayer->current_technical_ability + $gamePlayer->current_physical_ability) / 2);
                                        $hasStarterBonus = $gamePlayer->season_appearances >= 15;
                                        $projectedAbility = $currentAbility + ($gamePlayer->projection ?? 0);

                                        // Stats
                                        $goalsPerGame = $gamePlayer->appearances > 0 ? round($gamePlayer->goals / $gamePlayer->appearances, 2) : 0;
                                        $contributions = $gamePlayer->goals + $gamePlayer->assists;
                                        $isTopScorer = $maxGoals > 0 && $gamePlayer->goals === $maxGoals;
                                        $isTopAssister = $maxAssists > 0 && $gamePlayer->assists === $maxAssists;
                                        $isTopContributor = $maxContributions > 0 && $contributions === $maxContributions;
                                        $isTopAppearances = $maxAppearances > 0 && $gamePlayer->appearances === $maxAppearances;
                                        $isTopCleanSheets = $maxCleanSheets > 0 && $gamePlayer->position === 'Goalkeeper' && $gamePlayer->clean_sheets === $maxCleanSheets;

                                        // Dev status sort value
                                        $devStatusSort = match($gamePlayer->development_status) { 'growing' => 1, 'peak' => 2, 'declining' => 3, default => 4 };
                                    @endphp
                                    <tr class="border-b border-slate-100 hover:bg-slate-50 player-row"
                                        data-position="{{ \App\Modules\Lineup\Services\LineupService::positionSortOrder($gamePlayer->position) }}"
                                        data-name="{{ strtolower($gamePlayer->player->name) }}"
                                        data-age="{{ $gamePlayer->age }}"
                                        data-technical="{{ $gamePlayer->technical_ability }}"
                                        data-physical="{{ $gamePlayer->physical_ability }}"
                                        data-fitness="{{ $gamePlayer->fitness }}"
                                        data-morale="{{ $gamePlayer->morale }}"
                                        data-overall="{{ $gamePlayer->overall_score }}"
                                        data-projection="{{ $gamePlayer->projection ?? 0 }}"
                                        data-devstatus="{{ $devStatusSort }}"
                                        data-marketvalue="{{ $gamePlayer->market_value ?? 0 }}"
                                        data-wage="{{ $gamePlayer->annual_wage ?? 0 }}"
                                        data-contract="{{ $gamePlayer->contract_until ? $gamePlayer->contract_until->format('Y') : 9999 }}"
                                        data-appearances="{{ $gamePlayer->appearances }}"
                                        data-goals="{{ $gamePlayer->goals }}"
                                        data-assists="{{ $gamePlayer->assists }}"
                                        data-contributions="{{ $contributions }}"
                                        data-gpg="{{ $goalsPerGame }}"
                                        data-own_goals="{{ $gamePlayer->own_goals }}"
                                        data-yellow="{{ $gamePlayer->yellow_cards }}"
                                        data-red="{{ $gamePlayer->red_cards }}"
                                        data-clean_sheets="{{ $gamePlayer->clean_sheets }}">

                                        {{-- Position badge --}}
                                        <td class="py-2 text-center">
                                            <x-position-badge :position="$gamePlayer->position" :tooltip="\App\Support\PositionMapper::toDisplayName($gamePlayer->position)" class="cursor-help" />
                                        </td>

                                        {{-- Name --}}
                                        <td class="py-2">
                                            <div class="flex items-center space-x-2">
                                                <button @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gamePlayer->id]) }}')" class="p-1.5 text-slate-300 rounded hover:text-slate-400">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                        <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-slate-900 truncate @if($isUnavailable) text-slate-400 @endif">
                                                        {{ $gamePlayer->player->name }}
                                                    </div>
                                                    @if($unavailabilityReason)
                                                        <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Age (shown on development, contract, stats modes) --}}
                                        <td class="py-2 text-center" x-show="viewMode !== 'skills'">
                                            <span class="@if($gamePlayer->age <= 23) text-green-600 @elseif($gamePlayer->age >= 30) text-orange-500 @else text-slate-600 @endif">
                                                {{ $gamePlayer->age }}
                                            </span>
                                        </td>

                                        {{-- ===== SKILLS MODE ===== --}}
                                        {{-- Squad number --}}
                                        <td x-show="viewMode === 'skills'" class="py-2 text-center text-slate-400 text-xs hidden md:table-cell">{{ $gamePlayer->number ?? '-' }}</td>
                                        {{-- Nationality --}}
                                        <td x-show="viewMode === 'skills'" class="py-2 text-center hidden md:table-cell">
                                            @if($gamePlayer->nationality_flag)
                                                <img src="/flags/{{ $gamePlayer->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded shadow-sm" title="{{ $gamePlayer->nationality_flag['name'] }}">
                                            @endif
                                        </td>
                                        {{-- Age (skills mode — desktop only) --}}
                                        <td x-show="viewMode === 'skills'" class="py-2 text-center hidden md:table-cell">{{ $gamePlayer->age }}</td>
                                        {{-- Technical --}}
                                        <td x-show="viewMode === 'skills'" class="border-l border-slate-200 py-2 pl-3 text-center hidden md:table-cell">
                                            <x-ability-bar :value="$gamePlayer->technical_ability" size="sm" class="text-xs font-medium justify-center @if($gamePlayer->technical_ability >= 80) text-green-600 @elseif($gamePlayer->technical_ability >= 70) text-lime-600 @elseif($gamePlayer->technical_ability < 60) text-slate-400 @endif" />
                                        </td>
                                        {{-- Physical --}}
                                        <td x-show="viewMode === 'skills'" class="py-2 text-center hidden md:table-cell">
                                            <x-ability-bar :value="$gamePlayer->physical_ability" size="sm" class="text-xs font-medium justify-center @if($gamePlayer->physical_ability >= 80) text-green-600 @elseif($gamePlayer->physical_ability >= 70) text-lime-600 @elseif($gamePlayer->physical_ability < 60) text-slate-400 @endif" />
                                        </td>
                                        {{-- Fitness --}}
                                        <td x-show="viewMode === 'skills'" class="py-2 text-center hidden md:table-cell">
                                            <span class="@if($gamePlayer->fitness >= 90) text-green-600 @elseif($gamePlayer->fitness >= 80) text-lime-600 @elseif($gamePlayer->fitness < 50) text-red-500 font-medium @elseif($gamePlayer->fitness < 70) text-yellow-600 @endif">
                                                {{ $gamePlayer->fitness }}
                                            </span>
                                        </td>
                                        {{-- Morale --}}
                                        <td x-show="viewMode === 'skills'" class="py-2 text-center hidden md:table-cell">
                                            <span class="@if($gamePlayer->morale >= 85) text-green-600 @elseif($gamePlayer->morale >= 75) text-lime-600 @elseif($gamePlayer->morale < 50) text-red-500 font-medium @elseif($gamePlayer->morale < 65) text-yellow-600 @endif">
                                                {{ $gamePlayer->morale }}
                                            </span>
                                        </td>
                                        {{-- Overall --}}
                                        <td x-show="viewMode === 'skills'" class="py-2 text-center">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold
                                                @if($gamePlayer->overall_score >= 80) bg-emerald-500 text-white
                                                @elseif($gamePlayer->overall_score >= 70) bg-lime-500 text-white
                                                @elseif($gamePlayer->overall_score >= 60) bg-amber-500 text-white
                                                @else bg-slate-300 text-slate-700
                                                @endif">
                                                {{ $gamePlayer->overall_score }}
                                            </span>
                                        </td>

                                        {{-- ===== DEVELOPMENT MODE ===== --}}
                                        {{-- Potential bar --}}
                                        <td x-show="viewMode === 'development'" class="py-2 pl-2 hidden md:table-cell">
                                            <x-potential-bar
                                                :current-ability="$currentAbility"
                                                :potential-low="$gamePlayer->potential_low"
                                                :potential-high="$gamePlayer->potential_high"
                                                :projection="$gamePlayer->projection"
                                            />
                                        </td>
                                        {{-- Dev status --}}
                                        <td x-show="viewMode === 'development'" class="py-2 text-center hidden md:table-cell">
                                            @if($gamePlayer->development_status === 'growing')
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                                    {{ __('squad.growing') }}
                                                </span>
                                            @elseif($gamePlayer->development_status === 'peak')
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                                    {{ __('squad.peak') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                                    {{ __('squad.declining') }}
                                                </span>
                                            @endif
                                        </td>
                                        {{-- Playing time --}}
                                        <td x-show="viewMode === 'development'" class="py-2 text-center hidden md:table-cell">
                                            <div class="flex flex-col items-center gap-1">
                                                <div class="w-16 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full {{ $hasStarterBonus ? 'bg-green-500' : 'bg-amber-500' }}"
                                                         style="width: {{ min(100, ($gamePlayer->season_appearances / 15) * 100) }}%"></div>
                                                </div>
                                                <span class="text-xs {{ $hasStarterBonus ? 'text-green-600 font-medium' : 'text-slate-500' }}">
                                                    {{ $gamePlayer->season_appearances }}/15
                                                    @if($hasStarterBonus)
                                                        <svg class="w-3 h-3 inline text-green-500 -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                        {{-- Projection --}}
                                        <td x-show="viewMode === 'development'" class="py-2 text-center">
                                            @if($gamePlayer->projection > 0)
                                                <div class="flex items-center justify-center gap-1">
                                                    <span class="text-slate-500">{{ $currentAbility }}</span>
                                                    <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                    <span class="font-bold text-green-600">{{ $projectedAbility }}</span>
                                                    <span class="text-xs text-green-500">(+{{ $gamePlayer->projection }})</span>
                                                </div>
                                            @elseif($gamePlayer->projection < 0)
                                                <div class="flex items-center justify-center gap-1">
                                                    <span class="text-slate-500">{{ $currentAbility }}</span>
                                                    <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                    <span class="font-bold text-red-500">{{ $projectedAbility }}</span>
                                                    <span class="text-xs text-red-400">({{ $gamePlayer->projection }})</span>
                                                </div>
                                            @else
                                                <div class="flex items-center justify-center gap-1">
                                                    <span class="text-slate-500">{{ $currentAbility }}</span>
                                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                    <span class="font-medium text-slate-500">{{ $projectedAbility }}</span>
                                                    <span class="text-xs text-slate-400">(=)</span>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- ===== CONTRACT MODE (career only) ===== --}}
                                        @if($isCareer)
                                        {{-- Market Value --}}
                                        <td x-show="viewMode === 'contract'" class="py-2 pl-3 pr-4 text-right tabular-nums text-slate-600">{{ $gamePlayer->formatted_market_value }}</td>
                                        {{-- Wage --}}
                                        <td x-show="viewMode === 'contract'" class="py-2 pr-4 text-right tabular-nums text-slate-600 hidden md:table-cell">{{ $gamePlayer->formatted_wage }}</td>
                                        {{-- Contract expiry --}}
                                        <td x-show="viewMode === 'contract'" class="py-2 text-center text-slate-600">
                                            @if($gamePlayer->contract_until)
                                                @if($gamePlayer->isContractExpiring($seasonEndDate))
                                                    <span class="text-red-600 font-medium">{{ $gamePlayer->contract_expiry_year }}</span>
                                                @else
                                                    {{ $gamePlayer->contract_expiry_year }}
                                                @endif
                                            @endif
                                        </td>
                                        {{-- Status icons --}}
                                        <td x-show="viewMode === 'contract'" class="py-2 text-center hidden md:table-cell">
                                            @if($gamePlayer->isRetiring())
                                                <svg x-data="" x-tooltip.raw="{{ __('squad.retiring') }}" class="w-4 h-4 text-orange-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                </svg>
                                            @elseif($gamePlayer->isLoanedIn($game->team_id))
                                                <svg x-data="" x-tooltip.raw="{{ __('squad.on_loan') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                                </svg>
                                            @elseif($gamePlayer->hasPreContractAgreement())
                                                <svg x-data="" x-tooltip.raw="{{ __('squad.leaving_free') }}" class="w-4 h-4 text-red-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                </svg>
                                            @elseif($gamePlayer->hasRenewalAgreed())
                                                <svg x-data="" x-tooltip.raw="{{ __('squad.renewed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" />
                                                </svg>
                                            @elseif($gamePlayer->hasAgreedTransfer())
                                                <svg x-data="" x-tooltip.raw="{{ __('squad.sale_agreed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                </svg>
                                            @elseif($gamePlayer->hasActiveLoanSearch())
                                                <svg x-data="" x-tooltip.raw="{{ __('squad.loan_searching') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                </svg>
                                            @elseif($gamePlayer->isTransferListed())
                                                <svg x-data="" x-tooltip.raw="{{ __('squad.listed') }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="w-4 h-4 text-amber-500 mx-auto cursor-help" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M3.396 6.093a2 2 0 0 0 0 3.814 2 2 0 0 0 2.697 2.697 2 2 0 0 0 3.814 0 2.001 2.001 0 0 0 2.698-2.697 2 2 0 0 0-.001-3.814 2.001 2.001 0 0 0-2.697-2.698 2 2 0 0 0-3.814.001 2 2 0 0 0-2.697 2.697ZM6 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm3.47-1.53a.75.75 0 1 1 1.06 1.06l-4 4a.75.75 0 1 1-1.06-1.06l4-4ZM11 10a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        </td>
                                        @endif

                                        {{-- ===== STATS MODE ===== --}}
                                        {{-- Appearances --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center {{ $isTopAppearances ? 'bg-amber-50' : '' }}">
                                            <span class="{{ $gamePlayer->appearances > 0 ? 'font-medium text-slate-900' : 'text-slate-300' }}">{{ $gamePlayer->appearances > 0 ? $gamePlayer->appearances : '-' }}</span>
                                        </td>
                                        {{-- Goals --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center {{ $isTopScorer ? 'bg-amber-50' : '' }}">
                                            <span class="{{ $gamePlayer->goals > 0 ? 'font-semibold text-green-600' : 'text-slate-300' }}">{{ $gamePlayer->goals > 0 ? $gamePlayer->goals : '-' }}</span>
                                        </td>
                                        {{-- Assists --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center {{ $isTopAssister ? 'bg-amber-50' : '' }}">
                                            <span class="{{ $gamePlayer->assists > 0 ? 'font-medium text-sky-600' : 'text-slate-300' }}">{{ $gamePlayer->assists > 0 ? $gamePlayer->assists : '-' }}</span>
                                        </td>
                                        {{-- G+A --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center hidden md:table-cell {{ $isTopContributor ? 'bg-amber-50' : '' }}">
                                            <span class="{{ $contributions > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">{{ $contributions > 0 ? $contributions : '-' }}</span>
                                        </td>
                                        {{-- GPG --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center text-xs hidden md:table-cell {{ $goalsPerGame > 0 ? 'text-slate-500' : 'text-slate-300' }}">{{ $goalsPerGame > 0 ? number_format($goalsPerGame, 2) : '-' }}</td>
                                        {{-- Own Goals --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center hidden md:table-cell">
                                            <span class="{{ $gamePlayer->own_goals > 0 ? 'text-red-500 font-medium' : 'text-slate-300' }}">{{ $gamePlayer->own_goals > 0 ? $gamePlayer->own_goals : '-' }}</span>
                                        </td>
                                        {{-- Yellow Cards --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center hidden md:table-cell">
                                            @if($gamePlayer->yellow_cards > 0)
                                                <span class="inline-flex items-center gap-1 text-yellow-600 font-medium">
                                                    <span class="w-2.5 h-3.5 bg-yellow-400 rounded-sm flex-shrink-0"></span>
                                                    {{ $gamePlayer->yellow_cards }}
                                                </span>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                        {{-- Red Cards --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center hidden md:table-cell">
                                            @if($gamePlayer->red_cards > 0)
                                                <span class="inline-flex items-center gap-1 text-red-600 font-medium">
                                                    <span class="w-2.5 h-3.5 bg-red-500 rounded-sm flex-shrink-0"></span>
                                                    {{ $gamePlayer->red_cards }}
                                                </span>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                        {{-- Clean Sheets --}}
                                        <td x-show="viewMode === 'stats'" class="py-2 text-center hidden md:table-cell {{ $isTopCleanSheets ? 'bg-amber-50' : '' }}">
                                            @if($gamePlayer->position === 'Goalkeeper')
                                                <span class="{{ $gamePlayer->clean_sheets > 0 ? 'text-green-600 font-medium' : 'text-slate-300' }}">{{ $gamePlayer->clean_sheets > 0 ? $gamePlayer->clean_sheets : '0' }}</span>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>

                                        {{-- Player detail button --}}
                                        <td class="py-2 text-right">
                                            <button @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gamePlayer->id]) }}')" class="p-1.5 text-slate-400 hover:text-sky-600 rounded hover:bg-slate-100 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>

                        {{-- Squad summary (all modes except stats) --}}
                        <div x-show="viewMode !== 'stats'" class="pt-5 border-t mt-1">
                            <div class="flex flex-wrap gap-4 md:gap-8 text-sm text-slate-600">
                                <div>
                                    <span class="font-semibold text-slate-900">{{ $players->count() }}</span>
                                    <span class="text-slate-400 ml-1">{{ __('app.players') }}</span>
                                </div>
                                @if($isCareer)
                                @php $formattedWageBill = \App\Support\Money::format($players->sum('annual_wage')); @endphp
                                <div>
                                    <span class="text-slate-400">{{ __('squad.wage_bill') }}:</span>
                                    <span class="font-semibold text-slate-900">{{ $formattedWageBill }}{{ __('squad.per_year') }}</span>
                                </div>
                                @endif
                                <div class="flex items-center gap-1">
                                    <x-position-badge group="Goalkeeper" size="sm" />
                                    <span class="font-medium">{{ $goalkeepers->count() }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-position-badge group="Defender" size="sm" />
                                    <span class="font-medium">{{ $defenders->count() }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-position-badge group="Midfielder" size="sm" />
                                    <span class="font-medium">{{ $midfielders->count() }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-position-badge group="Forward" size="sm" />
                                    <span class="font-medium">{{ $forwards->count() }}</span>
                                </div>
                                <div class="border-l pl-4 md:pl-8 flex items-center gap-1">
                                    <span class="text-slate-400">{{ __('squad.avg_fitness') }}:</span>
                                    <span class="font-semibold @if($avgFitness >= 85) text-green-600 @elseif($avgFitness < 70) text-yellow-600 @else text-slate-900 @endif">{{ round($avgFitness) }}</span>
                                    @if($lowFitnessCount > 0)
                                        <span class="text-xs text-yellow-600">({{ $lowFitnessCount }} {{ __('squad.low') }})</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-slate-400">{{ __('squad.avg_morale') }}:</span>
                                    <span class="font-semibold @if($avgMorale >= 80) text-green-600 @elseif($avgMorale < 65) text-yellow-600 @else text-slate-900 @endif">{{ round($avgMorale) }}</span>
                                    @if($lowMoraleCount > 0)
                                        <span class="text-xs text-yellow-600">({{ $lowMoraleCount }} {{ __('squad.low') }})</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Stats legend (stats mode only) --}}
                        <div x-show="viewMode === 'stats'" x-cloak class="pt-5 border-t mt-1">
                            <div class="flex flex-wrap gap-4 md:gap-6 text-xs text-slate-500">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 bg-amber-50 border border-amber-200 rounded"></div>
                                    <span>{{ __('squad.top_in_squad') }}</span>
                                </div>
                                <div class="text-slate-400">{{ __('squad.click_to_sort') }}</div>
                            </div>
                        </div>

                        {{-- Development legend (development mode only) --}}
                        <div x-show="viewMode === 'development'" x-cloak class="pt-5 border-t mt-1">
                            <div class="flex flex-wrap gap-4 md:gap-6 text-xs text-slate-500">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    <span>{{ __('squad.growing') }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    <span>{{ __('squad.declining') }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                    <span>{{ __('squad.peak') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-1.5 bg-amber-500 rounded-full"></div>
                                    <span>&lt; 15 {{ __('squad.apps') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-1.5 bg-green-500 rounded-full"></div>
                                    <span>15+ {{ __('squad.apps') }} = {{ __('squad.starter_bonus') }}</span>
                                </div>
                            </div>
                        </div>

                    </div>{{-- end Alpine x-data --}}

                </div>
            </div>
        </div>
    </div>

    <x-player-detail-modal />
</x-app-layout>
