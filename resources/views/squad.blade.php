@php
    /** @var App\Models\Game $game */
    /** @var \Illuminate\Support\Collection $allPlayers */
    /** @var \Illuminate\Support\Collection $goalkeepers */
    /** @var \Illuminate\Support\Collection $defenders */
    /** @var \Illuminate\Support\Collection $midfielders */
    /** @var \Illuminate\Support\Collection $forwards */
    $isCareerMode = $game->isCareerMode();

    $positionGroups = [
        ['key' => 'goalkeepers', 'label' => __('squad.goalkeepers'), 'group' => 'Goalkeeper', 'players' => $goalkeepers],
        ['key' => 'defenders', 'label' => __('squad.defenders'), 'group' => 'Defender', 'players' => $defenders],
        ['key' => 'midfielders', 'label' => __('squad.midfielders'), 'group' => 'Midfielder', 'players' => $midfielders],
        ['key' => 'forwards', 'label' => __('squad.forwards'), 'group' => 'Forward', 'players' => $forwards],
    ];

    $numberAssignmentsJson = $allPlayers->mapWithKeys(fn($gp) => [
        $gp->id => [
            'number' => $gp->number,
            'name' => $gp->player->name,
        ],
    ])->toJson();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div x-data="{
        viewMode: new URLSearchParams(window.location.search).get('mode') || 'tactical',
        posFilter: 'all',
        availFilter: 'all',
        statusFilter: 'all',
        sortCol: null,
        sortDir: 'desc',
        sidebarOpen: true,

        numberAssignments: {{ Js::from(json_decode($numberAssignmentsJson, true)) }},
        numberSaving: {},
        numberErrors: {},
        numberSaved: {},

        async saveNumber(playerId, routeUrl, newValue) {
            const val = newValue === '' ? null : parseInt(newValue, 10);
            if (val === null) return;
            if (val === this.numberAssignments[playerId]?.number) return;
            this.numberSaving[playerId] = true;
            this.numberErrors[playerId] = '';
            this.numberSaved[playerId] = false;
            try {
                const response = await fetch(routeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ number: val }),
                });
                const data = await response.json();
                if (response.ok && data.success) {
                    this.numberAssignments[playerId].number = data.number;
                    this.numberSaved[playerId] = true;
                    setTimeout(() => { this.numberSaved[playerId] = false; }, 2000);
                } else {
                    this.numberErrors[playerId] = data.message || data.errors?.number?.[0] || '{{ __('squad.number_invalid') }}';
                }
            } catch {
                this.numberErrors[playerId] = '{{ __('squad.number_invalid') }}';
            }
            this.numberSaving[playerId] = false;
        },

        getNumberOwner(num) {
            for (const [id, info] of Object.entries(this.numberAssignments)) {
                if (info.number === num) return info;
            }
            return null;
        },

        isVisible(group, available, status) {
            if (this.posFilter !== 'all' && group !== this.posFilter) return false;
            if (this.availFilter === 'available' && !available) return false;
            if (this.availFilter === 'unavailable' && available) return false;
            if (this.statusFilter !== 'all' && status !== this.statusFilter) return false;
            return true;
        },
        activeFilterCount() {
            let c = 0;
            if (this.posFilter !== 'all') c++;
            if (this.availFilter !== 'all') c++;
            if (this.statusFilter !== 'all') c++;
            return c;
        },
        clearFilters() {
            this.posFilter = 'all';
            this.availFilter = 'all';
            this.statusFilter = 'all';
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-surface-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8">

                    {{-- Sub-navigation --}}
                    @php
                        $squadNavItems = [
                            ['href' => route('game.squad', $game->id), 'label' => $isCareerMode ? __('squad.first_team') : __('squad.squad'), 'active' => true],
                        ];
                        if ($isCareerMode) {
                            $squadNavItems[] = ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false];
                        }
                    @endphp
                    <x-section-nav :items="$squadNavItems" />

                    {{-- Flash Messages --}}
                    @if(session('success'))
                    <div class="mt-4 p-4 bg-accent-green/10 border border-accent-green/20 rounded-lg text-accent-green">
                        {{ session('success') }}
                    </div>
                    @endif
                    @if(session('error'))
                    <div class="mt-4 p-4 bg-accent-red/10 border border-accent-red/20 rounded-lg text-accent-red">
                        {{ session('error') }}
                    </div>
                    @endif

                    {{-- Squad trim warning --}}
                    @if($game->hasPendingAction('squad_trim') || $squadSize > \App\Modules\Transfer\Services\ContractService::MAX_SQUAD_SIZE)
                    <div class="mt-4 p-4 bg-accent-gold/10 border border-accent-gold/20 rounded-lg flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <p class="text-sm text-accent-gold font-medium">
                            {{ __('messages.squad_trim_required', [
                                'count' => $squadSize,
                                'excess' => $squadSize - \App\Modules\Transfer\Services\ContractService::MAX_SQUAD_SIZE,
                                'max' => \App\Modules\Transfer\Services\ContractService::MAX_SQUAD_SIZE,
                            ]) }}
                        </p>
                    </div>
                    @endif

                    {{-- ===== LAYER 0: Squad Dashboard KPIs ===== --}}
                    <div class="mt-6 grid grid-cols-2 {{ $isCareerMode ? 'md:grid-cols-5' : 'md:grid-cols-3' }} gap-3">
                        {{-- Squad Size --}}
                        <div class="bg-surface-700/50 rounded-lg p-3 border border-white/10">
                            <div class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ __('squad.squad_size') }}</div>
                            <div class="mt-1">
                                <span class="text-2xl font-bold text-white">{{ $squadSize }}</span>
                            </div>
                        </div>

                        {{-- Avg Age --}}
                        <div class="bg-surface-700/50 rounded-lg p-3 border border-white/10">
                            <div class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ __('squad.avg_age') }}</div>
                            <div class="mt-1">
                                <span class="text-2xl font-bold text-white">{{ $avgAge }}</span>
                            </div>
                        </div>

                        {{-- Condition: Fitness, Morale, Avg Overall --}}
                        <div class="bg-surface-700/50 rounded-lg p-3 border border-white/10">
                            <div class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ __('squad.condition') }}</div>
                            <div class="mt-1 flex items-end gap-3">
                                <div class="flex items-center gap-1 cursor-help" x-tooltip.raw="{{ __('squad.tooltip_fitness') }}">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <span class="text-xl font-bold @if($avgFitness >= 85) text-accent-green @elseif($avgFitness >= 70) text-white @else text-amber-600 @endif">{{ $avgFitness }}</span>
                                </div>
                                <div class="flex items-center gap-1 cursor-help" x-tooltip.raw="{{ __('squad.tooltip_morale') }}">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="text-xl font-bold @if($avgMorale >= 80) text-accent-green @elseif($avgMorale >= 65) text-white @else text-amber-600 @endif">{{ $avgMorale }}</span>
                                </div>
                                <div class="flex items-center gap-1 cursor-help" x-tooltip.raw="{{ __('squad.tooltip_avg_overall') }}">
                                    <span class="text-xs font-semibold text-slate-400 uppercase">{{ __('squad.avg_ovr') }}</span>
                                    <span class="text-xl font-bold @if($avgOverall >= 75) text-accent-green @elseif($avgOverall >= 65) text-white @else text-amber-600 @endif">{{ $avgOverall }}</span>
                                </div>
                            </div>
                        </div>

                        @if($isCareerMode)
                        {{-- Squad Value --}}
                        <div class="bg-surface-700/50 rounded-lg p-3 border border-white/10">
                            <div class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ __('squad.squad_value') }}</div>
                            <div class="mt-1">
                                <span class="text-2xl font-bold text-white">{{ \App\Support\Money::format($squadValue) }}</span>
                            </div>
                        </div>

                        {{-- Wage Bill --}}
                        <div class="bg-surface-700/50 rounded-lg p-3 border border-white/10">
                            <div class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ __('squad.wage_bill') }}</div>
                            <div class="mt-1">
                                <span class="text-2xl font-bold text-white">{{ \App\Support\Money::format($wageBill) }}</span>
                                <span class="text-sm text-slate-400">{{ __('squad.per_year') }}</span>
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- ===== VIEW MODES + FILTERS ===== --}}
                    <div class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        {{-- View Mode Toggle --}}
                        <div class="flex items-center overflow-x-auto scrollbar-hide gap-1 bg-surface-700 rounded-lg p-1">
                            <button @click="viewMode = 'tactical'" :class="viewMode === 'tactical' ? 'bg-surface-800 shadow-sm text-white' : 'text-slate-500 hover:text-slate-300'" class="shrink-0 px-3 py-1.5 text-sm font-medium rounded-md transition-colors min-h-[36px]">
                                {{ __('squad.tactical') }}
                            </button>
                            @if($isCareerMode)
                            <button @click="viewMode = 'planning'" :class="viewMode === 'planning' ? 'bg-surface-800 shadow-sm text-white' : 'text-slate-500 hover:text-slate-300'" class="shrink-0 px-3 py-1.5 text-sm font-medium rounded-md transition-colors min-h-[36px]">
                                {{ __('squad.planning') }}
                            </button>
                            @endif
                            <button @click="viewMode = 'stats'" :class="viewMode === 'stats' ? 'bg-surface-800 shadow-sm text-white' : 'text-slate-500 hover:text-slate-300'" class="shrink-0 px-3 py-1.5 text-sm font-medium rounded-md transition-colors min-h-[36px]">
                                {{ __('squad.stats') }}
                            </button>
                            <button @click="viewMode = 'numbers'" :class="viewMode === 'numbers' ? 'bg-surface-800 shadow-sm text-white' : 'text-slate-500 hover:text-slate-300'" class="shrink-0 px-3 py-1.5 text-sm font-medium rounded-md transition-colors min-h-[36px]">
                                {{ __('squad.numbers') }}
                            </button>
                        </div>

                        {{-- Filters --}}
                        <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                            {{-- Position filter --}}
                            <div class="flex items-center gap-1 shrink-0">
                                <button @click="posFilter = 'all'" :class="posFilter === 'all' ? 'bg-slate-800 text-white' : 'bg-surface-700 text-slate-400 hover:bg-slate-200'" class="px-2 py-1 text-xs font-medium rounded transition-colors">{{ __('squad.all') }}</button>
                                <button @click="posFilter = 'Goalkeeper'" :class="posFilter === 'Goalkeeper' ? 'bg-accent-gold/100 text-white' : 'bg-surface-700 text-slate-400 hover:bg-slate-200'" class="px-2 py-1 text-xs font-medium rounded transition-colors">{{ __('squad.goalkeepers_short') }}</button>
                                <button @click="posFilter = 'Defender'" :class="posFilter === 'Defender' ? 'bg-blue-600 text-white' : 'bg-surface-700 text-slate-400 hover:bg-slate-200'" class="px-2 py-1 text-xs font-medium rounded transition-colors">{{ __('squad.defenders_short') }}</button>
                                <button @click="posFilter = 'Midfielder'" :class="posFilter === 'Midfielder' ? 'bg-emerald-600 text-white' : 'bg-surface-700 text-slate-400 hover:bg-slate-200'" class="px-2 py-1 text-xs font-medium rounded transition-colors">{{ __('squad.midfielders_short') }}</button>
                                <button @click="posFilter = 'Forward'" :class="posFilter === 'Forward' ? 'bg-red-600 text-white' : 'bg-surface-700 text-slate-400 hover:bg-slate-200'" class="px-2 py-1 text-xs font-medium rounded transition-colors">{{ __('squad.forwards_short') }}</button>
                            </div>

                            {{-- Availability filter --}}
                            <div class="flex items-center gap-1 shrink-0 border-l border-white/10 pl-2">
                                <button @click="availFilter = availFilter === 'available' ? 'all' : 'available'" :class="availFilter === 'available' ? 'bg-green-600 text-white' : 'bg-surface-700 text-slate-400 hover:bg-slate-200'" class="px-2 py-1 text-xs font-medium rounded transition-colors">{{ __('squad.available') }}</button>
                                <button @click="availFilter = availFilter === 'unavailable' ? 'all' : 'unavailable'" :class="availFilter === 'unavailable' ? 'bg-red-600 text-white' : 'bg-surface-700 text-slate-400 hover:bg-slate-200'" class="px-2 py-1 text-xs font-medium rounded transition-colors">{{ __('squad.unavailable') }}</button>
                            </div>

                            {{-- Clear filters --}}
                            <button x-show="activeFilterCount() > 0" @click="clearFilters()" class="shrink-0 text-xs text-slate-400 hover:text-slate-400 underline underline-offset-2">
                                {{ __('squad.clear_filters') }}
                            </button>

                            {{-- Desktop: Squad Analysis toggle --}}
                            <button @click="sidebarOpen = !sidebarOpen" class="hidden xl:inline-flex shrink-0 ml-auto items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded border border-white/10 text-slate-400 hover:bg-surface-700/50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                {{ __('squad.squad_analysis') }}
                            </button>
                        </div>
                    </div>

                    {{-- ===== MAIN CONTENT: Table + Sidebar ===== --}}
                    <div class="mt-4 flex gap-6">
                        {{-- LEFT: Player Table/Cards --}}
                        <div class="flex-1 min-w-0">
                            {{-- ===== DESKTOP TABLE ===== --}}
                            <div class="hidden md:block overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-left border-b border-white/10 bg-surface-800">
                                        <tr>
                                            <th class="font-semibold py-2 pl-3 w-10"></th>
                                            <th class="py-2"></th>

                                            {{-- Tactical headers --}}
                                            <template x-if="viewMode === 'tactical'">
                                                <th class="font-semibold py-2 text-center w-16">{{ __('squad.technical_full') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'tactical'">
                                                <th class="font-semibold py-2 text-center w-16">{{ __('squad.physical_full') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'tactical'">
                                                <th class="font-semibold py-2 text-center w-16">{{ __('squad.fitness_full') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'tactical'">
                                                <th class="font-semibold py-2 text-center w-16">{{ __('squad.morale_full') }}</th>
                                            </template>

                                            {{-- Planning headers --}}
                                            @if($isCareerMode)
                                            <template x-if="viewMode === 'planning'">
                                                <th class="font-semibold py-2 text-right w-10">{{ __('app.age') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'planning'">
                                                <th class="font-semibold py-2 text-right w-20 pr-2">{{ __('app.value') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'planning'">
                                                <th class="font-semibold py-2 text-right w-20 pr-2">{{ __('app.wage') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'planning'">
                                                <th class="font-semibold py-2 text-right w-20">{{ __('app.contract') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'planning'">
                                                <th class="font-semibold py-2 text-center w-24">{{ __('squad.potential') }}</th>
                                            </template>
                                            @endif

                                            {{-- Stats headers --}}
                                            <template x-if="viewMode === 'stats'">
                                                <th class="font-semibold py-2 text-center w-10 cursor-help" x-data x-tooltip.raw="{{ __('squad.legend_apps') }}">{{ __('squad.apps') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'stats'">
                                                <th class="font-semibold py-2 text-center w-10 cursor-help" x-data x-tooltip.raw="{{ __('squad.legend_goals') }}">{{ __('squad.goals') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'stats'">
                                                <th class="font-semibold py-2 text-center w-10 cursor-help" x-data x-tooltip.raw="{{ __('squad.legend_assists') }}">{{ __('squad.assists') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'stats'">
                                                <th class="font-semibold py-2 text-center w-10 cursor-help" x-data x-tooltip.raw="{{ __('squad.clean_sheets_full') }}">{{ __('squad.clean_sheets') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'stats'">
                                                <th class="font-semibold py-2 text-center w-12 cursor-help" x-data x-tooltip.raw="{{ __('squad.legend_goals') }} / {{ __('squad.legend_apps') }}">{{ __('squad.goals_per_game') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'stats'">
                                                <th class="font-semibold py-2 text-center w-10 cursor-help" x-data x-tooltip.raw="{{ __('squad.legend_own_goals') }}">{{ __('squad.own_goals') }}</th>
                                            </template>
                                            <template x-if="viewMode === 'stats'">
                                                <th class="font-semibold py-2 text-center w-16">{{ __('squad.cards') }}</th>
                                            </template>

                                            {{-- Numbers header --}}
                                            <template x-if="viewMode === 'numbers'">
                                                <th class="font-semibold py-2 text-center w-20">#</th>
                                            </template>

                                            <th class="py-2 pr-3 w-12"></th>
                                        </tr>
                                    </thead>
                                    @foreach($positionGroups as $group)
                                        @if($group['players']->isNotEmpty())
                                        {{-- Group header --}}
                                        <tbody x-show="posFilter === 'all' || posFilter === '{{ $group['group'] }}'">
                                            <tr class="bg-surface-700">
                                                <td colspan="20" class="py-2 px-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ $group['label'] }}</span>
                                                        <span class="text-xs text-slate-400">({{ $group['players']->count() }})</span>
                                                        <span class="text-xs text-slate-400 ml-auto">{{ __('squad.avg_ovr') }} {{ round($group['players']->avg('overall_score')) }}</span>
                                                    </div>
                                                </td>
                                            </tr>

                                            @foreach($group['players'] as $gp)
                                            @php
                                                $isUnavailable = $gp->is_unavailable;
                                                $unavailReason = $gp->unavailability_reason;
                                                $groupKey = $group['group'];

                                                // Determine status key for filtering
                                                $statusKey = 'none';
                                                if ($isCareerMode) {
                                                    if ($gp->isContractExpiring($seasonEndDate)) $statusKey = 'expiring';
                                                    elseif ($gp->isTransferListed()) $statusKey = 'listed';
                                                    elseif ($gp->isLoanedIn($game->team_id)) $statusKey = 'on_loan';
                                                    elseif ($gp->isRetiring()) $statusKey = 'retiring';
                                                }
                                            @endphp
                                            <tr x-show="isVisible('{{ $groupKey }}', {{ $isUnavailable ? 'false' : 'true' }}, '{{ $statusKey }}')"
                                                class="border-b border-white/5 hover:bg-surface-700/50 transition-colors {{ $isUnavailable ? 'opacity-60' : '' }}">

                                                {{-- Position --}}
                                                <td class="py-2.5 pl-3 w-10">
                                                    <x-position-badge :position="$gp->position" :tooltip="\App\Support\PositionMapper::toDisplayName($gp->position)" class="cursor-help" />
                                                </td>

                                                {{-- Name + status + detail icon --}}
                                                <td class="py-2.5 pl-2 pr-2">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <button @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')" class="p-1 text-slate-300 rounded hover:text-slate-500 transition-colors shrink-0">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                                <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                        @if($gp->nationality_flag)
                                                            <img src="/flags/{{ $gp->nationality_flag['code'] }}.svg" class="w-5 h-3.5 rounded shadow-sm shrink-0" title="{{ $gp->nationality_flag['name'] }}">
                                                        @endif
                                                        <div class="min-w-0">
                                                            <div class="font-medium text-white truncate">{{ $gp->player->name }}</div>
                                                            @if($unavailReason)
                                                                <div class="text-xs text-red-500 truncate">{{ $unavailReason }}</div>
                                                            @endif
                                                        </div>
                                                        {{-- Status icons --}}
                                                        @include('partials.squad.player-status-icon', ['gp' => $gp, 'game' => $game])
                                                    </div>
                                                </td>

                                                {{-- === Tactical columns === --}}
                                                <template x-if="viewMode === 'tactical'">
                                                    <td class="px-2 w-16">
                                                        <x-ability-bar :value="$gp->technical_ability" size="sm" class="text-xs font-medium justify-center @if($gp->technical_ability >= 80) text-accent-green @elseif($gp->technical_ability >= 70) text-lime-600 @elseif($gp->technical_ability < 60) text-slate-400 @endif" />
                                                    </td>
                                                </template>
                                                <template x-if="viewMode === 'tactical'">
                                                    <td class="px-2 w-16">
                                                        <x-ability-bar :value="$gp->physical_ability" size="sm" class="text-xs font-medium justify-center @if($gp->physical_ability >= 80) text-accent-green @elseif($gp->physical_ability >= 70) text-lime-600 @elseif($gp->physical_ability < 60) text-slate-400 @endif" />
                                                    </td>
                                                </template>
                                                <template x-if="viewMode === 'tactical'">
                                                    <td class="px-2 w-16">
                                                        <x-ability-bar :value="$gp->fitness" :max="100" size="sm" class="text-xs font-medium justify-center @if($gp->fitness >= 90) text-accent-green @elseif($gp->fitness >= 80) text-lime-600 @elseif($gp->fitness < 70) text-amber-600 @endif" />
                                                    </td>
                                                </template>
                                                <template x-if="viewMode === 'tactical'">
                                                    <td class="px-2 pr-4 w-16">
                                                        <x-ability-bar :value="$gp->morale" :max="100" size="sm" class="text-xs font-medium justify-center @if($gp->morale >= 85) text-accent-green @elseif($gp->morale >= 75) text-lime-600 @elseif($gp->morale < 65) text-amber-600 @endif" />
                                                    </td>
                                                </template>

                                                {{-- === Planning columns (career only) === --}}
                                                @if($isCareerMode)
                                                <template x-if="viewMode === 'planning'">
                                                    <td class="py-2.5 text-right w-10 tabular-nums text-slate-400 pr-2">{{ $gp->age($game->current_date) }}</td>
                                                </template>
                                                <template x-if="viewMode === 'planning'">
                                                    <td class="py-2.5 text-right w-16 tabular-nums text-slate-400 pr-2">{{ $gp->formatted_market_value }}</td>
                                                </template>
                                                <template x-if="viewMode === 'planning'">
                                                    <td class="py-2.5 text-right w-16 tabular-nums text-slate-400 pr-2">{{ $gp->formatted_wage }}</td>
                                                </template>
                                                <template x-if="viewMode === 'planning'">
                                                    <td class="py-2.5 text-right w-16 tabular-nums pr-2">
                                                        @if($gp->contract_until)
                                                            <span class="@if($gp->isContractExpiring($seasonEndDate)) text-accent-red font-medium @else text-slate-400 @endif">{{ $gp->contract_expiry_year }}</span>
                                                        @endif
                                                    </td>
                                                </template>
                                                <template x-if="viewMode === 'planning'">
                                                    <td class="px-2 w-20">
                                                        <div class="flex items-center gap-1">
                                                            <x-potential-bar
                                                                :current-ability="$gp->overall_score"
                                                                :potential-low="$gp->potential_low"
                                                                :potential-high="$gp->potential_high"
                                                                size="sm"
                                                            />
                                                            @php $ds = $gp->dev_status; @endphp
                                                            <span class="shrink-0 @if($ds === 'growing') text-accent-green @elseif($ds === 'peak') text-accent-blue @else text-orange-600 @endif" x-data x-tooltip.raw="{{ __('squad.' . $ds) }}">
                                                                @if($ds === 'growing')
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                                                @elseif($ds === 'declining')
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                                                @else
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                                                @endif
                                                            </span>
                                                        </div>
                                                    </td>
                                                </template>
                                                @endif

                                                {{-- === Stats columns === --}}
                                                <template x-if="viewMode === 'stats'">
                                                    <td class="py-2.5 text-center w-10 tabular-nums text-slate-400">{{ $gp->appearances }}</td>
                                                </template>
                                                <template x-if="viewMode === 'stats'">
                                                    <td class="py-2.5 text-center w-10 tabular-nums font-medium">{{ $gp->goals }}</td>
                                                </template>
                                                <template x-if="viewMode === 'stats'">
                                                    <td class="py-2.5 text-center w-10 tabular-nums text-slate-400">{{ $gp->assists }}</td>
                                                </template>
                                                <template x-if="viewMode === 'stats'">
                                                    <td class="py-2.5 text-center w-10 tabular-nums text-slate-400">{{ $gp->clean_sheets }}</td>
                                                </template>
                                                <template x-if="viewMode === 'stats'">
                                                    <td class="py-2.5 text-center w-12 tabular-nums text-slate-400">{{ $gp->appearances > 0 ? number_format($gp->goals / $gp->appearances, 2) : '-' }}</td>
                                                </template>
                                                <template x-if="viewMode === 'stats'">
                                                    <td class="py-2.5 text-center w-10 tabular-nums text-slate-400">{{ $gp->own_goals }}</td>
                                                </template>
                                                <template x-if="viewMode === 'stats'">
                                                    <td class="py-2.5 text-center w-16">
                                                        <span class="inline-flex items-center gap-1">
                                                            <span class="w-2 h-3 bg-yellow-400 rounded-sm"></span>
                                                            <span class="text-xs tabular-nums">{{ $gp->yellow_cards }}</span>
                                                            <span class="w-2 h-3 bg-accent-red/100 rounded-sm ml-0.5"></span>
                                                            <span class="text-xs tabular-nums">{{ $gp->red_cards }}</span>
                                                        </span>
                                                    </td>
                                                </template>

                                                {{-- === Numbers column === --}}
                                                <template x-if="viewMode === 'numbers'">
                                                    <td class="py-2.5 w-36" x-data="{ localVal: numberAssignments['{{ $gp->id }}']?.number ?? '' }">
                                                        <div class="flex items-center gap-1.5 justify-center">
                                                            {{-- Status indicator (left of input) --}}
                                                            <div class="w-4 shrink-0 flex items-center justify-center">
                                                                <svg x-show="numberSaved['{{ $gp->id }}']" x-transition.opacity class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                                <svg x-show="numberErrors['{{ $gp->id }}']" class="w-4 h-4 text-red-500 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" :title="numberErrors['{{ $gp->id }}']"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            </div>
                                                            <input type="number" min="1" max="99"
                                                                x-model="localVal"
                                                                @blur="saveNumber('{{ $gp->id }}', '{{ route('game.squad.number', [$game->id, $gp->id]) }}', localVal)"
                                                                @keydown.enter.prevent="$el.blur()"
                                                                :disabled="numberSaving['{{ $gp->id }}']"
                                                                class="w-14 h-8 text-sm font-medium text-center border rounded tabular-nums focus:ring-2 focus:ring-accent-blue focus:border-accent-blue [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                                :class="numberErrors['{{ $gp->id }}'] ? 'border-red-300 bg-accent-red/10' : 'border-white/10'">
                                                        </div>
                                                    </td>
                                                </template>

                                                {{-- Overall (always visible) --}}
                                                <td class="py-2.5 pr-3 text-center w-12">
                                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-semibold
                                                        @if($gp->overall_score >= 80) bg-emerald-500 text-white
                                                        @elseif($gp->overall_score >= 70) bg-lime-500 text-white
                                                        @elseif($gp->overall_score >= 60) bg-accent-gold/100 text-white
                                                        @else bg-slate-300 text-slate-300
                                                        @endif">{{ $gp->overall_score }}</span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        @endif
                                    @endforeach

                                </table>
                            </div>

                            {{-- ===== MOBILE CARDS ===== --}}
                            <div class="md:hidden space-y-2">
                                @foreach($positionGroups as $group)
                                    @if($group['players']->isNotEmpty())
                                    <div x-show="posFilter === 'all' || posFilter === '{{ $group['group'] }}'">
                                        {{-- Group label --}}
                                        <div class="flex items-center gap-2 py-2 px-1">
                                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $group['label'] }} ({{ $group['players']->count() }})</span>
                                        </div>

                                        @foreach($group['players'] as $gp)
                                        @php
                                            $isUnavailable = $gp->is_unavailable;
                                            $unavailReason = $gp->unavailability_reason;
                                            $groupKey = $group['group'];
                                            $statusKey = 'none';
                                            if ($isCareerMode) {
                                                if ($gp->isContractExpiring($seasonEndDate)) $statusKey = 'expiring';
                                                elseif ($gp->isTransferListed()) $statusKey = 'listed';
                                                elseif ($gp->isLoanedIn($game->team_id)) $statusKey = 'on_loan';
                                                elseif ($gp->isRetiring()) $statusKey = 'retiring';
                                            }
                                        @endphp
                                        <div x-show="isVisible('{{ $groupKey }}', {{ $isUnavailable ? 'false' : 'true' }}, '{{ $statusKey }}')"
                                             class="{{ $isUnavailable ? 'opacity-60' : '' }}">
                                            {{-- Default card (all modes except numbers) --}}
                                            <button x-show="viewMode !== 'numbers'"
                                                    @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')"
                                                    class="w-full text-left p-3 rounded-lg border border-white/10 bg-surface-800 hover:bg-surface-700/50 transition-colors">
                                                <div class="flex items-center gap-2.5">
                                                    <x-position-badge :position="$gp->position" />
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="font-medium text-white truncate">{{ $gp->player->name }}</span>
                                                            @include('partials.squad.player-status-icon', ['gp' => $gp, 'game' => $game])
                                                        </div>
                                                        <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-2 flex-wrap">
                                                            @if($unavailReason)
                                                                <span class="text-red-500">{{ $unavailReason }}</span>
                                                            @else
                                                                {{-- Context-dependent second line --}}
                                                                <template x-if="viewMode === 'tactical'">
                                                                    <span>{{ __('squad.technical') }} {{ $gp->technical_ability }} &middot; {{ __('squad.physical') }} {{ $gp->physical_ability }} &middot; {{ __('squad.fitness') }} {{ $gp->fitness }}</span>
                                                                </template>
                                                                @if($isCareerMode)
                                                                <template x-if="viewMode === 'planning'">
                                                                    <span>
                                                                        {{ $gp->formatted_market_value }} &middot; {{ $gp->formatted_wage }}{{ __('squad.per_year') }} &middot; {{ $gp->contract_expiry_year ?? '?' }}
                                                                        @if($gp->potential_low && $gp->potential_high)
                                                                            &middot; {{ __('squad.potential') }} {{ $gp->potential_low }}-{{ $gp->potential_high }}
                                                                        @endif
                                                                    </span>
                                                                </template>
                                                                @endif
                                                                <template x-if="viewMode === 'stats'">
                                                                    <span>{{ $gp->appearances }} {{ __('squad.apps') }} &middot; {{ $gp->goals }}{{ __('squad.goals') }} {{ $gp->assists }}{{ __('squad.assists') }}</span>
                                                                </template>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <span class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-bold
                                                        @if($gp->overall_score >= 80) bg-emerald-500 text-white
                                                        @elseif($gp->overall_score >= 70) bg-lime-500 text-white
                                                        @elseif($gp->overall_score >= 60) bg-accent-gold/100 text-white
                                                        @else bg-slate-300 text-slate-300
                                                        @endif">{{ $gp->overall_score }}</span>
                                                </div>
                                            </button>
                                            {{-- Numbers mode card with inline input --}}
                                            <div x-show="viewMode === 'numbers'" class="p-3 rounded-lg border border-white/10 bg-surface-800" x-data="{ localVal: numberAssignments['{{ $gp->id }}']?.number ?? '' }">
                                                <div class="flex items-center gap-2.5">
                                                    <x-position-badge :position="$gp->position" />
                                                    <span class="flex-1 font-medium text-white truncate min-w-0">{{ $gp->player->name }}</span>
                                                    <div class="flex items-center gap-1 shrink-0">
                                                        <div class="w-4 flex items-center justify-center">
                                                            <svg x-show="numberSaved['{{ $gp->id }}']" x-transition.opacity class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                            <svg x-show="numberErrors['{{ $gp->id }}']" class="w-4 h-4 text-red-500 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24" :title="numberErrors['{{ $gp->id }}']"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        </div>
                                                        <input type="number" min="1" max="99"
                                                            x-model="localVal"
                                                            @blur="saveNumber('{{ $gp->id }}', '{{ route('game.squad.number', [$game->id, $gp->id]) }}', localVal)"
                                                            @keydown.enter.prevent="$el.blur()"
                                                            :disabled="numberSaving['{{ $gp->id }}']"
                                                            class="w-14 h-9 text-sm font-medium text-center border rounded tabular-nums focus:ring-2 focus:ring-accent-blue focus:border-accent-blue [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                            :class="numberErrors['{{ $gp->id }}'] ? 'border-red-300 bg-accent-red/10' : 'border-white/10'">
                                                    </div>
                                                    <span class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-bold
                                                        @if($gp->overall_score >= 80) bg-emerald-500 text-white
                                                        @elseif($gp->overall_score >= 70) bg-lime-500 text-white
                                                        @elseif($gp->overall_score >= 60) bg-accent-gold/100 text-white
                                                        @else bg-slate-300 text-slate-300
                                                        @endif">{{ $gp->overall_score }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- RIGHT: Squad Analysis Sidebar (desktop only) --}}
                        <div x-show="sidebarOpen" x-transition.opacity.duration.150ms class="hidden xl:block w-72 shrink-0">
                            @include('partials.squad.sidebar', [
                                'game' => $game,
                                'isCareerMode' => $isCareerMode,
                                'depthChart' => $depthChart,
                                'expiringThisSeason' => $expiringThisSeason,
                                'expiringNextSeason' => $expiringNextSeason,
                                'highEarners' => $highEarners,
                                'alerts' => $alerts,
                                'youngCount' => $youngCount,
                                'primeCount' => $primeCount,
                                'veteranCount' => $veteranCount,
                                'squadSize' => $squadSize,
                            ])
                        </div>
                    </div>

                    {{-- Mobile Squad Analysis (collapsible) --}}
                    <div class="xl:hidden mt-6" x-data="{ mobileAnalysisOpen: false }">
                        <button @click="mobileAnalysisOpen = !mobileAnalysisOpen" class="w-full flex items-center justify-between py-3 px-4 bg-surface-700/50 rounded-lg border border-white/10 text-sm font-medium text-slate-300">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                {{ __('squad.squad_analysis') }}
                            </span>
                            <svg :class="mobileAnalysisOpen && 'rotate-180'" class="w-4 h-4 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="mobileAnalysisOpen" x-transition.opacity.duration.150ms class="mt-2">
                            @include('partials.squad.sidebar', [
                                'game' => $game,
                                'isCareerMode' => $isCareerMode,
                                'depthChart' => $depthChart,
                                'expiringThisSeason' => $expiringThisSeason,
                                'expiringNextSeason' => $expiringNextSeason,
                                'highEarners' => $highEarners,
                                'alerts' => $alerts,
                                'youngCount' => $youngCount,
                                'primeCount' => $primeCount,
                                'veteranCount' => $veteranCount,
                                'squadSize' => $squadSize,
                            ])
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <x-player-detail-modal />
</x-app-layout>
