@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch|null $lastMatch */
    /** @var App\Models\GameMatch|null $nextMatch */
    /** @var \Illuminate\Support\Collection $leagueStandings */
    /** @var App\Models\GameStanding|null $playerStanding */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 pb-8">
        {{-- Fast-mode intro banner --}}
        <x-status-banner color="blue" :title="__('game.fast_mode')" :description="__('game.fast_mode_explanation')" class="mt-6">
            <x-slot name="icon">
                <svg fill="currentColor" viewBox="0 0 24 24">
                    <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </x-slot>
        </x-status-banner>

        {{-- Pending action warning (same semantics as dashboard) --}}
        @if($pendingAction)
            <x-status-banner color="gold" :title="__('messages.action_required')" :description="__('messages.fast_mode_action_required')" class="mt-4">
                <x-slot name="icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </x-slot>
                @if($pendingAction['route'])
                    <x-primary-button-link color="amber" :href="route($pendingAction['route'], $game->id)">
                        {{ __('messages.action_required_short') }}
                    </x-primary-button-link>
                @endif
            </x-status-banner>
        @endif

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
            {{-- Last result --}}
            <x-section-card :title="__('game.last_result')" class="md:col-span-1">
                @if($lastMatch)
                    <div class="p-4 md:p-5 space-y-3">
                        @php
                            $isHome = $lastMatch->home_team_id === $game->team_id;
                            $yourScore = $isHome ? $lastMatch->home_score : $lastMatch->away_score;
                            $oppScore = $isHome ? $lastMatch->away_score : $lastMatch->home_score;
                            $opponent = $isHome ? $lastMatch->awayTeam : $lastMatch->homeTeam;
                            $result = $yourScore > $oppScore ? 'W' : ($yourScore < $oppScore ? 'L' : 'D');
                            $resultClass = $result === 'W' ? 'text-accent-green' : ($result === 'L' ? 'text-accent-red' : 'text-text-secondary');
                            $resultLabel = $result === 'W' ? __('game.live_result_win') : ($result === 'L' ? __('game.live_result_loss') : __('game.live_result_draw'));
                        @endphp

                        <div class="flex items-center justify-between gap-2">
                            <x-competition-pill :competition="$lastMatch->competition" :round-name="$lastMatch->round_name" :round-number="$lastMatch->round_number" :short="true" />
                            <span class="text-[10px] text-text-muted">{{ $lastMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</span>
                        </div>

                        <div class="flex items-center justify-center gap-4 py-2">
                            <div class="flex-1 flex items-center justify-end gap-2 min-w-0">
                                <span class="text-sm md:text-base font-semibold text-text-primary truncate text-right">{{ $lastMatch->homeTeam->name }}</span>
                                <x-team-crest :team="$lastMatch->homeTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                            </div>
                            <div class="px-3 py-1.5 rounded-lg bg-surface-700 text-lg md:text-2xl font-heading font-bold text-text-primary shrink-0 tabular-nums">
                                {{ $lastMatch->home_score }} - {{ $lastMatch->away_score }}
                            </div>
                            <div class="flex-1 flex items-center gap-2 min-w-0">
                                <x-team-crest :team="$lastMatch->awayTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                                <span class="text-sm md:text-base font-semibold text-text-primary truncate">{{ $lastMatch->awayTeam->name }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-center gap-2 text-xs">
                            <span class="font-semibold uppercase tracking-wider {{ $resultClass }}">{{ $resultLabel }}</span>
                            <span class="text-text-muted">·</span>
                            <span class="text-text-muted">{{ __('game.vs') }} {{ $opponent->name }}</span>
                        </div>
                    </div>
                @else
                    <div class="p-6 text-center text-xs text-text-muted">
                        {{ __('game.fast_mode_no_last_match') }}
                    </div>
                @endif
            </x-section-card>

            {{-- League position --}}
            @if($leagueStandings->isNotEmpty())
                @php
                    $standingsTitle = ($game->isTournamentMode() && $leagueStandings->first()?->group_label)
                        ? __('game.group') . ' ' . $leagueStandings->first()->group_label
                        : __('game.standings');
                @endphp
                <x-section-card :title="$standingsTitle" class="md:col-span-1">
                    <div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2 text-[9px] text-text-faint uppercase tracking-wider border-b border-border-default">
                        <span>#</span>
                        <span>{{ __('game.team') }}</span>
                        <span class="text-center">{{ __('game.won_abbr') }}</span>
                        <span class="text-center">{{ __('game.drawn_abbr') }}</span>
                        <span class="text-center">{{ __('game.lost_abbr') }}</span>
                        <span class="text-center">{{ __('game.goal_diff_abbr') }}</span>
                        <span class="text-right">{{ __('game.pts_abbr') }}</span>
                    </div>
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

            {{-- Next match --}}
            @if($nextMatch)
                <x-section-card :title="__('game.next_match')" class="md:col-span-2">
                    <div class="p-4 md:p-5 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <x-competition-pill :competition="$nextMatch->competition" :round-name="$nextMatch->round_name" :round-number="$nextMatch->round_number" :short="true" />
                            <span class="text-[10px] text-text-muted">{{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</span>
                        </div>

                        <div class="flex items-center justify-center gap-4 py-2">
                            <div class="flex-1 flex items-center justify-end gap-2 min-w-0">
                                <span class="text-sm md:text-base font-semibold text-text-primary truncate text-right">{{ $nextMatch->homeTeam->name }}</span>
                                <x-team-crest :team="$nextMatch->homeTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                            </div>
                            <span class="text-base md:text-lg font-heading font-bold text-text-muted shrink-0">{{ __('game.vs') }}</span>
                            <div class="flex-1 flex items-center gap-2 min-w-0">
                                <x-team-crest :team="$nextMatch->awayTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                                <span class="text-sm md:text-base font-semibold text-text-primary truncate">{{ $nextMatch->awayTeam->name }}</span>
                            </div>
                        </div>
                    </div>
                </x-section-card>
            @endif
        </div>

        {{-- Action buttons --}}
        <div class="mt-6 flex flex-col-reverse sm:flex-row items-stretch sm:items-center sm:justify-between gap-3">
            <form action="{{ route('game.fast-mode.exit', $game->id) }}" method="POST" class="w-full sm:w-auto">
                @csrf
                <x-secondary-button type="submit" class="w-full sm:w-auto">
                    <svg class="w-4 h-4 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('game.fast_mode_exit') }}
                </x-secondary-button>
            </form>

            @if($nextMatch)
                <form action="{{ route('game.fast-mode.advance', $game->id) }}" method="POST" class="w-full sm:w-auto"
                      x-data="{ submitting: false }"
                      @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                    @csrf
                    <x-primary-button color="blue" x-bind:disabled="submitting" class="w-full sm:w-auto gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span x-show="!submitting">{{ __('game.fast_mode_simulate_next') }}</span>
                        <span x-show="submitting" x-cloak>{{ __('game.processing_short') }}</span>
                    </x-primary-button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
