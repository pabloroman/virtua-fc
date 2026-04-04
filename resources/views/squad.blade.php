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
        <div class="max-w-7xl mx-auto px-4 pb-8">

            {{-- Sub-navigation --}}
            @if($isCareerMode)
                @php
                    $squadNavItems = [
                        ['href' => route('game.squad', $game->id), 'label' => __('squad.first_team'), 'active' => true],
                        ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false],
                        ['href' => route('game.squad.registration', $game->id), 'label' => __('squad.registration'), 'active' => false],
                    ];
                @endphp
                <x-section-nav :items="$squadNavItems" />
            @endif

            {{-- Flash Messages --}}
            <x-flash-message type="success" :message="session('success')" class="mt-4" />
            <x-flash-message type="error" :message="session('error')" class="mt-4" />

            {{-- ===== Squad Header ===== --}}
            <div class="mt-6">
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ $isCareerMode ? __('squad.first_team') : __('squad.squad') }}</h2>
            </div>

            {{-- ===== Summary Cards ===== --}}
            <div class="flex gap-2.5 overflow-x-auto scrollbar-hide mt-4 pb-1">
                <x-summary-card :label="__('squad.squad_size')" :value="$squadSize" />
                <x-summary-card :label="__('squad.avg_age')" :value="$avgAge" />
                <x-summary-card :label="__('squad.fitness_full')" :value="$avgFitness . '%'" x-data x-tooltip.raw="{{ __('squad.tooltip_fitness') }}" :value-class="$avgFitness >= 85 ? 'text-accent-green' : ($avgFitness >= 70 ? 'text-text-primary' : 'text-amber-500')" />
                <x-summary-card :label="__('squad.morale_full')" :value="$avgMorale" x-data x-tooltip.raw="{{ __('squad.tooltip_morale') }}" :value-class="$avgMorale >= 80 ? 'text-accent-green' : ($avgMorale >= 65 ? 'text-text-primary' : 'text-amber-500')" />
                <x-summary-card :label="__('squad.avg_ovr')" :value="$avgOverall" x-data x-tooltip.raw="{{ __('squad.tooltip_avg_overall') }}" :value-class="$avgOverall >= 75 ? 'text-accent-green' : ($avgOverall >= 65 ? 'text-text-primary' : 'text-amber-500')" />
                @if($isCareerMode)
                <div class="md:ml-auto flex gap-2.5 shrink-0">
                    <x-summary-card :label="__('squad.squad_value')" :value="\App\Support\Money::format($squadValue)" class="min-w-[130px]" />
                    <x-summary-card :label="__('squad.wage_bill')" :value="\App\Support\Money::format($wageBill)" class="min-w-[130px]" />
                </div>
                @endif
            </div>

            {{-- ===== Filters Bar ===== --}}
            <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                {{-- Position filter pills --}}
                <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-hide">
                    <x-pill-button size="sm" @click="posFilter = 'all'" x-bind:class="posFilter === 'all' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'" class="pos-filter">{{ __('squad.all') }}</x-pill-button>
                    <x-pill-button size="sm" @click="posFilter = 'Goalkeeper'" x-bind:class="posFilter === 'Goalkeeper' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'" class="pos-filter">{{ __('squad.goalkeepers_short') }} <span class="text-text-faint ml-0.5">{{ $goalkeepers->count() }}</span></x-pill-button>
                    <x-pill-button size="sm" @click="posFilter = 'Defender'" x-bind:class="posFilter === 'Defender' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'" class="pos-filter">{{ __('squad.defenders_short') }} <span class="text-text-faint ml-0.5">{{ $defenders->count() }}</span></x-pill-button>
                    <x-pill-button size="sm" @click="posFilter = 'Midfielder'" x-bind:class="posFilter === 'Midfielder' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'" class="pos-filter">{{ __('squad.midfielders_short') }} <span class="text-text-faint ml-0.5">{{ $midfielders->count() }}</span></x-pill-button>
                    <x-pill-button size="sm" @click="posFilter = 'Forward'" x-bind:class="posFilter === 'Forward' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'" class="pos-filter">{{ __('squad.forwards_short') }} <span class="text-text-faint ml-0.5">{{ $forwards->count() }}</span></x-pill-button>
                </div>

                {{-- Right side: availability filter + view mode + clear --}}
                <div class="flex items-center gap-2 sm:ml-auto overflow-x-auto scrollbar-hide">
                    {{-- Availability filter --}}
                    <div class="flex items-center gap-1 shrink-0">
                        <x-pill-button size="xs" @click="availFilter = availFilter === 'available' ? 'all' : 'available'" x-bind:class="availFilter === 'available' ? 'bg-accent-green/20 text-accent-green border-accent-green/30' : 'bg-surface-700 text-text-secondary hover:text-text-body border-border-default'" class="rounded-sm border">{{ __('squad.available') }}</x-pill-button>
                        <x-pill-button size="xs" @click="availFilter = availFilter === 'unavailable' ? 'all' : 'unavailable'" x-bind:class="availFilter === 'unavailable' ? 'bg-accent-red/20 text-accent-red border-accent-red/30' : 'bg-surface-700 text-text-secondary hover:text-text-body border-border-default'" class="rounded-sm border">{{ __('squad.unavailable') }}</x-pill-button>
                    </div>

                    {{-- View Mode Toggle --}}
                    <div class="flex items-center gap-0.5 bg-surface-700 rounded-lg p-0.5 shrink-0">
                        <x-pill-button size="xs" @click="viewMode = 'tactical'" x-bind:class="viewMode === 'tactical' ? 'bg-surface-800 shadow-xs text-text-primary' : 'text-text-muted hover:text-text-body'" class="rounded-md">
                            {{ __('squad.tactical') }}
                        </x-pill-button>
                        @if($isCareerMode)
                        <x-pill-button size="xs" @click="viewMode = 'planning'" x-bind:class="viewMode === 'planning' ? 'bg-surface-800 shadow-xs text-text-primary' : 'text-text-muted hover:text-text-body'" class="rounded-md">
                            {{ __('squad.planning') }}
                        </x-pill-button>
                        @endif
                        <x-pill-button size="xs" @click="viewMode = 'stats'" x-bind:class="viewMode === 'stats' ? 'bg-surface-800 shadow-xs text-text-primary' : 'text-text-muted hover:text-text-body'" class="rounded-md">
                            {{ __('squad.stats') }}
                        </x-pill-button>
                    </div>

                    {{-- Clear filters --}}
                    <x-ghost-button color="slate" size="xs" x-show="activeFilterCount() > 0" @click="clearFilters()" class="shrink-0 underline underline-offset-2">
                        {{ __('squad.clear_filters') }}
                    </x-ghost-button>

                    {{-- Desktop: Squad Analysis toggle --}}
                    <x-ghost-button color="slate" size="xs" @click="sidebarOpen = !sidebarOpen" class="hidden xl:inline-flex shrink-0 gap-1.5 border border-border-strong">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        {{ __('squad.squad_analysis') }}
                    </x-ghost-button>
                </div>
            </div>

            {{-- ===== MAIN CONTENT: Player List + Sidebar ===== --}}
            <div class="mt-4 flex gap-6">
                {{-- LEFT: Player List --}}
                <div class="flex-1 min-w-0">
                    <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">

                        {{-- Desktop table header --}}
                        <div class="hidden lg:block">
                            <div class="grid items-center px-4 py-2 bg-surface-700/30 border-b border-border-default text-[10px] text-text-muted uppercase tracking-widest font-semibold"
                                 :class="{
                                    @if($isCareerMode)
                                    'grid-cols-[1fr_48px_32px_52px_88px_88px_80px_64px_64px_56px] gap-1.5': viewMode === 'tactical',
                                    @else
                                    'grid-cols-[1fr_48px_32px_52px_88px_88px_80px] gap-1.5': viewMode === 'tactical',
                                    @endif
                                    'grid-cols-[1fr_48px_32px_52px_88px_64px_64px_56px_80px] gap-1.5': viewMode === 'planning',
                                    'grid-cols-[1fr_48px_32px_52px_48px_48px_48px_40px_48px_48px_48px_64px] gap-1.5': viewMode === 'stats',
                                 }">
                                <span>{{ __('squad.player') }}</span>
                                <span class="text-center">{{ __('squad.pos') }}</span>
                                <span class="text-center">{{ __('app.age') }}</span>
                                <span class="text-center">{{ __('squad.rating') }}</span>

                                {{-- Tactical headers --}}
                                <template x-if="viewMode === 'tactical'">
                                    <span class="text-center">{{ __('squad.fitness_full') }}</span>
                                </template>
                                <template x-if="viewMode === 'tactical'">
                                    <span class="text-center">{{ __('squad.morale_full') }}</span>
                                </template>
                                <template x-if="viewMode === 'tactical'">
                                    <span class="text-center">{{ __('squad.key_stats') }}</span>
                                </template>
                                @if($isCareerMode)
                                <template x-if="viewMode === 'tactical'">
                                    <span class="text-right">{{ __('app.value') }}</span>
                                </template>
                                <template x-if="viewMode === 'tactical'">
                                    <span class="text-right">{{ __('app.wage') }}</span>
                                </template>
                                <template x-if="viewMode === 'tactical'">
                                    <span class="text-center">{{ __('app.contract') }}</span>
                                </template>
                                @endif

                                {{-- Planning headers --}}
                                @if($isCareerMode)
                                <template x-if="viewMode === 'planning'">
                                    <span class="text-center">{{ __('squad.potential') }}</span>
                                </template>
                                <template x-if="viewMode === 'planning'">
                                    <span class="text-right">{{ __('app.value') }}</span>
                                </template>
                                <template x-if="viewMode === 'planning'">
                                    <span class="text-right">{{ __('app.wage') }}</span>
                                </template>
                                <template x-if="viewMode === 'planning'">
                                    <span class="text-center">{{ __('app.contract') }}</span>
                                </template>
                                <template x-if="viewMode === 'planning'">
                                    <span class="text-center">{{ __('squad.dev_status_label') }}</span>
                                </template>
                                @endif

                                {{-- Stats headers --}}
                                <template x-if="viewMode === 'stats'">
                                    <span class="text-center" x-data x-tooltip.raw="{{ __('squad.legend_apps') }}">{{ __('squad.apps') }}</span>
                                </template>
                                <template x-if="viewMode === 'stats'">
                                    <span class="text-center" x-data x-tooltip.raw="{{ __('squad.legend_goals') }}">{{ __('squad.goals') }}</span>
                                </template>
                                <template x-if="viewMode === 'stats'">
                                    <span class="text-center" x-data x-tooltip.raw="{{ __('squad.legend_assists') }}">{{ __('squad.assists') }}</span>
                                </template>
                                <template x-if="viewMode === 'stats'">
                                    <span class="text-center text-accent-gold" x-data x-tooltip.raw="{{ __('squad.legend_mvp') }}">{{ __('squad.mvp') }}</span>
                                </template>
                                <template x-if="viewMode === 'stats'">
                                    <span class="text-center" x-data x-tooltip.raw="{{ __('squad.clean_sheets_full') }}">{{ __('squad.clean_sheets') }}</span>
                                </template>
                                <template x-if="viewMode === 'stats'">
                                    <span class="text-center" x-data x-tooltip.raw="{{ __('squad.legend_goals') }} / {{ __('squad.legend_apps') }}">{{ __('squad.goals_per_game') }}</span>
                                </template>
                                <template x-if="viewMode === 'stats'">
                                    <span class="text-center" x-data x-tooltip.raw="{{ __('squad.legend_own_goals') }}">{{ __('squad.own_goals') }}</span>
                                </template>
                                <template x-if="viewMode === 'stats'">
                                    <span class="text-center">{{ __('squad.cards') }}</span>
                                </template>

                            </div>
                        </div>

                        {{-- Player rows --}}
                        @foreach($positionGroups as $group)
                            @if($group['players']->isNotEmpty())
                            <div x-show="posFilter === 'all' || posFilter === '{{ $group['group'] }}'">
                                {{-- Position group header --}}
                                <div class="px-4 py-2 bg-surface-700/30 border-b border-border-default">
                                    <div class="flex items-center justify-between">
                                        <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">{{ $group['label'] }}</span>
                                        <span class="text-[10px] text-text-faint">{{ $group['players']->count() }} · {{ __('squad.avg_ovr') }} {{ round($group['players']->avg('overall_score')) }}</span>
                                    </div>
                                </div>

                                @foreach($group['players'] as $gp)
                                @php
                                    $isUnavailable = $gp->is_unavailable;
                                    $unavailReason = $gp->unavailability_reason;
                                    $groupKey = $group['group'];
                                    $posAbbrev = \App\Support\PositionMapper::toAbbreviation($gp->position);

                                    $statusKey = 'none';
                                    if ($isCareerMode) {
                                        if ($gp->isContractExpiring($seasonEndDate)) $statusKey = 'expiring';
                                        elseif ($gp->isTransferListed()) $statusKey = 'listed';
                                        elseif ($gp->isLoanedIn($game->team_id)) $statusKey = 'on_loan';
                                        elseif ($gp->isRetiring()) $statusKey = 'retiring';
                                    }
                                @endphp

                                <div x-show="isVisible('{{ $groupKey }}', {{ $isUnavailable ? 'false' : 'true' }}, '{{ $statusKey }}')"
                                     class="player-row border-b border-border-default {{ $isUnavailable ? 'opacity-60' : '' }}">

                                    {{-- ===== MOBILE ROW ===== --}}
                                    <div class="lg:hidden px-4 py-3 cursor-pointer" @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')">
                                        <div class="flex items-center gap-3">
                                            {{-- Avatar with position badge --}}
                                            <x-player-avatar :name="$gp->player->name" :position-group="$groupKey" :number="$gp->number" :position-abbrev="$posAbbrev" />

                                            {{-- Name + details --}}
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-text-primary truncate">{{ $gp->player->name }}</span>
                                                    <span class="text-[10px] text-text-faint">{{ $gp->age($game->current_date) }}</span>
                                                    @include('partials.squad.player-status-icon', ['gp' => $gp, 'game' => $game, 'seasonEndDate' => $seasonEndDate])
                                                </div>
                                                <div class="flex items-center gap-3 mt-1">
                                                    @if($unavailReason)
                                                        <span class="text-[10px] text-accent-orange flex items-center gap-0.5">
                                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                                            {{ $unavailReason }}
                                                        </span>
                                                    @else
                                                        <x-fitness-bar :value="$gp->fitness" :show-label="true" :show-percentage="false" size="sm" />
                                                        @if($isCareerMode)
                                                        <span class="text-[10px] text-text-faint">{{ $gp->formatted_market_value }}</span>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Rating badge --}}
                                            <x-rating-badge :value="$gp->overall_score" class="shrink-0" />
                                        </div>

                                    </div>

                                    {{-- ===== DESKTOP ROW ===== --}}
                                    <div class="hidden lg:grid items-center px-4 py-2.5 gap-2 cursor-pointer"
                                         @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')"
                                         :class="{
                                            @if($isCareerMode)
                                            'grid-cols-[1fr_48px_32px_52px_88px_88px_80px_64px_64px_56px] gap-1.5': viewMode === 'tactical',
                                            @else
                                            'grid-cols-[1fr_48px_32px_52px_88px_88px_80px] gap-1.5': viewMode === 'tactical',
                                            @endif
                                            'grid-cols-[1fr_48px_32px_52px_88px_64px_64px_56px_80px] gap-1.5': viewMode === 'planning',
                                            'grid-cols-[1fr_48px_32px_52px_48px_48px_48px_40px_48px_48px_48px_64px] gap-1.5': viewMode === 'stats',
                                         }">

                                        {{-- Player name with avatar --}}
                                        <div class="flex items-center gap-3 min-w-0">
                                            <x-player-avatar :name="$gp->player->name" :position-group="$groupKey" :number="$gp->number" size="sm" />
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-text-primary truncate">{{ $gp->player->name }}</span>
                                                    @include('partials.squad.player-status-icon', ['gp' => $gp, 'game' => $game, 'seasonEndDate' => $seasonEndDate])
                                                </div>
                                                @if($gp->nationality_flag)
                                                <div class="flex items-center gap-1 mt-0.5">
                                                    <img src="{{ Storage::disk('assets')->url('flags/' . $gp->nationality_flag['code'] . '.svg') }}" class="w-4 h-3 rounded-sm shadow-xs" title="{{ $gp->nationality_flag['name'] }}">
                                                    @if($unavailReason)
                                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-sm bg-orange-500/10 text-[9px] text-accent-orange font-medium shrink-0">
                                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                                            {{ $unavailReason }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Position badge --}}
                                        <div class="flex justify-center">
                                            <x-position-badge :position="$gp->position" size="sm" />
                                        </div>

                                        {{-- Age --}}
                                        <span class="text-xs text-text-secondary text-center tabular-nums">{{ $gp->age($game->current_date) }}</span>

                                        {{-- Rating badge --}}
                                        <div class="flex justify-center">
                                            <x-rating-badge :value="$gp->overall_score" size="sm" />
                                        </div>

                                        {{-- === Tactical columns === --}}
                                        <template x-if="viewMode === 'tactical'">
                                            <x-fitness-bar :value="$gp->fitness" class="justify-center" />
                                        </template>
                                        <template x-if="viewMode === 'tactical'">
                                            <x-morale-indicator :value="$gp->morale" class="justify-center" />
                                        </template>
                                        <template x-if="viewMode === 'tactical'">
                                            <div class="flex items-center gap-1 justify-center">
                                                <div class="text-center">
                                                    <span class="text-[9px] text-text-faint block">{{ __('squad.technical') }}</span>
                                                    <span class="text-[11px] text-text-body font-medium">{{ $gp->technical_ability }}</span>
                                                </div>
                                                <div class="text-center">
                                                    <span class="text-[9px] text-text-faint block">{{ __('squad.physical') }}</span>
                                                    <span class="text-[11px] text-text-body font-medium">{{ $gp->physical_ability }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        @if($isCareerMode)
                                        <template x-if="viewMode === 'tactical'">
                                            <span class="text-xs text-text-body text-right tabular-nums">{{ $gp->formatted_market_value }}</span>
                                        </template>
                                        <template x-if="viewMode === 'tactical'">
                                            <span class="text-xs text-text-muted text-right tabular-nums">{{ $gp->formatted_wage }}</span>
                                        </template>
                                        <template x-if="viewMode === 'tactical'">
                                            <span class="text-[11px] text-center tabular-nums @if($gp->isContractExpiring($seasonEndDate)) text-accent-red font-medium @else text-text-muted @endif">{{ $gp->contract_expiry_year }}</span>
                                        </template>
                                        @endif

                                        {{-- === Planning columns (career only) === --}}
                                        @if($isCareerMode)
                                        <template x-if="viewMode === 'planning'">
                                            <div class="flex items-center gap-1 justify-center">
                                                <x-potential-bar
                                                    :current-ability="$gp->overall_score"
                                                    :potential-low="$gp->potential_low"
                                                    :potential-high="$gp->potential_high"
                                                    size="sm"
                                                />
                                            </div>
                                        </template>
                                        <template x-if="viewMode === 'planning'">
                                            <span class="text-xs text-text-body text-right tabular-nums">{{ $gp->formatted_market_value }}</span>
                                        </template>
                                        <template x-if="viewMode === 'planning'">
                                            <span class="text-xs text-text-muted text-right tabular-nums">{{ $gp->formatted_wage }}</span>
                                        </template>
                                        <template x-if="viewMode === 'planning'">
                                            <span class="text-[11px] text-center tabular-nums @if($gp->isContractExpiring($seasonEndDate)) text-accent-red font-medium @else text-text-muted @endif">
                                                {{ $gp->contract_expiry_year ?? '' }}
                                            </span>
                                        </template>
                                        <template x-if="viewMode === 'planning'">
                                            <div class="flex items-center justify-center">
                                                @php $ds = $gp->dev_status; @endphp
                                                <span class="shrink-0 @if($ds === 'growing') text-accent-green @elseif($ds === 'peak') text-accent-blue @else text-orange-600 @endif" x-data x-tooltip.raw="{{ __('squad.' . $ds) }}">
                                                    @if($ds === 'growing')
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                                    @elseif($ds === 'declining')
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                                    @else
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                                    @endif
                                                </span>
                                            </div>
                                        </template>
                                        @endif

                                        {{-- === Stats columns === --}}
                                        <template x-if="viewMode === 'stats'">
                                            <span class="text-xs text-text-secondary text-center tabular-nums">{{ $gp->appearances }}</span>
                                        </template>
                                        <template x-if="viewMode === 'stats'">
                                            <span class="text-xs font-medium text-center tabular-nums">{{ $gp->goals }}</span>
                                        </template>
                                        <template x-if="viewMode === 'stats'">
                                            <span class="text-xs text-text-secondary text-center tabular-nums">{{ $gp->assists }}</span>
                                        </template>
                                        <template x-if="viewMode === 'stats'">
                                            @php $mvpCount = $mvpCounts[$gp->id] ?? 0; @endphp
                                            <span class="text-xs text-center tabular-nums {{ $mvpCount > 0 ? 'font-medium text-accent-gold' : 'text-text-muted' }}">{{ $mvpCount }}</span>
                                        </template>
                                        <template x-if="viewMode === 'stats'">
                                            <span class="text-xs text-text-secondary text-center tabular-nums">{{ $gp->clean_sheets }}</span>
                                        </template>
                                        <template x-if="viewMode === 'stats'">
                                            <span class="text-xs text-text-secondary text-center tabular-nums">{{ $gp->appearances > 0 ? number_format($gp->goals / $gp->appearances, 2) : '-' }}</span>
                                        </template>
                                        <template x-if="viewMode === 'stats'">
                                            <span class="text-xs text-text-secondary text-center tabular-nums">{{ $gp->own_goals }}</span>
                                        </template>
                                        <template x-if="viewMode === 'stats'">
                                            <div class="flex items-center gap-1.5 justify-center">
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span class="w-2 h-3 bg-yellow-400 rounded-xs"></span>
                                                    <span class="text-[11px] tabular-nums text-text-secondary">{{ $gp->yellow_cards }}</span>
                                                </span>
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span class="w-2 h-3 bg-accent-red rounded-xs"></span>
                                                    <span class="text-[11px] tabular-nums text-text-secondary">{{ $gp->red_cards }}</span>
                                                </span>
                                            </div>
                                        </template>

                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        @endforeach

                    </div>{{-- end player list container --}}
                </div>

                {{-- RIGHT: Squad Analysis Sidebar (desktop only) --}}
                <div x-show="sidebarOpen" x-transition.opacity.duration.150ms class="hidden xl:block w-72 shrink-0">
                    @include('partials.squad.sidebar', [
                        'game' => $game,
                        'isCareerMode' => $isCareerMode,
                        'depthChart' => $depthChart,
                        'expiringThisSeason' => $expiringThisSeason,
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
                <x-ghost-button color="slate" @click="mobileAnalysisOpen = !mobileAnalysisOpen" class="w-full justify-between py-3 px-4 bg-surface-700/50 rounded-lg border border-border-default">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        {{ __('squad.squad_analysis') }}
                    </span>
                    <svg :class="mobileAnalysisOpen && 'rotate-180'" class="w-4 h-4 text-text-secondary transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </x-ghost-button>
                <div x-show="mobileAnalysisOpen" x-transition.opacity.duration.150ms class="mt-2">
                    @include('partials.squad.sidebar', [
                        'game' => $game,
                        'isCareerMode' => $isCareerMode,
                        'depthChart' => $depthChart,
                        'expiringThisSeason' => $expiringThisSeason,
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

    <x-player-detail-modal />
    <x-negotiation-chat-modal />
</x-app-layout>
