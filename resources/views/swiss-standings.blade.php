@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var array $standingsZones */
/** @var \Illuminate\Support\Collection $knockoutTies */

$hasKnockout = $knockoutTies->isNotEmpty() || $knockoutRounds->isNotEmpty();
$knockoutStarted = $knockoutTies->isNotEmpty();
$defaultTab = $knockoutStarted ? 'knockout' : 'league';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" x-data="{ activeTab: '{{ $defaultTab }}' }">
                <div class="p-6 sm:p-8">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ $competition->name }}</h3>

                    {{-- Tab Navigation --}}
                    @if($hasKnockout)
                        <div class="flex border-b border-slate-200 mb-0">
                            <button @click="activeTab = 'league'"
                                    :class="activeTab === 'league' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                                {{ __('game.league_phase') }}
                                @if($leaguePhaseComplete)
                                    <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold bg-green-600 text-white rounded-full">{{ __('game.completed') }}</span>
                                @endif
                            </button>
                            <button @click="activeTab = 'knockout'"
                                    :class="activeTab === 'knockout' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                                {{ __('game.knockout_phase') }}
                            </button>
                        </div>
                    @endif

                    {{-- Knockout Phase Bracket --}}
                    @if($hasKnockout)
                        <div x-show="activeTab === 'knockout'" class="mt-6 space-y-6">
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
                    @endif

                    {{-- League Phase Standings --}}
                    <div x-show="activeTab === 'league'" @if($hasKnockout) x-cloak @endif class="mt-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                            <div class="md:col-span-2 space-y-3">
                                @if(!$hasKnockout)
                                    <h4 class="font-semibold text-lg text-slate-700">
                                        {{ __('game.league_phase') }}
                                    </h4>
                                @endif

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
            </div>
        </div>
    </div>
</x-app-layout>
