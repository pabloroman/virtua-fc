@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match" :continue-to-home="true"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            @if($matches->first()->round_name)
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    @if($competition)
                        <span>{{ __($competition->name) }} &centerdot;</span>
                    @endif
                    {{ __('game.matchday_results', ['name' => __($matches->first()?->round_name ?? '')]) }}
                </h2>
            @else
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    @if($competition)
                        <span>{{ __($competition->name) }} &centerdot;</span>
                    @endif
                    {{ __('game.matchday_results', ['name' => __('game.matchday_n', ['number' => $matchday])]) }}
                </h2>
            @endif
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

            <div class="bg-surface-800 rounded-xl border border-border-default overflow-hidden mb-6">
                <div class="p-4 md:p-6">
                    {{-- Teams & Score --}}
                    <div class="flex items-center justify-center gap-2 md:gap-6">
                        <div class="flex items-center gap-2 md:gap-3 flex-1 justify-end">
                            <span class="text-sm md:text-xl font-semibold text-white truncate">{{ $playerMatch->homeTeam->name }}</span>
                            <x-team-crest :team="$playerMatch->homeTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                        </div>
                        <div class="text-3xl md:text-5xl font-bold text-white tabular-nums px-2 md:px-6 shrink-0">
                            {{ $playerMatch->home_score }} <span class="text-text-muted mx-1">-</span> {{ $playerMatch->away_score }}
                        </div>
                        <div class="flex items-center gap-2 md:gap-3 flex-1">
                            <x-team-crest :team="$playerMatch->awayTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                            <span class="text-sm md:text-xl font-semibold text-white truncate">{{ $playerMatch->awayTeam->name }}</span>
                        </div>
                    </div>

                    {{-- Goal scorers --}}
                    @if($homeGoals->isNotEmpty() || $awayGoals->isNotEmpty())
                        <div class="mt-4 pt-4 border-t border-border-default">
                            <div class="flex gap-8 text-sm">
                                <div class="flex-1 text-right">
                                    @foreach($homeGoals->sortBy('minute') as $event)
                                        <div class="text-text-body">
                                            {{ $event->gamePlayer->player->name }}
                                            @if($event->event_type === 'own_goal')<span class="text-red-400">({{ __('game.og') }})</span>@endif
                                            <span class="text-text-muted">{{ $event->minute }}'</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="flex-1">
                                    @foreach($awayGoals->sortBy('minute') as $event)
                                        <div class="text-text-body">
                                            {{ $event->gamePlayer->player->name }}
                                            @if($event->event_type === 'own_goal')<span class="text-red-400">({{ __('game.og') }})</span>@endif
                                            <span class="text-text-muted">{{ $event->minute }}'</span>
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
                        <div class="mt-3 pt-3 border-t border-border-default">
                            <div class="flex gap-8 text-xs text-text-secondary">
                                <div class="flex-1 text-right">
                                    @foreach($homeCards as $event)
                                        <div class="inline-flex items-center gap-1 justify-end">
                                            {{ $event->gamePlayer->player->name }} {{ $event->minute }}'
                                            @if($event->event_type === 'yellow_card')
                                                <span class="w-2 h-3 bg-yellow-400 rounded-xs"></span>
                                            @else
                                                <span class="w-2 h-3 bg-accent-red rounded-xs"></span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <div class="flex-1">
                                    @foreach($awayCards as $event)
                                        <div class="inline-flex items-center gap-1">
                                            @if($event->event_type === 'yellow_card')
                                                <span class="w-2 h-3 bg-yellow-400 rounded-xs"></span>
                                            @else
                                                <span class="w-2 h-3 bg-accent-red rounded-xs"></span>
                                            @endif
                                            {{ $event->gamePlayer->player->name }} {{ $event->minute }}'
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- MVP of the Match --}}
                    @if($playerMatch->mvpPlayer)
                        <div class="mt-3 pt-3 border-t border-border-default">
                            <div class="flex items-center justify-center gap-2 text-sm">
                                <span class="text-accent-yellow text-base">&#9733;</span>
                                <span class="text-text-secondary">{{ __('game.mvp') }}</span>
                                <span class="font-semibold text-text-primary">{{ $playerMatch->mvpPlayer->player->name }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded bg-surface-700 text-text-muted">
                                    {{ $playerMatch->mvpPlayer->team_id === $playerMatch->home_team_id ? $playerMatch->homeTeam->name : $playerMatch->awayTeam->name }}
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- All Results --}}
        <div class="bg-surface-800 rounded-xl border border-border-default p-4 md:p-6">
            <div class="space-y-1">
                @foreach($matches as $match)
                    <div class="flex items-center py-3 px-4 rounded-lg {{ $match->id === $playerMatch?->id ? 'bg-surface-600' : 'bg-surface-700/50' }}">
                        <div class="flex items-center gap-2 flex-1 justify-end">
                            <span class="text-sm truncate {{ ($match->home_score > $match->away_score) ? 'font-semibold text-text-primary' : 'text-text-secondary' }}">
                                {{ $match->homeTeam->name }}
                            </span>
                            <x-team-crest :team="$match->homeTeam" class="w-6 h-6" />
                        </div>
                        <div class="px-4 font-semibold tabular-nums text-text-primary">
                            {{ $match->home_score }} - {{ $match->away_score }}
                        </div>
                        <div class="flex items-center gap-2 flex-1">
                            <x-team-crest :team="$match->awayTeam" class="w-6 h-6" />
                            <span class="text-sm truncate {{ ($match->away_score > $match->home_score) ? 'font-semibold text-text-primary' : 'text-text-secondary'  }}">
                                {{ $match->awayTeam->name }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
