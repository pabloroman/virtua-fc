@props(['tie', 'playerTeamId'])

@php
    $isPlayerTie = $tie->involvesTeam($playerTeamId);
    $homeWon = $tie->winner_id === $tie->home_team_id;
    $awayWon = $tie->winner_id === $tie->away_team_id;
    $isTwoLegged = $tie->isTwoLegged();
    $firstLegPlayed = $tie->firstLegMatch?->played;
    $secondLegPlayed = $tie->secondLegMatch?->played;

    // For extra time / penalties, show the final score instead of the 90-min score
    $resolutionType = $tie->resolution['type'] ?? 'normal';
    $scoreAfterEt = isset($tie->resolution['score_after_et']) ? explode('-', $tie->resolution['score_after_et']) : null;
    $firstLegHomeScore = $scoreAfterEt ? (int) $scoreAfterEt[0] : $tie->firstLegMatch?->home_score;
    $firstLegAwayScore = $scoreAfterEt ? (int) $scoreAfterEt[1] : $tie->firstLegMatch?->away_score;
@endphp

<div class="rounded-lg overflow-hidden {{ $isPlayerTie ? 'border border-accent-blue/30 bg-accent-blue/5' : 'border border-border-strong' }}">
    {{-- Home Team --}}
    <div class="flex items-center gap-2 px-2.5 py-2 {{ $homeWon ? 'bg-accent-green/10' : '' }} {{ $awayWon ? 'opacity-50' : '' }}">
        <x-team-crest :team="$tie->homeTeam" class="w-5 h-5 shrink-0" />
        <span class="flex-1 text-xs truncate @if($homeWon) font-semibold @endif {{ $tie->home_team_id === $playerTeamId ? 'font-semibold text-accent-blue' : 'text-text-body' }}">
            {{ $tie->homeTeam->name }}
        </span>
        @if($firstLegPlayed)
            <span class="text-xs tabular-nums font-heading {{ $homeWon ? 'font-semibold text-text-primary' : 'text-text-body' }}">{{ $firstLegHomeScore }}</span>
        @endif
        @if($isTwoLegged && $secondLegPlayed)
            <span class="text-xs tabular-nums font-heading {{ $homeWon ? 'font-semibold text-text-primary' : 'text-text-body' }}">{{ $tie->secondLegMatch->away_score }}</span>
        @endif
    </div>

    {{-- Away Team --}}
    <div class="flex items-center gap-2 px-2.5 py-2 border-t border-border-default {{ $awayWon ? 'bg-accent-green/10' : '' }} {{ $homeWon ? 'opacity-50' : '' }}">
        <x-team-crest :team="$tie->awayTeam" class="w-5 h-5 shrink-0" />
        <span class="flex-1 text-xs truncate @if($awayWon) font-semibold @endif {{ $tie->away_team_id === $playerTeamId ? 'font-semibold text-accent-blue' : 'text-text-body' }}">
            {{ $tie->awayTeam->name }}
        </span>
        @if($firstLegPlayed)
            <span class="text-xs tabular-nums font-heading {{ $awayWon ? 'font-semibold text-text-primary' : 'text-text-body' }}">{{ $firstLegAwayScore }}</span>
        @endif
        @if($isTwoLegged && $secondLegPlayed)
            <span class="text-xs tabular-nums font-heading {{ $awayWon ? 'font-semibold text-text-primary' : 'text-text-body' }}">{{ $tie->secondLegMatch->home_score }}</span>
        @endif
    </div>

    {{-- Resolution info --}}
    @if($tie->completed && $tie->resolution && ($tie->resolution['type'] ?? 'normal') !== 'normal')
        <div class="text-[10px] text-center text-text-muted py-1 border-t border-border-default bg-surface-700/50">
            @if($tie->resolution['type'] === 'penalties')
                {{ __('cup.pens') }} {{ $tie->resolution['penalties'] }}
            @elseif($tie->resolution['type'] === 'extra_time')
                {{ __('cup.aet') }}
            @elseif($tie->resolution['type'] === 'aggregate')
                {{ __('cup.agg') }} {{ $tie->resolution['aggregate'] }}
            @endif
        </div>
    @endif
</div>
