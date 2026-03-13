@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        {{-- Pending action alert --}}
        @if($game->hasPendingActions())
            @php $pendingAction = $game->getFirstPendingAction(); @endphp
            <div class="mt-6 p-4 bg-accent-gold/10 border border-accent-gold/20 rounded-lg flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <span class="text-sm text-accent-gold font-medium">{{ __('messages.action_required') }}</span>
                </div>
                @if($pendingAction && $pendingAction['route'])
                <x-primary-button-link color="amber" :href="route($pendingAction['route'], $game->id)" class="shrink-0">
                    {{ __('messages.action_required_short') }}
                </x-primary-button-link>
                @endif
            </div>
        @endif

        {{-- Pre-Season Banner --}}
        @if(!empty($isPreSeason))
        <div class="mt-6 p-4 bg-[var(--accent-tint)] border border-accent-blue/20 rounded-lg flex flex-col md:flex-row md:items-center md:justify-between gap-3" x-data="{ confirmSkip: false }">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-accent-blue/10 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-accent-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-accent-blue">{{ __('game.pre_season_banner_title') }}</h4>
                    <p class="text-sm text-accent-blue mt-0.5">
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
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
            {{-- Left Column (2/3) - Main Content --}}
            <div class="md:col-span-2 space-y-8">
                {{-- Highlighted Next Match Card --}}
                @include('partials.next-match-card')

                {{-- Mobile-only: Set Lineup Button --}}
                <div class="md:hidden">
                    <x-primary-button-link :href="route('game.lineup', $game->id)" class="w-full gap-2">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        {{ __('game.set_lineup') }}
                    </x-primary-button-link>
                </div>

                {{-- Remaining Upcoming Fixtures --}}
                @if($upcomingFixtures->skip(1)->isNotEmpty())
                <div class="bg-surface-800 rounded-xl border border-border-default p-4 md:p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-lg font-semibold text-text-primary">{{ __('game.upcoming_fixtures') }}</h4>
                        <a href="{{ route('game.calendar', $game->id) }}" class="text-sm text-accent-blue hover:text-accent-blue">
                            {{ __('game.full_calendar') }} &rarr;
                        </a>
                    </div>
                    <div class="space-y-2">
                        @foreach($upcomingFixtures->skip(1)->take(4) as $fixture)
                            <x-fixture-row :match="$fixture" :game="$game" :show-score="false" :highlight-next="false" />
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <hr class="border-border-strong md:hidden" />

            {{-- Right Column (1/3) - Notifications & Standings --}}
            <div class="space-y-8">
                {{-- Notifications Inbox --}}
                <div class="bg-surface-800 rounded-xl border border-border-default p-4 md:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <h4 class="text-lg font-semibold text-text-primary">{{ __('notifications.inbox') }}</h4>
                            @if($unreadNotificationCount > 0)
                            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-accent-red rounded-full">
                                {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                            </span>
                            @endif
                        </div>
                        @if($unreadNotificationCount > 0)
                        <form action="{{ route('game.notifications.read-all', $game->id) }}" method="POST">
                            @csrf
                            <x-ghost-button type="submit" color="blue" size="xs">
                                {{ __('notifications.mark_all_read') }}
                            </x-ghost-button>
                        </form>
                        @endif
                    </div>

                    @if($groupedNotifications->isEmpty())
                    <div class="text-center py-8">
                        <div class="text-text-body mb-2">
                            <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-text-secondary">{{ __('notifications.all_caught_up') }}</p>
                    </div>
                    @else
                    <div class="space-y-4">
                        @foreach($groupedNotifications as $date => $notifications)
                        <div>
                            {{-- Date Header --}}
                            <div class="text-xs font-medium text-text-muted uppercase tracking-wide mb-2">
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
                                            <div class="relative shrink-0">
                                                <x-notification-icon :icon="$notification->icon" :icon-bg="$classes['icon_bg']" :icon-text="$classes['icon_text']" />
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-semibold text-sm {{ $classes['text'] }} truncate">{{ $notification->title }}</span>
                                                    <svg class="w-4 h-4 {{ $classes['text'] }} opacity-40 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </div>
                                                @if($notification->message)
                                                <p class="text-xs text-text-secondary mt-0.5">{{ $notification->message }}</p>
                                                @endif
                                                @php $badge = $notification->getPriorityBadge(); @endphp
                                                @if($badge)
                                                <span class="inline-flex items-center mt-1 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-sm {{ $badge['bg'] }} {{ $badge['text'] }}">
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

                <hr class="border-border-strong md:hidden" />

                {{-- Abridged League Standings (hidden during pre-season) --}}
                @if($leagueStandings->isNotEmpty() && empty($isPreSeason))
                <div class="bg-surface-800 rounded-xl border border-border-default p-4 md:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-semibold text-text-primary">
                            @if($game->isTournamentMode() && $leagueStandings->first()?->group_label)
                                {{ __('game.group') }} {{ $leagueStandings->first()->group_label }}
                            @else
                                {{ __('game.standings') }}
                            @endif
                        </h4>
                        <a href="{{ route('game.competition', [$game->id, $game->competition_id]) }}" class="text-sm text-accent-blue hover:text-accent-blue">
                            {{ __('game.full_table') }} &rarr;
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border-strong text-xs text-text-muted font-semibold">
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
                                    <tr><td colspan="5" class="text-center text-text-body py-0.5 text-xs">&middot;&middot;&middot;</td></tr>
                                @endif
                                <tr class="border-b border-border-default {{ $isPlayer ? 'bg-accent-gold/10 font-semibold' : '' }}">
                                    <td class="py-1.5 text-text-muted">{{ $standing->position }}</td>
                                    <td class="py-1.5">
                                        <div class="flex items-center gap-2">
                                            <x-team-crest :team="$standing->team" class="w-5 h-5 shrink-0" />
                                            <span class="truncate {{ $isPlayer ? 'text-text-primary' : 'text-text-body' }}">{{ $standing->team->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-1.5 text-center text-text-secondary">{{ $standing->played }}</td>
                                    <td class="py-1.5 text-center text-text-secondary">{{ $standing->goal_difference >= 0 ? '+' : '' }}{{ $standing->goal_difference }}</td>
                                    <td class="py-1.5 text-center font-semibold text-text-primary">{{ $standing->points }}</td>
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
        @elseif($hasRemainingMatches)
        {{-- AI Matches Remaining State --}}
        <div class="mt-6 bg-surface-800 rounded-xl border border-border-default p-4 md:p-8 text-center">
            <div class="text-text-body mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-3xl font-bold text-text-primary mb-2">{{ __('game.other_competitions_in_progress') }}</h2>
            <p class="text-text-muted mb-8">{{ __('game.other_competitions_desc') }}</p>
            <form action="{{ route('game.advance', $game->id) }}" method="POST">
                @csrf
                <x-primary-button color="red">
                    {{ __('game.advance_other_matches') }}
                </x-primary-button>
            </form>
        </div>
        @endif
    </div>
</x-app-layout>
