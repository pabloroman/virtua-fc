@props(['topScorers', 'playerTeamId'])

<div class="grid-cols-1 space-y-6">
    <h4 class="font-heading font-semibold text-xl text-white uppercase tracking-wide">{{ __('game.top_scorers') }}</h4>

    @if($topScorers->isEmpty())
        <p class="text-sm text-slate-500">{{ __('game.no_goals_yet') }}</p>
    @else
        <div class="space-y-2">
            @foreach($topScorers as $index => $scorer)
                @php
                    $scorerTeam = $scorer->scorer_team ?? $scorer->team;
                    $isPlayerTeam = $scorerTeam?->id === $playerTeamId;
                @endphp
                <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-accent-blue/10 -mx-2 px-2 py-1 rounded-sm @endif">
                    <span class="w-5 text-slate-500 text-xs">{{ $index + 1 }}</span>
                    <x-team-crest :team="$scorerTeam" class="w-4 h-4" title="{{ $scorerTeam?->name }}" />
                    <span class="flex-1 truncate text-slate-300 @if($isPlayerTeam) font-medium text-white @endif">{{ $scorer->player->name }}</span>
                    <span class="font-semibold text-white">{{ $scorer->goals }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
