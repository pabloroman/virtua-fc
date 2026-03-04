@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
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
                        <div class="flex overflow-x-auto scrollbar-hide gap-2 pb-3 mb-5 border-b border-slate-100">
                            <template x-for="comp in competitions" :key="comp.id">
                                <button @click="selectCompetition(comp)"
                                        :class="selectedCompetition?.id === comp.id
                                            ? 'bg-slate-900 text-white border-slate-900'
                                            : 'bg-white text-slate-700 border-slate-200 hover:border-slate-400'"
                                        class="shrink-0 flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-colors min-h-[44px]">
                                    <template x-if="comp.country">
                                        <img :src="'/flags/' + comp.country.toLowerCase() + '.svg'" class="w-5 h-3.5 rounded-sm shadow-sm" :alt="comp.country">
                                    </template>
                                    <span x-text="comp.name"></span>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full"
                                          :class="selectedCompetition?.id === comp.id ? 'bg-white/20' : 'bg-slate-100 text-slate-500'"
                                          x-text="comp.teamCount"></span>
                                </button>
                            </template>
                        </div>

                        {{-- Two-column layout (desktop) / Tab toggle (mobile) --}}
                        <div class="flex flex-col md:flex-row gap-6">

                            {{-- Mobile tab toggle --}}
                            <div class="flex md:hidden border-b border-slate-200 mb-2">
                                <button @click="mobileView = 'teams'"
                                        :class="mobileView === 'teams' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500'"
                                        class="flex-1 text-center py-2.5 text-sm font-medium border-b-2 transition-colors min-h-[44px]">
                                    {{ __('transfers.explore_mobile_teams') }}
                                </button>
                                <button @click="mobileView = 'squad'"
                                        :class="mobileView === 'squad' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500'"
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
                                                        ? 'bg-sky-50 border-sky-200 ring-1 ring-sky-200'
                                                        : 'bg-white border-slate-100 hover:bg-slate-50'"
                                                    class="w-full flex items-center gap-3 p-3 rounded-lg border transition-all text-left min-h-[44px]">
                                                <img :src="team.image" :alt="team.name" class="w-8 h-8 shrink-0 object-contain">
                                                <div class="min-w-0">
                                                    <div class="text-sm font-medium text-slate-900 truncate" x-text="team.name"></div></div>
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
                            <div class="md:w-2/3 md:border-l md:border-slate-100 md:pl-6"
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
                                <template x-if="!loadingSquad && !squad">
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <svg class="w-16 h-16 text-slate-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <p class="text-sm text-slate-400">{{ __('transfers.explore_select_team') }}</p>
                                    </div>
                                </template>

                                {{-- Squad content --}}
                                <template x-if="!loadingSquad && squad">
                                    <div>
                                        {{-- Team header --}}
                                        <div class="flex items-center gap-4 mb-5 pb-4 border-b border-slate-100">
                                            <img :src="squad.team.image" :alt="squad.team.name" class="w-14 h-14 md:w-16 md:h-16 shrink-0 object-contain">
                                            <div class="min-w-0">
                                                <h3 class="text-lg font-bold text-slate-900 truncate" x-text="squad.team.name"></h3>
                                            </div>
                                        </div>

                                        {{-- Scouting nudge --}}
                                        <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 mb-5">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>{{ __('transfers.explore_scouting_nudge') }}</span>
                                        </div>

                                        {{-- Position groups --}}
                                        <template x-for="[groupKey, groupLabel] in positionGroups" :key="groupKey">
                                            <div x-show="squad.positions[groupKey] && squad.positions[groupKey].length > 0" class="mb-5">
                                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2" x-text="groupLabel"></h4>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
                                                                <th class="pb-2 font-medium"></th>
                                                                <th class="pb-2 font-medium">{{ __('transfers.transfer_activity_player') }}</th>
                                                                <th class="pb-2 font-medium hidden md:table-cell">{{ __('transfers.explore_age') }}</th>
                                                                <th class="pb-2 font-medium hidden md:table-cell">{{ __('transfers.explore_value') }}</th>
                                                                <th class="pb-2 font-medium hidden md:table-cell">{{ __('transfers.explore_contract_year') }}</th>
                                                                <th class="pb-2 font-medium w-10"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <template x-for="player in squad.positions[groupKey]" :key="player.id">
                                                                <tr class="border-b border-slate-50 hover:bg-slate-50/50">
                                                                    {{-- Position badge --}}
                                                                    <td class="py-2 pr-2">
                                                                        <span :class="player.positionBg + ' ' + player.positionText"
                                                                              class="inline-flex items-center justify-center text-[10px] font-bold rounded px-1.5 py-0.5 min-w-[28px]"
                                                                              x-text="player.positionAbbr"></span>
                                                                    </td>
                                                                    {{-- Name + nationality + mobile details --}}
                                                                    <td class="py-2 pr-3">
                                                                        <div class="flex items-center gap-2">
                                                                            <template x-if="player.nationalityCode">
                                                                                <img :src="'/flags/' + player.nationalityCode + '.svg'" class="w-4 h-3 rounded-sm shadow-sm shrink-0" :title="player.nationalityName">
                                                                            </template>
                                                                            <a :href="`/game/${gameId}/player/${player.id}/detail`"
                                                                               class="font-medium text-slate-900 hover:text-sky-600 truncate"
                                                                               x-text="player.name"></a>
                                                                            <template x-if="player.isLoanedIn">
                                                                                <span class="text-[10px] bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded font-medium shrink-0">{{ __('transfers.loans') }}</span>
                                                                            </template>
                                                                        </div>
                                                                        {{-- Mobile-only details --}}
                                                                        <div class="md:hidden text-xs text-slate-400 mt-0.5">
                                                                            <span x-text="player.age + ' {{ __('transfers.explore_age') }}'"></span>
                                                                            <span class="mx-1">&middot;</span>
                                                                            <span x-text="player.formattedMarketValue"></span>
                                                                        </div>
                                                                    </td>
                                                                    {{-- Age --}}
                                                                    <td class="py-2 pr-3 hidden md:table-cell text-slate-600" x-text="player.age"></td>
                                                                    {{-- Market value --}}
                                                                    <td class="py-2 pr-3 hidden md:table-cell text-slate-600 font-medium" x-text="player.formattedMarketValue"></td>
                                                                    {{-- Contract --}}
                                                                    <td class="py-2 pr-3 hidden md:table-cell text-slate-500" x-text="player.contractUntil || '—'"></td>
                                                                    {{-- Shortlist star --}}
                                                                    <td class="py-2 text-center">
                                                                        <button @click.prevent="toggleShortlist(player)"
                                                                                class="p-1.5 rounded-full transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
                                                                                :class="player.isShortlisted ? 'text-amber-500 hover:text-amber-600' : 'text-slate-300 hover:text-amber-400'"
                                                                                :title="player.isShortlisted ? '{{ __('transfers.remove_from_shortlist') }}' : '{{ __('transfers.add_to_shortlist') }}'">
                                                                            <svg class="w-5 h-5" :fill="player.isShortlisted ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                                            </svg>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            </template>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
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
                squad: null,
                loadingTeams: false,
                loadingSquad: false,
                mobileView: 'teams',
                gameId: '{{ $game->id }}',
                shortlistUrl: '{{ route('game.scouting.shortlist.toggle', [$game->id, '__PLAYER_ID__']) }}',
                csrfToken: '{{ csrf_token() }}',
                positionGroups: [
                    ['GK', '{{ __('transfers.explore_goalkeepers') }}'],
                    ['DEF', '{{ __('transfers.explore_defenders') }}'],
                    ['MID', '{{ __('transfers.explore_midfielders') }}'],
                    ['FWD', '{{ __('transfers.explore_forwards') }}'],
                ],

                init() {
                    if (this.competitions.length > 0) {
                        this.selectCompetition(this.competitions[0]);
                    }
                },

                async selectCompetition(comp) {
                    this.selectedCompetition = comp;
                    this.selectedTeam = null;
                    this.squad = null;
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
                        this.squad = await response.json();
                    } catch (e) {
                        this.squad = null;
                    } finally {
                        this.loadingSquad = false;
                    }
                },

                async toggleShortlist(player) {
                    const url = this.shortlistUrl.replace('__PLAYER_ID__', player.id);

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });
                        const data = await response.json();
                        if (data.success) {
                            player.isShortlisted = data.action === 'added';
                        }
                    } catch (e) {
                        // Silently fail
                    }
                },
            };
        }
    </script>
</x-app-layout>
