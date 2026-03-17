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
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => false, 'badge' => $counterOfferCount > 0 ? $counterOfferCount : null],
                        ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => false],
                        ['href' => route('game.explore', $game->id), 'label' => __('transfers.explore_tab'), 'active' => true],
                    ]" />

                    {{-- Explorer Content --}}
                    <div class="mt-6"
                         x-data="exploreApp()"
                         x-init="init()">

                        {{-- Hint --}}
                        <p class="text-sm text-text-muted mb-5" x-show="viewMode === 'competition'">{{ __('transfers.explore_hint') }}</p>

                        {{-- Competition Selector + Free Agents pill --}}
                        <div class="flex overflow-x-auto scrollbar-hide gap-2 pb-3 mb-5 border-b border-border-default">
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

    <script>
        function exploreApp() {
            return {
                competitions: @json($competitions),
                assetUrl: '{{ rtrim(Storage::disk('assets')->url(''), '/') }}',
                viewMode: 'competition',
                selectedCompetition: null,
                teams: [],
                selectedTeam: null,
                squadHtml: '',
                loadingTeams: false,
                loadingSquad: false,
                loadingFreeAgents: false,
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

                init() {
                    if (this.competitions.length > 0) {
                        this.selectCompetition(this.competitions[0]);
                    }
                },

                async selectCompetition(comp) {
                    this.viewMode = 'competition';
                    this.selectedCompetition = comp;
                    this.selectedTeam = null;
                    this.squadHtml = '';
                    if (this.$refs.squadPanel) this.$refs.squadPanel.innerHTML = '';
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

                    try {
                        const response = await fetch(`/game/${this.gameId}/explore/squad/${team.id}`);
                        const html = await response.text();
                        this.squadHtml = html;
                        this.$refs.squadPanel.innerHTML = html;
                        this.$nextTick(() => Alpine.initTree(this.$refs.squadPanel));
                    } catch (e) {
                        this.squadHtml = '';
                        this.$refs.squadPanel.innerHTML = '';
                    } finally {
                        this.loadingSquad = false;
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
