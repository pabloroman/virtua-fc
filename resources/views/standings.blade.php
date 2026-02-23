@php
    $hasPlayoff = $knockoutTies->isNotEmpty() || $knockoutRounds->isNotEmpty();
    $knockoutStarted = $knockoutTies->isNotEmpty();
    $defaultTab = $knockoutStarted ? 'playoff' : 'league';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

            @if($hasPlayoff)
                <div class="p-4 sm:p-6 md:p-8" x-data="{ activeTab: '{{ $defaultTab }}' }">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ $competition->name }}</h3>

                    {{-- Tab Navigation --}}
                    <div class="flex border-b border-slate-200 mb-0">
                        <button @click="activeTab = 'league'"
                                :class="activeTab === 'league' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                            {{ __('game.league_phase') }}
                            @if($leaguePhaseComplete)
                                <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold bg-green-600 text-white rounded-full">{{ __('game.completed') }}</span>
                            @endif
                        </button>
                        <button @click="activeTab = 'playoff'"
                                :class="activeTab === 'playoff' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                            {{ __('game.promotion_playoff') }}
                        </button>
                    </div>

                    {{-- Playoff Bracket --}}
                    <div x-show="activeTab === 'playoff'" class="mt-6 space-y-6">
                        <div class="overflow-x-auto">
                            <div class="flex gap-4" style="min-width: fit-content;">
                                @foreach($knockoutRounds as $round)
                                    @php $ties = $knockoutTies->get($round->round, collect()); @endphp
                                    <div class="flex-shrink-0 w-64">
                                        <div class="text-center mb-4">
                                            <h4 class="font-semibold text-slate-700">{{ $round->name }}</h4>
                                            <div class="text-xs text-slate-400">
                                                {{ $round->firstLegDate->format('M d') }}
                                                @if($round->twoLegged)
                                                    / {{ $round->secondLegDate->format('M d') }}
                                                @endif
                                            </div>
                                        </div>

                                        @if($ties->isEmpty())
                                            <div class="p-4 text-center border border-dashed rounded-lg">
                                                <div class="text-slate-400 text-sm">-</div>
                                            </div>
                                        @else
                                            <div class="space-y-2">
                                                @foreach($ties as $tie)
                                                    <x-cup-tie-card :tie="$tie" :player-team-id="$game->team_id" />
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- League Phase Standings --}}
                    <div x-show="activeTab === 'league'" x-cloak class="mt-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                            <div class="md:col-span-2 space-y-3">
                                @include('partials.standings-flat', [
                                    'game' => $game,
                                    'standings' => $standings,
                                    'teamForms' => $teamForms,
                                    'standingsZones' => $standingsZones,
                                ])
                            </div>

                            <x-top-scorers :top-scorers="$topScorers" :player-team-id="$game->team_id" />
                        </div>
                    </div>
                </div>

            @elseif(!empty($groupedStandings))
                <div class="p-4 sm:p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                    @include('partials.standings-grouped', [
                        'game' => $game,
                        'competition' => $competition,
                        'groupedStandings' => $groupedStandings,
                        'teamForms' => $teamForms,
                    ])

                    <x-top-scorers :top-scorers="$topScorers" :player-team-id="$game->team_id" />
                </div>

            @else
                <div class="p-4 sm:p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                    <div class="md:col-span-2 space-y-3">
                        <h3 class="font-semibold text-xl text-slate-900">{{ $competition->name }}</h3>

                        @include('partials.standings-flat', [
                            'game' => $game,
                            'standings' => $standings,
                            'teamForms' => $teamForms,
                            'standingsZones' => $standingsZones,
                        ])
                    </div>

                    <x-top-scorers :top-scorers="$topScorers" :player-team-id="$game->team_id" />
                </div>
            @endif

            </div>
        </div>
    </div>
</x-app-layout>
