{{-- Used by partials/opponent-analysis-content.blade.php — shown directly
     underneath the pitch in both the modal and the standalone page. Compact
     single-line rows (avatar + name + positions + rating). --}}
@if($topThreats->isNotEmpty())
<x-section-card :title="__('opponent.key_players')">
    <ul class="divide-y divide-border-default">
        @foreach($topThreats as $player)
            <li class="px-4 py-1.5 flex items-center gap-2.5">
                <x-position-badge :position="$player->position" size="sm" class="shrink-0" />
                <span class="text-sm font-medium text-text-primary truncate min-w-0 flex-1">{{ $player->name }}</span>
                <x-rating-badge :value="$player->effective_rating" size="sm" class="shrink-0" />
            </li>
        @endforeach
    </ul>
</x-section-card>
@endif
