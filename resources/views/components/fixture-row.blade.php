@props([
    'match',
    'game',
    'showScore' => true,
    'highlightNext' => true,
])

@php
    $isHome = $match->home_team_id === $game->team_id;
    $opponent = $isHome ? $match->awayTeam : $match->homeTeam;
    $isNextMatch = $highlightNext && !$match->played && $game->next_match?->id === $match->id;

    // Calculate result styling
    $resultClass = '';
    $resultText = '-';
    if ($showScore && $match->played) {
        $yourScore = $isHome ? $match->home_score : $match->away_score;
        $oppScore = $isHome ? $match->away_score : $match->home_score;
        $result = $yourScore > $oppScore ? 'W' : ($yourScore < $oppScore ? 'L' : 'D');
        $resultClass = $result === 'W' ? 'text-green-600' : ($result === 'L' ? 'text-red-600' : 'text-slate-600');
        $resultText = $yourScore . ' - ' . $oppScore;
    }
@endphp

<div class="flex items-center px-3 py-1 gap-6 rounded-lg @if($isNextMatch) bg-yellow-50 ring-2 ring-yellow-400 @elseif($match->played) bg-slate-50 @else bg-white border border-slate-200 @endif">
    {{-- Date & Competition --}}
    <div class="w-16">
        <div class="text-xs text-slate-700">{{ $match->scheduled_date->format('D d M') }}</div>
        <div class="text-xs text-slate-400 truncate" title="{{ $match->competition->name ?? 'League' }}">
            {{ $match->competition->name ?? 'League' }}
        </div>
    </div>

    {{-- Home/Away indicator --}}
    <div>
        <span class="text-xs font-semibold px-2 py-1 rounded @if($isHome) bg-green-100 text-green-700 @else bg-slate-100 text-slate-600 @endif">
            {{ $isHome ? 'HOME' : 'AWAY' }}
        </span>
    </div>

    {{-- Opponent --}}
    <div class="flex-1 flex items-center gap-2">
        <img src="{{ $opponent->image }}" class="w-6 h-6">
        <span class="font-medium text-slate-900">{{ $opponent->name }}</span>
    </div>

    {{-- Result/Status --}}
    <div class="w-20 text-center">
        @if($showScore && $match->played)
            <span class="{{ $resultClass }} font-semibold">{{ $resultText }}</span>
        @elseif($isNextMatch)
            <span class="text-yellow-600 font-semibold text-sm">NEXT</span>
        @else
            <span class="text-slate-400">-</span>
        @endif
    </div>
</div>
