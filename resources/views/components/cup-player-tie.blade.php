@props(['tie', 'playerTeamId', 'competitionName' => null, 'cupStatus' => null, 'roundName' => null])

@php
    $isHome = $tie->home_team_id === $playerTeamId;
    $opponent = $isHome ? $tie->awayTeam : $tie->homeTeam;
    $isTwoLegged = $tie->isTwoLegged();
    $resolutionType = $tie->resolution['type'] ?? 'normal';

    if ($tie->completed) {
        $won = $tie->winner_id === $playerTeamId;
        $accentColor = $won ? 'green' : 'red';
    } else {
        $won = null;
        $accentColor = 'blue';
    }
@endphp

<div class="mb-8 rounded-xl overflow-hidden border
    {{ $accentColor === 'blue' ? 'border-accent-blue/20' : ($accentColor === 'green' ? 'border-accent-green/20' : 'border-accent-red/20') }}">

    {{-- Header bar --}}
    <div class="px-4 py-2 flex items-center justify-between
        {{ $accentColor === 'blue' ? 'bg-accent-blue/10' : ($accentColor === 'green' ? 'bg-accent-green/10' : 'bg-accent-red/10') }}">
        <span class="text-[10px] font-heading font-semibold uppercase tracking-widest
            {{ $accentColor === 'blue' ? 'text-accent-blue' : ($accentColor === 'green' ? 'text-accent-green' : 'text-accent-red') }}">
            @if($tie->completed)
                @if($cupStatus === 'champion' && $competitionName)
                    {{ __('cup.champion_message', ['competition' => __($competitionName)]) }}
                @elseif($won)
                    {{ __('cup.advanced_to_next_round') }}
                @else
                    {{ __('cup.eliminated') }}
                @endif
            @elseif($roundName)
                {{ __('cup.your_current_cup_tie', ['round' => __($roundName)]) }}
            @else
                {{ __('cup.upcoming_tie') }}
            @endif
        </span>
        @if($isTwoLegged && !$tie->completed)
            <span class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('cup.two_legged_tie') }}</span>
        @endif
    </div>

    {{-- Match display --}}
    <div class="bg-surface-800 px-4 py-4 md:px-6 md:py-5">
        <div class="flex items-center justify-center gap-3 md:gap-6">
            {{-- Home team --}}
            <div class="flex items-center gap-2.5 md:gap-3 flex-1 justify-end min-w-0">
                <span class="font-heading text-sm md:text-xl font-semibold truncate
                    {{ $tie->completed && $tie->winner_id !== $tie->home_team_id ? 'text-text-muted' : 'text-text-primary' }}">
                    {{ $tie->homeTeam->name }}
                </span>
                <x-team-crest :team="$tie->homeTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
            </div>

            {{-- Score / VS --}}
            <div class="px-2 md:px-5 text-center shrink-0">
                @if($tie->firstLegMatch?->played)
                    <div class="font-heading text-2xl md:text-3xl font-bold tabular-nums text-text-primary">
                        {{ $tie->getScoreDisplay() }}
                    </div>
                    @if($tie->completed && $resolutionType !== 'normal')
                        <div class="text-[10px] text-text-muted mt-0.5 uppercase tracking-wider">
                            @if($resolutionType === 'penalties')
                                {{ __('cup.pens') }} {{ $tie->resolution['penalties'] }}
                            @elseif($resolutionType === 'extra_time')
                                {{ __('cup.aet') }}
                            @elseif($resolutionType === 'aggregate')
                                {{ __('cup.agg') }} {{ $tie->resolution['aggregate'] }}
                            @endif
                        </div>
                    @endif
                @else
                    <div class="font-heading text-lg md:text-xl font-semibold text-text-muted tracking-wider">{{ __('game.vs') }}</div>
                @endif
            </div>

            {{-- Away team --}}
            <div class="flex items-center gap-2.5 md:gap-3 flex-1 min-w-0">
                <x-team-crest :team="$tie->awayTeam" class="w-10 h-10 md:w-14 md:h-14 shrink-0" />
                <span class="font-heading text-sm md:text-xl font-semibold truncate
                    {{ $tie->completed && $tie->winner_id !== $tie->away_team_id ? 'text-text-muted' : 'text-text-primary' }}">
                    {{ $tie->awayTeam->name }}
                </span>
            </div>
        </div>
    </div>
</div>
