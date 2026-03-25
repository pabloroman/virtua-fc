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
    {{-- Sticky Header --}}
    <header class="sticky top-0 z-50 bg-surface-900/95 backdrop-blur-md border-b border-border-default">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between pt-0 py-2">
                {{-- Left: Team badge + name --}}
                <div class="flex items-center gap-3">
                    {{-- Hamburger (mobile) --}}
                    <x-icon-button @click="mobileMenuOpen = true" class="lg:hidden" aria-label="Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                    </x-icon-button>
                    {{-- Team badge + name --}}
                    <div class="flex items-center gap-2.5">
                        <x-team-crest :team="$game->team" class="w-8 h-8 shrink-0" />
                        <div class="hidden sm:block">
                            <h1 class="font-heading font-semibold text-base text-text-primary leading-none tracking-wide uppercase">{{ $game->team->name }}</h1>
                            <p class="text-[10px] text-text-muted uppercase tracking-widest mt-0.5">
                                @if($game->game_mode === \App\Models\Game::MODE_CAREER)
                                    {{ __('game.season') }} {{ $game->formatted_season }}
                                @elseif($game->game_mode === \App\Models\Game::MODE_TOURNAMENT)
                                    {{ __($teamCompetitions[0]->name ?? '') }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Center: Desktop nav --}}
                <nav class="hidden lg:flex items-center gap-1">
                    <a href="{{ route('show-game', $game->id) }}" class="nav-item @if(Route::currentRouteName() == 'show-game') active @endif whitespace-nowrap px-3 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'show-game' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.dashboard') }}</a>
                    <a href="{{ route('game.squad', $game->id) }}" class="nav-item @if(Str::startsWith(Route::currentRouteName(), 'game.squad')) active @endif whitespace-nowrap px-3 py-2 text-xs font-medium uppercase tracking-wider {{ Str::startsWith(Route::currentRouteName(), 'game.squad') ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.squad') }}</a>
                    @if($nextMatch)
                    <a href="{{ route('game.lineup', $game->id) }}" class="nav-item @if(Route::currentRouteName() == 'game.lineup') active @endif whitespace-nowrap px-3 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'game.lineup' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.starting_xi') }}</a>
                    @endif
                    @if($game->isCareerMode())
                    <a href="{{ route('game.finances', $game->id) }}" class="nav-item @if(Route::currentRouteName() == 'game.finances') active @endif whitespace-nowrap px-3 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'game.finances' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.finances') }}</a>
                    <a href="{{ route('game.transfers', $game->id) }}" class="nav-item @if(in_array(Route::currentRouteName(), ['game.transfers', 'game.transfers.outgoing', 'game.scouting', 'game.explore'])) active @endif whitespace-nowrap px-3 py-2 text-xs font-medium uppercase tracking-wider {{ in_array(Route::currentRouteName(), ['game.transfers', 'game.transfers.outgoing', 'game.scouting', 'game.explore']) ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.transfers') }}</a>
                    @endif
                    <a href="{{ route('game.calendar', $game->id) }}" class="nav-item @if(Route::currentRouteName() == 'game.calendar') active @endif whitespace-nowrap px-3 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'game.calendar' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.calendar') }}</a>
                    @if($game->isTournamentMode() && $teamCompetitions->isNotEmpty())
                    <a href="{{ route('game.competition', [$game->id, $teamCompetitions[0]->id]) }}" class="nav-item @if(Route::currentRouteName() == 'game.competition') active @endif whitespace-nowrap px-3 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'game.competition' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('game.standings') }}</a>
                    @else
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="nav-item @if(Route::currentRouteName() == 'game.competition') active @endif inline-flex items-center gap-1 whitespace-nowrap px-3 py-2 text-xs font-medium uppercase tracking-wider transition-colors {{ Route::currentRouteName() == 'game.competition' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">
                            {{ __('app.competitions') }}
                            <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-48 rounded-lg shadow-xl bg-surface-800 border border-border-strong" style="display: none;">
                            <div class="py-1">
                                @foreach($teamCompetitions as $competition)
                                <a href="{{ route('game.competition', [$game->id, $competition->id]) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-surface-700 hover:text-text-primary @if(request()->route('competitionId') == $competition->id) bg-surface-700 text-text-primary font-semibold @endif">
                                    {{ __($competition->name) }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                </nav>

                {{-- Right: Next match + action button --}}
                <div class="flex items-center gap-3">
                    @if($nextMatch)
                        <div class="hidden sm:flex items-center gap-2 bg-surface-700/50 rounded-lg px-3 py-1.5">
                            <span class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('game.next_match') }}</span>
                            <div class="flex items-center gap-1">
                                <x-team-crest :team="$nextMatch->homeTeam" class="w-4 h-4" />
                                <span class="text-xs font-semibold text-text-primary font-heading tracking-wide">vs</span>
                                <x-team-crest :team="$nextMatch->awayTeam" class="w-4 h-4" />
                            </div>
                        </div>
                        @if($game->hasPendingActions())
                            @php $pendingAction = $game->getFirstPendingAction(); @endphp
                            <x-primary-button-link color="amber" :href="$pendingAction && $pendingAction['route'] ? route($pendingAction['route'], $game->id) : route('show-game', $game->id)" class="whitespace-nowrap gap-2 animate-pulse">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <span class="hidden sm:inline">{{ __('messages.action_required_short') }}</span>
                            </x-primary-button-link>
                        @elseif($continueToHome)
                            <x-primary-button-link :href="route('show-game', $game->id)">{{ __('app.continue') }}</x-primary-button-link>
                        @else
                            <x-primary-button type="button" @click="$dispatch('show-pre-match', @js(route('game.pre-match-data', $game->id)))">
                                {{ __('app.continue') }}
                            </x-primary-button>
                        @endif
                    @else
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-text-secondary">{{ __('game.season_complete') }}</span>
                            <x-primary-button-link color="amber" :href="route('game.season-end', $game->id)">
                                {{ __('game.view_season_summary') }}
                            </x-primary-button-link>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    {{-- Mobile Slide-out Drawer --}}
    <div x-show="mobileMenuOpen" x-cloak class="fixed inset-0 z-50 lg:hidden">
        {{-- Backdrop --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="mobileMenuOpen = false"
             class="fixed inset-0 bg-black/60"></div>

        {{-- Drawer Panel --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 w-72 bg-surface-800 border-r border-border-default shadow-xl overflow-y-auto">

            {{-- Drawer Header --}}
            <div class="flex items-center justify-between p-4 border-b border-border-strong">
                <div class="flex items-center gap-3 min-w-0">
                    <x-team-crest :team="$game->team" class="w-10 h-10 shrink-0" />
                    <div class="min-w-0">
                        <h3 class="font-heading font-semibold text-sm text-text-primary truncate uppercase tracking-wide">{{ $game->team->name }}</h3>
                        <p class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.season') }} {{ $game->formatted_season }}</p>
                    </div>
                </div>
                <x-icon-button @click="mobileMenuOpen = false">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- Next Match Info --}}
            @if($nextMatch)
            <div class="px-4 py-3 border-b border-border-default bg-surface-700/30">
                <div class="text-[10px] text-text-muted uppercase tracking-wider mb-1">{{ __('game.next_match') }} - {{ $nextMatch->scheduled_date->format('d/m/Y') }}</div>
                <div class="flex items-center gap-1 text-sm text-text-body">
                    <x-team-crest :team="$nextMatch->homeTeam" class="w-4 h-4" />
                    <span class="truncate">{{ $nextMatch->homeTeam->name }}</span>
                    <span class="text-text-muted">vs</span>
                    <span class="truncate">{{ $nextMatch->awayTeam->name }}</span>
                    <x-team-crest :team="$nextMatch->awayTeam" class="w-4 h-4" />
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
                <x-responsive-nav-link :href="route('game.lineup', $game->id)" :active="Route::currentRouteName() == 'game.lineup'">
                    {{ __('app.starting_xi') }}
                </x-responsive-nav-link>
                @endif
                @if($game->isCareerMode())
                <x-responsive-nav-link :href="route('game.finances', $game->id)" :active="Route::currentRouteName() == 'game.finances'">
                    {{ __('app.finances') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('game.transfers', $game->id)" :active="in_array(Route::currentRouteName(), ['game.transfers', 'game.transfers.outgoing', 'game.scouting', 'game.explore'])">
                    {{ __('app.transfers') }}
                </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('game.calendar', $game->id)" :active="Route::currentRouteName() == 'game.calendar'">
                    {{ __('app.calendar') }}
                </x-responsive-nav-link>
                @if($game->isTournamentMode() && $teamCompetitions->isNotEmpty())
                <x-responsive-nav-link :href="route('game.competition', [$game->id, $teamCompetitions[0]->id])" :active="Route::currentRouteName() == 'game.competition'">
                    {{ __('game.standings') }}
                </x-responsive-nav-link>
                @endif
            </nav>

            {{-- Competitions (career mode only) --}}
            @if($game->isCareerMode() && $teamCompetitions->isNotEmpty())
            <div class="border-t border-border-default py-2">
                <div class="px-4 py-2 text-[10px] font-semibold text-text-muted uppercase tracking-widest">
                    {{ __('app.competitions') }}
                </div>
                @foreach($teamCompetitions as $competition)
                <x-responsive-nav-link :href="route('game.competition', [$game->id, $competition->id])" :active="request()->route('competitionId') == $competition->id">
                    {{ __($competition->name) }}
                </x-responsive-nav-link>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Pre-Match Confirmation Modal --}}
    @if($nextMatch && !$game->hasPendingActions() && !$continueToHome)
    <div x-data="{
        loading: false,
        content: '',
        loadPreMatch(url) {
            this.content = '';
            this.loading = true;
            this.$dispatch('open-modal', 'pre-match');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => { this.content = html; this.loading = false; })
                .catch(() => { this.loading = false; });
        }
    }" @show-pre-match.window="loadPreMatch($event.detail)">
        <x-modal name="pre-match" maxWidth="lg">
            <x-modal-header modalName="pre-match">{{ __('messages.pre_match_title') }}</x-modal-header>
            <div class="p-4 md:p-6">
                {{-- Loading spinner --}}
                <div x-show="loading" class="flex items-center justify-center py-12">
                    <svg class="animate-spin h-8 w-8 text-text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                {{-- Server-rendered content --}}
                <div x-show="!loading" x-html="content"></div>
            </div>
        </x-modal>
    </div>
    @endif
</div>
