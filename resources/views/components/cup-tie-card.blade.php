@props(['tie', 'playerTeamId'])

@php
    $isPlayerTie = $tie->involvesTeam($playerTeamId);
    $homeWon = $tie->winner_id === $tie->home_team_id;
    $awayWon = $tie->winner_id === $tie->away_team_id;
    $isTwoLegged = $tie->isTwoLegged();
    $firstLegPlayed = $tie->firstLegMatch?->played;
    $secondLegPlayed = $tie->secondLegMatch?->played;
@endphp

<div class="border rounded-lg overflow-hidden {{ $isPlayerTie ? 'border-sky-300 bg-sky-50' : 'border-slate-200' }}">
    {{-- Home Team --}}
    <div class="flex items-center gap-2 p-2 {{ $homeWon ? 'bg-green-50' : '' }} {{ $awayWon ? 'opacity-50' : '' }}">
        <x-team-crest :team="$tie->homeTeam" class="w-5 h-5" />
        <span class="flex-1 text-sm truncate @if($homeWon) font-semibold @endif {{ $tie->home_team_id === $playerTeamId ? 'font-semibold text-sky-700' : '' }}">
            {{ $tie->homeTeam->name }}
        </span>
        @if($firstLegPlayed)
            <span class="text-sm tabular-nums {{ $homeWon ? 'font-semibold' : '' }}">{{ $tie->firstLegMatch->home_score }}</span>
        @endif
        @if($isTwoLegged && $secondLegPlayed)
            <span class="text-sm tabular-nums {{ $homeWon ? 'font-semibold' : '' }}">{{ $tie->secondLegMatch->away_score }}</span>
        @endif
    </div>

    {{-- Away Team --}}
    <div class="flex items-center gap-2 p-2 border-t {{ $awayWon ? 'bg-green-50' : '' }} {{ $homeWon ? 'opacity-50' : '' }}">
        <x-team-crest :team="$tie->awayTeam" class="w-5 h-5" />
        <span class="flex-1 text-sm truncate @if($awayWon) font-semibold @endif {{ $tie->away_team_id === $playerTeamId ? 'font-semibold text-sky-700' : '' }}">
            {{ $tie->awayTeam->name }}
        </span>
        @if($firstLegPlayed)
            <span class="text-sm tabular-nums {{ $awayWon ? 'font-semibold' : '' }}">{{ $tie->firstLegMatch->away_score }}</span>
        @endif
        @if($isTwoLegged && $secondLegPlayed)
            <span class="text-sm tabular-nums {{ $awayWon ? 'font-semibold' : '' }}">{{ $tie->secondLegMatch->home_score }}</span>
        @endif
    </div>

    {{-- Resolution info --}}
    @if($tie->completed && $tie->resolution && ($tie->resolution['type'] ?? 'normal') !== 'normal')
        <div class="text-xs text-center text-slate-400 py-1 border-t bg-slate-50">
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
