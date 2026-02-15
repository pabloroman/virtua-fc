@props(['game', 'nextMatch' => null, 'continueToHome' => false])

@php
    // Get competitions the team participates in for this game
    $teamCompetitions = \App\Models\Competition::whereIn('id',
        $game->competitionEntries()
            ->where('team_id', $game->team_id)
            ->pluck('competition_id')
    )->orderBy('tier')->get();
@endphp

<div x-data="{ mobileMenuOpen: false }">
    {{-- Desktop Header --}}
    <div class="hidden md:flex justify-between text-slate-400">
        <div class="flex items-center space-x-4">
            <img src="{{ $game->team->image }}" class="w-16 h-16">
            <div>
                <h2 class="font-semibold text-xl text-white leading-tight">
                    {{ $game->team->name }}
                </h2>
                <p>{{ __('game.season') }} {{ $game->formatted_season }}</p>
            </div>
        </div>
        <div class="text-right flex items-center space-x-4">
            @if($nextMatch)
            <div>
                <div class="text-xs">{{ __('game.next_match') }} - {{ $nextMatch->scheduled_date->format('d/m/Y') }}</div>
                <div class="flex items-center space-x-1">
                    <img class="w-4 h-4" src="{{ $nextMatch->homeTeam->image }}">
                    <span>{{ $nextMatch->homeTeam->name }}</span>
                    <span> vs </span>
                    <span>{{ $nextMatch->awayTeam->name }}</span>
                    <img class="w-4 h-4" src="{{ $nextMatch->awayTeam->image }}">
                </div>
            </div>
            @if($continueToHome)
                <x-primary-button-link :href="route('show-game', $game->id)">{{ __('app.continue') }}</x-primary-button-link>
            @else
                <form method="post" action="{{ route('game.advance', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
                    @csrf
                    <x-primary-button-spin>{{ __('app.continue') }}</x-primary-button-spin>
                </form>
            @endif
            @else
            <div class="flex items-center space-x-4">
                <div class="text-white">{{ __('game.season_complete') }}</div>
                <a href="{{ route('game.season-end', $game->id) }}"
                   class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-amber-500 to-yellow-400 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-wide hover:from-amber-600 hover:to-yellow-500 transition-all">
                    {{ __('game.view_season_summary') }}
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- Desktop Navigation --}}
    <nav class="hidden md:flex text-white/40 space-x-4 mt-4 items-center text-xl">
        <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'show-game') text-white @endif" href="{{ route('show-game', $game->id) }}">{{ __('app.dashboard') }}</a></div>
        <div><a class="hover:text-slate-300 @if(Str::startsWith(Route::currentRouteName(), 'game.squad')) text-white @endif" href="{{ route('game.squad', $game->id) }}">{{ __('app.squad') }}</a></div>
        @if($nextMatch)
        <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.lineup') text-white @endif" href="{{ route('game.lineup', [$game->id, $nextMatch->id]) }}">{{ __('app.starting_xi') }}</a></div>
        @endif
        @if($game->isCareerMode())
        <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.finances') text-white @endif" href="{{ route('game.finances', $game->id) }}">{{ __('app.finances') }}</a></div>
        <div><a class="hover:text-slate-300 @if(in_array(Route::currentRouteName(), ['game.transfers', 'game.scouting'])) text-white @endif" href="{{ route('game.transfers', $game->id) }}">{{ __('app.transfers') }}</a></div>
        @endif
        <div><a class="hover:text-slate-300 @if(Route::currentRouteName() == 'game.calendar') text-white @endif" href="{{ route('game.calendar', $game->id) }}">{{ __('app.calendar') }}</a></div>
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button @click="open = !open" class="hover:text-slate-300 flex items-center gap-1 @if(Route::currentRouteName() == 'game.competition') text-white @endif">
                {{ __('app.competitions') }}
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

    {{-- Mobile Header --}}
    <div class="flex md:hidden items-center justify-between text-slate-400">
        <div class="flex items-center space-x-3 min-w-0">
            <img src="{{ $game->team->image }}" class="w-8 h-8 shrink-0">
            <div class="min-w-0">
                <h2 class="font-semibold text-base text-white leading-tight truncate">
                    {{ $game->team->name }}
                </h2>
                <p class="text-xs truncate">{{ __('game.season') }} {{ $game->formatted_season }}{{ $game->current_matchday ? ' - ' . __('game.matchday') . ' ' . $game->current_matchday : '' }}</p>
            </div>
        </div>
        <div class="flex items-center space-x-2 shrink-0">
            @if($nextMatch)
                @if($continueToHome)
                    <x-primary-button-link :href="route('show-game', $game->id)" class="text-xs! px-3">{{ __('app.continue') }}</x-primary-button-link>
                @else
                    <form method="post" action="{{ route('game.advance', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <x-primary-button-spin class="text-xs! px-3">{{ __('app.continue') }}</x-primary-button-spin>
                    </form>
                @endif
            @else
                <a href="{{ route('game.season-end', $game->id) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-amber-500 to-yellow-400 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-wide hover:from-amber-600 hover:to-yellow-500 transition-all">
                    {{ __('game.view_season_summary') }}
                </a>
            @endif
            <button @click="mobileMenuOpen = true" class="p-2 text-slate-300 hover:text-white min-h-[44px] min-w-[44px] flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Slide-out Drawer --}}
    <div x-show="mobileMenuOpen" x-cloak class="fixed inset-0 z-50 md:hidden">
        {{-- Backdrop --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="mobileMenuOpen = false"
             class="fixed inset-0 bg-black/50"></div>

        {{-- Drawer Panel --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 w-72 bg-white shadow-xl overflow-y-auto">

            {{-- Drawer Header --}}
            <div class="flex items-center justify-between p-4 border-b border-slate-200 bg-slate-50">
                <div class="flex items-center space-x-3 min-w-0">
                    <img src="{{ $game->team->image }}" class="w-10 h-10 shrink-0">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-sm text-slate-900 truncate">{{ $game->team->name }}</h3>
                        <p class="text-xs text-slate-500">{{ __('game.season') }} {{ $game->formatted_season }}</p>
                    </div>
                </div>
                <button @click="mobileMenuOpen = false" class="p-2 text-slate-400 hover:text-slate-600 min-h-[44px] min-w-[44px] flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Next Match Info --}}
            @if($nextMatch)
            <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/50">
                <div class="text-xs text-slate-500 mb-1">{{ __('game.next_match') }} - {{ $nextMatch->scheduled_date->format('d/m/Y') }}</div>
                <div class="flex items-center space-x-1 text-sm text-slate-700">
                    <img class="w-4 h-4" src="{{ $nextMatch->homeTeam->image }}">
                    <span class="truncate">{{ $nextMatch->homeTeam->name }}</span>
                    <span class="text-slate-400">vs</span>
                    <span class="truncate">{{ $nextMatch->awayTeam->name }}</span>
                    <img class="w-4 h-4" src="{{ $nextMatch->awayTeam->image }}">
                </div>
            </div>
            @endif

            {{-- Navigation Links --}}
            <nav class="py-2">
                <x-responsive-nav-link :href="route('show-game', $game->id)" :active="Route::currentRouteName() == 'show-game'">
                    {{ __('app.dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('game.squad', $game->id)" :active="Str::startsWith(Route::currentRouteName(), 'game.squad')">
                    {{ __('app.squad') }}
                </x-responsive-nav-link>
                @if($nextMatch)
                <x-responsive-nav-link :href="route('game.lineup', [$game->id, $nextMatch->id])" :active="Route::currentRouteName() == 'game.lineup'">
                    {{ __('app.starting_xi') }}
                </x-responsive-nav-link>
                @endif
                @if($game->isCareerMode())
                <x-responsive-nav-link :href="route('game.finances', $game->id)" :active="Route::currentRouteName() == 'game.finances'">
                    {{ __('app.finances') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('game.transfers', $game->id)" :active="in_array(Route::currentRouteName(), ['game.transfers', 'game.scouting'])">
                    {{ __('app.transfers') }}
                </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('game.calendar', $game->id)" :active="Route::currentRouteName() == 'game.calendar'">
                    {{ __('app.calendar') }}
                </x-responsive-nav-link>
            </nav>

            {{-- Competitions --}}
            @if($teamCompetitions->isNotEmpty())
            <div class="border-t border-slate-200 py-2">
                <div class="px-4 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                    {{ __('app.competitions') }}
                </div>
                @foreach($teamCompetitions as $competition)
                <x-responsive-nav-link :href="route('game.competition', [$game->id, $competition->id])" :active="request()->route('competitionId') == $competition->id">
                    {{ $competition->name }}
                </x-responsive-nav-link>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
