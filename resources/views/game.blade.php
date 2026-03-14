@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        {{-- Pending action alert --}}
        @if($game->hasPendingActions())
            @php $pendingAction = $game->getFirstPendingAction(); @endphp
            <x-status-banner color="gold" :title="__('messages.action_required')" class="mt-6">
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </x-slot>
                @if($pendingAction && $pendingAction['route'])
                <x-primary-button-link color="amber" :href="route($pendingAction['route'], $game->id)" class="shrink-0">
                    {{ __('messages.action_required_short') }}
                </x-primary-button-link>
                @endif
            </x-status-banner>
        @endif

        {{-- Pre-Season Banner --}}
        @if(!empty($isPreSeason))
        <x-status-banner color="blue"
            :title="__('game.pre_season_banner_title')"
            :description="__('game.pre_season_banner_desc', ['date' => isset($seasonStartDate) ? $seasonStartDate->locale(app()->getLocale())->translatedFormat('d M Y') : ''])"
            class="mt-6" x-data="{ confirmSkip: false }">
            <x-slot name="icon">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </x-slot>
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
        </x-status-banner>
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
                <x-section-card :title="__('game.upcoming_fixtures')">
                    <x-slot name="badge">
                        <a href="{{ route('game.calendar', $game->id) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                            {{ __('game.full_calendar') }} &rarr;
                        </a>
                    </x-slot>
                    <div class="divide-y divide-border-default">
                        @foreach($upcomingFixtures->skip(1)->take(4) as $fixture)
                            <x-fixture-row :match="$fixture" :game="$game" :show-score="false" :highlight-next="false" />
                        @endforeach
                    </div>
                </x-section-card>
                @endif
            </div>

            <hr class="border-border-strong md:hidden" />

            {{-- Right Column (1/3) - Notifications & Standings --}}
            <div class="space-y-8">
                {{-- Notifications Inbox --}}
                <x-section-card :title="__('notifications.inbox')">
                    <x-slot name="badge">
                        <div class="flex items-center gap-2">
                            @if($unreadNotificationCount > 0)
                            <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[9px] font-semibold text-accent-blue">
                                {{ $unreadNotificationCount }} {{ __('notifications.new') }}
                            </span>
                            @endif
                            <form action="{{ route('game.notifications.read-all', $game->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">{{ __('notifications.mark_all_read') }}</button>
                            </form>
                        </div>
                    </x-slot>

                    @if($groupedNotifications->isEmpty())
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
                        @foreach($groupedNotifications->flatten() as $notification)
                            <x-notification-row :notification="$notification" :game="$game" />
                        @endforeach
                    </div>
                    @endif
                </x-section-card>

                <hr class="border-border-strong md:hidden" />

                {{-- Abridged League Standings (hidden during pre-season) --}}
                @if($leagueStandings->isNotEmpty() && empty($isPreSeason))
                @php
                    $standingsTitle = ($game->isTournamentMode() && $leagueStandings->first()?->group_label)
                        ? __('game.group') . ' ' . $leagueStandings->first()->group_label
                        : __('game.standings');
                @endphp
                <x-section-card :title="$standingsTitle">
                    <x-slot name="badge">
                        <a href="{{ route('game.competition', [$game->id, $game->competition_id]) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                            {{ __('game.full_table') }} &rarr;
                        </a>
                    </x-slot>

                    {{-- Column headers --}}
                    <div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2 text-[9px] text-text-faint uppercase tracking-wider border-b border-border-default">
                        <span>#</span>
                        <span>{{ __('game.team') }}</span>
                        <span class="text-center">{{ __('game.won_abbr') }}</span>
                        <span class="text-center">{{ __('game.drawn_abbr') }}</span>
                        <span class="text-center">{{ __('game.lost_abbr') }}</span>
                        <span class="text-center">{{ __('game.goal_diff_abbr') }}</span>
                        <span class="text-right">{{ __('game.pts_abbr') }}</span>
                    </div>

                    {{-- Rows --}}
                    <div class="divide-y divide-border-default">
                        @php $prevPosition = 0; @endphp
                        @foreach($leagueStandings as $standing)
                            <x-standing-row
                                :standing="$standing"
                                :is-player="$standing->team_id === $game->team_id"
                                :show-gap="$standing->position > $prevPosition + 1"
                            />
                            @php $prevPosition = $standing->position; @endphp
                        @endforeach
                    </div>
                </x-section-card>
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
