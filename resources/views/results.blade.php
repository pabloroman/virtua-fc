@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match" :continue-to-home="true"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">

            {{-- Title bar --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    @if($matches->first()->round_name)
                        <h3 class="font-semibold text-xl text-slate-900">
                            @if($competition)
                                <span>{{ $competition->name }} &centerdot;</span>
                            @endif
                            {{ __('game.matchday_results', ['name' => $matches->first()?->round_name]) }}</h3>
                    @else
                        <h3 class="font-semibold text-xl text-slate-900">
                            @if($competition)
                                <span>{{ $competition->name }} &centerdot;</span>
                            @endif
                        {{ __('game.matchday_results', ['name' => __('game.matchday_n', ['number' => $matchday])]) }}</h3>
                    @endif
                </div>
            </div>

            {{-- Player's Match Card --}}
            @if($playerMatch)
                @php
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

                <div class="bg-slate-800 rounded-xl overflow-hidden mb-6">
                    <div class="p-6">
                        {{-- Teams & Score --}}
                        <div class="flex items-center justify-center gap-6">
                            <div class="flex items-center gap-3 flex-1 justify-end">
                                <span class="text-xl font-semibold text-white">{{ $playerMatch->homeTeam->name }}</span>
                                <img src="{{ $playerMatch->homeTeam->image }}" class="w-14 h-14">
                            </div>
                            <div class="text-5xl font-bold text-white tabular-nums px-6">
                                {{ $playerMatch->home_score }} <span class="text-slate-500 mx-1">-</span> {{ $playerMatch->away_score }}
                            </div>
                            <div class="flex items-center gap-3 flex-1">
                                <img src="{{ $playerMatch->awayTeam->image }}" class="w-14 h-14">
                                <span class="text-xl font-semibold text-white">{{ $playerMatch->awayTeam->name }}</span>
                            </div>
                        </div>

                        {{-- Goal scorers --}}
                        @if($homeGoals->isNotEmpty() || $awayGoals->isNotEmpty())
                            <div class="mt-4 pt-4 border-t border-slate-700">
                                <div class="flex gap-8 text-sm">
                                    <div class="flex-1 text-right">
                                        @foreach($homeGoals->sortBy('minute') as $event)
                                            <div class="text-slate-300">
                                                {{ $event->gamePlayer->player->name }}
                                                @if($event->event_type === 'own_goal')<span class="text-red-400">({{ __('game.og') }})</span>@endif
                                                <span class="text-slate-500">{{ $event->minute }}'</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="flex-1">
                                        @foreach($awayGoals->sortBy('minute') as $event)
                                            <div class="text-slate-300">
                                                {{ $event->gamePlayer->player->name }}
                                                @if($event->event_type === 'own_goal')<span class="text-red-400">({{ __('game.og') }})</span>@endif
                                                <span class="text-slate-500">{{ $event->minute }}'</span>
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
                            <div class="mt-3 pt-3 border-t border-slate-700">
                                <div class="flex gap-8 text-xs text-slate-400">
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
                </div>
            @endif

            {{-- All Results --}}
            <div class="space-y-1">
                @foreach($matches as $match)
                    <div class="flex items-center py-3 px-4 rounded-lg {{ $match->id === $playerMatch?->id ? 'bg-slate-200' : 'bg-slate-50' }}">
                        <div class="flex items-center gap-2 flex-1 justify-end">
                            <span class="text-sm truncate {{ ($match->home_score > $match->away_score) ? 'font-semibold text-slate-900' : 'text-slate-600' }}">
                                {{ $match->homeTeam->name }}
                            </span>
                            <img src="{{ $match->homeTeam->image }}" class="w-6 h-6">
                        </div>
                        <div class="px-4 font-semibold tabular-nums text-slate-900">
                            {{ $match->home_score }} - {{ $match->away_score }}
                        </div>
                        <div class="flex items-center gap-2 flex-1">
                            <img src="{{ $match->awayTeam->image }}" class="w-6 h-6">
                            <span class="text-sm truncate {{ ($match->away_score > $match->home_score) ? 'font-semibold text-slate-900' : 'text-slate-600'  }}">
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
