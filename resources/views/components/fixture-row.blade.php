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
        $resultClass = $result === 'W' ? 'text-accent-green' : ($result === 'L' ? 'text-accent-red' : 'text-slate-400');
        $resultText = $yourScore . ' - ' . $oppScore;
    }

    // Competition color-coded left border
    $comp = $match->competition;
    $borderColor = match(true) {
        ($comp->handler_type ?? '') === 'preseason' => 'border-l-accent-blue',
        ($comp->scope ?? '') === 'continental' => 'border-l-blue-400',
        ($comp->role ?? '') === 'domestic_cup' => 'border-l-accent-green',
        default => 'border-l-accent-gold',
    };
@endphp

<div class="flex items-center px-3 py-1 gap-2 md:gap-6 rounded-lg border-l-4 {{ $borderColor }} @if($isNextMatch) bg-accent-gold/10 ring-1 ring-accent-gold/30 @elseif($match->played) bg-surface-800 @else bg-surface-700/50 border border-white/5 @endif">
    {{-- Date & Competition --}}
    <div class="w-16">
        <div class="text-xs text-slate-300">{{ $match->scheduled_date->locale(app()->getLocale())->translatedFormat('d/m/Y') }}</div>
        <div class="text-xs text-slate-500 truncate" title="{{ __($match->competition->name ?? __('transfers.league')) }}">
            {{ __($match->competition->name ?? __('transfers.league')) }}
        </div>
    </div>

    {{-- Home/Away indicator --}}
    <div>
        <span class="text-xs font-semibold px-2 py-1 rounded @if($isHome) bg-accent-green/10 text-accent-green @else bg-surface-600 text-slate-400 @endif">
            {{ $isHome ? mb_strtoupper(__('game.home')) : mb_strtoupper(__('game.away')) }}
        </span>
    </div>

    {{-- Opponent --}}
    <div class="flex-1 flex items-center gap-2">
        <x-team-crest :team="$opponent" class="w-6 h-6" />
        <span class="font-medium text-white">{{ $opponent->name }}</span>
    </div>

    {{-- Result/Status --}}
    <div class="w-20 text-center">
        @if($showScore && $match->played)
            <span class="{{ $resultClass }} font-semibold">{{ $resultText }}</span>
        @elseif($isNextMatch)
            <span class="text-accent-gold font-semibold text-sm">{{ mb_strtoupper(__('game.next')) }}</span>
        @else
            <span class="text-slate-600">-</span>
        @endif
    </div>
</div>
