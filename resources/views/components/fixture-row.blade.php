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
    $compDot = \App\Support\CompetitionColors::dot($match->competition);

    // Calculate result styling
    $resultClass = '';
    $resultText = '-';
    $resultDot = '';
    if ($showScore && $match->played) {
        $yourScore = $isHome ? $match->home_score : $match->away_score;
        $oppScore = $isHome ? $match->away_score : $match->home_score;
        $result = $yourScore > $oppScore ? 'W' : ($yourScore < $oppScore ? 'L' : 'D');
        $resultClass = $result === 'W' ? 'text-accent-green' : ($result === 'L' ? 'text-accent-red' : 'text-text-secondary');
        $resultDot = $result === 'W' ? 'bg-accent-green' : ($result === 'L' ? 'bg-accent-red' : 'bg-surface-600');
        $resultText = $yourScore . ' - ' . $oppScore;
    }
@endphp

<div class="flex items-center gap-3 px-4 py-2.5 {{ $isNextMatch ? 'bg-accent-blue/[0.06] border-l-2 border-l-accent-blue' : '' }} hover:bg-surface-700/30 transition-colors">
    {{-- Date + competition dot --}}
    <div class="w-10 shrink-0 text-center">
        <div class="text-[11px] font-medium text-text-body leading-tight">{{ $match->scheduled_date->locale(app()->getLocale())->translatedFormat('d') }}</div>
        <div class="text-[9px] text-text-faint uppercase">{{ $match->scheduled_date->locale(app()->getLocale())->translatedFormat('M') }}</div>
        @unless($game->isTournamentMode())
            <div class="w-3 h-0.5 rounded-full {{ $compDot }} mx-auto mt-1"></div>
        @endunless
    </div>

    {{-- Home/Away indicator (hidden for neutral-venue competitions like World Cup) --}}
    @if($match->competition_id !== 'WC2026')
        <span class="inline-flex px-2 py-0.5 text-[9px] font-semibold rounded-full shrink-0 uppercase tracking-wider {{ $isHome ? 'bg-accent-green/10 text-accent-green' : 'bg-surface-600 text-text-secondary' }}">
            {{ $isHome ? __('game.home_abbr') : __('game.away_abbr') }}
        </span>
    @endif

    {{-- Opponent --}}
    <div class="flex-1 flex items-center gap-2 min-w-0">
        <x-team-crest :team="$opponent" class="w-5 h-5 shrink-0" />
        <span class="text-xs truncate {{ $isNextMatch ? 'text-text-primary font-medium' : 'text-text-body' }}">{{ $opponent->name }}</span>
    </div>

    {{-- Competition pill: short name on mobile, full on desktop (hidden in tournament mode) --}}
    @unless($game->isTournamentMode())
        <div class="shrink-0">
            <span class="md:hidden"><x-competition-pill :competition="$match->competition" :short="true" class="scale-90 origin-right" /></span>
            <span class="hidden md:inline"><x-competition-pill :competition="$match->competition" class="scale-90 origin-right" /></span>
        </div>
    @endunless

    {{-- Result / Status --}}
    <div class="shrink-0 text-right">
        @if($showScore && $match->played)
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-1.5 rounded-full {{ $resultDot }} shrink-0"></div>
                <span class="text-[11px] font-semibold {{ $resultClass }}">{{ $resultText }}</span>
            </div>
        @elseif($isNextMatch)
            <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[9px] font-semibold text-accent-blue uppercase tracking-wider">{{ __('game.next') }}</span>
        @endif
    </div>
</div>
