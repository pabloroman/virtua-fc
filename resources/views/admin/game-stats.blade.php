<x-admin-layout>
    <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-6">
        {{ __('admin.game_stats_title') }}
    </h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        {{-- Team Popularity --}}
        <div class="bg-surface-800 border border-border-default rounded-xl p-5">
            <h2 class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary mb-4">
                {{ __('admin.team_popularity') }}
            </h2>

            @if($teamPopularity->isEmpty())
                <p class="text-sm text-text-muted">{{ __('admin.no_data') }}</p>
            @else
                @php $maxPicks = $teamPopularity->max('picks'); @endphp
                <div class="space-y-2">
                    @foreach($teamPopularity as $index => $entry)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-5 text-right shrink-0">{{ $index + 1 }}</span>
                            @if($entry->team?->image)
                                <img src="{{ $entry->team->image }}" alt="" class="w-5 h-5 shrink-0">
                            @else
                                <div class="w-5 h-5 shrink-0 rounded bg-surface-700"></div>
                            @endif
                            <span class="text-sm text-text-primary truncate w-28 shrink-0">{{ $entry->team?->name ?? '—' }}</span>
                            <div class="flex-1 h-5 bg-surface-700 rounded-sm overflow-hidden">
                                <div class="h-full bg-accent-blue/60 rounded-sm" style="width: {{ ($entry->picks / $maxPicks) * 100 }}%"></div>
                            </div>
                            <span class="text-xs text-text-muted shrink-0 w-12 text-right">{{ $entry->picks }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Formation & Mentality --}}
        <div class="space-y-4">
            {{-- Formation Preferences --}}
            <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                <h2 class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary mb-4">
                    {{ __('admin.formation_preferences') }}
                </h2>

                @if($formations->isEmpty())
                    <p class="text-sm text-text-muted">{{ __('admin.no_data') }}</p>
                @else
                    @php
                        $maxFormation = $formations->max('usage_count');
                        $totalFormation = $formations->sum('usage_count');
                    @endphp
                    <div class="space-y-2">
                        @foreach($formations as $entry)
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-text-primary w-16 shrink-0 font-mono">{{ $entry->formation }}</span>
                                <div class="flex-1 h-5 bg-surface-700 rounded-sm overflow-hidden">
                                    <div class="h-full bg-accent-blue/60 rounded-sm" style="width: {{ ($entry->usage_count / $maxFormation) * 100 }}%"></div>
                                </div>
                                <span class="text-xs text-text-muted shrink-0 w-12 text-right">{{ round(($entry->usage_count / $totalFormation) * 100) }}%</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Mentality Distribution --}}
            <div class="bg-surface-800 border border-border-default rounded-xl p-5">
                <h2 class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary mb-4">
                    {{ __('admin.mentality_distribution') }}
                </h2>

                @if($mentalities->isEmpty())
                    <p class="text-sm text-text-muted">{{ __('admin.no_data') }}</p>
                @else
                    @php
                        $totalMentality = $mentalities->sum('usage_count');
                        $mentalityColors = [
                            'defensive' => 'bg-accent-blue/60',
                            'balanced'  => 'bg-accent-gold/60',
                            'attacking' => 'bg-accent-red/60',
                        ];
                        $mentalityLabels = [
                            'defensive' => __('game.mentality_defensive'),
                            'balanced'  => __('game.mentality_balanced'),
                            'attacking' => __('game.mentality_attacking'),
                        ];
                    @endphp
                    <div class="space-y-2">
                        @foreach($mentalities as $entry)
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-text-primary w-20 shrink-0">{{ $mentalityLabels[$entry->mentality] ?? ucfirst($entry->mentality) }}</span>
                                <div class="flex-1 h-5 bg-surface-700 rounded-sm overflow-hidden">
                                    <div class="h-full {{ $mentalityColors[$entry->mentality] ?? 'bg-accent-primary/60' }} rounded-sm" style="width: {{ ($entry->usage_count / $totalMentality) * 100 }}%"></div>
                                </div>
                                <span class="text-xs text-text-muted shrink-0 w-12 text-right">{{ round(($entry->usage_count / $totalMentality) * 100) }}%</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Season Progress --}}
    <div class="bg-surface-800 border border-border-default rounded-xl p-5">
        <h2 class="font-heading text-sm font-bold uppercase tracking-wider text-text-primary mb-4">
            {{ __('admin.season_progress') }}
        </h2>

        @if($seasonProgress->isEmpty())
            <p class="text-sm text-text-muted">{{ __('admin.no_data') }}</p>
        @else
            @php $maxSeason = $seasonProgress->max('count'); @endphp
            <div class="flex items-end gap-2 md:gap-3 h-40">
                @foreach($seasonProgress as $entry)
                    @php $heightPct = ($entry->count / $maxSeason) * 100; @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full gap-1">
                        <span class="text-xs text-text-muted">{{ $entry->count }}</span>
                        <div class="w-full bg-accent-blue/60 rounded-t-sm" style="height: {{ $heightPct }}%"></div>
                        <span class="text-xs text-text-muted truncate max-w-full">S{{ $entry->season }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-admin-layout>
