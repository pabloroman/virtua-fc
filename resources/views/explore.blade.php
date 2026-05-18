@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('app.transfers') }}</h2>
        </div>

        {{-- Flash Messages --}}
        <x-flash-message type="success" :message="session('success')" class="mb-4" />

        @include('partials.transfers-header')

                    {{-- Tab Navigation --}}
                    <x-section-nav :items="[
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => false],
                        ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => false],
                        ['href' => route('game.explore', $game->id), 'label' => __('transfers.explore_tab'), 'active' => true],
                        ['href' => route('game.transfers.market', $game->id), 'label' => __('transfers.market_tab'), 'active' => false],
                    ]" />

                    {{-- Explorer Content --}}
                    <div class="mt-6"
                         x-data="explore({
                             initialFilters: @js($initialFilters),
                             searchMode: @js((bool) $searchMode),
                             competitions: @js($competitions),
                             pools: @js($pools),
                             freeAgentCount: @js((int) $freeAgentCount),
                             assetUrl: @js(rtrim(Storage::disk('assets')->url(''), '/')),
                             gameId: @js($game->id),
                             initialTeam: @js($initialTeam ?? null),
                             initialCompetitionId: @js($initialCompetitionId ?? null),
                             initialPoolId: @js($initialPoolId ?? null),
                             labels: {
                                 freeAgents: @js(__('transfers.explore_free_agents')),
                                 leagueKind: @js(__('transfers.league')),
                                 poolKind: @js(__('transfers.explore_pool_picker_label')),
                                 freeAgentsKind: @js(__('transfers.explore_free_agents')),
                                 searchKind: @js(__('transfers.explore_search_scope_label')),
                                 searchScope: @js(__('transfers.explore_search_scope_label')),
                                 positionAll: @js(__('transfers.explore_filter_all')),
                                 positionGk: @js(__('transfers.explore_goalkeepers')),
                                 positionDef: @js(__('transfers.explore_defenders')),
                                 positionMid: @js(__('transfers.explore_midfielders')),
                                 positionFwd: @js(__('transfers.explore_forwards')),
                             },
                         })">

                        <form method="GET" action="{{ route('game.explore', $game->id) }}" @submit="searching = true">
                            {{-- Scope picker + Search bar + Advanced-filter toggle, on a single row from sm: upwards --}}
                            <div class="flex flex-col sm:flex-row gap-2 mb-3">
                                {{-- Scope picker (dropdown). Replaces the previous
                                     pill bar, which became too wide once foreign
                                     leagues + EUR + INT were included. The selected
                                     scope is summarised in a single trigger button;
                                     the panel groups leagues, transfer pools, and
                                     free agents so users can see every option at
                                     once instead of scrolling. --}}
                                <div class="relative w-full sm:w-72 shrink-0" @click.outside="scopePickerOpen = false" @keydown.escape.window="scopePickerOpen = false">
                                    <button type="button"
                                            @click="scopePickerOpen = !scopePickerOpen"
                                            :class="{
                                                'border-accent-blue/40': viewMode === 'competition' && scopePickerOpen,
                                                'border-accent-gold/40': viewMode === 'pool' && scopePickerOpen,
                                                'border-accent-green/40': viewMode === 'freeAgents' && scopePickerOpen,
                                                'border-border-strong': scopePickerOpen,
                                                'border-border-default hover:border-border-strong': !scopePickerOpen,
                                            }"
                                            class="w-full flex items-center gap-2 px-3 py-2.5 rounded-lg border bg-surface-800 text-left min-h-[44px] transition-colors">
                                        {{-- Glyph priority: search (search mode) > emoji (INT pool) > flag (leagues + EUR) > free-agents icon --}}
                                        <template x-if="activeScope.icon === 'search'">
                                            <svg class="w-5 h-5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                        </template>
                                        <template x-if="!activeScope.icon && activeScope.emoji">
                                            <span class="text-lg leading-none shrink-0" x-text="activeScope.emoji"></span>
                                        </template>
                                        <template x-if="!activeScope.icon && !activeScope.emoji && activeScope.flag">
                                            <img :src="assetUrl + '/flags/' + activeScope.flag + '.svg'" class="w-6 h-4 rounded-xs shadow-xs shrink-0" :alt="activeScope.label">
                                        </template>
                                        <template x-if="!activeScope.icon && !activeScope.emoji && !activeScope.flag">
                                            <svg class="w-5 h-5 text-accent-green shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </template>

                                        <span class="flex-1 text-sm font-medium text-text-primary truncate" x-text="activeScope.label"></span>
                                        <span x-show="activeScope.count !== null" class="text-xs px-1.5 py-0.5 rounded-full bg-surface-700 text-text-muted shrink-0" x-text="activeScope.count"></span>
                                        <svg class="w-4 h-4 text-text-muted shrink-0 transition-transform" :class="scopePickerOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    <div x-show="scopePickerOpen" x-cloak x-transition.opacity.duration.150ms
                                         class="absolute z-30 mt-1 left-0 right-0 sm:right-auto sm:w-80 max-h-[70vh] overflow-y-auto rounded-lg border border-border-strong bg-surface-800 shadow-xl">

                                        {{-- Leagues group --}}
                                        <template x-if="competitions.length > 0">
                                            <div class="py-1">
                                                <div class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.league') }}</div>
                                                <template x-for="comp in competitions" :key="comp.id">
                                                    <button type="button"
                                                            @click="selectCompetition(comp); scopePickerOpen = false"
                                                            :class="viewMode === 'competition' && selectedCompetition?.id === comp.id
                                                                ? 'bg-accent-blue/10 text-accent-blue'
                                                                : 'text-text-primary hover:bg-surface-700/60'"
                                                            class="w-full flex items-center gap-3 px-3 py-2 text-left transition-colors min-h-[44px]">
                                                        <template x-if="comp.flag">
                                                            <img :src="assetUrl + '/flags/' + comp.flag + '.svg'" class="w-5 h-3.5 rounded-xs shadow-xs shrink-0" :alt="comp.country">
                                                        </template>
                                                        <span class="flex-1 text-sm truncate" x-text="comp.name"></span>
                                                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-surface-700 text-text-muted shrink-0" x-text="comp.teamCount"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Transfer pools group (Europe / Resto del mundo) --}}
                                        @if(count($pools) > 0)
                                        <div class="border-t border-border-default py-1">
                                            <div class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.explore_pool_picker_label') }}</div>
                                            <template x-for="pool in pools" :key="pool.id">
                                                <button type="button"
                                                        @click="selectPool(pool); scopePickerOpen = false"
                                                        :class="viewMode === 'pool' && activePoolId === pool.id
                                                            ? 'bg-accent-gold/10 text-accent-gold'
                                                            : 'text-text-primary hover:bg-surface-700/60'"
                                                        class="w-full flex items-center gap-3 px-3 py-2 text-left transition-colors min-h-[44px]">
                                                    <template x-if="pool.emoji">
                                                        <span class="w-5 text-center text-base leading-none shrink-0" x-text="pool.emoji"></span>
                                                    </template>
                                                    <template x-if="!pool.emoji && pool.flag">
                                                        <img :src="assetUrl + '/flags/' + pool.flag + '.svg'" class="w-5 h-3.5 rounded-xs shadow-xs shrink-0" :alt="pool.label">
                                                    </template>
                                                    <span class="flex-1 text-sm truncate" x-text="pool.label"></span>
                                                    <span class="text-xs px-1.5 py-0.5 rounded-full bg-surface-700 text-text-muted shrink-0" x-text="pool.count"></span>
                                                </button>
                                            </template>
                                        </div>
                                        @endif

                                        {{-- Free agents --}}
                                        @if($freeAgentCount > 0)
                                        <div class="border-t border-border-default py-1">
                                            <div class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.explore_free_agents') }}</div>
                                            <button type="button"
                                                    @click="selectFreeAgents(); scopePickerOpen = false"
                                                    :class="viewMode === 'freeAgents'
                                                        ? 'bg-accent-green/10 text-accent-green'
                                                        : 'text-text-primary hover:bg-surface-700/60'"
                                                    class="w-full flex items-center gap-3 px-3 py-2 text-left transition-colors min-h-[44px]">
                                                <svg class="w-5 h-5 shrink-0" :class="viewMode === 'freeAgents' ? 'text-accent-green' : 'text-text-muted'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span class="flex-1 text-sm truncate">{{ __('transfers.explore_free_agents') }}</span>
                                                <span class="text-xs px-1.5 py-0.5 rounded-full bg-surface-700 text-text-muted shrink-0">{{ $freeAgentCount }}</span>
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text"
                                           name="query"
                                           x-model="searchQuery"
                                           :placeholder="@js(__('transfers.explore_search_placeholder'))"
                                           class="w-full pl-10 pr-10 py-2.5 bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary placeholder-text-muted focus:outline-none focus:border-accent-blue/50 focus:ring-1 focus:ring-accent-blue/30 min-h-[44px]">
                                    <button type="button" x-show="searchQuery.length > 0"
                                            @click="searchQuery = ''"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-text-muted hover:text-text-primary">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <button type="button" @click="filtersOpen = !filtersOpen"
                                        :class="activeFilterCount > 0 || filtersOpen ? 'bg-accent-blue/10 border-accent-blue/30 text-accent-blue' : 'bg-surface-700 border-border-default text-text-body hover:border-border-strong'"
                                        class="shrink-0 inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium min-h-[44px] transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                    </svg>
                                    <span>{{ __('transfers.explore_advanced_filters') }}</span>
                                    <span x-show="activeFilterCount > 0" x-text="activeFilterCount"
                                          class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-accent-blue/20 text-[10px] font-semibold"></span>
                                    <svg class="w-4 h-4 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Advanced filter panel --}}
                            <div x-show="filtersOpen" x-cloak x-transition class="mb-5 p-4 rounded-lg bg-surface-800 border border-border-default">
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-5">
                                    {{-- Position (specific + group) --}}
                                    <label class="flex flex-col gap-1">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.position_required', ['*' => '']) }}</span>
                                        <select name="position" x-model="filters.position"
                                                class="bg-surface-700 border border-border-default rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-accent-blue/50 min-h-[40px]">
                                            <option value="">{{ __('transfers.explore_filter_all') }}</option>
                                            <optgroup label="{{ __('transfers.position_groups') }}">
                                                <option value="gk">{{ __('transfers.explore_goalkeepers') }}</option>
                                                <option value="def">{{ __('transfers.explore_defenders') }}</option>
                                                <option value="mid">{{ __('transfers.explore_midfielders') }}</option>
                                                <option value="fwd">{{ __('transfers.explore_forwards') }}</option>
                                            </optgroup>
                                            <optgroup label="{{ __('transfers.specific_positions') }}">
                                                @foreach(\App\Support\PositionMapper::getFilterOptions() as $code => $key)
                                                    <option value="{{ $code }}">{{ __("positions.{$key}_label") }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                    </label>

                                    {{-- Competition --}}
                                    <label class="flex flex-col gap-1">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.league') }}</span>
                                        <select name="competition_id" x-model="filters.competition_id"
                                                class="bg-surface-700 border border-border-default rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-accent-blue/50 min-h-[40px]">
                                            <option value="">{{ __('transfers.explore_filter_all') }}</option>
                                            <template x-for="comp in competitions" :key="comp.id">
                                                <option :value="comp.id" x-text="comp.name"></option>
                                            </template>
                                        </select>
                                    </label>

                                    {{-- Nationality --}}
                                    <label class="flex flex-col gap-1">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.explore_nationality') }}</span>
                                        <select name="nationality" x-model="filters.nationality"
                                                class="bg-surface-700 border border-border-default rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-accent-blue/50 min-h-[40px]">
                                            <option value="">{{ __('transfers.explore_filter_all') }}</option>
                                            @foreach($nationalities as $nat)
                                                <option value="{{ $nat }}">{{ __("countries.{$nat}") }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    {{-- Age range (dual slider) --}}
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.age_range') }}</span>
                                            <span class="text-xs font-semibold text-text-primary" x-text="ageMin + ' – ' + ageMax"></span>
                                        </div>
                                        <div class="dual-range">
                                            <div class="track"></div>
                                            <div class="track-fill" :style="'left:' + ageTrackLeft() + ';width:' + ageTrackWidth()"></div>
                                            <input type="range" :min="AGE_MIN_BOUND" :max="AGE_MAX_BOUND" step="1" x-model.number="ageMin" @input="enforceAgeMin()">
                                            <input type="range" :min="AGE_MIN_BOUND" :max="AGE_MAX_BOUND" step="1" x-model.number="ageMax" @input="enforceAgeMax()">
                                        </div>
                                        <input type="hidden" name="min_age" :value="ageMin > AGE_MIN_BOUND ? ageMin : ''">
                                        <input type="hidden" name="max_age" :value="ageMax < AGE_MAX_BOUND ? ageMax : ''">
                                    </div>

                                    {{-- Overall range (dual slider) --}}
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.explore_overall_range') }}</span>
                                            <span class="text-xs font-semibold text-text-primary" x-text="overallMin + ' – ' + overallMax"></span>
                                        </div>
                                        <div class="dual-range">
                                            <div class="track"></div>
                                            <div class="track-fill" :style="'left:' + overallTrackLeft() + ';width:' + overallTrackWidth()"></div>
                                            <input type="range" :min="OVERALL_MIN_BOUND" :max="OVERALL_MAX_BOUND" step="1" x-model.number="overallMin" @input="enforceOverallMin()">
                                            <input type="range" :min="OVERALL_MIN_BOUND" :max="OVERALL_MAX_BOUND" step="1" x-model.number="overallMax" @input="enforceOverallMax()">
                                        </div>
                                        <input type="hidden" name="min_overall" :value="overallMin > OVERALL_MIN_BOUND ? overallMin : ''">
                                        <input type="hidden" name="max_overall" :value="overallMax < OVERALL_MAX_BOUND ? overallMax : ''">
                                    </div>

                                    {{-- Market value range (stepped dual slider) --}}
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.value_range') }}</span>
                                            <span class="text-xs font-semibold text-text-primary" x-text="formatValue(valueMin()) + ' – ' + formatValue(valueMax())"></span>
                                        </div>
                                        <div class="dual-range">
                                            <div class="track"></div>
                                            <div class="track-fill" :style="'left:' + valueTrackLeft() + ';width:' + valueTrackWidth()"></div>
                                            <input type="range" min="0" :max="valueSteps.length - 1" step="1" x-model.number="valueStepMin" @input="enforceValueMin()">
                                            <input type="range" min="0" :max="valueSteps.length - 1" step="1" x-model.number="valueStepMax" @input="enforceValueMax()">
                                        </div>
                                        <input type="hidden" name="min_value" :value="valueStepMin > 0 ? valueMin() : ''">
                                        <input type="hidden" name="max_value" :value="valueStepMax < valueSteps.length - 1 ? valueMax() : ''">
                                    </div>

                                    {{-- Max contract year --}}
                                    <label class="flex flex-col gap-1">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('transfers.explore_contract_expires_by') }}</span>
                                        <input type="number" name="max_contract_year"
                                               min="{{ (int) $game->current_date->year }}" max="{{ (int) $game->current_date->year + 10 }}"
                                               x-model.number="filters.max_contract_year"
                                               placeholder="{{ (int) $game->current_date->year + 1 }}"
                                               class="bg-surface-700 border border-border-default rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-accent-blue/50 min-h-[40px]">
                                    </label>
                                </div>

                                {{-- Actions --}}
                                <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-between gap-3">
                                    <a href="{{ route('game.explore', $game->id) }}"
                                       x-show="activeFilterCount > 0 || searchQuery.length > 0"
                                       class="text-xs text-text-muted hover:text-text-body underline-offset-2 hover:underline text-center sm:text-left">
                                        {{ __('transfers.explore_clear_filters') }}
                                    </a>
                                    <x-primary-button-spin loading="searching" class="gap-2 sm:ml-auto">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        {{ __('transfers.explore_search_submit') }}
                                    </x-primary-button-spin>
                                </div>
                            </div>
                        </form>


                        {{-- Hint (rendered below the picker so the active scope is always anchored to the top) --}}
                        <p class="text-sm text-text-muted mb-5" x-show="viewMode === 'competition'">{!! __('transfers.explore_hint', [
                            'scouting' => '<a href="' . route('game.scouting', $game->id) . '" class="text-accent-blue hover:text-accent-blue/80 font-medium underline-offset-2 hover:underline">' . __('transfers.explore_link_to_scouting') . '</a>',
                        ]) !!}</p>
                        <p class="text-sm text-text-muted mb-5" x-show="viewMode === 'pool'" x-text="activePoolHint"></p>

                        {{-- Competition mode: Two-column layout (desktop) / Tab toggle (mobile) --}}
                        <div x-show="viewMode === 'competition'" class="flex flex-col md:flex-row gap-6">

                            {{-- Mobile tab toggle --}}
                            <div class="flex md:hidden border-b border-border-strong mb-2">
                                <x-tab-button @click="mobileView = 'teams'"
                                        x-bind:class="mobileView === 'teams' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted'"
                                        class="flex-1 text-center min-h-[44px]">
                                    {{ __('transfers.explore_mobile_teams') }}
                                </x-tab-button>
                                <x-tab-button @click="mobileView = 'squad'"
                                        x-bind:class="mobileView === 'squad' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted'"
                                        class="flex-1 text-center min-h-[44px]">
                                    {{ __('transfers.explore_mobile_squad') }}
                                </x-tab-button>
                            </div>

                            {{-- Left column: Teams list --}}
                            <div class="md:w-1/3 md:max-h-[70vh] md:overflow-y-auto md:pr-2"
                                 :class="{ 'hidden md:block': mobileView === 'squad' }">

                                {{-- Loading state --}}
                                <template x-if="loadingTeams">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin h-6 w-6 text-text-secondary" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </template>

                                {{-- Teams grid --}}
                                <template x-if="!loadingTeams && teams.length > 0">
                                    <div class="space-y-1">
                                        <template x-for="team in teams" :key="team.id">
                                            <button @click="selectTeam(team)"
                                                    :class="selectedTeam?.id === team.id
                                                        ? 'bg-accent-blue/10 border-accent-blue/20 ring-1 ring-accent-blue/20'
                                                        : 'bg-surface-800 border-border-default hover:bg-surface-700/50'"
                                                    class="w-full flex items-center gap-3 p-3 rounded-lg border transition-all text-left min-h-[44px]">
                                                <img :src="team.image" :alt="team.name" class="w-8 h-8 shrink-0 object-contain">
                                                <div class="min-w-0">
                                                    <div class="text-sm font-medium text-text-primary truncate" x-text="team.name"></div></div>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                {{-- Empty state --}}
                                <template x-if="!loadingTeams && teams.length === 0 && selectedCompetition">
                                    <p class="text-sm text-text-secondary text-center py-8">{{ __('transfers.explore_no_teams') }}</p>
                                </template>
                            </div>

                            {{-- Right column: Squad view --}}
                            <div class="md:w-2/3 md:border-l md:border-border-default md:pl-6"
                                 :class="{ 'hidden md:block': mobileView === 'teams' }">

                                {{-- Loading state --}}
                                <template x-if="loadingSquad">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin h-6 w-6 text-text-secondary" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </template>

                                {{-- Empty state: no team selected --}}
                                <template x-if="!loadingSquad && !squadHtml">
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <svg class="w-16 h-16 text-text-body mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <p class="text-sm text-text-secondary">{{ __('transfers.explore_select_team') }}</p>
                                    </div>
                                </template>

                                {{-- Squad content (server-rendered HTML) --}}
                                <div x-show="!loadingSquad && squadHtml" x-ref="squadPanel"></div>
                            </div>
                        </div>

                        {{-- Pool mode (Europe / International / future): two-column layout with teams grouped by country --}}
                        <div x-show="viewMode === 'pool'" class="flex flex-col md:flex-row gap-6">

                            {{-- Mobile tab toggle --}}
                            <div class="flex md:hidden border-b border-border-strong mb-2">
                                <x-tab-button @click="mobileView = 'teams'"
                                        x-bind:class="mobileView === 'teams' ? 'border-accent-gold text-accent-gold' : 'border-transparent text-text-muted'"
                                        class="flex-1 text-center min-h-[44px]">
                                    {{ __('transfers.explore_mobile_teams') }}
                                </x-tab-button>
                                <x-tab-button @click="mobileView = 'squad'"
                                        x-bind:class="mobileView === 'squad' ? 'border-accent-gold text-accent-gold' : 'border-transparent text-text-muted'"
                                        class="flex-1 text-center min-h-[44px]">
                                    {{ __('transfers.explore_mobile_squad') }}
                                </x-tab-button>
                            </div>

                            {{-- Left column: Teams grouped by country --}}
                            <div class="md:w-1/3 md:max-h-[70vh] md:overflow-y-auto md:pr-2"
                                 :class="{ 'hidden md:block': mobileView === 'squad' }">

                                {{-- Loading state --}}
                                <template x-if="loadingPool">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin h-6 w-6 text-text-secondary" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </template>

                                {{-- Grouped teams --}}
                                <template x-if="!loadingPool && poolGroups.length > 0">
                                    <div class="space-y-4">
                                        <template x-for="group in poolGroups" :key="group.code">
                                            <div>
                                                {{-- Country header --}}
                                                <div class="flex items-center gap-2 px-2 py-1.5 mb-1">
                                                    <img :src="assetUrl + '/flags/' + group.flag + '.svg'" class="w-5 h-3.5 rounded-xs shadow-xs" :alt="group.name">
                                                    <span class="text-xs font-semibold uppercase tracking-wider text-text-muted" x-text="group.name"></span>
                                                    <span class="text-xs text-text-muted" x-text="'(' + group.teams.length + ')'"></span>
                                                </div>
                                                {{-- Teams in this country --}}
                                                <div class="space-y-1">
                                                    <template x-for="team in group.teams" :key="team.id">
                                                        <button @click="selectTeam(team)"
                                                                :class="selectedTeam?.id === team.id
                                                                    ? 'bg-accent-gold/10 border-accent-gold/20 ring-1 ring-accent-gold/20'
                                                                    : 'bg-surface-800 border-border-default hover:bg-surface-700/50'"
                                                                class="w-full flex items-center gap-3 p-3 rounded-lg border transition-all text-left min-h-[44px]">
                                                            <img :src="team.image" :alt="team.name" class="w-8 h-8 shrink-0 object-contain">
                                                            <div class="min-w-0">
                                                                <div class="text-sm font-medium text-text-primary truncate" x-text="team.name"></div>
                                                            </div>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Empty state --}}
                                <template x-if="!loadingPool && poolGroups.length === 0">
                                    <p class="text-sm text-text-secondary text-center py-8">{{ __('transfers.explore_no_teams') }}</p>
                                </template>
                            </div>

                            {{-- Right column: Squad view (reuses same refs as competition mode) --}}
                            <div class="md:w-2/3 md:border-l md:border-border-default md:pl-6"
                                 :class="{ 'hidden md:block': mobileView === 'teams' }">

                                {{-- Loading state --}}
                                <template x-if="loadingSquad">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin h-6 w-6 text-text-secondary" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </template>

                                {{-- Empty state: no team selected --}}
                                <template x-if="!loadingSquad && !squadHtml">
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <svg class="w-16 h-16 text-text-body mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <p class="text-sm text-text-secondary">{{ __('transfers.explore_select_team') }}</p>
                                    </div>
                                </template>

                                {{-- Squad content (server-rendered HTML) --}}
                                <div x-show="!loadingSquad && squadHtml" x-ref="poolSquadPanel"></div>
                            </div>
                        </div>

                        {{-- Search results (server-rendered when query params present) --}}
                        @if($searchMode && $searchResults !== null)
                            <div x-show="viewMode === 'search'">
                                @include('partials.explore-search-results', [
                                    'players' => $searchResults['players'],
                                    'game' => $game,
                                    'query' => $searchResults['query'],
                                    'total' => $searchResults['total'],
                                    'truncated' => $searchResults['truncated'],
                                    'hasCriteria' => $searchResults['hasCriteria'],
                                ])
                            </div>
                        @endif

                        {{-- Free Agents mode: Two-column layout with position filters --}}
                        <div x-show="viewMode === 'freeAgents'" class="flex flex-col md:flex-row gap-6">

                            {{-- Mobile tab toggle --}}
                            <div class="flex md:hidden border-b border-border-strong mb-2">
                                <x-tab-button @click="mobileView = 'teams'"
                                        x-bind:class="mobileView === 'teams' ? 'border-accent-green text-accent-green' : 'border-transparent text-text-muted'"
                                        class="flex-1 text-center min-h-[44px]">
                                    {{ __('transfers.explore_filter_all') }}
                                </x-tab-button>
                                <x-tab-button @click="mobileView = 'squad'"
                                        x-bind:class="mobileView === 'squad' ? 'border-accent-green text-accent-green' : 'border-transparent text-text-muted'"
                                        class="flex-1 text-center min-h-[44px]">
                                    {{ __('app.players') }}
                                </x-tab-button>
                            </div>

                            {{-- Left column: Position filters --}}
                            <div class="md:w-1/3 md:pr-2"
                                 :class="{ 'hidden md:block': mobileView === 'squad' }">
                                <div class="space-y-1">
                                    <template x-for="filter in positionFilters" :key="filter.key">
                                        <button @click="selectPositionFilter(filter.key)"
                                                :class="selectedPositionFilter === filter.key
                                                    ? 'bg-accent-green/10 border-accent-green/20 ring-1 ring-accent-green/20'
                                                    : 'bg-surface-700 border-border-strong hover:bg-surface-600/50'"
                                                class="w-full flex items-center gap-3 p-3 rounded-lg border transition-all text-left min-h-[44px]">
                                            <span class="text-sm font-medium text-text-primary" x-text="filter.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Right column: Free agents list --}}
                            <div class="md:w-2/3 md:border-l md:border-border-default md:pl-6"
                                 :class="{ 'hidden md:block': mobileView === 'teams' }">

                                {{-- Loading state --}}
                                <template x-if="loadingFreeAgents">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin h-6 w-6 text-text-secondary" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </template>

                                {{-- Free agents content (server-rendered HTML) --}}
                                <div x-show="!loadingFreeAgents" x-ref="freeAgentPanel"></div>
                            </div>
                        </div>
                    </div>
    </div>

    <x-negotiation-chat-modal />
    <x-wage-cap-modal :game="$game" />

</x-app-layout>
