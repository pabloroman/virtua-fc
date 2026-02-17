<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">

                @if(!empty($groupedStandings))
                    @include('partials.standings-grouped', [
                        'game' => $game,
                        'competition' => $competition,
                        'groupedStandings' => $groupedStandings,
                        'teamForms' => $teamForms,
                    ])
                @else
                    <div class="md:col-span-2 space-y-3">
                        <h3 class="font-semibold text-xl text-slate-900">{{ $competition->name }}</h3>

                        @include('partials.standings-flat', [
                            'game' => $game,
                            'standings' => $standings,
                            'teamForms' => $teamForms,
                            'standingsZones' => $standingsZones,
                        ])
                    </div>
                @endif

                <x-top-scorers :top-scorers="$topScorers" :player-team-id="$game->team_id" />

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
