@php
    /** @var \App\Models\GameMatch $match */
    use App\Modules\Match\Services\MatchSummaryPresenter;

    // Optional props (set by the caller via @include data array).
    $showHeader = $showHeader ?? false;
    $viewerTeamId = $viewerTeamId ?? null;

    $summary = app(MatchSummaryPresenter::class)->present(
        $match,
        $showHeader ? $viewerTeamId : null,
    );
@endphp

<div class="rounded-xl border border-border-default bg-surface-800 overflow-hidden">
    {{-- Optional header bar: "LAST RESULT · WIN · Competition" --}}
    @if($showHeader)
        <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-border-default bg-surface-800/60">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-[10px] text-text-faint uppercase tracking-widest">{{ __('game.last_result') }}</span>
                @if($summary->resultLabel)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border {{ $summary->resultBg }} {{ $summary->resultColor }}">
                        {{ $summary->resultLabel }}
                    </span>
                @endif
            </div>
            <x-competition-pill :competition="$match->competition" :round-name="$match->round_name" :round-number="$match->round_number" :short="true" class="scale-90 origin-right" />
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
    </div>
</div>
