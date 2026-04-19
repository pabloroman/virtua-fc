@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch|null $lastMatch */
    /** @var App\Models\GameMatch|null $nextMatch */
    /** @var \Illuminate\Support\Collection $leagueStandings */
    /** @var App\Models\GameStanding|null $playerStanding */

    // Last-result summary (compact one-line view)
    $lastResultLabel = null;
    $lastResultClass = null;
    $lastOpponent = null;
    $homeScorers = collect();
    $awayScorers = collect();
    if ($lastMatch) {
        $isHome = $lastMatch->home_team_id === $game->team_id;
        $yourScore = $isHome ? $lastMatch->home_score : $lastMatch->away_score;
        $oppScore = $isHome ? $lastMatch->away_score : $lastMatch->home_score;
        $lastOpponent = $isHome ? $lastMatch->awayTeam : $lastMatch->homeTeam;
        $result = $yourScore > $oppScore ? 'W' : ($yourScore < $oppScore ? 'L' : 'D');
        $lastResultClass = $result === 'W' ? 'text-accent-green' : ($result === 'L' ? 'text-accent-red' : 'text-text-secondary');
        $lastResultLabel = $result . ' ' . $yourScore . '-' . $oppScore;

        // Group goal events by team, then by player, preserving first-occurrence
        // order so the list reads chronologically.
        $formatScorers = function ($events) {
            return $events
                ->groupBy(fn ($e) => optional($e->gamePlayer?->player)->name ?? '—')
                ->map(function ($playerEvents, $name) {
                    $minutes = $playerEvents->map(function ($e) {
                        $label = $e->minute . "'";
                        if ($e->event_type === \App\Models\MatchEvent::TYPE_OWN_GOAL) {
                            $label .= ' ' . __('game.og');
                        }
                        return $label;
                    })->implode(', ');
                    return ['name' => $name, 'minutes' => $minutes];
                })
                ->values();
        };

        $homeScorers = $formatScorers(
            $lastMatch->goalEvents->filter(fn ($e) => $e->team_id === $lastMatch->home_team_id)
        );
        $awayScorers = $formatScorers(
            $lastMatch->goalEvents->filter(fn ($e) => $e->team_id === $lastMatch->away_team_id)
        );
    }
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-[100dvh] flex flex-col">
        {{-- Top bar: fast-mode badge + exit shortcut (subtle, always reachable) --}}
        <div class="shrink-0 flex items-center justify-between px-4 pt-4 md:pt-6 max-w-3xl w-full mx-auto">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-accent-blue/10 text-accent-blue border border-accent-blue/20">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span class="text-[11px] font-semibold uppercase tracking-wider">{{ __('game.fast_mode') }}</span>
            </div>
            <a href="{{ route('show-game', $game->id) }}" class="text-[10px] text-text-muted hover:text-text-body transition-colors uppercase tracking-wider">
                {{ __('game.season') }} {{ $game->formatted_season }}
            </a>
        </div>

        {{-- Pending-action warning (inline, only when present) --}}
        @if($pendingAction)
            <div class="shrink-0 px-4 mt-4 max-w-3xl w-full mx-auto">
                <x-status-banner color="gold" :title="__('messages.action_required')" :description="__('messages.fast_mode_action_required')">
                    <x-slot name="icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </x-slot>
                    @if($pendingAction['route'])
                        <x-primary-button-link color="amber" size="xs" :href="route($pendingAction['route'], $game->id)">
                            {{ __('messages.action_required_short') }}
                        </x-primary-button-link>
                    @endif
                </x-status-banner>
            </div>
        @endif

        {{-- Main content: scrollable if necessary, centered on larger viewports --}}
        <div class="flex-1 flex flex-col justify-center px-4 py-6 md:py-10 max-w-3xl w-full mx-auto">
            {{-- Last result panel with goal scorers + inline league position --}}
            <div class="rounded-xl border border-border-default bg-surface-800/60 px-3 py-3 md:px-4 md:py-4 mb-6 md:mb-10">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div class="text-[9px] md:text-[10px] text-text-faint uppercase tracking-widest">{{ __('game.last_result') }}</div>
                    @if($playerStanding)
                        <div class="flex items-baseline gap-1 text-[10px] md:text-xs">
                            <span class="text-text-faint uppercase tracking-wider">{{ __('game.standings') }}</span>
                            <span class="font-heading font-bold text-accent-blue tabular-nums">
                                {{ $playerStanding->position }}{{ $playerStanding->position == 1 ? 'st' : ($playerStanding->position == 2 ? 'nd' : ($playerStanding->position == 3 ? 'rd' : 'th')) }}
                            </span>
                            <span class="text-text-muted">· {{ $playerStanding->points }} {{ __('game.pts') }}</span>
                        </div>
                    @endif
                </div>

                @if($lastMatch)
                    <div class="flex items-center gap-2 min-w-0 mb-2">
                        <span class="text-base md:text-lg font-heading font-bold {{ $lastResultClass }} tabular-nums shrink-0">{{ $lastResultLabel }}</span>
                        <span class="text-[11px] md:text-xs text-text-muted truncate">{{ __('game.vs') }} {{ $lastOpponent->short_name ?? $lastOpponent->name }}</span>
                    </div>

                    @if($homeScorers->isNotEmpty() || $awayScorers->isNotEmpty())
                        <div class="space-y-1 mt-2 pt-2 border-t border-border-default">
                            @foreach([['team' => $lastMatch->homeTeam, 'scorers' => $homeScorers], ['team' => $lastMatch->awayTeam, 'scorers' => $awayScorers]] as $side)
                                @if($side['scorers']->isNotEmpty())
                                    <div class="flex items-start gap-2 text-[11px] md:text-xs">
                                        <x-team-crest :team="$side['team']" class="w-4 h-4 shrink-0 mt-0.5" />
                                        <div class="flex-1 min-w-0 flex flex-wrap gap-x-2 gap-y-0.5 text-text-body">
                                            @foreach($side['scorers'] as $scorer)
                                                <span class="whitespace-nowrap">
                                                    <span class="font-medium">{{ $scorer['name'] }}</span>
                                                    <span class="text-text-muted tabular-nums">{{ $scorer['minutes'] }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="text-[11px] md:text-xs text-text-muted">{{ __('game.fast_mode_no_last_match') }}</div>
                @endif
            </div>

            @if($nextMatch)
                {{-- Big face-off for the upcoming match (inspired by game-loading-matchday) --}}
                <div class="text-center mb-6">
                    <x-competition-pill :competition="$nextMatch->competition" class="justify-center mb-2" />
                    <h1 class="text-base md:text-xl font-heading font-bold text-text-primary">
                        @if($nextMatch->round_name)
                            {{ __($nextMatch->round_name) }}
                        @elseif($nextMatch->round_number)
                            {{ __('game.matchday_n', ['number' => $nextMatch->round_number]) }}
                        @endif
                    </h1>
                    <p class="text-[11px] md:text-xs text-text-muted mt-1">
                        {{ $nextMatch->homeTeam->stadium_name ?? '' }} &middot; {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                    </p>
                </div>

                <div class="flex items-center justify-center gap-4 md:gap-10">
                    <div class="flex-1 flex flex-col items-center text-center min-w-0">
                        <x-team-crest :team="$nextMatch->homeTeam" class="w-16 h-16 md:w-28 md:h-28 mb-2" />
                        <h4 class="text-sm md:text-lg font-bold text-text-primary truncate max-w-full">{{ $nextMatch->homeTeam->short_name ?? $nextMatch->homeTeam->name }}</h4>
                    </div>
                    <span class="text-xl md:text-3xl font-black font-heading text-text-muted tracking-tight shrink-0">{{ __('game.vs') }}</span>
                    <div class="flex-1 flex flex-col items-center text-center min-w-0">
                        <x-team-crest :team="$nextMatch->awayTeam" class="w-16 h-16 md:w-28 md:h-28 mb-2" />
                        <h4 class="text-sm md:text-lg font-bold text-text-primary truncate max-w-full">{{ $nextMatch->awayTeam->short_name ?? $nextMatch->awayTeam->name }}</h4>
                    </div>
                </div>
            @else
                {{-- Rare case: fast mode active but no next match (season just ended between clicks) --}}
                <div class="text-center py-10">
                    <p class="text-text-secondary">{{ __('game.season_complete') }}</p>
                </div>
            @endif
        </div>

        {{-- Sticky action bar — always visible at bottom of viewport on desktop and mobile --}}
        <div class="shrink-0 sticky bottom-0 bg-surface-900/95 backdrop-blur-md border-t border-border-default">
            <div class="max-w-3xl mx-auto px-4 py-3 md:py-4 flex items-center gap-2 md:gap-3">
                <form action="{{ route('game.fast-mode.exit', $game->id) }}" method="POST" class="shrink-0">
                    @csrf
                    <x-secondary-button type="submit" class="gap-1.5" aria-label="{{ __('game.fast_mode_exit') }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span class="hidden sm:inline">{{ __('game.fast_mode_exit') }}</span>
                    </x-secondary-button>
                </form>

                @if($nextMatch)
                    <form action="{{ route('game.fast-mode.advance', $game->id) }}" method="POST" class="flex-1"
                          x-data="{ submitting: false }"
                          @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                        @csrf
                        <x-primary-button color="blue" x-bind:disabled="submitting" class="w-full gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" x-show="!submitting">
                                <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <svg class="w-4 h-4 shrink-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" x-show="submitting" x-cloak>
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-show="!submitting">{{ __('game.fast_mode_simulate_next') }}</span>
                            <span x-show="submitting" x-cloak>{{ __('game.processing_short') }}</span>
                        </x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
