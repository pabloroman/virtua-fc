{{-- Used by partials/opponent-analysis-content.blade.php in both layout variants. --}}
<x-section-card :title="__('opponent.strengths_radar')">
    <div class="p-4">
        <div class="flex items-center justify-center gap-3 mb-2">
            <span class="flex items-center gap-1 text-[10px] text-text-muted">
                <span class="w-2 h-1 rounded-xs bg-sky-400 inline-block"></span>
                {{ $game->team->short_name ?? $game->team->name }}
            </span>
            <span class="flex items-center gap-1 text-[10px] text-text-muted">
                <span class="w-2 h-1 rounded-xs bg-red-400 inline-block"></span>
                {{ $opponent->short_name ?? $opponent->name }}
            </span>
        </div>
        <x-radar-chart
            :userValues="$userRadar"
            :opponentValues="$opponentRadar"
            :labels="[
                'goalkeeper' => __('squad.radar_gk'),
                'defense' => __('squad.radar_def'),
                'midfield' => __('squad.radar_mid'),
                'attack' => __('squad.radar_att'),
                'fitness' => __('squad.radar_fit'),
                'morale' => __('squad.radar_mor'),
                'overall' => __('squad.radar_overall'),
            ]"
        />
    </div>
</x-section-card>
