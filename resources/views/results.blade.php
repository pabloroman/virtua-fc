@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match" :continue-to-home="true"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="font-semibold text-xl text-slate-900">Matchday {{ $matchday }} Results</h3>
                            @if($competition)
                                <p class="text-sm text-slate-500">{{ $competition->name }}</p>
                            @endif
                        </div>
                        <a href="{{ route('show-game', $game->id) }}" class="text-sky-600 hover:text-sky-800">Back to Dashboard</a>
                    </div>

                    {{-- Player's Match Highlight --}}
                    @if($playerMatch)
                        @php
                            $playerIsHome = $playerMatch->home_team_id === $game->team_id;
                            $playerScore = $playerIsHome ? $playerMatch->home_score : $playerMatch->away_score;
                            $oppScore = $playerIsHome ? $playerMatch->away_score : $playerMatch->home_score;

                            // Get events for player's match
                            $homeGoals = $playerMatch->events->filter(fn($e) =>
                                ($e->event_type === 'goal' && $e->team_id === $playerMatch->home_team_id) ||
                                ($e->event_type === 'own_goal' && $e->team_id === $playerMatch->away_team_id)
                            );
                            $awayGoals = $playerMatch->events->filter(fn($e) =>
                                ($e->event_type === 'goal' && $e->team_id === $playerMatch->away_team_id) ||
                                ($e->event_type === 'own_goal' && $e->team_id === $playerMatch->home_team_id)
                            );
                            $cards = $playerMatch->events->filter(fn($e) => in_array($e->event_type, ['yellow_card', 'red_card']));
                        @endphp
                        <div class="mb-8 p-6 rounded-xl bg-gradient-to-r from-sky-50 to-sky-100 border border-sky-200">
                            <div class="flex items-center justify-center gap-6">
                                <div class="flex items-center gap-3 flex-1 justify-end">
                                    <span class="text-xl font-semibold">
                                        {{ $playerMatch->homeTeam->name }}
                                    </span>
                                    <img src="{{ $playerMatch->homeTeam->image }}" class="w-12 h-12">
                                </div>
                                <div class="text-4xl font-semibold px-6">
                                    {{ $playerMatch->home_score }} - {{ $playerMatch->away_score }}
                                </div>
                                <div class="flex items-center gap-3 flex-1">
                                    <img src="{{ $playerMatch->awayTeam->image }}" class="w-12 h-12">
                                    <span class="text-xl font-semibold">
                                        {{ $playerMatch->awayTeam->name }}
                                    </span>
                                </div>
                            </div>

                            {{-- Goal scorers --}}
                            @if($homeGoals->isNotEmpty() || $awayGoals->isNotEmpty())
                                <div class="mt-4 pt-4 border-t border-sky-200">
                                    <div class="flex gap-8 text-sm">
                                        <div class="flex-1 text-right">
                                            @foreach($homeGoals->sortBy('minute') as $event)
                                                <div class="text-slate-600">
                                                    {{ $event->gamePlayer->player->name }}
                                                    @if($event->event_type === 'own_goal')<span class="text-red-500">(OG)</span>@endif
                                                    <span class="text-slate-400">{{ $event->minute }}'</span>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="flex-1">
                                            @foreach($awayGoals->sortBy('minute') as $event)
                                                <div class="text-slate-600">
                                                    {{ $event->gamePlayer->player->name }}
                                                    @if($event->event_type === 'own_goal')<span class="text-red-500">(OG)</span>@endif
                                                    <span class="text-slate-400">{{ $event->minute }}'</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Cards --}}
                            @php
                                $homeCards = $cards->filter(fn($e) => $e->team_id === $playerMatch->home_team_id)->sortBy('minute');
                                $awayCards = $cards->filter(fn($e) => $e->team_id === $playerMatch->away_team_id)->sortBy('minute');
                            @endphp
                            @if($cards->isNotEmpty())
                                <div class="mt-3 pt-3 border-t border-sky-200">
                                    <div class="flex gap-8 text-xs text-slate-500">
                                        <div class="flex-1 text-right">
                                            @foreach($homeCards as $event)
                                                <div class="inline-flex items-center gap-1 justify-end">
                                                    {{ $event->gamePlayer->player->name }} {{ $event->minute }}'
                                                    @if($event->event_type === 'yellow_card')
                                                        <span class="w-2 h-3 bg-yellow-400 rounded-sm"></span>
                                                    @else
                                                        <span class="w-2 h-3 bg-red-500 rounded-sm"></span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="flex-1">
                                            @foreach($awayCards as $event)
                                                <div class="inline-flex items-center gap-1">
                                                    @if($event->event_type === 'yellow_card')
                                                        <span class="w-2 h-3 bg-yellow-400 rounded-sm"></span>
                                                    @else
                                                        <span class="w-2 h-3 bg-red-500 rounded-sm"></span>
                                                    @endif
                                                    {{ $event->gamePlayer->player->name }} {{ $event->minute }}'
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- All Results --}}
                    <h4 class="text-md font-semibold text-slate-600 mb-3">All Results</h4>
                    <div class="space-y-2">
                        @foreach($matches as $match)
                            <div class="flex items-center p-4 rounded-lg @if($match->id === $playerMatch?->id) bg-sky-50 @else bg-slate-50 @endif">
                                <div class="flex items-center gap-2 flex-1 justify-end">
                                    <span class="@if($match->home_score > $match->away_score) font-semibold @endif">
                                        {{ $match->homeTeam->name }}
                                    </span>
                                    <img src="{{ $match->homeTeam->image }}" class="w-6 h-6">
                                </div>
                                <div class="px-6 font-semibold text-lg">
                                    {{ $match->home_score }} - {{ $match->away_score }}
                                </div>
                                <div class="flex items-center gap-2 flex-1">
                                    <img src="{{ $match->awayTeam->image }}" class="w-6 h-6">
                                    <span class="@if($match->away_score > $match->home_score) font-semibold @endif">
                                        {{ $match->awayTeam->name }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
