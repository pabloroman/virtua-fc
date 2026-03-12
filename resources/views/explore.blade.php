@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="mb-4 p-4 bg-accent-green/10 border border-accent-green/20 rounded-lg text-accent-green">
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-surface-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8">
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
                        <p class="text-sm text-slate-500 mb-5">{{ __('transfers.explore_hint') }}</p>

                        {{-- Competition Selector --}}
                        <div class="flex overflow-x-auto scrollbar-hide gap-2 pb-3 mb-5 border-b border-white/5">
                            <template x-for="comp in competitions" :key="comp.id">
                                <button @click="selectCompetition(comp)"
                                        :class="selectedCompetition?.id === comp.id
                                            ? 'bg-slate-900 text-white border-slate-900'
                                            : 'bg-surface-800 text-slate-300 border-white/10 hover:border-slate-400'"
                                        class="shrink-0 flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-colors min-h-[44px]">
                                    <template x-if="comp.country">
                                        <img :src="'/flags/' + comp.flag + '.svg'" class="w-5 h-3.5 rounded-xs shadow-xs" :alt="comp.country">
                                    </template>
                                    <span x-text="comp.name"></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full"
                                          :class="selectedCompetition?.id === comp.id ? 'bg-surface-800/20' : 'bg-surface-700 text-slate-500'"
                                          x-text="comp.teamCount"></span>
                                </button>
                            </template>
                        </div>

                        {{-- Two-column layout (desktop) / Tab toggle (mobile) --}}
                        <div class="flex flex-col md:flex-row gap-6">

                            {{-- Mobile tab toggle --}}
                            <div class="flex md:hidden border-b border-white/10 mb-2">
                                <button @click="mobileView = 'teams'"
                                        :class="mobileView === 'teams' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-slate-500'"
                                        class="flex-1 text-center py-2.5 text-sm font-medium border-b-2 transition-colors min-h-[44px]">
                                    {{ __('transfers.explore_mobile_teams') }}
                                </button>
                                <button @click="mobileView = 'squad'"
                                        :class="mobileView === 'squad' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-slate-500'"
                                        class="flex-1 text-center py-2.5 text-sm font-medium border-b-2 transition-colors min-h-[44px]">
                                    {{ __('transfers.explore_mobile_squad') }}
                                </button>
                            </div>

                            {{-- Left column: Teams list --}}
                            <div class="md:w-1/3 md:max-h-[70vh] md:overflow-y-auto md:pr-2"
                                 :class="{ 'hidden md:block': mobileView === 'squad' }">

                                {{-- Loading state --}}
                                <template x-if="loadingTeams">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24">
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
                                                        ? 'bg-accent-blue/10 border-accent-blue/20 ring-1 ring-sky-200'
                                                        : 'bg-surface-800 border-white/5 hover:bg-surface-700/50'"
                                                    class="w-full flex items-center gap-3 p-3 rounded-lg border transition-all text-left min-h-[44px]">
                                                <img :src="team.image" :alt="team.name" class="w-8 h-8 shrink-0 object-contain">
                                                <div class="min-w-0">
                                                    <div class="text-sm font-medium text-white truncate" x-text="team.name"></div></div>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                {{-- Empty state --}}
                                <template x-if="!loadingTeams && teams.length === 0 && selectedCompetition">
                                    <p class="text-sm text-slate-400 text-center py-8">{{ __('transfers.explore_no_teams') }}</p>
                                </template>
                            </div>

                            {{-- Right column: Squad view --}}
                            <div class="md:w-2/3 md:border-l md:border-white/5 md:pl-6"
                                 :class="{ 'hidden md:block': mobileView === 'teams' }">

                                {{-- Loading state --}}
                                <template x-if="loadingSquad">
                                    <div class="flex items-center justify-center py-12">
                                        <svg class="animate-spin h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </template>

                                {{-- Empty state: no team selected --}}
                                <template x-if="!loadingSquad && !squadHtml">
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <svg class="w-16 h-16 text-slate-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <p class="text-sm text-slate-400">{{ __('transfers.explore_select_team') }}</p>
                                    </div>
                                </template>

                                {{-- Squad content (server-rendered HTML) --}}
                                <div x-show="!loadingSquad && squadHtml" x-ref="squadPanel"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exploreApp() {
            return {
                competitions: @json($competitions),
                selectedCompetition: null,
                teams: [],
                selectedTeam: null,
                squadHtml: '',
                loadingTeams: false,
                loadingSquad: false,
                mobileView: 'teams',
                gameId: '{{ $game->id }}',

                init() {
                    if (this.competitions.length > 0) {
                        this.selectCompetition(this.competitions[0]);
                    }
                },

                async selectCompetition(comp) {
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

            };
        }
    </script>
</x-app-layout>
