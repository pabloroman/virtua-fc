@props(['topScorers', 'playerTeamId'])

<x-section-card :title="__('game.top_scorers')" class="self-start">
    @if($topScorers->isEmpty())
        <div class="px-4 py-6 text-center">
            <p class="text-sm text-text-muted">{{ __('game.no_goals_yet') }}</p>
        </div>
    @else
        <div class="divide-y divide-border-default">
            @foreach($topScorers as $index => $scorer)
                @php
                    $scorerTeam = $scorer->scorer_team ?? $scorer->team;
                    $isPlayerTeam = $scorerTeam?->id === $playerTeamId;
                @endphp
                <div class="flex items-center gap-2.5 px-4 py-2 text-sm {{ $isPlayerTeam ? 'bg-accent-blue/[0.06] border-l-2 border-l-accent-blue' : '' }}">
                    <span class="w-5 text-[11px] font-heading font-semibold text-text-muted shrink-0">{{ $index + 1 }}</span>
                    <x-team-crest :team="$scorerTeam" class="w-4 h-4 shrink-0" title="{{ $scorerTeam?->name }}" />
                    <span class="flex-1 truncate text-xs {{ $isPlayerTeam ? 'font-medium text-text-primary' : 'text-text-body' }}">{{ $scorer->player->name }}</span>
                    <span class="text-[11px] font-semibold text-text-primary shrink-0">{{ $scorer->goals }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-section-card>
