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

        @if($nextMatch)
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
            {{-- Context rail: next match + fixtures + standings. The narrow 1/3
                 column on desktop (md:order-2); on mobile it stacks first (DOM
                 order) so the "play match" flow stays at the top. --}}
            <div class="space-y-8 md:order-2 md:col-span-1">
                {{-- Highlighted Next Match Card --}}
                @include('partials.next-match-card')

                {{-- Remaining Upcoming Fixtures / Next Round Preview --}}
                @if($upcomingFixtures->skip(1)->isNotEmpty())
                <x-section-card :title="__('game.upcoming_fixtures')">
                    <x-slot name="badge">
                        <a href="{{ route('game.calendar', $game->id) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                            {{ __('game.full_calendar') }} &rarr;
                        </a>
                    </x-slot>
                    <div class="divide-y divide-border-default">
                        @foreach($upcomingFixtures->skip(1)->take(4) as $fixture)
                            <x-fixture-row :match="$fixture" :game="$game" :show-score="false" :highlight-next="false" :short-competition="true" />
                        @endforeach
                    </div>
                </x-section-card>
                @elseif(isset($nextRoundPreview))
                <x-section-card :title="__('game.upcoming_fixtures')">
                    <div class="flex items-center gap-3 px-4 py-2.5">
                        <div class="w-10 shrink-0 text-center">
                            <span class="text-[9px] text-text-faint uppercase">TBD</span>
                        </div>
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                            @if($nextRoundPreview['opponent'])
                                <x-team-crest :team="$nextRoundPreview['opponent']" class="w-5 h-5 shrink-0" />
                                <span class="text-xs text-text-body">{{ $nextRoundPreview['opponent']->name }}</span>
                            @else
                                <div class="flex items-center gap-1.5">
                                    <x-team-crest :team="$nextRoundPreview['tie']->homeTeam" class="w-5 h-5 shrink-0" />
                                    <span class="text-xs text-text-secondary truncate">{{ $nextRoundPreview['tie']->homeTeam->name }}</span>
                                    <span class="text-xs text-text-muted">/</span>
                                    <x-team-crest :team="$nextRoundPreview['tie']->awayTeam" class="w-5 h-5 shrink-0" />
                                    <span class="text-xs text-text-secondary truncate">{{ $nextRoundPreview['tie']->awayTeam->name }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-section-card>
                @endif

                {{-- Contextual standings or cup-path card (hidden during pre-season).
                     The card follows the competition of the *next* match, not the
                     primary league, so European/cup matchdays surface the right table. --}}
                @if(empty($isPreSeason))
                    @if($dashboardContext['mode'] === 'league' && $dashboardContext['standings']->isNotEmpty())
                    <x-section-card :title="$dashboardContext['title']">
                        <x-slot name="badge">
                            <a href="{{ route('game.competition', [$game->id, $dashboardContext['competition']->id]) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
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
                            @foreach($dashboardContext['standings'] as $standing)
                                <x-standing-row
                                    :standing="$standing"
                                    :is-player="$standing->team_id === $game->team_id"
                                    :show-gap="$standing->position > $prevPosition + 1"
                                />
                                @php $prevPosition = $standing->position; @endphp
                            @endforeach
                        </div>
                    </x-section-card>
                    @elseif($dashboardContext['mode'] === 'knockout' && $dashboardContext['playerTie'])
                    <x-cup-path-card
                        :game="$game"
                        :competition="$dashboardContext['competition']"
                        :player-tie="$dashboardContext['playerTie']"
                        :rounds-remaining="$dashboardContext['roundsRemaining']"
                        :final-venue="$dashboardContext['finalVenue']"
                    />
                    @endif
                @endif
            </div>

            <hr class="border-border-strong md:hidden" />

            {{-- Wide 2/3 column on desktop (md:order-1): the notifications inbox
                 leads with actionable per-matchday events, then News sets the scene. --}}
            <div class="space-y-4 md:space-y-6 md:order-1 md:col-span-2">
                @if($showInbox)
                <x-section-card :title="__('notifications.inbox')">
                    <x-slot name="badge">
                        @if($unreadNotificationCount > 0)
                        <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[9px] font-semibold text-accent-blue">
                            {{ $unreadNotificationCount }} {{ __('notifications.new') }}
                        </span>
                        @endif
                    </x-slot>

                    @if($groupedNotifications->isEmpty())
                    <div class="flex items-center gap-2 px-5 py-2.5 text-xs text-text-muted">
                        <svg class="w-4 h-4 text-text-faint shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ __('notifications.all_caught_up') }}</span>
                    </div>
                    @else
                    <x-notification-inbox-list :notifications="$groupedNotifications->flatten()" :game="$game" />
                    @endif
                </x-section-card>
                @endif

                {{-- News: the league narrative engine surfaced as icon-tagged story
                     items (transfer buzz, rivalry, European nights, form, mood…).
                     Season-based modes surface it here; tournament mode keeps the
                     prose in the next-match card. Leads the column on quiet
                     matchdays when the inbox is empty and hidden. --}}
                @if($showNews)
                    <x-news :narratives="$narratives" :game="$game" />
                @endif
            </div>
        </div>
        @elseif($hasRemainingMatches)
        {{-- AI Matches Remaining State --}}
        <div class="mt-6 bg-surface-800 rounded-lg border border-border-default p-4 md:p-8 text-center">
            <div class="text-text-body mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-3xl font-bold text-text-primary mb-2">{{ __('game.other_competitions_in_progress') }}</h2>
            <p class="text-text-muted mb-8">{{ __('game.other_competitions_desc') }}</p>
            <form action="{{ route('game.advance', $game->id) }}" method="POST" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); return; } submitting = true; $dispatch('matchday-advance-starting')">
                @csrf
                <x-primary-button color="red" x-bind:disabled="submitting">
                    {{ __('game.advance_other_matches') }}
                </x-primary-button>
            </form>
        </div>
        @else
        {{-- Season Complete Preview State.
             All matches are played but the user has not yet clicked "Start
             New Season" on the summary page (the irreversible step that
             closes the season). Show a card that points to the summary, but
             leave the rest of the dashboard browsable so the user can take
             one last look at the squad, finances, transfers, and standings. --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
            <div class="md:col-span-2 space-y-8">
                <x-section-card>
                    <div class="p-4 md:p-6 text-center">
                        <div class="text-accent-gold mb-3 inline-flex">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                        </div>
                        <h2 class="text-2xl md:text-3xl font-bold text-text-primary mb-2">{{ __('season.complete_title') }}</h2>
                        <p class="text-text-muted mb-6 max-w-xl mx-auto">{{ __('season.complete_body') }}</p>
                        <x-primary-button-link color="amber" :href="route('game.season-end', $game->id)">
                            {{ __('game.view_season_summary') }}
                        </x-primary-button-link>
                    </div>
                </x-section-card>
            </div>

            <hr class="border-border-strong md:hidden" />

            {{-- Right Column - Notifications (standings panel is hidden when
                 there is no next match; the user can still reach it via the
                 Competitions nav). --}}
            <div class="space-y-8">
                <x-section-card :title="__('notifications.inbox')">
                    <x-slot name="badge">
                        @if($unreadNotificationCount > 0)
                        <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[9px] font-semibold text-accent-blue">
                            {{ $unreadNotificationCount }} {{ __('notifications.new') }}
                        </span>
                        @endif
                    </x-slot>

                    @if($groupedNotifications->isEmpty())
                    <div class="flex items-center gap-2 px-5 py-2.5 text-xs text-text-muted">
                        <svg class="w-4 h-4 text-text-faint shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ __('notifications.all_caught_up') }}</span>
                    </div>
                    @else
                    <x-notification-inbox-list :notifications="$groupedNotifications->flatten()" :game="$game" />
                    @endif
                </x-section-card>
            </div>
        </div>
        @endif
    </div>

</x-app-layout>
