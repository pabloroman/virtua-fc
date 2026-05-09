{{-- Used by partials/opponent-analysis-content.blade.php — opponent players
     who will miss the match through injury or suspension. Same row style as
     key-players for visual consistency. --}}
@if($absentees->isNotEmpty())
<x-section-card :title="__('opponent.absentees')" :badge="(string) $absentees->count()">
    <ul class="divide-y divide-border-default">
        @foreach($absentees as $entry)
            @php $player = $entry['player']; @endphp
            <li class="px-4 py-1.5 flex items-center gap-2.5">
                <x-position-badge :position="$player->position" size="sm" class="shrink-0" />
                <span class="text-sm font-medium text-text-primary truncate min-w-0 flex-1">{{ $player->name }}</span>
                <x-player-unavailable-icon :player="$player" :match-date="$match->scheduled_date" :competition-id="$match->competition_id" />
                <span class="text-[11px] text-text-muted truncate max-w-[55%] hidden sm:inline">{{ $entry['reason'] }}</span>
            </li>
        @endforeach
    </ul>
</x-section-card>
@endif
