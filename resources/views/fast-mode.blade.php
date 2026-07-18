@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch|null $lastMatch */
    /** @var App\Models\GameMatch|null $nextMatch */
    /** @var App\Models\Competition $focalCompetition */
    /** @var string $displayMode */
    /** @var \Illuminate\Support\Collection|null $standings */
    /** @var App\Models\GameStanding|null $playerStanding */
    /** @var \Illuminate\Support\Collection|null $rounds */
    /** @var \Illuminate\Support\Collection|null $tiesByRound */
    /** @var int|null $currentRoundNumber */

    // Title: when the focal competition isn't the player's primary league,
    // show its name (e.g. "Champions League") so the panel reads in context
    // with the result above. For grouped standings, append the group label.
    $isPrimary = $focalCompetition->id === $game->competition_id;
    if ($displayMode === 'standings' && $standings && $standings->first()?->group_label) {
        $standingsTitle = $focalCompetition->shortName() . ' · ' . __('game.group') . ' ' . $standings->first()->group_label;
    } elseif ($isPrimary) {
        $standingsTitle = __('game.standings');
    } else {
        $standingsTitle = $focalCompetition->shortName();
    }

    // Condensed bracket: the round just played plus the next one.
    $bracketRounds = collect();
    if ($displayMode === 'bracket' && $rounds && $currentRoundNumber !== null) {
        $bracketRounds = $rounds
            ->filter(fn ($r) => $r->round === $currentRoundNumber || $r->round === $currentRoundNumber + 1)
            ->values();
    }
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
                @include('partials.match-summary', [
                    'match' => $lastMatch,
                    'showHeader' => true,
                    'mode' => 'full',
                ])
            @else
                <div class="rounded-lg border border-border-default bg-surface-800/60 px-4 py-6 text-center text-xs text-text-muted">
                    {{ __('game.fast_mode_no_last_match') }}
                </div>
            @endif

            {{-- Standings or condensed bracket for the just-played competition.
                 Standings: top 3 + 5-team window centered on the player (or
                 full group for grouped formats). Bracket: the round just
                 played and the next round. --}}
            @if($displayMode === 'standings' && $standings && $standings->isNotEmpty())
                <x-section-card :title="$standingsTitle">
                    <x-slot name="badge">
                        <a href="{{ route('game.competition', [$game->id, $focalCompetition->id]) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                            {{ __('game.full_table') }} &rarr;
                        </a>
                    </x-slot>

                    <div class="divide-y divide-border-default">
                        @php $prevPosition = 0; @endphp
                        @foreach($standings as $standing)
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
            @elseif($displayMode === 'bracket' && $bracketRounds->isNotEmpty())
                <x-section-card :title="$standingsTitle">
                    <x-slot name="badge">
                        <a href="{{ route('game.competition', [$game->id, $focalCompetition->id]) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                            {{ __('cup.bracket') }} &rarr;
                        </a>
                    </x-slot>

                    <div class="overflow-x-auto p-3 md:p-4">
                        <div class="flex gap-3 md:gap-4" style="min-width: fit-content;">
                            @foreach($bracketRounds as $round)
                                @php $ties = $tiesByRound->get($round->round, collect()); @endphp
                                <div class="shrink-0 w-60 md:w-64">
                                    <div class="text-center mb-3">
                                        <h4 class="font-heading text-xs font-semibold uppercase tracking-wide text-text-body">{{ __($round->name) }}</h4>
                                        <div class="text-[10px] text-text-muted mt-0.5">
                                            {{ $round->firstLegDate->format('d M') }}
                                            @if($round->twoLegged)
                                                / {{ $round->secondLegDate->format('d M') }}
                                            @endif
                                        </div>
                                    </div>

                                    @if($ties->isEmpty())
                                        <div class="p-3 text-center border border-dashed border-border-strong rounded-lg">
                                            <div class="text-text-muted text-xs">{{ __('cup.draw_pending') }}</div>
                                        </div>
                                    @else
                                        <div class="space-y-2">
                                            @foreach($ties as $tie)
                                                <x-cup-tie-card :tie="$tie" :player-team-id="$game->team_id" />
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
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
                @else
                    {{-- Season complete: fast mode is done. Continue exits fast
                         mode and lands on the dashboard preview, which has its
                         own "View Season Summary" CTA. --}}
                    <form action="{{ route('game.fast-mode.exit', $game->id) }}" method="POST" class="flex-1">
                        @csrf
                        <x-primary-button color="amber" class="w-full">
                            {{ __('app.continue') }}
                        </x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
