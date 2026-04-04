@props(['game', 'nextMatch' => null, 'continueToHome' => false])

@php
    // Get competitions the team participates in for this game
    $teamCompetitions = \App\Models\Competition::whereIn('id',
        $game->competitionEntries()
            ->where('team_id', $game->team_id)
            ->pluck('competition_id')
    )->orderBy('tier')->get();
@endphp

<div>
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
                            {{-- Desktop: full button --}}
                            <x-primary-button-link color="amber" :href="$pendingAction && $pendingAction['route'] ? route($pendingAction['route'], $game->id) : route('show-game', $game->id)" class="hidden lg:inline-flex whitespace-nowrap gap-2 animate-pulse">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                {{ __('messages.action_required_short') }}
                            </x-primary-button-link>
                            {{-- Mobile: compact icon button --}}
                            <a href="{{ $pendingAction && $pendingAction['route'] ? route($pendingAction['route'], $game->id) : route('show-game', $game->id) }}" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500 text-white animate-pulse transition-colors hover:bg-amber-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </a>
                        @elseif($continueToHome)
                            {{-- Desktop: full button --}}
                            <x-primary-button-link :href="route('show-game', $game->id)" class="hidden lg:inline-flex">{{ __('app.continue') }}</x-primary-button-link>
                            {{-- Mobile: compact icon button --}}
                            <a href="{{ route('show-game', $game->id) }}" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg bg-accent-blue text-white transition-colors hover:bg-blue-600">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </a>
                        @else
                            {{-- Desktop: full button --}}
                            <x-primary-button type="button" @click="$dispatch('show-pre-match', '{{ route('game.pre-match-data', $game->id) }}')" class="hidden lg:inline-flex">
                                {{ __('app.continue') }}
                            </x-primary-button>
                            {{-- Mobile: compact icon button --}}
                            <button type="button" @click="$dispatch('show-pre-match', '{{ route('game.pre-match-data', $game->id) }}')" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg bg-accent-blue text-white transition-colors hover:bg-blue-600">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </button>
                        @endif
                    @else
                        <div class="flex items-center gap-3">
                            <span class="hidden sm:inline text-sm text-text-secondary">{{ __('game.season_complete') }}</span>
                            {{-- Desktop: full button --}}
                            <x-primary-button-link color="amber" :href="route('game.season-end', $game->id)" class="hidden lg:inline-flex">
                                {{ __('game.view_season_summary') }}
                            </x-primary-button-link>
                            {{-- Mobile: compact button --}}
                            <a href="{{ route('game.season-end', $game->id) }}" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500 text-white transition-colors hover:bg-amber-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 01-5.54 0"/>
                                </svg>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    {{-- Pre-Match Confirmation Modal --}}
    @if($nextMatch && !$game->hasPendingActions() && !$continueToHome)
    <div x-data="{
        loading: false,
        content: '',
        loadPreMatch(url) {
            if (localStorage.getItem('autoLineup') === '1') {
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
                            if (data.lineupReady) {
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

    {{-- Mobile Bottom Tab Bar --}}
    <x-bottom-tab-bar :game="$game" :next-match="$nextMatch" :team-competitions="$teamCompetitions" />
</div>
