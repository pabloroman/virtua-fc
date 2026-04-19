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
@endphp

<div x-data>
    {{-- Sticky Header --}}
    <header class="sticky top-0 z-50 bg-surface-900/95 backdrop-blur-md border-b border-border-default">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between pt-0 py-2">
                {{-- Left: Team badge + name --}}
                <div class="flex items-center gap-3">
                    {{-- Team badge + name --}}
                    <div class="flex items-center gap-2.5">
                        <x-team-crest :team="$game->team" class="w-8 h-8 shrink-0" />
                        <div class="min-w-0">
                            <h1 class="font-heading font-semibold text-base text-text-primary leading-none tracking-wide uppercase truncate">{{ $game->team->name }}</h1>
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
                            <x-primary-button-link color="amber" :href="$pendingAction && $pendingAction['route'] ? route($pendingAction['route'], $game->id) : route('show-game', $game->id)" class="whitespace-nowrap gap-2 animate-pulse">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <span class="hidden sm:inline">{{ __('messages.action_required_short') }}</span>
                            </x-primary-button-link>
                        @elseif($continueToHome)
                            <x-primary-button-link :href="route('show-game', $game->id)">{{ __('app.continue') }}</x-primary-button-link>
                        @elseif($game->isFastMode())
                            <a href="{{ route('game.fast-mode', $game->id) }}" class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[44px] text-xs font-semibold uppercase tracking-wider rounded-lg bg-accent-blue/10 text-accent-blue border border-accent-blue/30 hover:bg-accent-blue/20 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span>{{ __('game.fast_mode') }}</span>
                            </a>
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
                            <span class="hidden sm:inline text-sm text-text-secondary">{{ __('game.season_complete') }}</span>
                            <x-primary-button-link color="amber" :href="route($game->isTournamentMode() ? 'game.tournament-end' : 'game.season-end', $game->id)">
                                {{ __('game.view_season_summary') }}
                            </x-primary-button-link>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    {{-- Fast Mode Info Modal (opened by split-button chevron) --}}
    @if($nextMatch && !$game->hasPendingActions() && !$continueToHome && !$game->isFastMode())
        @include('partials.fast-mode-info-modal')
    @endif

    {{-- Pre-Match Confirmation Modal --}}
    @if($nextMatch && !$game->hasPendingActions() && !$continueToHome)
    <div x-data="{
        loading: false,
        submitting: false,
        content: '',
        loadPreMatch(url) {
            if (this.submitting) return;
            if (localStorage.getItem('autoLineup') === '1') {
                this.submitting = true;
                this.$refs.autoAdvanceForm.submit();
                return;
            }
            this.content = '';
            this.loading = true;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => {
                    const contentType = r.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        return r.json().then(data => {
                            if (data.lineupReady && !this.submitting) {
                                this.submitting = true;
                                this.$refs.autoAdvanceForm.submit();
                            }
                        });
                    }
                    this.$dispatch('open-modal', 'pre-match');
                    return r.text().then(html => { this.content = html; this.loading = false; });
                })
                .catch(() => { this.loading = false; });
        }
    }" x-on:show-pre-match.window="loadPreMatch($event.detail)">
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
                <div class="divide-y divide-border-default">
                    @foreach($recentNotifications as $notification)
                        <x-notification-row :notification="$notification" :game="$game" />
                    @endforeach
                </div>
                @endif
            </div>
        </x-modal>
    </div>

    {{-- Mobile Bottom Tab Bar --}}
    <x-bottom-tab-bar :game="$game" :next-match="$nextMatch" :team-competitions="$teamCompetitions" />
</div>
