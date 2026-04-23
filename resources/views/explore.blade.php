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
                         x-data="exploreApp()"
                         x-init="init()">

                        <form method="GET" action="{{ route('game.explore', $game->id) }}">
                            {{-- Search bar + Advanced-filter toggle --}}
                            <div class="flex flex-col sm:flex-row gap-2 mb-3">
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
                                    <button type="submit"
                                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-accent-blue/15 border border-accent-blue/30 text-accent-blue font-semibold text-sm hover:bg-accent-blue/20 min-h-[44px] sm:ml-auto">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        {{ __('transfers.explore_search_submit') }}
                                    </button>
                                </div>
                            </div>
                        </form>

                        {{-- Hint --}}
                        <p class="text-sm text-text-muted mb-5" x-show="viewMode === 'competition'">{!! __('transfers.explore_hint', [
                            'scouting' => '<a href="' . route('game.scouting', $game->id) . '" class="text-accent-blue hover:text-accent-blue/80 font-medium underline-offset-2 hover:underline">' . __('transfers.explore_link_to_scouting') . '</a>',
                        ]) !!}</p>
                        <p class="text-sm text-text-muted mb-5" x-show="viewMode === 'europe'">{{ __('transfers.explore_europe_hint') }}</p>

                        {{-- Competition Selector + Free Agents pill --}}
                        <div x-show="viewMode !== 'search'" class="flex overflow-x-auto scrollbar-hide gap-2 pb-3 mb-5 border-b border-border-default">
                            <template x-for="comp in competitions" :key="comp.id">
                                <x-pill-button @click="selectCompetition(comp)"
                                        x-bind:class="viewMode === 'competition' && selectedCompetition?.id === comp.id
                                            ? 'bg-accent-blue/15 text-accent-blue border-accent-blue/30'
                                            : 'bg-surface-800 text-text-body border-border-default hover:border-border-strong'"
                                        class="shrink-0 gap-2 rounded-lg border min-h-[44px]">
                                    <template x-if="comp.country">
                                        <img :src="assetUrl + '/flags/' + comp.flag + '.svg'" class="w-5 h-3.5 rounded-xs shadow-xs" :alt="comp.country">
                                    </template>
                                    <span x-text="comp.name"></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full"
                                          :class="viewMode === 'competition' && selectedCompetition?.id === comp.id ? 'bg-accent-blue/20 text-accent-blue' : 'bg-surface-700 text-text-muted'"
                                          x-text="comp.teamCount"></span>
                                </x-pill-button>
                            </template>

                            {{-- Europe pill --}}
                            @if($europeTeamCount > 0)
                            <x-pill-button @click="selectEurope()"
                                    x-bind:class="viewMode === 'europe'
                                        ? 'bg-accent-gold/15 text-accent-gold border-accent-gold/30'
                                        : 'bg-surface-800 text-text-body border-border-default hover:border-border-strong'"
                                    class="shrink-0 gap-2 rounded-lg border min-h-[44px]">
                                <img :src="assetUrl + '/flags/eu.svg'" class="w-5 h-3.5 rounded-xs shadow-xs" alt="Europe">
                                <span>{{ __('transfers.explore_europe') }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded-full"
                                      :class="viewMode === 'europe' ? 'bg-accent-gold/20 text-accent-gold' : 'bg-surface-700 text-text-muted'">{{ $europeTeamCount }}</span>
                            </x-pill-button>
                            @endif

                            {{-- Free Agents pill --}}
                            @if($freeAgentCount > 0)
                            <x-pill-button @click="selectFreeAgents()"
                                    x-bind:class="viewMode === 'freeAgents'
                                        ? 'bg-accent-green/15 text-accent-green border-accent-green/30'
                                        : 'bg-surface-800 text-text-body border-border-default hover:border-border-strong'"
                                    class="shrink-0 gap-2 rounded-lg border min-h-[44px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>{{ __('transfers.explore_free_agents') }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded-full"
                                      :class="viewMode === 'freeAgents' ? 'bg-accent-green/20 text-accent-green' : 'bg-surface-700 text-text-muted'">{{ $freeAgentCount }}</span>
                            </x-pill-button>
                            @endif
                        </div>

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

                        {{-- Europe mode: Two-column layout with teams grouped by country --}}
                        <div x-show="viewMode === 'europe'" class="flex flex-col md:flex-row gap-6">

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
                                <template x-if="loadingEurope">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin h-6 w-6 text-text-secondary" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </template>

                                {{-- Grouped teams --}}
                                <template x-if="!loadingEurope && europeGroups.length > 0">
                                    <div class="space-y-4">
                                        <template x-for="group in europeGroups" :key="group.code">
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
                                <template x-if="!loadingEurope && europeGroups.length === 0">
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
                                <div x-show="!loadingSquad && squadHtml" x-ref="europeSquadPanel"></div>
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

    <script>
        function exploreApp() {
            const initialFilters = @js($initialFilters);
            const searchMode = @js((bool) $searchMode);

            return {
                competitions: @json($competitions),
                assetUrl: '{{ rtrim(Storage::disk('assets')->url(''), '/') }}',
                viewMode: searchMode ? 'search' : 'competition',
                selectedCompetition: null,
                teams: [],
                selectedTeam: null,
                squadHtml: '',
                loadingTeams: false,
                loadingSquad: false,
                loadingFreeAgents: false,
                loadingEurope: false,
                europeGroups: [],
                searchQuery: initialFilters.name || '',
                mobileView: 'teams',
                gameId: '{{ $game->id }}',
                selectedPositionFilter: 'all',
                positionFilters: [
                    { key: 'all', label: @js(__('transfers.explore_filter_all')) },
                    { key: 'gk', label: @js(__('transfers.explore_goalkeepers')) },
                    { key: 'def', label: @js(__('transfers.explore_defenders')) },
                    { key: 'mid', label: @js(__('transfers.explore_midfielders')) },
                    { key: 'fwd', label: @js(__('transfers.explore_forwards')) },
                ],
                filtersOpen: searchMode,
                filters: {
                    position: initialFilters.position || '',
                    nationality: initialFilters.nationality || '',
                    competition_id: initialFilters.competition_id || '',
                    max_contract_year: initialFilters.max_contract_year || null,
                },

                // Dual-range bounds (mirrors scout-search-modal pattern)
                AGE_MIN_BOUND: 15,
                AGE_MAX_BOUND: 50,
                OVERALL_MIN_BOUND: 40,
                OVERALL_MAX_BOUND: 99,
                valueSteps: [0, 500000, 1000000, 2000000, 5000000, 10000000, 20000000, 50000000, 100000000, 200000000],

                ageMin: null,
                ageMax: null,
                overallMin: null,
                overallMax: null,
                valueStepMin: null,
                valueStepMax: null,

                enforceAgeMin() { if (this.ageMin > this.ageMax) this.ageMax = this.ageMin; },
                enforceAgeMax() { if (this.ageMax < this.ageMin) this.ageMin = this.ageMax; },
                enforceOverallMin() { if (this.overallMin > this.overallMax) this.overallMax = this.overallMin; },
                enforceOverallMax() { if (this.overallMax < this.overallMin) this.overallMin = this.overallMax; },
                enforceValueMin() { if (this.valueStepMin > this.valueStepMax) this.valueStepMax = this.valueStepMin; },
                enforceValueMax() { if (this.valueStepMax < this.valueStepMin) this.valueStepMin = this.valueStepMax; },

                ageTrackLeft() { return ((this.ageMin - this.AGE_MIN_BOUND) / (this.AGE_MAX_BOUND - this.AGE_MIN_BOUND)) * 100 + '%'; },
                ageTrackWidth() { return ((this.ageMax - this.ageMin) / (this.AGE_MAX_BOUND - this.AGE_MIN_BOUND)) * 100 + '%'; },
                overallTrackLeft() { return ((this.overallMin - this.OVERALL_MIN_BOUND) / (this.OVERALL_MAX_BOUND - this.OVERALL_MIN_BOUND)) * 100 + '%'; },
                overallTrackWidth() { return ((this.overallMax - this.overallMin) / (this.OVERALL_MAX_BOUND - this.OVERALL_MIN_BOUND)) * 100 + '%'; },
                valueTrackLeft() { return (this.valueStepMin / (this.valueSteps.length - 1)) * 100 + '%'; },
                valueTrackWidth() { return ((this.valueStepMax - this.valueStepMin) / (this.valueSteps.length - 1)) * 100 + '%'; },

                valueMin() { return this.valueSteps[this.valueStepMin]; },
                valueMax() { return this.valueSteps[this.valueStepMax]; },
                formatValue(val) {
                    if (val === 0) return '€0';
                    if (val >= 1000000) return '€' + (val / 1000000) + 'M';
                    if (val >= 1000) return '€' + (val / 1000) + 'K';
                    return '€' + val;
                },

                get ageActive() { return this.ageMin > this.AGE_MIN_BOUND || this.ageMax < this.AGE_MAX_BOUND; },
                get overallActive() { return this.overallMin > this.OVERALL_MIN_BOUND || this.overallMax < this.OVERALL_MAX_BOUND; },
                get valueActive() { return this.valueStepMin > 0 || this.valueStepMax < this.valueSteps.length - 1; },

                get activeFilterCount() {
                    let n = 0;
                    if (this.filters.position) n++;
                    if (this.filters.nationality) n++;
                    if (this.filters.competition_id) n++;
                    if (this.filters.max_contract_year) n++;
                    if (this.ageActive) n++;
                    if (this.overallActive) n++;
                    if (this.valueActive) n++;
                    return n;
                },
                get hasAnyCriteria() {
                    return this.searchQuery.trim().length >= 2 || this.activeFilterCount > 0;
                },

                init() {
                    this.initRangesFromFilters();
                    if (!searchMode && this.competitions.length > 0) {
                        this.selectCompetition(this.competitions[0]);
                    }
                },

                initRangesFromFilters() {
                    const f = initialFilters;
                    this.ageMin = f.min_age ? Number(f.min_age) : this.AGE_MIN_BOUND;
                    this.ageMax = f.max_age ? Number(f.max_age) : this.AGE_MAX_BOUND;
                    this.overallMin = f.min_overall ? Number(f.min_overall) : this.OVERALL_MIN_BOUND;
                    this.overallMax = f.max_overall ? Number(f.max_overall) : this.OVERALL_MAX_BOUND;
                    this.valueStepMin = f.min_value ? this.stepForValue(Number(f.min_value), 0) : 0;
                    this.valueStepMax = f.max_value ? this.stepForValue(Number(f.max_value), this.valueSteps.length - 1) : this.valueSteps.length - 1;
                },

                stepForValue(value, fallback) {
                    const idx = this.valueSteps.indexOf(value);
                    return idx >= 0 ? idx : fallback;
                },

                async selectCompetition(comp) {
                    this.viewMode = 'competition';
                    this.selectedCompetition = comp;
                    this.selectedTeam = null;
                    this.squadHtml = '';
                    if (this.$refs.squadPanel) this.$refs.squadPanel.innerHTML = '';
                    if (this.$refs.europeSquadPanel) this.$refs.europeSquadPanel.innerHTML = '';
                    this.loadingTeams = true;

                    try {
                        const response = await fetch(`/game/${this.gameId}/explore/teams/${comp.id}`);
                        this.teams = await response.json();
                    } catch (e) {
                        this.teams = [];
                    } finally {
                        this.loadingTeams = false;
                    }
                },

                async selectTeam(team) {
                    this.selectedTeam = team;
                    this.loadingSquad = true;
                    this.mobileView = 'squad';

                    const panel = this.viewMode === 'europe' ? this.$refs.europeSquadPanel : this.$refs.squadPanel;

                    try {
                        const response = await fetch(`/game/${this.gameId}/explore/squad/${team.id}`);
                        const html = await response.text();
                        this.squadHtml = html;
                        if (panel) {
                            panel.innerHTML = html;
                            this.$nextTick(() => Alpine.initTree(panel));
                        }
                    } catch (e) {
                        this.squadHtml = '';
                        if (panel) panel.innerHTML = '';
                    } finally {
                        this.loadingSquad = false;
                    }
                },

                async selectEurope() {
                    this.viewMode = 'europe';
                    this.selectedCompetition = null;
                    this.selectedTeam = null;
                    this.squadHtml = '';
                    if (this.$refs.europeSquadPanel) this.$refs.europeSquadPanel.innerHTML = '';
                    this.mobileView = 'teams';

                    if (this.europeGroups.length > 0) return;

                    this.loadingEurope = true;
                    try {
                        const response = await fetch(`/game/${this.gameId}/explore/europe-teams`);
                        this.europeGroups = await response.json();
                    } catch (e) {
                        this.europeGroups = [];
                    } finally {
                        this.loadingEurope = false;
                    }
                },

                selectFreeAgents() {
                    this.viewMode = 'freeAgents';
                    this.selectedCompetition = null;
                    this.selectedPositionFilter = 'all';
                    this.mobileView = 'teams';
                    this.loadFreeAgents('all');
                },

                selectPositionFilter(position) {
                    this.selectedPositionFilter = position;
                    this.mobileView = 'squad';
                    this.loadFreeAgents(position);
                },

                async loadFreeAgents(position) {
                    this.loadingFreeAgents = true;

                    try {
                        const response = await fetch(`/game/${this.gameId}/explore/free-agents?position=${position}`);
                        const html = await response.text();
                        this.$refs.freeAgentPanel.innerHTML = html;
                        this.$nextTick(() => Alpine.initTree(this.$refs.freeAgentPanel));
                    } catch (e) {
                        if (this.$refs.freeAgentPanel) this.$refs.freeAgentPanel.innerHTML = '';
                    } finally {
                        this.loadingFreeAgents = false;
                    }
                },

            };
        }
    </script>
</x-app-layout>
