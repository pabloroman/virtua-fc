@props(['game', 'nextMatch' => null])

<div class="flex justify-between text-gray-400">
    <div class="flex items-center space-x-4">
        <img src="{{ $game->team->image }}" class="w-16 h-16">
        <div>
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ $game->team->name }}
            </h2>
            <p>{{ $game->season }} Season - Matchday {{ $game->current_matchday ?: 'Pre-season' }}</p>
        </div>
    </div>
    <div class="text-right flex items-center space-x-4">
        @if($nextMatch)
        <div>
            <div class="text-xs">Next match - {{ $nextMatch->scheduled_date->format('d/m/Y') }}</div>
            <div class="flex items-center space-x-1">
                <img class="w-4 h-4" src="{{ $nextMatch->homeTeam->image }}">
                <span>{{ $nextMatch->homeTeam->name }}</span>
                <span> vs </span>
                <span>{{ $nextMatch->awayTeam->name }}</span>
                <img class="w-4 h-4" src="{{ $nextMatch->awayTeam->image }}">
            </div>
        </div>
        <form method="post" action="{{ route('game.advance', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
            @csrf
            <x-primary-button-spin>Continue</x-primary-button-spin>
        </form>
        @else
        <div class="text-white">Season Complete!</div>
        @endif
    </div>
</div>

<nav class="flex text-white/40 space-x-4 mt-4 items-center text-xl">
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'show-game') text-white @endif" href="{{ route('show-game', $game->id) }}">Dashboard</a></div>
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.squad') text-white @endif" href="{{ route('game.squad', $game->id) }}">Squad</a></div>
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.calendar') text-white @endif" href="{{ route('game.calendar', $game->id) }}">Calendar</a></div>
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.standings') text-white @endif" href="{{ route('game.standings', $game->id) }}">Standings</a></div>
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.cup') text-white @endif" href="{{ route('game.cup', $game->id) }}">Copa del Rey</a></div>
</nav>
