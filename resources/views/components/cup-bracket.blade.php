@props([
    'rounds',
    'tiesByRound',
    'playerTeamId',
    'mode' => 'draw',                  // 'draw' = round-by-round (Copa del Rey) | 'fixed' = predetermined bracket (World Cup)
    'slotsByRound' => null,            // array<int, int>  round_number => slot count, required when mode='fixed'
    'displayOrderByRound' => null,     // array<int, array<int>>  round_number => [match_number,...] in bracket-tree order
])

@php
    $isFixed = $mode === 'fixed' && is_array($slotsByRound) && is_array($displayOrderByRound);

    if ($isFixed) {
        // 3rd-place ties branch off the SF losers, sharing the SF parent with the Final.
        // Render them out-of-band below the main bracket so connectors flow cleanly SF → Final.
        $thirdPlaceRound = $rounds->first(fn ($r) => $r->name === 'cup.third_place');
        $bracketRounds = $rounds->reject(fn ($r) => $r->name === 'cup.third_place')->values();

        // Build a per-round lookup of match_number → CupTie for fast slot resolution.
        $tiesByMatchNumber = [];
        foreach ($tiesByRound as $roundNumber => $roundTies) {
            $tiesByMatchNumber[$roundNumber] = $roundTies->keyBy('bracket_position');
        }

        // WC2026 generates rounds atomically — either all ties for a round exist or none.
        // We treat the connector style as a per-round flag: solid when both endpoints exist.
        $roundHasTies = [];
        foreach ($rounds as $r) {
            $roundHasTies[$r->round] = $tiesByRound->get($r->round, collect())->isNotEmpty();
        }

        // Column height drives every round's `justify-around` distribution. Allocate 5rem per
        // first-round slot — card height is ~4.5rem, leaving ~0.5rem between adjacent R32 cards.
        $maxSlots = max($slotsByRound ?: [1]);
        $columnHeightRem = max(16, $maxSlots * 5);
    }
@endphp

<x-section-card :title="__('cup.bracket')">
    @if($isFixed)
        <div class="overflow-x-auto p-4 md:p-5">
            <div
                class="cup-bracket flex"
                style="min-width: fit-content; --col-h: {{ $columnHeightRem }}rem; --connector-w: 1rem;"
            >
                @foreach($bracketRounds as $idx => $round)
                    @php
                        $slots = $slotsByRound[$round->round] ?? 0;
                        $displayOrder = $displayOrderByRound[$round->round] ?? [];
                        $tieLookup = $tiesByMatchNumber[$round->round] ?? collect();
                        $isLastRound = $idx === $bracketRounds->count() - 1;
                        $cardsPerPair = $isLastRound ? 1 : 2;
                        $pairCount = max(1, (int) ceil($slots / $cardsPerPair));
                        $hasIncomingConnector = $idx > 0;
                        $hasOutgoingConnector = ! $isLastRound;

                        $prevRound = $idx > 0 ? $bracketRounds[$idx - 1] : null;
                        $nextRound = ! $isLastRound ? $bracketRounds[$idx + 1] : null;
                        $incomingGhost = $hasIncomingConnector
                            && ! ($roundHasTies[$round->round] && $roundHasTies[$prevRound->round]);
                        $outgoingGhost = $hasOutgoingConnector
                            && ! ($roundHasTies[$round->round] && $roundHasTies[$nextRound->round]);
                    @endphp
                    <div class="cup-bracket__col shrink-0 w-64 flex flex-col" @if($hasIncomingConnector) style="margin-left: var(--connector-w);" @endif>
                        <div class="text-center mb-3">
                            <h4 class="font-heading text-sm font-semibold uppercase tracking-wide text-text-body">{{ __($round->name) }}</h4>
                            <div class="text-[10px] text-text-muted mt-0.5">
                                {{ $round->firstLegDate->format('d M') }}
                                @if($round->twoLegged)
                                    / {{ $round->secondLegDate->format('d M') }}
                                @endif
                            </div>
                        </div>

                        <div class="cup-bracket__column-body flex flex-col" style="height: var(--col-h);">
                            @for($pairIdx = 0; $pairIdx < $pairCount; $pairIdx++)
                                <div
                                    @class([
                                        'cup-bracket__pair flex flex-col justify-around flex-1 min-h-0',
                                        'cup-bracket__pair--has-out' => $hasOutgoingConnector,
                                        'cup-bracket__pair--ghost-out' => $outgoingGhost,
                                    ])
                                >
                                    @for($cardIdx = 0; $cardIdx < $cardsPerPair; $cardIdx++)
                                        @php
                                            $slotIndex = $pairIdx * $cardsPerPair + $cardIdx;
                                            $matchNumber = $displayOrder[$slotIndex] ?? null;
                                            $tie = $matchNumber !== null ? $tieLookup->get($matchNumber) : null;
                                        @endphp
                                        <div @class([
                                            'cup-bracket__cell',
                                            'cup-bracket__cell--has-in' => $hasIncomingConnector,
                                            'cup-bracket__cell--ghost' => $incomingGhost,
                                        ])>
                                            @if($tie)
                                                <x-cup-tie-card :tie="$tie" :player-team-id="$playerTeamId" />
                                            @else
                                                <x-cup-tie-card-ghost />
                                            @endif
                                        </div>
                                    @endfor
                                </div>
                            @endfor
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if($thirdPlaceRound)
            @php
                $thirdTies = $tiesByRound->get($thirdPlaceRound->round, collect())->values();
            @endphp
            <div class="px-4 md:px-5 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex-1 border-t border-dashed border-border-default"></div>
                    <div class="text-[10px] uppercase tracking-wide text-text-muted font-heading">
                        {{ __($thirdPlaceRound->name) }}
                        <span class="ml-1 normal-case tracking-normal">{{ $thirdPlaceRound->firstLegDate->format('d M') }}</span>
                    </div>
                    <div class="flex-1 border-t border-dashed border-border-default"></div>
                </div>
                <div class="mt-3 max-w-xs mx-auto">
                    @if($thirdTies->isEmpty())
                        <x-cup-tie-card-ghost />
                    @else
                        <x-cup-tie-card :tie="$thirdTies->first()" :player-team-id="$playerTeamId" />
                    @endif
                </div>
            </div>
        @endif
    @else
        {{-- Draw mode: round-by-round draws (e.g. Copa del Rey). Empty rounds show "draw pending". --}}
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
    @endif

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
