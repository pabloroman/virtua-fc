@php
    /** @var App\Models\Game $game **/

    // Stats: compute top performers for highlighting
    $maxGoals = $players->max('goals');
    $maxAssists = $players->max('assists');
    $maxContributions = $players->max('goal_contributions');
    $maxAppearances = $players->max('appearances');
    $maxCleanSheets = $players->where('position', 'Goalkeeper')->max('clean_sheets');

    $isCareer = $game->isCareerMode();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8" x-data="{
                    tab: 'skills',
                    sorts: {
                        skills:      { col: 'position', asc: true },
                        development: { col: 'position', asc: true },
                        stats:       { col: 'position', asc: true },
                        contract:    { col: 'position', asc: true },
                    },
                    sort(col) {
                        const s = this.sorts[this.tab];
                        s.asc = s.col === col ? !s.asc : (col === 'name' ? true : false);
                        s.col = col;
                        const tbody = this.$refs[this.tab + 'Body'];
                        const rows = Array.from(tbody.querySelectorAll('.player-row'));
                        rows.sort((a, b) => {
                            let aVal = a.dataset[col];
                            let bVal = b.dataset[col];
                            if (!isNaN(parseFloat(aVal)) && !isNaN(parseFloat(bVal))) {
                                aVal = parseFloat(aVal);
                                bVal = parseFloat(bVal);
                            }
                            if (aVal < bVal) return s.asc ? -1 : 1;
                            if (aVal > bVal) return s.asc ? 1 : -1;
                            return 0;
                        });
                        rows.forEach(r => tbody.appendChild(r));
                    }
                }">

                    {{-- Tab navigation --}}
                    <div class="flex border-b border-slate-200 mb-0 overflow-x-auto scrollbar-hide">
                        <button @click="tab = 'skills'" :class="tab === 'skills' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="shrink-0 px-4 py-2.5 min-h-[44px] text-sm font-medium border-b-2 transition-colors">
                            {{ __('squad.skills') }}
                        </button>
                        <button @click="tab = 'development'" :class="tab === 'development' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="shrink-0 px-4 py-2.5 min-h-[44px] text-sm font-medium border-b-2 transition-colors">
                            {{ __('squad.development') }}
                        </button>
                        <button @click="tab = 'stats'" :class="tab === 'stats' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="shrink-0 px-4 py-2.5 min-h-[44px] text-sm font-medium border-b-2 transition-colors">
                            {{ __('squad.stats') }}
                        </button>
                        @if($isCareer)
                        <button @click="tab = 'contract'" :class="tab === 'contract' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="shrink-0 px-4 py-2.5 min-h-[44px] text-sm font-medium border-b-2 transition-colors">
                            {{ __('squad.contract') }}
                        </button>
                        @endif
                        @if($isCareer)
                        <a href="{{ route('game.squad.academy', $game->id) }}" class="shrink-0 px-4 py-2.5 min-h-[44px] text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors ml-auto flex items-center gap-1">
                            {{ __('squad.academy') }}
                            @if($academyCount > 0)
                                <span class="px-1.5 py-0.5 text-[10px] font-bold bg-red-600 text-white rounded-full">{{ $academyCount }}</span>
                            @endif
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                        @endif
                    </div>

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

                    {{-- ============================================================ --}}
                    {{-- SKILLS TAB                                                    --}}
                    {{-- ============================================================ --}}
                    <div x-show="tab === 'skills'" x-cloak>
                        <x-squad-table tbody-ref="skillsBody">
                            <x-slot name="headRow">
                                <tr>
                                    <th class="font-semibold py-2 w-10 cursor-pointer hover:text-sky-600 select-none" @click="sort('position')">
                                        <span class="flex items-center justify-center gap-1">
                                            <span x-show="sorts.skills.col === 'position'" x-text="sorts.skills.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-8 text-slate-400 hidden md:table-cell">#</th>
                                    <th class="font-semibold py-2 w-1/2 cursor-pointer hover:text-sky-600 select-none" @click="sort('name')">
                                        <span class="flex items-center gap-1">
                                            {{ __('app.name') }}
                                            <span x-show="sorts.skills.col === 'name'" x-text="sorts.skills.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="py-2 w-6"></th>
                                    <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.country') }}</th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('age')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.age') }}
                                            <span x-show="sorts.skills.col === 'age'" x-text="sorts.skills.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="py-2"></th>
                                    <th class="font-semibold py-2 pl-3 text-center w-10 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('technical')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.technical') }}
                                            <span x-show="sorts.skills.col === 'technical'" x-text="sorts.skills.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-10 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('physical')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.physical') }}
                                            <span x-show="sorts.skills.col === 'physical'" x-text="sorts.skills.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-10 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('fitness')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.fitness') }}
                                            <span x-show="sorts.skills.col === 'fitness'" x-text="sorts.skills.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-10 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('morale')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.morale') }}
                                            <span x-show="sorts.skills.col === 'morale'" x-text="sorts.skills.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-10 cursor-pointer hover:text-sky-600 select-none" @click="sort('overall')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.overall') }}
                                            <span x-show="sorts.skills.col === 'overall'" x-text="sorts.skills.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                </tr>
                            </x-slot>

                            @foreach($players as $gamePlayer)
                                @php
                                    $nextMatchday = $game->current_matchday + 1;
                                    $isUnavailable = !$gamePlayer->isAvailable($game->current_date, $nextMatchday);
                                    $unavailabilityReason = $gamePlayer->getUnavailabilityReason($game->current_date, $nextMatchday);
                                @endphp
                                <tr class="border-b border-slate-200 hover:bg-slate-50 player-row"
                                    data-position="{{ \App\Modules\Lineup\Services\LineupService::positionSortOrder($gamePlayer->position) }}"
                                    data-name="{{ strtolower($gamePlayer->name) }}"
                                    data-age="{{ $gamePlayer->age }}"
                                    data-technical="{{ $gamePlayer->technical_ability }}"
                                    data-physical="{{ $gamePlayer->physical_ability }}"
                                    data-fitness="{{ $gamePlayer->fitness }}"
                                    data-morale="{{ $gamePlayer->morale }}"
                                    data-overall="{{ $gamePlayer->overall_score }}">

                                    <x-squad-table-row :game="$game" :player="$gamePlayer" :unavailable="$isUnavailable">
                                        @if($unavailabilityReason)
                                        <x-slot name="nameExtra">
                                            <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                        </x-slot>
                                        @endif

                                        <x-slot name="status">
                                            @if($isCareer)
                                                @if($gamePlayer->isRetiring())
                                                    <svg title="{{ __('squad.retiring') }}" class="w-4 h-4 text-orange-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                    </svg>
                                                @elseif($gamePlayer->isLoanedIn($game->team_id))
                                                    <svg title="{{ __('squad.on_loan') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                                    </svg>
                                                @elseif($gamePlayer->hasPreContractAgreement())
                                                    <svg title="{{ __('squad.leaving_free') }}" class="w-4 h-4 text-red-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                    </svg>
                                                @elseif($gamePlayer->hasRenewalAgreed())
                                                    <svg title="{{ __('squad.renewed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" />
                                                    </svg>
                                                @elseif($gamePlayer->hasAgreedTransfer())
                                                    <svg title="{{ __('squad.sale_agreed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                    </svg>
                                                @elseif($gamePlayer->hasActiveLoanSearch())
                                                    <svg title="{{ __('squad.loan_searching') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                    </svg>
                                                @elseif($gamePlayer->isTransferListed())
                                                    <svg title="{{ __('squad.listed') }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="w-4 h-4 text-amber-500 mx-auto cursor-help" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M3.396 6.093a2 2 0 0 0 0 3.814 2 2 0 0 0 2.697 2.697 2 2 0 0 0 3.814 0 2.001 2.001 0 0 0 2.698-2.697 2 2 0 0 0-.001-3.814 2.001 2.001 0 0 0-2.697-2.698 2 2 0 0 0-3.814.001 2 2 0 0 0-2.697 2.697ZM6 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm3.47-1.53a.75.75 0 1 1 1.06 1.06l-4 4a.75.75 0 1 1-1.06-1.06l4-4ZM11 10a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                            @endif
                                        </x-slot>
                                    </x-squad-table-row>

                                    {{-- Technical --}}
                                    <td class="border-l border-slate-200 py-2 pl-3 text-center hidden md:table-cell">
                                        <x-ability-bar :value="$gamePlayer->technical_ability" size="sm" class="text-xs font-medium justify-center @if($gamePlayer->technical_ability >= 80) text-green-600 @elseif($gamePlayer->technical_ability >= 70) text-lime-600 @elseif($gamePlayer->technical_ability < 60) text-slate-400 @endif" />
                                    </td>
                                    {{-- Physical --}}
                                    <td class="py-2 text-center hidden md:table-cell">
                                        <x-ability-bar :value="$gamePlayer->physical_ability" size="sm" class="text-xs font-medium justify-center @if($gamePlayer->physical_ability >= 80) text-green-600 @elseif($gamePlayer->physical_ability >= 70) text-lime-600 @elseif($gamePlayer->physical_ability < 60) text-slate-400 @endif" />
                                    </td>
                                    {{-- Fitness --}}
                                    <td class="py-2 text-center hidden md:table-cell">
                                        <span class="@if($gamePlayer->fitness >= 90) text-green-600 @elseif($gamePlayer->fitness >= 80) text-lime-600 @elseif($gamePlayer->fitness < 50) text-red-500 font-medium @elseif($gamePlayer->fitness < 70) text-yellow-600 @endif">
                                            {{ $gamePlayer->fitness }}
                                        </span>
                                    </td>
                                    {{-- Morale --}}
                                    <td class="py-2 text-center hidden md:table-cell">
                                        <span class="@if($gamePlayer->morale >= 85) text-green-600 @elseif($gamePlayer->morale >= 75) text-lime-600 @elseif($gamePlayer->morale < 50) text-red-500 font-medium @elseif($gamePlayer->morale < 65) text-yellow-600 @endif">
                                            {{ $gamePlayer->morale }}
                                        </span>
                                    </td>
                                    {{-- Overall --}}
                                    <td class="py-2 text-center">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold
                                            @if($gamePlayer->overall_score >= 80) bg-emerald-500 text-white
                                            @elseif($gamePlayer->overall_score >= 70) bg-lime-500 text-white
                                            @elseif($gamePlayer->overall_score >= 60) bg-amber-500 text-white
                                            @else bg-slate-300 text-slate-700
                                            @endif">
                                            {{ $gamePlayer->overall_score }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </x-squad-table>
                    </div>

                    {{-- ============================================================ --}}
                    {{-- DEVELOPMENT TAB                                               --}}
                    {{-- ============================================================ --}}
                    <div x-show="tab === 'development'" x-cloak>
                        <x-squad-table tbody-ref="developmentBody">
                            <x-slot name="headRow">
                                <tr>
                                    <th class="font-semibold py-2 w-10 cursor-pointer hover:text-sky-600 select-none" @click="sort('position')">
                                        <span class="flex items-center justify-center gap-1">
                                            <span x-show="sorts.development.col === 'position'" x-text="sorts.development.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-8 text-slate-400 hidden md:table-cell">#</th>
                                    <th class="font-semibold py-2 w-1/2 cursor-pointer hover:text-sky-600 select-none" @click="sort('name')">
                                        <span class="flex items-center gap-1">
                                            {{ __('app.name') }}
                                            <span x-show="sorts.development.col === 'name'" x-text="sorts.development.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="py-2 w-6"></th>
                                    <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.country') }}</th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('age')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.age') }}
                                            <span x-show="sorts.development.col === 'age'" x-text="sorts.development.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="py-2"></th>
                                    <th class="font-semibold py-2 pl-3 w-44">{{ __('squad.ability') }}</th>
                                    <th class="font-semibold py-2 text-center w-24 cursor-pointer hover:text-sky-600 select-none" @click="sort('devstatus')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.status') }}
                                            <span x-show="sorts.development.col === 'devstatus'" x-text="sorts.development.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-24 hidden md:table-cell">{{ __('squad.playing_time') }}</th>
                                    <th class="font-semibold py-2 text-center w-32 cursor-pointer hover:text-sky-600 select-none" @click="sort('projection')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.projection') }}
                                            <span x-show="sorts.development.col === 'projection'" x-text="sorts.development.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                </tr>
                            </x-slot>

                            @foreach($players as $player)
                                @php
                                    $currentAbility = (int) round(($player->current_technical_ability + $player->current_physical_ability) / 2);
                                    $potentialGap = $player->potential_high ? $player->potential_high - $currentAbility : 0;
                                    $hasStarterBonus = $player->season_appearances >= 15;
                                    $projectedAbility = $currentAbility + ($player->projection ?? 0);
                                    $ageClass = $player->age <= 23 ? 'text-green-600' : ($player->age >= 30 ? 'text-orange-500' : '');
                                    $devStatusOrder = match($player->development_status) { 'growing' => 0, 'peak' => 1, default => 2 };
                                @endphp
                                <tr class="border-b border-slate-200 hover:bg-slate-50 player-row"
                                    data-position="{{ \App\Modules\Lineup\Services\LineupService::positionSortOrder($player->position) }}"
                                    data-name="{{ strtolower($player->name) }}"
                                    data-age="{{ $player->age }}"
                                    data-devstatus="{{ $devStatusOrder }}"
                                    data-projection="{{ $player->projection ?? 0 }}">

                                    <x-squad-table-row :game="$game" :player="$player" :age-class="$ageClass" />

                                    {{-- Ability: potential bar --}}
                                    <td class="py-3 pl-3">
                                        <x-potential-bar
                                            :current-ability="$currentAbility"
                                            :potential-low="$player->potential_low"
                                            :potential-high="$player->potential_high"
                                            :projection="$player->projection"
                                        />
                                    </td>

                                    {{-- Status badge --}}
                                    <td class="py-3 text-center">
                                        @if($player->development_status === 'growing')
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                                {{ __('squad.growing') }}
                                            </span>
                                        @elseif($player->development_status === 'peak')
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
                                    <td class="py-3 text-center hidden md:table-cell">
                                        <div class="flex flex-col items-center gap-1">
                                            <div class="w-16 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full {{ $hasStarterBonus ? 'bg-green-500' : 'bg-amber-500' }}"
                                                     style="width: {{ min(100, ($player->season_appearances / 15) * 100) }}%"></div>
                                            </div>
                                            <span class="text-xs {{ $hasStarterBonus ? 'text-green-600 font-medium' : 'text-slate-500' }}"
                                                  title="{{ $hasStarterBonus ? __('squad.qualifies_starter_bonus') : __('squad.needs_appearances', ['count' => 15]) }}">
                                                {{ $player->season_appearances }}/15
                                                @if($hasStarterBonus)
                                                    <svg class="w-3 h-3 inline text-green-500 -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                @endif
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Projection --}}
                                    <td class="py-3 text-center">
                                        @if($player->projection > 0)
                                            <div class="flex items-center justify-center gap-1">
                                                <span class="text-slate-500">{{ $currentAbility }}</span>
                                                <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                <span class="font-bold text-green-600">{{ $projectedAbility }}</span>
                                                <span class="text-xs text-green-500">(+{{ $player->projection }})</span>
                                            </div>
                                        @elseif($player->projection < 0)
                                            <div class="flex items-center justify-center gap-1">
                                                <span class="text-slate-500">{{ $currentAbility }}</span>
                                                <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                <span class="font-bold text-red-500">{{ $projectedAbility }}</span>
                                                <span class="text-xs text-red-400">({{ $player->projection }})</span>
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
                                </tr>
                            @endforeach
                        </x-squad-table>

                        {{-- Legend --}}
                        <div class="mt-8 pt-6 border-t">
                            <div class="flex flex-wrap gap-6 text-xs text-slate-500">
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
                                    <div class="w-8 h-2 bg-sky-100 rounded-full border border-sky-200"></div>
                                    <span>{{ __('squad.potential_range') }}</span>
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
                    </div>

                    {{-- ============================================================ --}}
                    {{-- STATS TAB                                                     --}}
                    {{-- ============================================================ --}}
                    <div x-show="tab === 'stats'" x-cloak>
                        <x-squad-table :sticky="true" tbody-ref="statsBody">
                            <x-slot name="headRow">
                                <tr>
                                    <th class="font-semibold py-2 w-10 sticky left-0 bg-white z-10 cursor-pointer hover:text-sky-600 select-none" @click="sort('position')">
                                        <span class="flex items-center justify-center gap-1">
                                            <span x-show="sorts.stats.col === 'position'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-8 text-slate-400 hidden md:table-cell">#</th>
                                    <th class="font-semibold py-2 w-1/2 cursor-pointer hover:text-sky-600 select-none sticky left-10 bg-white z-10" @click="sort('name')">
                                        <span class="flex items-center gap-1">
                                            {{ __('app.name') }}
                                            <span x-show="sorts.stats.col === 'name'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="py-2 w-6"></th>
                                    <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.country') }}</th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('age')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.age') }}
                                            <span x-show="sorts.stats.col === 'age'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="py-2"></th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sort('appearances')" title="{{ __('squad.appearances') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.apps') }}
                                            <span x-show="sorts.stats.col === 'appearances'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sort('goals')" title="{{ __('squad.legend_goals') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goals') }}
                                            <span x-show="sorts.stats.col === 'goals'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none" @click="sort('assists')" title="{{ __('squad.legend_assists') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.assists') }}
                                            <span x-show="sorts.stats.col === 'assists'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('contributions')" title="{{ __('squad.legend_contributions') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goal_contributions') }}
                                            <span x-show="sorts.stats.col === 'contributions'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-14 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('gpg')" title="{{ __('squad.goals_per_game') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.goals_per_game') }}
                                            <span x-show="sorts.stats.col === 'gpg'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('own_goals')" title="{{ __('squad.legend_own_goals') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.own_goals') }}
                                            <span x-show="sorts.stats.col === 'own_goals'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('yellow')" title="{{ __('squad.yellow_cards') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            <span class="w-3 h-4 bg-yellow-400 rounded-sm"></span>
                                            <span x-show="sorts.stats.col === 'yellow'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('red')" title="{{ __('squad.red_cards') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            <span class="w-3 h-4 bg-red-500 rounded-sm"></span>
                                            <span x-show="sorts.stats.col === 'red'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('clean_sheets')" title="{{ __('squad.legend_clean_sheets') }}">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.clean_sheets') }}
                                            <span x-show="sorts.stats.col === 'clean_sheets'" x-text="sorts.stats.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                </tr>
                            </x-slot>

                            @foreach($players as $player)
                                @php
                                    $contributions = $player->goal_contributions;
                                    $goalsPerGame = $player->goals_per_game;
                                    $isTopScorer = $maxGoals > 0 && $player->goals === $maxGoals;
                                    $isTopAssister = $maxAssists > 0 && $player->assists === $maxAssists;
                                    $isTopContributor = $maxContributions > 0 && $contributions === $maxContributions;
                                    $isTopAppearances = $maxAppearances > 0 && $player->appearances === $maxAppearances;
                                    $isTopCleanSheets = $maxCleanSheets > 0 && $player->position === 'Goalkeeper' && $player->clean_sheets === $maxCleanSheets;
                                @endphp
                                <tr class="border-b border-slate-200 hover:bg-slate-50 player-row"
                                    data-position="{{ \App\Modules\Lineup\Services\LineupService::positionSortOrder($player->position) }}"
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

                                    <x-squad-table-row :game="$game" :player="$player" :sticky="true" />

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
                                    <td class="py-2.5 text-center hidden md:table-cell {{ $isTopContributor ? 'bg-amber-50' : '' }}">
                                        <span class="{{ $contributions > 0 ? 'font-semibold text-slate-900' : 'text-slate-300' }}">{{ $contributions > 0 ? $contributions : '-' }}</span>
                                    </td>
                                    {{-- Goals per Game --}}
                                    <td class="py-2.5 text-center text-xs hidden md:table-cell {{ $goalsPerGame > 0 ? 'text-slate-500' : 'text-slate-300' }}">{{ $goalsPerGame > 0 ? number_format($goalsPerGame, 2) : '-' }}</td>
                                    {{-- Own Goals --}}
                                    <td class="py-2.5 text-center hidden md:table-cell">
                                        <span class="{{ $player->own_goals > 0 ? 'text-red-500 font-medium' : 'text-slate-300' }}">{{ $player->own_goals > 0 ? $player->own_goals : '-' }}</span>
                                    </td>
                                    {{-- Yellow Cards --}}
                                    <td class="py-2.5 text-center hidden md:table-cell">
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
                                    <td class="py-2.5 text-center hidden md:table-cell">
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
                                    <td class="py-2.5 text-center hidden md:table-cell {{ $isTopCleanSheets ? 'bg-amber-50' : '' }}">
                                        @if($player->position === 'Goalkeeper')
                                            <span class="{{ $player->clean_sheets > 0 ? 'text-green-600 font-medium' : 'text-slate-300' }}">{{ $player->clean_sheets > 0 ? $player->clean_sheets : '0' }}</span>
                                        @else
                                            <span class="text-slate-300">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-squad-table>

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

                    {{-- ============================================================ --}}
                    {{-- CONTRACT TAB (Career mode only)                               --}}
                    {{-- ============================================================ --}}
                    @if($isCareer)
                    <div x-show="tab === 'contract'" x-cloak>
                        <x-squad-table tbody-ref="contractBody">
                            <x-slot name="headRow">
                                <tr>
                                    <th class="font-semibold py-2 w-10 cursor-pointer hover:text-sky-600 select-none" @click="sort('position')">
                                        <span class="flex items-center justify-center gap-1">
                                            <span x-show="sorts.contract.col === 'position'" x-text="sorts.contract.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-8 text-slate-400 hidden md:table-cell">#</th>
                                    <th class="font-semibold py-2 w-1/2 cursor-pointer hover:text-sky-600 select-none" @click="sort('name')">
                                        <span class="flex items-center gap-1">
                                            {{ __('app.name') }}
                                            <span x-show="sorts.contract.col === 'name'" x-text="sorts.contract.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="py-2 w-6"></th>
                                    <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.country') }}</th>
                                    <th class="font-semibold py-2 text-center w-12 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('age')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.age') }}
                                            <span x-show="sorts.contract.col === 'age'" x-text="sorts.contract.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="py-2"></th>
                                    <th class="font-semibold py-2 text-center w-10 cursor-pointer hover:text-sky-600 select-none" @click="sort('overall')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('squad.overall') }}
                                            <span x-show="sorts.contract.col === 'overall'" x-text="sorts.contract.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 pl-3 pr-4 text-right border-l border-slate-200 w-24 cursor-pointer hover:text-sky-600 select-none" @click="sort('value')">
                                        <span class="flex items-center justify-end gap-1">
                                            {{ __('app.value') }}
                                            <span x-show="sorts.contract.col === 'value'" x-text="sorts.contract.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 pr-4 text-right w-24 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('wage')">
                                        <span class="flex items-center justify-end gap-1">
                                            {{ __('app.wage') }}
                                            <span x-show="sorts.contract.col === 'wage'" x-text="sorts.contract.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                    <th class="font-semibold py-2 text-center w-20 cursor-pointer hover:text-sky-600 select-none hidden md:table-cell" @click="sort('contract')">
                                        <span class="flex items-center justify-center gap-1">
                                            {{ __('app.contract') }}
                                            <span x-show="sorts.contract.col === 'contract'" x-text="sorts.contract.asc ? '↑' : '↓'" class="text-sky-600"></span>
                                        </span>
                                    </th>
                                </tr>
                            </x-slot>

                            @foreach($players as $gamePlayer)
                                @php
                                    $nextMatchday = $game->current_matchday + 1;
                                    $isUnavailable = !$gamePlayer->isAvailable($game->current_date, $nextMatchday);
                                    $unavailabilityReason = $gamePlayer->getUnavailabilityReason($game->current_date, $nextMatchday);
                                @endphp
                                <tr class="border-b border-slate-200 hover:bg-slate-50 player-row {{ $gamePlayer->contract_until && $gamePlayer->isContractExpiring($seasonEndDate) ? 'bg-red-50/50' : '' }}"
                                    data-position="{{ \App\Modules\Lineup\Services\LineupService::positionSortOrder($gamePlayer->position) }}"
                                    data-name="{{ strtolower($gamePlayer->name) }}"
                                    data-age="{{ $gamePlayer->age }}"
                                    data-overall="{{ $gamePlayer->overall_score }}"
                                    data-value="{{ $gamePlayer->market_value ?? 0 }}"
                                    data-wage="{{ $gamePlayer->annual_wage ?? 0 }}"
                                    data-contract="{{ $gamePlayer->contract_until ? $gamePlayer->contract_until->year : 0 }}">

                                    <x-squad-table-row :game="$game" :player="$gamePlayer" :unavailable="$isUnavailable">
                                        @if($unavailabilityReason)
                                        <x-slot name="nameExtra">
                                            <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                        </x-slot>
                                        @endif

                                        <x-slot name="status">
                                            @if($gamePlayer->isRetiring())
                                                <svg title="{{ __('squad.retiring') }}" class="w-4 h-4 text-orange-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                </svg>
                                            @elseif($gamePlayer->isLoanedIn($game->team_id))
                                                <svg title="{{ __('squad.on_loan') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                                </svg>
                                            @elseif($gamePlayer->hasPreContractAgreement())
                                                <svg title="{{ __('squad.leaving_free') }}" class="w-4 h-4 text-red-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                </svg>
                                            @elseif($gamePlayer->hasRenewalAgreed())
                                                <svg title="{{ __('squad.renewed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" />
                                                </svg>
                                            @elseif($gamePlayer->hasAgreedTransfer())
                                                <svg title="{{ __('squad.sale_agreed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                </svg>
                                            @elseif($gamePlayer->hasActiveLoanSearch())
                                                <svg title="{{ __('squad.loan_searching') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                </svg>
                                            @elseif($gamePlayer->isTransferListed())
                                                <svg title="{{ __('squad.listed') }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="w-4 h-4 text-amber-500 mx-auto cursor-help" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M3.396 6.093a2 2 0 0 0 0 3.814 2 2 0 0 0 2.697 2.697 2 2 0 0 0 3.814 0 2.001 2.001 0 0 0 2.698-2.697 2 2 0 0 0-.001-3.814 2.001 2.001 0 0 0-2.697-2.698 2 2 0 0 0-3.814.001 2 2 0 0 0-2.697 2.697ZM6 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm3.47-1.53a.75.75 0 1 1 1.06 1.06l-4 4a.75.75 0 1 1-1.06-1.06l4-4ZM11 10a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        </x-slot>
                                    </x-squad-table-row>

                                    {{-- Overall --}}
                                    <td class="py-2 text-center">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold
                                            @if($gamePlayer->overall_score >= 80) bg-emerald-500 text-white
                                            @elseif($gamePlayer->overall_score >= 70) bg-lime-500 text-white
                                            @elseif($gamePlayer->overall_score >= 60) bg-amber-500 text-white
                                            @else bg-slate-300 text-slate-700
                                            @endif">
                                            {{ $gamePlayer->overall_score }}
                                        </span>
                                    </td>
                                    {{-- Market Value --}}
                                    <td class="border-l border-slate-200 py-2 pl-3 pr-4 text-right tabular-nums text-slate-600">{{ $gamePlayer->formatted_market_value }}</td>
                                    {{-- Annual Wage --}}
                                    <td class="py-2 pr-4 text-right tabular-nums text-slate-600 hidden md:table-cell">{{ $gamePlayer->formatted_wage }}</td>
                                    {{-- Contract --}}
                                    <td class="py-2 text-center text-slate-600 hidden md:table-cell">
                                        @if($gamePlayer->contract_until)
                                            @if($gamePlayer->isContractExpiring($seasonEndDate))
                                                <span class="text-red-600 font-medium" title="Contract expiring">
                                                    {{ $gamePlayer->contract_expiry_year }}
                                                </span>
                                            @else
                                                {{ $gamePlayer->contract_expiry_year }}
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-squad-table>
                    </div>
                    @endif

                    {{-- Squad summary --}}
                    @php
                        $avgFitness = $players->avg('fitness');
                        $avgMorale = $players->avg('morale');
                        $lowFitnessCount = $players->filter(fn($p) => $p->fitness < 70)->count();
                        $lowMoraleCount = $players->filter(fn($p) => $p->morale < 65)->count();
                        $grouped = $players->groupBy('position_group');
                    @endphp
                    <div class="pt-6 border-t">
                        <div class="flex flex-wrap gap-8 text-sm text-slate-600">
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
                                <span class="font-medium">{{ $grouped->get('Goalkeeper', collect())->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-position-badge group="Defender" size="sm" />
                                <span class="font-medium">{{ $grouped->get('Defender', collect())->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-position-badge group="Midfielder" size="sm" />
                                <span class="font-medium">{{ $grouped->get('Midfielder', collect())->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-position-badge group="Forward" size="sm" />
                                <span class="font-medium">{{ $grouped->get('Forward', collect())->count() }}</span>
                            </div>
                            <div class="border-l pl-8 flex items-center gap-1">
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

                </div>
            </div>
        </div>
    </div>

    <x-player-detail-modal />
</x-app-layout>
