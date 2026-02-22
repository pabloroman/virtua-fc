@props([
    'match',
    'game',
    'showScore' => true,
    'highlightNext' => true,
    'nextMatchId' => null,
])

@php
    $isHome = $match->home_team_id === $game->team_id;
    $opponent = $isHome ? $match->awayTeam : $match->homeTeam;
    $isNextMatch = $highlightNext && !$match->played && $nextMatchId !== null && $nextMatchId === $match->id;

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

    // Competition color-coded left border
    $comp = $match->competition;
    $borderColor = match(true) {
        ($comp->scope ?? '') === 'continental' => 'border-l-blue-500',
        ($comp->role ?? '') === 'domestic_cup' => 'border-l-emerald-500',
        default => 'border-l-amber-500',
    };
@endphp

<div class="flex items-center px-3 py-1 gap-2 md:gap-6 rounded-lg border-l-4 {{ $borderColor }} @if($isNextMatch) bg-yellow-50 ring-2 ring-yellow-400 @elseif($match->played) bg-slate-50 @else bg-white border border-slate-200 @endif">
    {{-- Date & Competition --}}
    <div class="w-16">
        <div class="text-xs text-slate-700">{{ $match->scheduled_date->locale(app()->getLocale())->translatedFormat('d/m/Y') }}</div>
        <div class="text-xs text-slate-400 truncate" title="{{ $match->competition->name ?? __('transfers.league') }}">
            {{ $match->competition->name ?? __('transfers.league') }}
        </div>
    </div>

    {{-- Home/Away indicator --}}
    <div>
        <span class="text-xs font-semibold px-2 py-1 rounded @if($isHome) bg-green-100 text-green-700 @else bg-slate-100 text-slate-600 @endif">
            {{ $isHome ? mb_strtoupper(__('game.home')) : mb_strtoupper(__('game.away')) }}
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
            <span class="text-yellow-600 font-semibold text-sm">{{ mb_strtoupper(__('game.next')) }}</span>
        @else
            <span class="text-slate-400">-</span>
        @endif
    </div>
</div>
