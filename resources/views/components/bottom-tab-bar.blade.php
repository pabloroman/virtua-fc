@props(['game', 'nextMatch' => null, 'teamCompetitions' => collect()])

@php
    $currentRoute = Route::currentRouteName();
    $isCareer = $game->isCareerMode();
    $isTournament = $game->isTournamentMode();

    // Active states
    $dashboardActive = $currentRoute === 'show-game';
    $squadActive = Str::startsWith($currentRoute, 'game.squad');
    $lineupActive = $currentRoute === 'game.lineup';
    $transfersActive = in_array($currentRoute, ['game.transfers', 'game.transfers.outgoing', 'game.scouting', 'game.explore', 'game.transfer-activity', 'game.transfers.market']);
    $clubRoutes = ['game.club', 'game.club.finances', 'game.club.stadium', 'game.club.reputation'];
    $moreActive = in_array($currentRoute, array_merge($clubRoutes, ['game.calendar', 'game.competition']));

@endphp

<div x-data="{ moreOpen: false }" class="lg:hidden">
    {{-- Bottom Tab Bar --}}
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-surface-900/95 backdrop-blur-md border-t border-border-default" style="padding-bottom: env(safe-area-inset-bottom, 0px);">
        <div class="flex items-center h-16">
            {{-- Dashboard --}}
            <a href="{{ route('show-game', $game->id) }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 min-h-[44px] px-3 transition-colors {{ $dashboardActive ? 'text-accent-blue' : 'text-text-muted' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
                <span class="text-[9px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.dashboard') }}</span>
            </a>

            {{-- Squad --}}
            <a href="{{ route('game.squad', $game->id) }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 min-h-[44px] px-3 transition-colors {{ $squadActive ? 'text-accent-blue' : 'text-text-muted' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
                <span class="text-[9px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.squad') }}</span>
            </a>

            {{-- Starting XI (only when there's a next match) --}}
            @if($nextMatch)
            <a href="{{ route('game.lineup', $game->id) }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 min-h-[44px] px-3 transition-colors {{ $lineupActive ? 'text-accent-blue' : 'text-text-muted' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/>
                </svg>
                <span class="text-[9px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.starting_xi') }}</span>
            </a>
            @endif

            {{-- Transfers (career mode only) --}}
            @if($isCareer)
            <a href="{{ route('game.transfers', $game->id) }}" class="flex-1 flex flex-col items-center justify-center gap-0.5 min-h-[44px] px-3 transition-colors {{ $transfersActive ? 'text-accent-blue' : 'text-text-muted' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                </svg>
                <span class="text-[9px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.transfers') }}</span>
            </a>
            @endif

            {{-- More --}}
            <button
                @click="moreOpen = !moreOpen"
                class="relative flex-1 flex flex-col items-center justify-center gap-0.5 min-h-[44px] px-3 transition-colors {{ $moreActive ? 'text-accent-blue' : 'text-text-muted' }}"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                </svg>
                <span class="text-[9px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.more') }}</span>
            </button>
        </div>
    </nav>

    {{-- More Menu Overlay --}}
    <div x-show="moreOpen" x-cloak class="fixed inset-0 z-50">
        {{-- Backdrop --}}
        <div
            x-show="moreOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="moreOpen = false"
            class="fixed inset-0 bg-black/60"
        ></div>

        {{-- Slide-up Panel --}}
        <div
            x-show="moreOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="fixed bottom-0 inset-x-0 max-h-[85vh] bg-surface-800 border-t border-border-strong rounded-t-2xl shadow-2xl overflow-y-auto"
            style="padding-bottom: env(safe-area-inset-bottom, 0px);"
        >
            {{-- Drag handle --}}
            <div class="flex justify-center pt-3 pb-2">
                <div class="w-10 h-1 rounded-full bg-surface-600"></div>
            </div>

            {{-- Menu Items --}}
            <nav class="px-2 pb-4 space-y-1">
                {{-- Calendar --}}
                <a href="{{ route('game.calendar', $game->id) }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors {{ $currentRoute === 'game.calendar' ? 'bg-accent-blue/10 text-accent-blue' : 'text-text-body hover:bg-surface-700' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('app.calendar') }}</span>
                </a>

                @if($isCareer)
                {{-- Club section --}}
                <div class="pt-2 pb-1 px-4">
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.club') }}</span>
                </div>
                <a href="{{ route('game.club.finances', $game->id) }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors {{ $currentRoute === 'game.club.finances' ? 'bg-accent-blue/10 text-accent-blue' : 'text-text-body hover:bg-surface-700' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('club.nav.finances') }}</span>
                </a>
                <a href="{{ route('game.club.stadium', $game->id) }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors {{ $currentRoute === 'game.club.stadium' ? 'bg-accent-blue/10 text-accent-blue' : 'text-text-body hover:bg-surface-700' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('club.nav.stadium') }}</span>
                </a>
                <a href="{{ route('game.club.reputation', $game->id) }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors {{ $currentRoute === 'game.club.reputation' ? 'bg-accent-blue/10 text-accent-blue' : 'text-text-body hover:bg-surface-700' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('club.nav.reputation') }}</span>
                </a>
                @endif

                {{-- Competitions --}}
                @if($teamCompetitions->isNotEmpty())
                <div class="pt-2 pb-1 px-4">
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.competitions') }}</span>
                </div>
                @foreach($teamCompetitions as $competition)
                <a href="{{ route('game.competition', [$game->id, $competition->id]) }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors {{ request()->route('competitionId') == $competition->id ? 'bg-accent-blue/10 text-accent-blue' : 'text-text-body hover:bg-surface-700' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 01-5.54 0"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __($competition->name) }}</span>
                </a>
                @endforeach
                @endif

                {{-- Site Links --}}
                @if(auth()->user())
                <div class="border-t border-border-default/50 mt-2 pt-3">
                    <div class="pb-1 px-4">
                        <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.account') }}</span>
                    </div>
                    <a href="{{ route('select-team') }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span class="text-sm font-medium">{{ __('app.new_game') }}</span>
                    </a>
                    <a href="{{ route('dashboard') }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/>
                        </svg>
                        <span class="text-sm font-medium">{{ __('app.load_game') }}</span>
                    </a>
                    <a href="{{ route('leaderboard') }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                        </svg>
                        <span class="text-sm font-medium">{{ __('leaderboard.title') }}</span>
                    </a>
                    <a href="{{ route('profile.edit') }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        <span class="text-sm font-medium">{{ __('app.edit_profile') }}</span>
                    </a>
                    <div class="flex items-center justify-between px-4 py-4">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-text-body">{{ __('app.theme') }}</span>
                        </div>
                        <x-theme-toggle />
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" @click="moreOpen = false" class="flex items-center gap-3 w-full px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                            </svg>
                            <span class="text-sm font-medium">{{ __('app.log_out') }}</span>
                        </button>
                    </form>
                </div>
                @endif

                {{-- Copyright --}}
                <div class="border-t border-border-default/50 mt-2 pt-3 px-4 pb-2">
                    <p class="text-[10px] text-text-faint text-center">
                        &copy; {{ date('Y') }} Pablo Román &middot; <a href="{{ route('legal') }}" class="hover:text-text-muted transition-colors">Aviso Legal</a>
                        @if(auth()->user()?->is_admin)
                            &middot; <a href="{{ route('admin.dashboard') }}" class="hover:text-text-muted transition-colors">Admin</a>
                        @endif
                    </p>
                </div>

            </nav>
        </div>
    </div>
</div>
