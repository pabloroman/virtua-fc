@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if($nextMatch)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                    {{-- Left Column (2/3) - Main Content --}}
                    <div class="md:col-span-2 space-y-8">
                        {{-- Next Match --}}
                        @php
                            $competitionRole = $nextMatch->competition->role ?? 'primary';
                            $accent = match($competitionRole) {
                                'domestic_cup' => ['border' => 'border-l-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-800'],
                                'european' => ['border' => 'border-l-blue-600', 'badge' => 'bg-blue-100 text-blue-800'],
                                default => ['border' => 'border-l-amber-500', 'badge' => 'bg-amber-100 text-amber-800'],
                            };
                        @endphp
                        <div class="border-l-4 {{ $accent['border'] }} pl-6">
                            {{-- Competition & Round Header --}}
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-semibold text-xl text-slate-900">{{ __('game.next_match') }}</h3>
                                <div class="flex items-center gap-3">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $accent['badge'] }}">
                                        {{ $nextMatch->competition->name ?? 'League' }}
                                    </span>
                                    @if($nextMatch->round_name)
                                        <span class="text-sm text-slate-500">{{ $nextMatch->round_name }}</span>
                                    @else
                                        <span class="text-sm text-slate-500">{{ __('game.matchday_n', ['number' => $nextMatch->round_number]) }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Teams Face-off --}}
                            <div class="flex items-center justify-between py-4">
                                {{-- Home Team --}}
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 md:gap-4">
                                        <img src="{{ $nextMatch->homeTeam->image }}" class="w-12 h-12 md:w-20 md:h-20">
                                        <div>
                                            <h4 class="text-xl font-bold text-slate-900">{{ $nextMatch->homeTeam->name }}</h4>
                                            @if($homeStanding)
                                            <div class="text-sm text-slate-500 mt-1">
                                                {{ $homeStanding->position }}{{ $homeStanding->position == 1 ? 'st' : ($homeStanding->position == 2 ? 'nd' : ($homeStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $homeStanding->points }} pts
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
                                                    <span class="text-slate-400 text-xs">{{ __('game.no_form') }}</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- VS --}}
                                <div class="px-2 md:px-8 text-center">
                                    <div class="text-xl md:text-2xl font-black text-slate-300">vs</div>
                                </div>

                                {{-- Away Team --}}
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 md:gap-4 flex-row-reverse">
                                        <img src="{{ $nextMatch->awayTeam->image }}" class="w-12 h-12 md:w-20 md:h-20">
                                        <div class="text-right">
                                            <h4 class="text-xl font-bold text-slate-900">{{ $nextMatch->awayTeam->name }}</h4>
                                            @if($awayStanding)
                                            <div class="text-sm text-slate-500 mt-1">
                                                {{ $awayStanding->position }}{{ $awayStanding->position == 1 ? 'st' : ($awayStanding->position == 2 ? 'nd' : ($awayStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $awayStanding->points }} pts
                                            </div>
                                            @endif
                                            <div class="flex gap-1 mt-2 justify-end">
                                                @php $awayForm = $nextMatch->away_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                                                @forelse($awayForm as $result)
                                                    <span class="w-5 h-5 rounded text-xs font-bold flex items-center justify-center
                                                        @if($result === 'W') bg-green-500 text-white
                                                        @elseif($result === 'D') bg-slate-400 text-white
                                                        @else bg-red-500 text-white @endif">
                                                        {{ $result }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-400 text-xs">{{ __('game.no_form') }}</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Set Lineup Button --}}
                            <div class="mt-6 text-center">
                                <a href="{{ route('game.lineup', [$game->id, $nextMatch->id]) }}"
                                   class="inline-flex items-center gap-2 px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                    {{ __('game.set_lineup') }}
                                </a>
                            </div>
                        </div>

                        {{-- Upcoming Fixtures --}}
                        @if($upcomingFixtures->isNotEmpty())
                        <div class="pt-8 border-t">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-xl text-slate-900">{{ __('game.upcoming_fixtures') }}</h3>
                                <a href="{{ route('game.calendar', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                    {{ __('game.full_calendar') }} &rarr;
                                </a>
                            </div>

                            <div class="space-y-2">
                                @foreach($upcomingFixtures->take(5) as $fixture)
                                    <x-fixture-row :match="$fixture" :game="$game" :show-score="false" :highlight-next="false" />
                                @endforeach
                            </div>
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
                                                        @if($notification->isUnread())
                                                        <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                                                        @endif
                                                    </div>

                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="{{ $notification->isUnread() ? 'font-semibold' : 'font-normal' }} text-sm {{ $classes['text'] }} truncate">{{ $notification->title }}</span>
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
                    </div>
                </div>
            </div>
            @else
            {{-- Season Complete State --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8 text-center">
                    <div class="text-6xl mb-4">&#127942;</div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-2">{{ __('game.season_complete') }}</h2>
                    <p class="text-slate-500 mb-8">{{ __('game.season_complete_congrats', ['season' => $game->formatted_season]) }}</p>
                    <a href="{{ route('game.season-end', $game->id) }}"
                       class="inline-flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors">
                        {{ __('game.view_season_summary') }}
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
