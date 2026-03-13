@props(['rounds', 'tiesByRound', 'playerTeamId'])

<x-section-card :title="__('cup.bracket')">
    <div class="overflow-x-auto p-4 md:p-5">
        <div class="flex gap-4" style="min-width: fit-content;">
            @foreach($rounds as $round)
                @php $ties = $tiesByRound->get($round->round, collect()); @endphp
                <div class="shrink-0 w-64">
                    <div class="text-center mb-4">
                        <h4 class="font-heading text-sm font-semibold uppercase tracking-wide text-text-body">{{ __($round->name) }}</h4>
                        <div class="text-[10px] text-text-muted mt-0.5">
                            {{ $round->firstLegDate->format('d M') }}
                            @if($round->twoLegged)
                                / {{ $round->secondLegDate->format('d M') }}
                            @endif
                        </div>
                    </div>

                    @if($ties->isEmpty())
                        <div class="p-4 text-center border border-dashed border-border-strong rounded-lg">
                            <div class="text-text-muted text-xs">{{ __('cup.draw_pending') }}</div>
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach($ties as $tie)
                                <x-cup-tie-card :tie="$tie" :player-team-id="$playerTeamId" />
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Legend --}}
    <div class="px-4 md:px-5 py-3 border-t border-border-default">
        <div class="flex flex-wrap gap-4 md:gap-6 text-xs text-text-muted">
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 bg-accent-blue/10 border border-accent-blue/30 rounded-sm"></div>
                <span>{{ __('cup.your_matches') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 bg-accent-green/10 rounded-sm"></div>
                <span>{{ __('cup.winner') }}</span>
            </div>
        </div>
    </div>
</x-section-card>
