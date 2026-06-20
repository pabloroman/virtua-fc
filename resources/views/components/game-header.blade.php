@props(['game', 'nextMatch' => null, 'continueToHome' => false])

@php
    // Get competitions the team participates in for this game
    $teamCompetitions = \App\Models\Competition::whereIn('id',
        $game->competitionEntries()
            ->where('team_id', $game->team_id)
            ->pluck('competition_id')
    )->orderBy('tier')->get();

    // Notifications for mobile bell icon + modal
    $unreadCount = $game->notifications()->whereNull('read_at')->count();
    $recentNotifications = $game->notifications()->orderByDesc('game_date')->limit(20)->get();

    // Highest-stakes (CRITICAL) notifications that haven't been acknowledged yet
    // surface as a blocking, must-dismiss popup on the next page load so they
    // can't be missed (e.g. a purchase offer for one of your players). All pending
    // criticals of the most-recent type are shown together as one group (single
    // dismiss + single action, since they route to the same page); other types
    // follow as their own group on subsequent loads.
    $criticalAlerts = app(\App\Modules\Notification\Services\NotificationService::class)
        ->pendingCriticalAlertGroup($game->id);
@endphp

<div x-data>
    {{-- Sticky Header --}}
    <header class="sticky top-0 z-50 bg-surface-900/95 backdrop-blur-md border-b border-border-default">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between pt-0 py-2">
                {{-- Left: Team badge + name (links to dashboard) --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('show-game', $game->id) }}" class="flex items-center gap-2.5 rounded-md -mx-1 px-1 py-0.5 hover:bg-surface-700/50 transition-colors" aria-label="{{ __('app.dashboard') }}">
                        <x-team-crest :team="$game->team" class="w-8 h-8 shrink-0" />
                        <div class="min-w-0">
                            <h1 class="font-heading font-semibold text-base text-text-primary leading-none tracking-wide uppercase truncate">{{ $game->team->name }}</h1>
                            <p class="text-[10px] text-text-muted tracking-widest mt-0.5">
                                @if($game->isCareerMode() && $game->current_date)
                                    <span class="inline-flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3">
                                            <path fill-rule="evenodd" d="M4 1.75a.75.75 0 0 1 1.5 0V3h5V1.75a.75.75 0 0 1 1.5 0V3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2V1.75ZM4.5 6a1 1 0 0 0-1 1v4.5a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-7Z" clip-rule="evenodd" />
                                        </svg>
                                        {{ Str::upper($game->current_date->locale(app()->getLocale())->translatedFormat('j F Y')) }}
                                    </span>
                                @elseif($game->isTournamentMode())
                                    <span class="uppercase">{{ __($teamCompetitions[0]->name ?? '') }}</span>
                                @endif
                            </p>
                        </div>
                    </a>
                </div>

                {{-- Center: Desktop nav --}}
                <nav class="hidden lg:flex items-center gap-0">
                    @if($game->isCareerMode())
                    @php
                        $squadActive = Str::startsWith(Route::currentRouteName(), 'game.squad');
                        $squadSecondary = $game->isFilial()
                            ? ['route' => 'game.squad.reserve', 'label' => __('squad.reserve_team')]
                            : ['route' => 'game.squad.academy', 'label' => __('squad.academy')];
                    @endphp
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="nav-item @if($squadActive) active @endif inline-flex items-center gap-1 whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider transition-colors {{ $squadActive ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">
                            {{ __('app.squad') }}
                            <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-48 rounded-lg shadow-xl bg-surface-800 border border-border-strong" style="display: none;">
                            <div class="py-1">
                                <a href="{{ route('game.squad', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.squad' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('squad.first_team') }}</a>
                                <a href="{{ route('game.squad.planner', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.squad.planner' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('planner.planner') }}</a>
                                <a href="{{ route($squadSecondary['route'], $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == $squadSecondary['route'] ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ $squadSecondary['label'] }}</a>
                                <a href="{{ route('game.squad.registration', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.squad.registration' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('squad.registration') }}</a>
                            </div>
                        </div>
                    </div>
                    @else
                    <a href="{{ route('game.squad', $game->id) }}" class="nav-item @if(Str::startsWith(Route::currentRouteName(), 'game.squad')) active @endif whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider {{ Str::startsWith(Route::currentRouteName(), 'game.squad') ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.squad') }}</a>
                    @endif
                    @if($nextMatch)
                    <a href="{{ route('game.lineup', $game->id) }}" class="nav-item @if(Route::currentRouteName() == 'game.lineup') active @endif whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'game.lineup' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.starting_xi') }}</a>
                    @endif
                    @if($game->isCareerMode())
                    @php
                        $clubRoutes = ['game.club', 'game.club.finances', 'game.club.investment', 'game.club.stadium', 'game.club.commercial', 'game.club.reputation'];
                        $clubActive = in_array(Route::currentRouteName(), $clubRoutes);
                    @endphp
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="nav-item @if($clubActive) active @endif inline-flex items-center gap-1 whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider transition-colors {{ $clubActive ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">
                            {{ __('app.club') }}
                            <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-48 rounded-lg shadow-xl bg-surface-800 border border-border-strong" style="display: none;">
                            <div class="py-1">
                                <a href="{{ route('game.club.finances', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.club.finances' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('club.nav.finances') }}</a>
                                <a href="{{ route('game.club.investment', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.club.investment' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('club.nav.investment') }}</a>
                                <a href="{{ route('game.club.stadium', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.club.stadium' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('club.nav.stadium') }}</a>
                                <a href="{{ route('game.club.commercial', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.club.commercial' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('club.nav.commercial') }}</a>
                                <a href="{{ route('game.club.reputation', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.club.reputation' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('club.nav.reputation') }}</a>
                            </div>
                        </div>
                    </div>
                    @php
                        $transfersRoutes = ['game.transfers', 'game.transfers.outgoing', 'game.scouting', 'game.scouting.results', 'game.explore', 'game.explore.teams', 'game.explore.squad', 'game.explore.pool-teams', 'game.transfers.market'];
                        $transfersActive = in_array(Route::currentRouteName(), $transfersRoutes);
                    @endphp
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="nav-item @if($transfersActive) active @endif inline-flex items-center gap-1 whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider transition-colors {{ $transfersActive ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">
                            {{ __('app.transfers') }}
                            <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-48 rounded-lg shadow-xl bg-surface-800 border border-border-strong" style="display: none;">
                            <div class="py-1">
                                <a href="{{ route('game.transfers', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.transfers' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('transfers.incoming') }}</a>
                                <a href="{{ route('game.transfers.outgoing', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.transfers.outgoing' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('transfers.outgoing') }}</a>
                                <a href="{{ route('game.scouting', $game->id) }}" class="block px-4 py-2 text-sm {{ Str::startsWith(Route::currentRouteName(), 'game.scouting') ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('transfers.scouting_tab') }}</a>
                                <a href="{{ route('game.explore', $game->id) }}" class="block px-4 py-2 text-sm {{ Str::startsWith(Route::currentRouteName(), 'game.explore') ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('transfers.explore_tab') }}</a>
                                <a href="{{ route('game.transfers.market', $game->id) }}" class="block px-4 py-2 text-sm {{ Route::currentRouteName() == 'game.transfers.market' ? 'bg-surface-700 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700 hover:text-text-primary' }}">{{ __('transfers.market_tab') }}</a>
                            </div>
                        </div>
                    </div>
                    @endif
                    <a href="{{ route('game.calendar', $game->id) }}" class="nav-item @if(Route::currentRouteName() == 'game.calendar') active @endif whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'game.calendar' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('app.calendar') }}</a>
                    @if($game->isTournamentMode() && $teamCompetitions->isNotEmpty())
                    <a href="{{ route('game.competition', [$game->id, $teamCompetitions[0]->id]) }}" class="nav-item @if(Route::currentRouteName() == 'game.competition') active @endif whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'game.competition' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('game.standings') }}</a>
                    @else
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="nav-item @if(Route::currentRouteName() == 'game.competition') active @endif inline-flex items-center gap-1 whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider transition-colors {{ Route::currentRouteName() == 'game.competition' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">
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
                    @if($game->isProManagerMode())
                    <a href="{{ route('game.manager.career', $game->id) }}" class="nav-item @if(Route::currentRouteName() == 'game.manager.career') active @endif whitespace-nowrap px-2 py-2 text-xs font-medium uppercase tracking-wider {{ Route::currentRouteName() == 'game.manager.career' ? 'text-text-primary' : 'text-text-muted hover:text-text-body' }}">{{ __('manager.career_title') }}</a>
                    @endif
                </nav>

                {{-- Right: Notification bell + action button --}}
                <div class="flex items-center gap-2">
                    {{-- Mobile notification bell --}}
                    <button
                        @click="$dispatch('open-modal', 'notifications-mobile')"
                        class="lg:hidden relative inline-flex items-center justify-center p-2 min-h-[44px] min-w-[44px] rounded-sm text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors shrink-0"
                        aria-label="{{ __('notifications.inbox') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                        </svg>
                        @if($unreadCount > 0)
                        <span class="absolute top-1 right-1 min-w-[16px] h-4 px-0.5 rounded-full bg-accent-red text-white text-[8px] font-bold flex items-center justify-center">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                        @endif
                    </button>

                    @if($nextMatch)
                        <div class="hidden sm:flex items-center gap-2 bg-surface-700/50 rounded-lg px-2.5 py-1">
                            <x-team-crest :team="$nextMatch->homeTeam" class="w-6 h-6 cursor-help" x-data x-tooltip.raw="{{ $nextMatch->homeTeam->name }}" />
                            <span class="text-xs font-semibold text-text-muted font-heading tracking-wide">vs</span>
                            <x-team-crest :team="$nextMatch->awayTeam" class="w-6 h-6 cursor-help" x-data x-tooltip.raw="{{ $nextMatch->awayTeam->name }}" />
                        </div>
                        @if($game->hasPendingActions())
                            @php $pendingAction = $game->getFirstPendingAction(); @endphp
                            <x-primary-button-link size="sm" color="amber" :href="$pendingAction && $pendingAction['route'] ? route($pendingAction['route'], $game->id) : route('show-game', $game->id)" class="whitespace-nowrap gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <span class="hidden sm:inline">{{ __('messages.action_required_short') }}</span>
                            </x-primary-button-link>
                        @elseif($continueToHome)
                            <x-primary-button-link size="sm" :href="route('show-game', $game->id)">{{ __('app.continue') }}</x-primary-button-link>
                        @elseif($game->isFastMode())
                            <a href="{{ route('game.fast-mode', $game->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 min-h-[36px] text-xs font-semibold uppercase tracking-wider rounded-lg bg-accent-blue/10 text-accent-blue border border-accent-blue/30 hover:bg-accent-blue/20 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span>{{ __('game.fast_mode') }}</span>
                            </a>
                        @elseif($game->isTournamentMode())
                            {{-- Fast mode is disabled in tournament mode — show a plain Continue button. --}}
                            <button type="button"
                                    x-data="{ clicked: false }"
                                    @click="if (clicked) return; clicked = true; $dispatch('show-pre-match', '{{ route('game.pre-match-data', $game->id) }}')"
                                    x-bind:disabled="clicked"
                                    class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] text-xs rounded-lg bg-accent-blue hover:bg-blue-600 active:bg-blue-700 border border-transparent font-semibold text-white uppercase tracking-wider focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150">
                                {{ __('app.continue') }}
                            </button>
                        @else
                            {{-- Split button: left half = Continue (pre-match flow), right half = open fast-mode info modal --}}
                            <div class="inline-flex items-stretch">
                                <button type="button"
                                        x-data="{ clicked: false }"
                                        @click="if (clicked) return; clicked = true; $dispatch('show-pre-match', '{{ route('game.pre-match-data', $game->id) }}')"
                                        x-bind:disabled="clicked"
                                        class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] text-xs rounded-l-lg bg-accent-blue hover:bg-blue-600 active:bg-blue-700 border border-transparent font-semibold text-white uppercase tracking-wider focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150">
                                    {{ __('app.continue') }}
                                </button>
                                <button type="button"
                                        @click="$dispatch('open-modal', 'fast-mode-info')"
                                        aria-label="{{ __('game.fast_mode_enter') }}"
                                        class="inline-flex items-center justify-center px-2 min-h-[36px] rounded-r-lg bg-accent-blue hover:bg-blue-600 active:bg-blue-700 border border-transparent border-l border-l-blue-700/60 text-white focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 transition ease-in-out duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                        <path fill-rule="evenodd" d="M9.58 1.077a.75.75 0 0 1 .405.82L9.165 6h4.085a.75.75 0 0 1 .567 1.241l-6.5 7.5a.75.75 0 0 1-1.302-.638L6.835 10H2.75a.75.75 0 0 1-.567-1.241l6.5-7.5a.75.75 0 0 1 .897-.182Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        @endif
                    @else
                        <div class="flex items-center gap-3">
                            <x-primary-button-link size="sm" color="amber" :href="route($game->isTournamentMode() ? 'game.tournament-end' : 'game.season-end', $game->id)">
                                {{ __('game.view_season_summary') }}
                            </x-primary-button-link>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    {{-- Fast Mode Info Modal (opened by split-button chevron) --}}
    @if($nextMatch && !$game->hasPendingActions() && !$continueToHome && !$game->isFastMode() && !$game->isTournamentMode())
        @include('partials.fast-mode-info-modal')
    @endif

    {{-- Pre-Match Confirmation Modal --}}
    @if($nextMatch && !$game->hasPendingActions() && !$continueToHome)
    <div x-data="preMatchLoader()" x-on:show-pre-match.window="loadPreMatch($event.detail)">
        <form x-ref="autoAdvanceForm" method="POST" action="{{ route('game.advance', $game->id) }}" class="hidden">
            @csrf
        </form>
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

    {{-- Mobile Notifications Modal (triggered by header bell icon) --}}
    <div class="lg:hidden" x-data>
        <x-modal name="notifications-mobile" maxWidth="lg">
            <x-modal-header modalName="notifications-mobile">{{ __('notifications.inbox') }}</x-modal-header>

            @if($unreadCount > 0)
            <div class="px-4 py-2.5 border-b border-border-default flex items-center justify-between">
                <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[10px] font-semibold text-accent-blue">
                    {{ $unreadCount }} {{ __('notifications.new') }}
                </span>
                <form action="{{ route('game.notifications.read-all', $game->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                        {{ __('notifications.mark_all_read') }}
                    </button>
                </form>
            </div>
            @endif

            <div class="max-h-[70vh] overflow-y-auto">
                @if($recentNotifications->isEmpty())
                <div class="text-center py-8 px-4">
                    <div class="text-text-faint mb-2">
                        <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs text-text-muted">{{ __('notifications.all_caught_up') }}</p>
                </div>
                @else
                <div x-data="{ dept: 'all' }">
                    <x-notification-department-tabs :notifications="$recentNotifications" />
                    <div class="divide-y divide-border-default">
                        @foreach($recentNotifications as $notification)
                            <div x-show="dept === 'all' || dept === @js($notification->getDepartment())">
                                <x-notification-row :notification="$notification" :game="$game" />
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </x-modal>
    </div>

    {{-- Critical-alert popup: blocking, must-dismiss alert for the highest-stakes
         (PRIORITY_CRITICAL) notifications. Renders nothing when none are pending. --}}
    <x-critical-alert-modal :alerts="$criticalAlerts" :game="$game" />

    {{-- Mobile Bottom Tab Bar --}}
    <x-bottom-tab-bar :game="$game" :next-match="$nextMatch" :team-competitions="$teamCompetitions" />

    {{-- Matchday-advance overlay. Shown instantly (client-side) on submit of any
         form that POSTs to game.advance so the user sees the branded loading
         screen while the HTTP request runs the advance inline. Listens for a
         window-level "matchday-advance-starting" event dispatched by those
         forms. If the request fails or the browser is refreshed mid-flight,
         ShowGame still renders game-loading-matchday as the server-side
         fallback. --}}
    <div x-data="{ visible: false }"
         x-show="visible"
         x-on:matchday-advance-starting.window="visible = true"
         x-cloak
         style="display: none"
         class="fixed inset-0 z-[100] bg-surface-900 flex items-start md:items-center justify-center pt-24 md:pt-0 pb-8">
        <div class="w-full max-w-md px-4">
            @if($nextMatch)
                @php($comp = $nextMatch->competition)
                <div class="text-center mb-8">
                    <x-competition-pill :competition="$comp" class="justify-center mb-2" />
                    <h1 class="text-lg md:text-2xl font-bold text-text-primary">
                        @if($nextMatch->round_name)
                            {{ __($nextMatch->round_name) }}
                        @elseif($nextMatch->round_number)
                            {{ __('game.matchday_n', ['number' => $nextMatch->round_number]) }}
                        @endif
                    </h1>
                    <p class="text-sm text-text-muted mt-1">
                        {{ $nextMatch->venueName() ?? '' }} &middot; {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                    </p>
                </div>

                <div class="flex items-center justify-center gap-4 md:gap-8 mb-8">
                    <div class="flex-1 flex flex-col items-center text-center min-w-0">
                        <x-team-crest :team="$nextMatch->homeTeam" class="w-16 h-16 md:w-24 md:h-24 mb-2" />
                        <h4 class="text-sm md:text-base font-bold text-text-primary truncate max-w-full">{{ $nextMatch->homeTeam->short_name ?? $nextMatch->homeTeam->name }}</h4>
                    </div>
                    <div class="shrink-0">
                        <span class="text-lg md:text-2xl font-black text-text-body tracking-tight">{{ __('game.vs') }}</span>
                    </div>
                    <div class="flex-1 flex flex-col items-center text-center min-w-0">
                        <x-team-crest :team="$nextMatch->awayTeam" class="w-16 h-16 md:w-24 md:h-24 mb-2" />
                        <h4 class="text-sm md:text-base font-bold text-text-primary truncate max-w-full">{{ $nextMatch->awayTeam->short_name ?? $nextMatch->awayTeam->name }}</h4>
                    </div>
                </div>
            @endif

            <div class="text-center">
                <div class="flex justify-center mb-3">
                    <svg class="animate-spin h-6 w-6 text-accent-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <p class="text-sm text-text-secondary">{{ __('game.simulating_matches_message') }}</p>
            </div>
        </div>
    </div>
</div>
