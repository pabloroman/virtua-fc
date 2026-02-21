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

            @if($nextMatch)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                    {{-- Left Column (2/3) - Main Content --}}
                    <div class="md:col-span-2 space-y-8">
                        {{-- Upcoming Fixtures Header --}}
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-xl text-slate-900">{{ __('game.upcoming_fixtures') }}</h3>
                            <a href="{{ route('game.calendar', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                {{ __('game.full_calendar') }} &rarr;
                            </a>
                        </div>

                        {{-- Tournament Knockout Progress --}}
                        @if($game->isTournamentMode() && isset($tournamentTie) && $tournamentTie)
                        <div class="pt-4 border-t">
                            @php
                                $won = $tournamentTie->completed && $tournamentTie->winner_id === $game->team_id;
                                $lost = $tournamentTie->completed && $tournamentTie->winner_id !== $game->team_id;
                                $isHome = $tournamentTie->home_team_id === $game->team_id;
                                $opponent = $isHome ? $tournamentTie->awayTeam : $tournamentTie->homeTeam;
                            @endphp

                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-lg text-slate-900">{{ __('game.knockout_phase') }}</h3>
                                <a href="{{ route('game.competition', [$game->id, $game->competition_id]) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                    {{ __('game.full_calendar') }} &rarr;
                                </a>
                            </div>

                            <div class="p-4 rounded-lg border {{ $won ? 'bg-green-50 border-green-200' : ($lost ? 'bg-red-50 border-red-200' : 'bg-sky-50 border-sky-200') }}">
                                <div class="text-xs font-semibold uppercase tracking-wide text-center mb-3 {{ $won ? 'text-green-600' : ($lost ? 'text-red-600' : 'text-sky-600') }}">
                                    {{ $tournamentTie->firstLegMatch?->round_name ?? '' }}
                                    @if($won) &mdash; {{ __('cup.advanced_to_next_round') }}
                                    @elseif($lost) &mdash; {{ __('cup.eliminated') }}
                                    @endif
                                </div>
                                <div class="flex items-center justify-center gap-4">
                                    <div class="flex items-center gap-2 flex-1 justify-end">
                                        <span class="text-sm font-semibold text-slate-900 truncate">{{ $tournamentTie->homeTeam->name }}</span>
                                        <img src="{{ $tournamentTie->homeTeam->image }}" class="w-8 h-8 shrink-0">
                                    </div>
                                    <div class="text-center px-2">
                                        @if($tournamentTie->firstLegMatch?->played)
                                            <span class="text-lg font-bold text-slate-900">{{ $tournamentTie->firstLegMatch->home_score }} - {{ $tournamentTie->firstLegMatch->away_score }}</span>
                                            @if($tournamentTie->firstLegMatch->is_extra_time)
                                                <div class="text-[10px] text-slate-400">
                                                    @if($tournamentTie->firstLegMatch->home_score_penalties !== null)
                                                        {{ __('season.pens_abbr') }} {{ $tournamentTie->firstLegMatch->home_score_penalties }}-{{ $tournamentTie->firstLegMatch->away_score_penalties }}
                                                    @else
                                                        {{ __('season.aet_abbr') }}
                                                    @endif
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-lg font-bold text-slate-400">{{ __('game.vs') }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 flex-1">
                                        <img src="{{ $tournamentTie->awayTeam->image }}" class="w-8 h-8 shrink-0">
                                        <span class="text-sm font-semibold text-slate-900 truncate">{{ $tournamentTie->awayTeam->name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Highlighted Next Match Card --}}
                        @php
                            $comp = $nextMatch->competition;
                            $accent = match(true) {
                                ($comp->scope ?? '') === 'continental' => ['badge' => 'bg-blue-100 text-blue-800'],
                                ($comp->type ?? '') === 'cup' => ['badge' => 'bg-emerald-100 text-emerald-800'],
                                default => ['badge' => 'bg-amber-100 text-amber-800'],
                            };
                        @endphp
                        <div class="rounded-lg bg-white border border-slate-200 p-4 md:p-5">
                            {{-- Competition, Round & Date --}}
                            <div class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $accent['badge'] }}">
                                        {{ $nextMatch->competition->name ?? 'League' }}
                                    </span>
                                    @if($nextMatch->round_name)
                                        <span class="text-sm text-slate-500">&middot; {{ $nextMatch->round_name }}</span>
                                    @else
                                        <span class="text-sm text-slate-500">&middot; {{ __('game.matchday_n', ['number' => $nextMatch->round_number]) }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-slate-500 mt-1">
                                    {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                </div>
                            </div>

                            {{-- Teams Face-off --}}
                            <div class="flex items-start justify-around">
                                {{-- Home Team --}}
                                <div class="flex-1 flex flex-col items-center text-center min-w-0 px-2">
                                    <img src="{{ $nextMatch->homeTeam->image }}" class="w-12 h-12 md:w-20 md:h-20 mb-2">
                                    <h4 class="text-base md:text-xl font-bold text-slate-900 truncate max-w-full">{{ $nextMatch->homeTeam->name }}</h4>
                                    @if($homeStanding)
                                    <div class="text-sm text-slate-500 mt-0.5">
                                        {{ $homeStanding->position }}{{ $homeStanding->position == 1 ? 'st' : ($homeStanding->position == 2 ? 'nd' : ($homeStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $homeStanding->points }} {{ __('game.pts') }}
                                    </div>
                                    @endif
                                    <div class="flex gap-1 mt-2">
                                        @php $homeForm = $nextMatch->home_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                                        @forelse($homeForm as $result)
                                            <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                                                @if($result === 'W') bg-green-500 text-white
                                                @elseif($result === 'D') bg-slate-400 text-white
                                                @else bg-red-500 text-white @endif">
                                                {{ $result }}
                                            </span>
                                        @empty
                                            <span class="text-slate-400 text-sm">{{ __('game.no_form') }}</span>
                                        @endforelse
                                    </div>
                                </div>

                                {{-- Away Team --}}
                                <div class="flex-1 flex flex-col items-center text-center min-w-0 px-2">
                                    <img src="{{ $nextMatch->awayTeam->image }}" class="w-12 h-12 md:w-20 md:h-20 mb-2">
                                    <h4 class="text-base md:text-xl font-bold text-slate-900 truncate max-w-full">{{ $nextMatch->awayTeam->name }}</h4>
                                    @if($awayStanding)
                                    <div class="text-sm text-slate-500 mt-0.5">
                                        {{ $awayStanding->position }}{{ $awayStanding->position == 1 ? 'st' : ($awayStanding->position == 2 ? 'nd' : ($awayStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $awayStanding->points }} {{ __('game.pts') }}
                                    </div>
                                    @endif
                                    <div class="flex gap-1 mt-2">
                                        @php $awayForm = $nextMatch->away_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                                        @forelse($awayForm as $result)
                                            <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                                                @if($result === 'W') bg-green-500 text-white
                                                @elseif($result === 'D') bg-slate-400 text-white
                                                @else bg-red-500 text-white @endif">
                                                {{ $result }}
                                            </span>
                                        @empty
                                            <span class="text-slate-400 text-sm">{{ __('game.no_form') }}</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            {{-- Set Lineup Button --}}
                            <div class="mt-4 text-center">
                                <a href="{{ route('game.lineup', $game->id) }}"
                                   class="inline-flex items-center gap-2 px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors min-h-[44px]">
                                    {{ __('game.set_lineup') }}
                                </a>
                            </div>
                        </div>

                        {{-- Remaining Upcoming Fixtures --}}
                        @if($upcomingFixtures->skip(1)->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($upcomingFixtures->skip(1)->take(4) as $fixture)
                                <x-fixture-row :match="$fixture" :game="$game" :show-score="false" :highlight-next="false" />
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Right Column (1/3) - Notifications Inbox --}}
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
                                                        <p class="text-xs text-slate-600 mt-0.5 line-clamp-2">{{ $notification->message }}</p>
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

                        {{-- Abridged League Standings --}}
                        @if($leagueStandings->isNotEmpty())
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-xl text-slate-900">{{ __('game.standings') }}</h4>
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
                                                    <img src="{{ $standing->team->image }}" class="w-5 h-5 shrink-0">
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
                <div class="p-6 sm:p-8 text-center">
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
            @else
            {{-- Season / Tournament Complete State --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8 text-center">
                    <div class="text-6xl mb-4">&#127942;</div>
                    @if($game->isTournamentMode())
                        <h2 class="text-3xl font-bold text-slate-900 mb-2">{{ __('game.tournament_complete') }}</h2>
                        <p class="text-slate-500 mb-8">{{ __('game.tournament_complete_desc') }}</p>
                        <a href="{{ route('game.tournament-end', $game->id) }}"
                           class="inline-flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors min-h-[44px]">
                            {{ __('game.view_tournament_summary') }}
                        </a>
                    @else
                        <h2 class="text-3xl font-bold text-slate-900 mb-2">{{ __('game.season_complete') }}</h2>
                        <p class="text-slate-500 mb-8">{{ __('game.season_complete_congrats', ['season' => $game->formatted_season]) }}</p>
                        <a href="{{ route('game.season-end', $game->id) }}"
                           class="inline-flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors min-h-[44px]">
                            {{ __('game.view_season_summary') }}
                        </a>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
