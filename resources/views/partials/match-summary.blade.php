@php
    /** @var \App\Models\GameMatch $match */
    use App\Modules\Match\Services\MatchSummaryPresenter;

    // Optional props (set by the caller via @include data array).
    $showHeader = $showHeader ?? false;
    $mode = $mode ?? MatchSummaryPresenter::MODE_COMPACT;

    $summary = app(MatchSummaryPresenter::class)->present($match, $mode);
    $isFull = $mode === MatchSummaryPresenter::MODE_FULL;
@endphp

<div class="rounded-xl border border-border-default bg-surface-800 overflow-hidden">
    {{-- Optional header: competition pill + venue · date --}}
    @if($showHeader)
        <div class="px-4 py-3 md:py-4 border-b border-border-default">
            <x-match-card-header :match="$match" />
        </div>
    @endif

    {{-- Face-off: crests, names, big score --}}
    <div class="px-4 py-4 md:py-5">
        <div class="flex items-center justify-center gap-3 md:gap-5">
            <div class="flex-1 flex items-center justify-end gap-2 md:gap-3 min-w-0">
                <span class="text-sm md:text-base font-semibold text-text-primary truncate text-right">
                    {{ $match->homeTeam->short_name ?? $match->homeTeam->name }}
                </span>
                <x-team-crest :team="$match->homeTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
            </div>
            <div class="shrink-0 flex flex-col items-center gap-1">
                <div class="px-3 py-1.5 md:px-4 md:py-2 rounded-lg bg-surface-700 text-xl md:text-3xl font-heading font-bold text-text-primary tabular-nums">
                    {{ $summary->homeTotal }} - {{ $summary->awayTotal }}
                </div>
                @if($match->is_extra_time)
                    <div class="text-[9px] md:text-[10px] text-text-muted uppercase tracking-widest tabular-nums">
                        @if($summary->hasPenalties)
                            {{ __('season.aet_abbr') }} &middot; {{ __('season.pens_abbr') }} {{ $match->home_score_penalties }}-{{ $match->away_score_penalties }}
                        @else
                            {{ __('season.aet_abbr') }}
                        @endif
                    </div>
                @endif
            </div>
            <div class="flex-1 flex items-center gap-2 md:gap-3 min-w-0">
                <x-team-crest :team="$match->awayTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                <span class="text-sm md:text-base font-semibold text-text-primary truncate">
                    {{ $match->awayTeam->short_name ?? $match->awayTeam->name }}
                </span>
            </div>
        </div>

        {{-- Goal scorers --}}
        @if($summary->hasScorers())
            <div class="grid grid-cols-2 gap-3 md:gap-6 mt-4 pt-3 border-t border-border-default">
                @foreach([['scorers' => $summary->homeScorers, 'align' => 'right'], ['scorers' => $summary->awayScorers, 'align' => 'left']] as $side)
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

        {{-- MVP --}}
        @if($summary->mvp)
            <div class="mt-3 pt-2 border-t border-border-default flex items-center justify-center gap-1.5 text-[11px] md:text-xs">
                <span class="text-accent-gold">★</span>
                <span class="text-text-muted uppercase tracking-wider">{{ __('game.mvp') }}:</span>
                <span class="font-semibold text-text-primary truncate">{{ $summary->mvp['name'] }}</span>
            </div>
        @endif
    </div>

    {{-- Lineups / ratings — reuses the live-match lineups markup via
         partials/live-match/lineups-roster.blade.php, mounted under the
         read-only `matchSummaryLineups` Alpine factory so the same partial
         renders identically post-match. --}}
    @if($isFull && $summary->lineups && $summary->lineups->hasAny())
        @php
            $homeFormation = $summary->lineups->homeFormation ?? '';
            $awayFormation = $summary->lineups->awayFormation ?? '';
        @endphp
        <div class="border-t border-border-default px-3 sm:px-4 py-3 sm:py-4">
            <div x-data="matchSummaryLineups({
                homeRoster: {{ Js::from($summary->lineups->homeRoster) }},
                awayRoster: {{ Js::from($summary->lineups->awayRoster) }},
                subInPlayers: {{ Js::from($summary->lineups->subInPlayers) }},
                events: {{ Js::from($summary->lineups->events) }},
                extraTimeEvents: {{ Js::from($summary->lineups->extraTimeEvents) }},
                homeTeamId: {{ Js::from($summary->lineups->homeTeamId) }},
                awayTeamId: {{ Js::from($summary->lineups->awayTeamId) }},
                homeScore: {{ $summary->lineups->homeScore }},
                awayScore: {{ $summary->lineups->awayScore }},
            })">
                @include('partials.live-match.lineups-roster')
            </div>
        </div>
    @endif
</div>
