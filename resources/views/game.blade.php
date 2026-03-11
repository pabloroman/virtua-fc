@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Pending action alert --}}
            @if($game->hasPendingActions())
                @php $pendingAction = $game->getFirstPendingAction(); @endphp
                <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span class="text-sm text-amber-800 font-medium">{{ __('messages.action_required') }}</span>
                    </div>
                    @if($pendingAction && $pendingAction['route'])
                    <a href="{{ route($pendingAction['route'], $game->id) }}"
                       class="inline-flex items-center px-4 py-2 bg-amber-500 text-white text-xs font-semibold uppercase tracking-wide rounded-md hover:bg-amber-600 transition-colors shrink-0 min-h-[44px]">
                        {{ __('messages.action_required_short') }}
                    </a>
                    @endif
                </div>
            @endif

            {{-- Pre-Season Banner --}}
            @if(!empty($isPreSeason))
            <div class="mb-4 p-4 bg-gradient-to-r from-sky-50 to-indigo-50 border border-sky-200 rounded-lg flex flex-col md:flex-row md:items-center md:justify-between gap-3" x-data="{ confirmSkip: false }">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-sky-900">{{ __('game.pre_season_banner_title') }}</h4>
                        <p class="text-sm text-sky-700 mt-0.5">
                            {{ __('game.pre_season_banner_desc', ['date' => isset($seasonStartDate) ? $seasonStartDate->locale(app()->getLocale())->translatedFormat('d M Y') : '']) }}
                        </p>
                    </div>
                </div>
                <div class="shrink-0">
                    <x-secondary-button @click="confirmSkip = true" x-show="!confirmSkip">
                        {{ __('game.pre_season_skip') }}
                    </x-secondary-button>
                    <div x-show="confirmSkip" x-cloak class="flex items-center gap-2">
                        <form action="{{ route('game.skip-pre-season', $game->id) }}" method="POST" class="inline">
                            @csrf
                            <x-primary-button color="sky">
                                {{ __('app.confirm') }}
                            </x-primary-button>
                        </form>
                        <x-secondary-button @click="confirmSkip = false">
                            {{ __('app.cancel') }}
                        </x-secondary-button>
                    </div>
                </div>
            </div>
            @endif

            @if($nextMatch)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                    {{-- Left Column (2/3) - Main Content --}}
                    <div class="md:col-span-2 space-y-8">
                        {{-- Highlighted Next Match Card --}}
                        @php
                            $comp = $nextMatch->competition;
                            $isPreseason = ($comp->handler_type ?? '') === 'preseason';
                            $accent = match(true) {
                                $isPreseason => [
                                    'bg' => 'bg-sky-600', 'badge' => 'bg-white/20 text-white',
                                ],
                                ($comp->scope ?? '') === 'continental' => [
                                    'bg' => 'bg-blue-600', 'badge' => 'bg-white/20 text-white',
                                ],
                                ($comp->type ?? '') === 'cup' => [
                                    'bg' => 'bg-emerald-600', 'badge' => 'bg-white/20 text-white',
                                ],
                                default => [
                                    'bg' => 'bg-amber-600', 'badge' => 'bg-white/20 text-white',
                                ],
                            };

                            $cupTie = $nextMatch->cup_tie_id ? $nextMatch->cupTie : null;
                            $firstLegScore = null;
                            if ($cupTie && $cupTie->first_leg_match_id && $cupTie->firstLegMatch?->played) {
                                $fl = $cupTie->firstLegMatch;
                                $firstLegScore = $fl->home_score . ' - ' . $fl->away_score;
                            }
                        @endphp
                        <div class="rounded-md overflow-hidden border border-slate-200">
                            {{-- Section 1: Competition Banner --}}
                            <div class="{{ $accent['bg'] }} px-4 py-3 md:px-5 md:py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="px-2.5 py-0.5 text-sm font-semibold rounded-full {{ $accent['badge'] }} shrink-0">
                                            {{ $isPreseason ? __('game.pre_season_friendly') : __($comp->name ?? 'League') }}
                                        </span>
                                        @if($nextMatch->round_name)
                                            <span class="text-sm text-white/70 truncate">&middot; {{ __($nextMatch->round_name) }}</span>
                                        @else
                                            <span class="text-sm text-white/70 truncate">&middot; {{ __('game.matchday_n', ['number' => $nextMatch->round_number]) }}</span>
                                        @endif
                                    </div>
                                    <span class="text-sm text-white/70 shrink-0 truncate">
                                        {{ $nextMatch->homeTeam->stadium_name ?? '' }} &middot; {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                    </span>
                                </div>
                            </div>

                            {{-- First Leg Score (cup ties) --}}
                            @if($firstLegScore)
                                <div class="bg-slate-100 border-b border-slate-200 px-4 py-2 text-center">
                                    <span class="text-xs text-slate-500 font-medium">1st leg: {{ $firstLegScore }}</span>
                                </div>
                            @endif

                            {{-- Section 2: Team Face-Off --}}
                            <div class="bg-white px-4 py-5 md:px-6 md:py-6">
                                <div class="flex items-start justify-center gap-3 md:gap-6">
                                    {{-- Home Team --}}
                                    <div class="flex-1 flex flex-col items-center text-center min-w-0">
                                        <x-team-crest :team="$nextMatch->homeTeam" class="w-14 h-14 md:w-20 md:h-20 mb-2" />
                                        <h4 class="text-sm md:text-xl font-bold text-slate-900 truncate max-w-full">{{ $nextMatch->homeTeam->name }}</h4>
                                        @if(!$isPreseason)
                                            @if($homeStanding)
                                            <div class="text-xs text-slate-500 mt-1.5">
                                                {{ $homeStanding->position }}{{ $homeStanding->position == 1 ? 'st' : ($homeStanding->position == 2 ? 'nd' : ($homeStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $homeStanding->points }} {{ __('game.pts') }}
                                            </div>
                                            @endif
                                            <div class="flex gap-1 mt-2">
                                                @php $homeForm = $nextMatch->home_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                                                @forelse($homeForm as $result)
                                                    <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center
                                                        @if($result === 'W') bg-green-500 text-white
                                                        @elseif($result === 'D') bg-slate-300 text-slate-600
                                                        @else bg-red-500 text-white @endif">
                                                        {{ $result }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-400 text-xs">{{ __('game.no_form') }}</span>
                                                @endforelse
                                            </div>
                                        @endif
                                    </div>

                                    {{-- VS Divider --}}
                                    <div class="flex flex-col items-center justify-center pt-4 md:pt-6 shrink-0">
                                        <span class="text-lg md:text-2xl font-black text-slate-300 tracking-tight">{{ __('game.vs') }}</span>
                                    </div>

                                    {{-- Away Team --}}
                                    <div class="flex-1 flex flex-col items-center text-center min-w-0">
                                        <x-team-crest :team="$nextMatch->awayTeam" class="w-14 h-14 md:w-20 md:h-20 mb-2" />
                                        <h4 class="text-sm md:text-xl font-bold text-slate-900 truncate max-w-full">{{ $nextMatch->awayTeam->name }}</h4>
                                        @if(!$isPreseason)
                                            @if($awayStanding)
                                            <div class="text-xs text-slate-500 mt-1.5">
                                                {{ $awayStanding->position }}{{ $awayStanding->position == 1 ? 'st' : ($awayStanding->position == 2 ? 'nd' : ($awayStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $awayStanding->points }} {{ __('game.pts') }}
                                            </div>
                                            @endif
                                            <div class="flex gap-1 mt-2">
                                                @php $awayForm = $nextMatch->away_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                                                @forelse($awayForm as $result)
                                                    <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center
                                                        @if($result === 'W') bg-green-500 text-white
                                                        @elseif($result === 'D') bg-slate-300 text-slate-600
                                                        @else bg-red-500 text-white @endif">
                                                        {{ $result }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-400 text-xs">{{ __('game.no_form') }}</span>
                                                @endforelse
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                        </div>

                        {{-- Mobile-only: Set Lineup Button --}}
                        <div class="md:hidden">
                            <a href="{{ route('game.lineup', $game->id) }}"
                               class="flex items-center justify-center gap-2 w-full px-4 py-3 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg transition-colors min-h-[44px]">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                {{ __('game.set_lineup') }}
                            </a>
                        </div>

                        {{-- Remaining Upcoming Fixtures --}}
                        @if($upcomingFixtures->skip(1)->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($upcomingFixtures->skip(1)->take(4) as $fixture)
                                <x-fixture-row :match="$fixture" :game="$game" :show-score="false" :highlight-next="false" />
                            @endforeach
                            <div class="text-center pt-1">
                                <a href="{{ route('game.calendar', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                    {{ __('game.full_calendar') }} &rarr;
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>

                    <hr class="border-slate-200 md:hidden" />

                    {{-- Right Column (1/3) - Notifications & Standings --}}
                    <div class="space-y-8">
                        {{-- Notifications Inbox --}}
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-semibold text-xl text-slate-900">{{ __('notifications.inbox') }}</h4>
                                    @if($unreadNotificationCount > 0)
                                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">
                                        {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                                    </span>
                                    @endif
                                </div>
                                @if($unreadNotificationCount > 0)
                                <form action="{{ route('game.notifications.read-all', $game->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-xs text-sky-600 hover:text-sky-800">
                                        {{ __('notifications.mark_all_read') }}
                                    </button>
                                </form>
                                @endif
                            </div>

                            @if($groupedNotifications->isEmpty())
                            <div class="text-center py-8">
                                <div class="text-slate-300 mb-2">
                                    <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <p class="text-sm text-slate-400">{{ __('notifications.all_caught_up') }}</p>
                            </div>
                            @else
                            <div class="space-y-4">
                                @foreach($groupedNotifications as $date => $notifications)
                                <div>
                                    {{-- Date Header --}}
                                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">
                                        {{ \Carbon\Carbon::parse($date)->format('j M Y') }}
                                    </div>

                                    {{-- Notifications for this date --}}
                                    <div class="space-y-2">
                                        @foreach($notifications as $notification)
                                        @php $classes = $notification->getTypeClasses(); @endphp
                                        <form action="{{ route('game.notifications.read', [$game->id, $notification->id]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="w-full text-left block p-3 {{ $classes['bg'] }} border {{ $classes['border'] }} rounded-lg hover:opacity-90 transition-opacity {{ $notification->isRead() ? 'opacity-60' : '' }}">
                                                <div class="flex items-start gap-3">
                                                    {{-- Type icon with unread indicator --}}
                                                    <div class="relative flex-shrink-0">
                                                        <x-notification-icon :icon="$notification->icon" :icon-bg="$classes['icon_bg']" :icon-text="$classes['icon_text']" />
                                                    </div>

                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="font-semibold text-sm {{ $classes['text'] }} truncate">{{ $notification->title }}</span>
                                                            <svg class="w-4 h-4 {{ $classes['text'] }} opacity-40 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                            </svg>
                                                        </div>
                                                        @if($notification->message)
                                                        <p class="text-xs text-slate-600 mt-0.5">{{ $notification->message }}</p>
                                                        @endif
                                                        @php $badge = $notification->getPriorityBadge(); @endphp
                                                        @if($badge)
                                                        <span class="inline-flex items-center mt-1 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded {{ $badge['bg'] }} {{ $badge['text'] }}">
                                                            {{ $badge['label'] }}
                                                        </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </button>
                                        </form>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        <hr class="border-slate-200 md:hidden" />

                        {{-- Abridged League Standings (hidden during pre-season) --}}
                        @if($leagueStandings->isNotEmpty() && empty($isPreSeason))
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-xl text-slate-900">
                                    @if($game->isTournamentMode() && $leagueStandings->first()?->group_label)
                                        {{ __('game.group') }} {{ $leagueStandings->first()->group_label }}
                                    @else
                                        {{ __('game.standings') }}
                                    @endif
                                </h4>
                                <a href="{{ route('game.competition', [$game->id, $game->competition_id]) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                    {{ __('game.full_table') }} &rarr;
                                </a>
                            </div>

                            <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs text-slate-500 font-semibold">
                                        <th class="text-left py-1.5 w-6 font-semibold">#</th>
                                        <th class="text-left py-1.5 font-semibold"></th>
                                        <th class="text-center py-1.5 w-8 font-semibold">{{ __('game.played_abbr') }}</th>
                                        <th class="text-center py-1.5 w-8 font-semibold">{{ __('game.goal_diff_abbr') }}</th>
                                        <th class="text-center py-1.5 w-8 font-semibold">{{ __('game.pts_abbr') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $prevPosition = 0; @endphp
                                    @foreach($leagueStandings as $standing)
                                        @php $isPlayer = $standing->team_id === $game->team_id; @endphp
                                        @if($standing->position > $prevPosition + 1)
                                            <tr><td colspan="5" class="text-center text-slate-300 py-0.5 text-xs">&middot;&middot;&middot;</td></tr>
                                        @endif
                                        <tr class="border-b border-slate-100 {{ $isPlayer ? 'bg-amber-50 font-semibold' : '' }}">
                                            <td class="py-1.5 text-slate-500">{{ $standing->position }}</td>
                                            <td class="py-1.5">
                                                <div class="flex items-center gap-2">
                                                    <x-team-crest :team="$standing->team" class="w-5 h-5 shrink-0" />
                                                    <span class="truncate {{ $isPlayer ? 'text-slate-900' : 'text-slate-700' }}">{{ $standing->team->name }}</span>
                                                </div>
                                            </td>
                                            <td class="py-1.5 text-center text-slate-400">{{ $standing->played }}</td>
                                            <td class="py-1.5 text-center text-slate-400">{{ $standing->goal_difference >= 0 ? '+' : '' }}{{ $standing->goal_difference }}</td>
                                            <td class="py-1.5 text-center font-semibold text-slate-900">{{ $standing->points }}</td>
                                        </tr>
                                        @php $prevPosition = $standing->position; @endphp
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @elseif($hasRemainingMatches)
            {{-- AI Matches Remaining State --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8 text-center">
                    <div class="text-6xl mb-4">&#9917;</div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-2">{{ __('game.other_competitions_in_progress') }}</h2>
                    <p class="text-slate-500 mb-8">{{ __('game.other_competitions_desc') }}</p>
                    <form action="{{ route('game.advance', $game->id) }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors min-h-[44px]">
                            {{ __('game.advance_other_matches') }}
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
