@props(['topScorers', 'playerTeamId'])

<div class="grid-cols-1 space-y-6">
    <h4 class="font-semibold text-xl text-slate-900">{{ __('game.top_scorers') }}</h4>

    @if($topScorers->isEmpty())
        <p class="text-sm text-slate-500">{{ __('game.no_goals_yet') }}</p>
    @else
        <div class="space-y-2">
            @foreach($topScorers as $index => $scorer)
                @php $isPlayerTeam = $scorer->team_id === $playerTeamId; @endphp
                <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-sky-50 -mx-2 px-2 py-1 rounded @endif">
                    <span class="w-5 text-slate-400 text-xs">{{ $index + 1 }}</span>
                    <img src="{{ $scorer->team->image }}" class="w-4 h-4" title="{{ $scorer->team->name }}">
                    <span class="flex-1 truncate @if($isPlayerTeam) font-medium @endif">{{ $scorer->player->name }}</span>
                    <span class="font-semibold">{{ $scorer->goals }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
