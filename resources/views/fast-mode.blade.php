@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch|null $lastMatch */
    /** @var App\Models\GameMatch|null $nextMatch */
    /** @var \Illuminate\Support\Collection $leagueStandings */
    /** @var App\Models\GameStanding|null $playerStanding */

    // Last-result summary
    $lastResultLabel = null;
    $lastResultColor = null;
    $lastResultBg = null;
    $lastOpponent = null;
    $homeScorers = collect();
    $awayScorers = collect();
    $lastHomeTotal = null;
    $lastAwayTotal = null;
    if ($lastMatch) {
        $isHome = $lastMatch->home_team_id === $game->team_id;
        // ET-inclusive totals: home_score/away_score are the 90-minute score;
        // goals scored in extra time are stored separately in home_score_et/away_score_et.
        $lastHomeTotal = (int) $lastMatch->home_score + (int) ($lastMatch->home_score_et ?? 0);
        $lastAwayTotal = (int) $lastMatch->away_score + (int) ($lastMatch->away_score_et ?? 0);
        $yourTotal = $isHome ? $lastHomeTotal : $lastAwayTotal;
        $oppTotal = $isHome ? $lastAwayTotal : $lastHomeTotal;
        $lastOpponent = $isHome ? $lastMatch->awayTeam : $lastMatch->homeTeam;

        if ($yourTotal !== $oppTotal) {
            $result = $yourTotal > $oppTotal ? 'W' : 'L';
        } elseif ($lastMatch->home_score_penalties !== null) {
            // Tied after ET — penalty shootout decides the outcome.
            $yourPens = $isHome ? $lastMatch->home_score_penalties : $lastMatch->away_score_penalties;
            $oppPens = $isHome ? $lastMatch->away_score_penalties : $lastMatch->home_score_penalties;
            $result = $yourPens > $oppPens ? 'W' : 'L';
        } else {
            $result = 'D';
        }

        $lastResultLabel = $result === 'W' ? __('game.live_result_win') : ($result === 'L' ? __('game.live_result_loss') : __('game.live_result_draw'));
        $lastResultColor = $result === 'W' ? 'text-accent-green' : ($result === 'L' ? 'text-accent-red' : 'text-text-secondary');
        $lastResultBg = $result === 'W' ? 'bg-accent-green/10 border-accent-green/20' : ($result === 'L' ? 'bg-accent-red/10 border-accent-red/20' : 'bg-surface-700 border-border-default');

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

        // Own goals are stored with team_id = the scoring player's own team (the conceding side).
        // They should display under the team that benefits — i.e. the opponent.
        $scoringTeamId = function ($event) use ($lastMatch) {
            if ($event->event_type === \App\Models\MatchEvent::TYPE_OWN_GOAL) {
                return $event->team_id === $lastMatch->home_team_id
                    ? $lastMatch->away_team_id
                    : $lastMatch->home_team_id;
            }
            return $event->team_id;
        };

        $homeScorers = $formatScorers(
            $lastMatch->goalEvents->filter(fn ($e) => $scoringTeamId($e) === $lastMatch->home_team_id)
        );
        $awayScorers = $formatScorers(
            $lastMatch->goalEvents->filter(fn ($e) => $scoringTeamId($e) === $lastMatch->away_team_id)
        );
    }

    $standingsTitle = ($game->isTournamentMode() && $leagueStandings->first()?->group_label)
        ? __('game.group') . ' ' . $leagueStandings->first()->group_label
        : __('game.standings');
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-[100dvh] flex flex-col">
        {{-- Top bar: fast-mode badge + season link --}}
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

        {{-- Main content: stacks top-to-bottom, scrolls when tall --}}
        <div class="flex-1 px-4 py-5 md:py-8 max-w-3xl w-full mx-auto space-y-5 md:space-y-6">
            {{-- Last result — the focal card --}}
            @if($lastMatch)
                <div class="rounded-xl border border-border-default bg-surface-800 overflow-hidden">
                    {{-- Header row: label + result badge + competition --}}
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-border-default bg-surface-800/60">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-[10px] text-text-faint uppercase tracking-widest">{{ __('game.last_result') }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border {{ $lastResultBg }} {{ $lastResultColor }}">
                                {{ $lastResultLabel }}
                            </span>
                        </div>
                        <x-competition-pill :competition="$lastMatch->competition" :round-name="$lastMatch->round_name" :round-number="$lastMatch->round_number" :short="true" class="scale-90 origin-right" />
                    </div>

                    {{-- Face-off: crests, names, big score --}}
                    <div class="px-4 py-4 md:py-5">
                        <div class="flex items-center justify-center gap-3 md:gap-5">
                            <div class="flex-1 flex items-center justify-end gap-2 md:gap-3 min-w-0">
                                <span class="text-sm md:text-base font-semibold text-text-primary truncate text-right">
                                    {{ $lastMatch->homeTeam->short_name ?? $lastMatch->homeTeam->name }}
                                </span>
                                <x-team-crest :team="$lastMatch->homeTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                            </div>
                            <div class="shrink-0 flex flex-col items-center gap-1">
                                <div class="px-3 py-1.5 md:px-4 md:py-2 rounded-lg bg-surface-700 text-xl md:text-3xl font-heading font-bold text-text-primary tabular-nums">
                                    {{ $lastHomeTotal }} - {{ $lastAwayTotal }}
                                </div>
                                @if($lastMatch->is_extra_time)
                                    <div class="text-[9px] md:text-[10px] text-text-muted uppercase tracking-widest tabular-nums">
                                        @if($lastMatch->home_score_penalties !== null)
                                            {{ __('season.aet_abbr') }} &middot; {{ __('season.pens_abbr') }} {{ $lastMatch->home_score_penalties }}-{{ $lastMatch->away_score_penalties }}
                                        @else
                                            {{ __('season.aet_abbr') }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 flex items-center gap-2 md:gap-3 min-w-0">
                                <x-team-crest :team="$lastMatch->awayTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                                <span class="text-sm md:text-base font-semibold text-text-primary truncate">
                                    {{ $lastMatch->awayTeam->short_name ?? $lastMatch->awayTeam->name }}
                                </span>
                            </div>
                        </div>

                        {{-- Goal scorers --}}
                        @if($homeScorers->isNotEmpty() || $awayScorers->isNotEmpty())
                            <div class="grid grid-cols-2 gap-3 md:gap-6 mt-4 pt-3 border-t border-border-default">
                                @foreach([['scorers' => $homeScorers, 'align' => 'right'], ['scorers' => $awayScorers, 'align' => 'left']] as $side)
                                    <div class="text-[11px] md:text-xs {{ $side['align'] === 'right' ? 'text-right' : 'text-left' }}">
                                        @forelse($side['scorers'] as $scorer)
                                            <div class="truncate">
                                                <span class="font-medium text-text-body">{{ $scorer['name'] }}</span>
                                                <span class="text-text-muted tabular-nums">{{ $scorer['minutes'] }}</span>
                                            </div>
                                        @empty
                                            <div class="text-text-faint">&mdash;</div>
                                        @endforelse
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-border-default bg-surface-800/60 px-4 py-6 text-center text-xs text-text-muted">
                    {{ __('game.fast_mode_no_last_match') }}
                </div>
            @endif

            {{-- Compact standings — position / crest / name / GD / Pts.
                 Drops W/D/L columns and column headers versus the dashboard
                 sidebar so the block stays light; the "Full table" link
                 leads to the full view for deeper inspection. --}}
            @if($leagueStandings->isNotEmpty())
                <x-section-card :title="$standingsTitle">
                    <x-slot name="badge">
                        <a href="{{ route('game.competition', [$game->id, $game->competition_id]) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                            {{ __('game.full_table') }} &rarr;
                        </a>
                    </x-slot>

                    <div class="divide-y divide-border-default">
                        @php $prevPosition = 0; @endphp
                        @foreach($leagueStandings as $standing)
                            @if($standing->position > $prevPosition + 1)
                                <div class="px-4 py-0.5 text-center text-text-faint text-[10px]">&middot;&middot;&middot;</div>
                            @endif
                            @php $isPlayer = $standing->team_id === $game->team_id; @endphp
                            <div class="grid grid-cols-[20px_1fr_32px_32px] gap-2 items-center px-4 py-1.5 {{ $isPlayer ? 'bg-accent-blue/[0.06] border-l-2 border-l-accent-blue' : '' }}">
                                <span class="text-[11px] font-heading font-semibold {{ $isPlayer ? 'text-accent-blue' : 'text-text-muted' }} tabular-nums">{{ $standing->position }}</span>
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-team-crest :team="$standing->team" class="w-4 h-4 shrink-0" />
                                    <span class="text-xs truncate {{ $isPlayer ? 'text-text-primary font-semibold' : 'text-text-body' }}">{{ $standing->team->short_name ?? $standing->team->name }}</span>
                                </div>
                                <span class="text-[11px] text-right tabular-nums {{ $isPlayer ? 'text-text-primary' : 'text-text-muted' }}">{{ $standing->goal_difference >= 0 ? '+' : '' }}{{ $standing->goal_difference }}</span>
                                <span class="text-xs text-right font-semibold tabular-nums {{ $isPlayer ? 'text-accent-blue font-bold' : 'text-text-primary' }}">{{ $standing->points }}</span>
                            </div>
                            @php $prevPosition = $standing->position; @endphp
                        @endforeach
                    </div>
                </x-section-card>
            @endif

            {{-- Next match — compact row, low visual weight. Exists to confirm
                 what the Simulate button is about to play. --}}
            @if($nextMatch)
                <div class="rounded-lg border border-border-default bg-surface-800/40">
                    <div class="flex items-center justify-between gap-2 px-4 py-2 border-b border-border-default">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-[10px] text-text-faint uppercase tracking-widest">{{ __('game.next_match') }}</span>
                            <span class="text-[10px] text-text-muted">· {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</span>
                        </div>
                        <x-competition-pill :competition="$nextMatch->competition" :round-name="$nextMatch->round_name" :round-number="$nextMatch->round_number" :short="true" class="scale-90 origin-right" />
                    </div>
                    <div class="flex items-center justify-center gap-3 px-4 py-2.5">
                        <div class="flex-1 flex items-center justify-end gap-2 min-w-0">
                            <span class="text-xs md:text-sm font-medium text-text-body truncate text-right">{{ $nextMatch->homeTeam->short_name ?? $nextMatch->homeTeam->name }}</span>
                            <x-team-crest :team="$nextMatch->homeTeam" class="w-5 h-5 shrink-0" />
                        </div>
                        <span class="text-[10px] text-text-faint uppercase tracking-wider shrink-0">{{ __('game.vs') }}</span>
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                            <x-team-crest :team="$nextMatch->awayTeam" class="w-5 h-5 shrink-0" />
                            <span class="text-xs md:text-sm font-medium text-text-body truncate">{{ $nextMatch->awayTeam->short_name ?? $nextMatch->awayTeam->name }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-border-default bg-surface-800/40 px-4 py-3 text-center text-xs text-text-muted">
                    {{ __('game.season_complete') }}
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
