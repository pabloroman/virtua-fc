@props(['game', 'nextMatch' => null, 'continueToHome' => false])

@php
    // Get competitions the team participates in for this season
    $teamCompetitions = $game->team->competitions()
        ->wherePivot('season', $game->season)
        ->orderBy('tier')
        ->get();
@endphp

<div class="flex justify-between text-slate-400">
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
        @if($continueToHome)
            <x-primary-button-link :href="route('show-game', $game->id)">Continue</x-primary-button-link>
        @else
            <form method="post" action="{{ route('game.advance', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
                @csrf
                <x-primary-button-spin>Continue</x-primary-button-spin>
            </form>
        @endif
        @else
        <div class="flex items-center space-x-4">
            <div class="text-white">Season Complete!</div>
            <a href="{{ route('game.season-end', $game->id) }}"
               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-amber-500 to-yellow-400 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:from-amber-600 hover:to-yellow-500 transition-all">
                View Season Summary
            </a>
        </div>
        @endif
    </div>
</div>

<nav class="flex text-white/40 space-x-4 mt-4 items-center text-xl">
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'show-game') text-white @endif" href="{{ route('show-game', $game->id) }}">Dashboard</a></div>
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.squad') text-white @endif" href="{{ route('game.squad', $game->id) }}">Squad</a></div>
    @if($nextMatch)
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.lineup') text-white @endif" href="{{ route('game.lineup', [$game->id, $nextMatch->id]) }}">Starting XI</a></div>
    @endif
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.finances') text-white @endif" href="{{ route('game.finances', $game->id) }}">Finances</a></div>
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.transfers') text-white @endif" href="{{ route('game.transfers', $game->id) }}">Transfers</a></div>
    <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.calendar') text-white @endif" href="{{ route('game.calendar', $game->id) }}">Calendar</a></div>
    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
        <button @click="open = !open" class="hover:text-slate-300 flex items-center gap-1 @if(Route::currentRouteName() == 'game.competition') text-white @endif">
            Competitions
            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5" style="display: none;">
            <div class="py-1">
                @foreach($teamCompetitions as $competition)
                <a href="{{ route('game.competition', [$game->id, $competition->id]) }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 @if(request()->route('competitionId') == $competition->id) bg-slate-100 font-semibold @endif">
                    {{ $competition->name }}
                </a>
                @endforeach
            </div>
        </div>
    </div>
</nav>
