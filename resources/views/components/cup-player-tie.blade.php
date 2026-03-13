@props(['tie', 'playerTeamId', 'competitionName' => null, 'cupStatus' => null, 'roundName' => null])

@php
    $isHome = $tie->home_team_id === $playerTeamId;
    $opponent = $isHome ? $tie->awayTeam : $tie->homeTeam;
    $isTwoLegged = $tie->isTwoLegged();
@endphp

@if(!$tie->completed)
    {{-- Active Tie --}}
    <div class="mb-8 p-4 md:p-6 rounded-xl bg-[var(--accent-tint)] border border-accent-blue/20">
        @if($roundName)
            <div class="text-center text-sm text-accent-blue mb-3">{{ __('cup.your_current_cup_tie', ['round' => __($roundName)]) }}</div>
        @endif
        <div class="flex items-center justify-center gap-3 md:gap-6">
            <div class="flex items-center gap-2 md:gap-3 flex-1 justify-end min-w-0">
                <span class="text-base md:text-xl font-semibold truncate @if($tie->home_team_id === $playerTeamId) text-accent-blue @endif">
                    {{ $tie->homeTeam->name }}
                </span>
                <x-team-crest :team="$tie->homeTeam" class="w-10 h-10 md:w-12 md:h-12 shrink-0" />
            </div>
            <div class="px-2 md:px-6 text-center shrink-0">
                @if($tie->firstLegMatch?->played)
                    <div class="text-xl md:text-2xl font-semibold tabular-nums">{{ $tie->getScoreDisplay() }}</div>
                @else
                    <div class="text-text-secondary text-sm">{{ __('game.vs') }}</div>
                @endif
            </div>
            <div class="flex items-center gap-2 md:gap-3 flex-1 min-w-0">
                <x-team-crest :team="$tie->awayTeam" class="w-10 h-10 md:w-12 md:h-12 shrink-0" />
                <span class="text-base md:text-xl font-semibold truncate @if($tie->away_team_id === $playerTeamId) text-accent-blue @endif">
                    {{ $tie->awayTeam->name }}
                </span>
            </div>
        </div>
        @if($isTwoLegged)
            <div class="text-center text-xs text-text-muted mt-2">{{ __('cup.two_legged_tie') }}</div>
        @endif
    </div>
@else
    {{-- Completed Tie --}}
    @php $won = $tie->winner_id === $playerTeamId; @endphp
    <div class="mb-8 p-4 md:p-5 rounded-xl {{ $won ? 'bg-accent-green/10 border-accent-green/20' : 'bg-accent-red/10 border-accent-red/20' }} border">
        <div class="text-center text-sm {{ $won ? 'text-accent-green' : 'text-accent-red' }} mb-3">
            @if($cupStatus === 'champion' && $competitionName)
                {{ __('cup.champion_message', ['competition' => __($competitionName)]) }}
            @elseif($won)
                {{ __('cup.advanced_to_next_round') }}
            @else
                {{ __('cup.eliminated') }}
            @endif
        </div>
        <div class="flex items-center justify-center gap-3 md:gap-6">
            <div class="flex items-center gap-2 md:gap-3 flex-1 justify-end min-w-0">
                <span class="text-sm md:text-lg font-semibold truncate @if($tie->home_team_id === $playerTeamId) {{ $won ? 'text-accent-green' : 'text-accent-red' }} @endif">
                    {{ $tie->homeTeam->name }}
                </span>
                <x-team-crest :team="$tie->homeTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
            </div>
            <div class="px-2 md:px-4 text-base md:text-lg font-semibold tabular-nums shrink-0">
                {{ $tie->getScoreDisplay() }}
            </div>
            <div class="flex items-center gap-2 md:gap-3 flex-1 min-w-0">
                <x-team-crest :team="$tie->awayTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                <span class="text-sm md:text-lg font-semibold truncate @if($tie->away_team_id === $playerTeamId) {{ $won ? 'text-accent-green' : 'text-accent-red' }} @endif">
                    {{ $tie->awayTeam->name }}
                </span>
            </div>
        </div>
    </div>
@endif
