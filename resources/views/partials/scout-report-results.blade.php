@php
    /** @var App\Models\Game $game */
    /** @var App\Models\ScoutReport $report */
    /** @var array $buckets */
    /** @var int $totalResults */
    /** @var array $playerDetails */

    // When rendered inline on the scouting hub (vs. inside the modal) there is
    // no modal to close, so the header close button is omitted.
    $inline ??= false;

    $bucketMeta = [
        'primary' => [
            'title' => __('transfers.scout_bucket_primary_title'),
            'description' => __('transfers.scout_bucket_primary_description'),
            'accent' => 'text-accent-green',
        ],
        'ambitious' => [
            'title' => __('transfers.scout_bucket_ambitious_title'),
            'description' => __('transfers.scout_bucket_ambitious_description'),
            'accent' => 'text-accent-gold',
        ],
        'persuasion' => [
            'title' => __('transfers.scout_bucket_persuasion_title'),
            'description' => __('transfers.scout_bucket_persuasion_description'),
            'accent' => 'text-accent-blue',
        ],
    ];

    // Flatten into renderable sections: a legacy (pre-three-pass) report becomes
    // a single header-less section; otherwise one section per non-empty bucket.
    $sections = [];
    if ($buckets['legacy']->isNotEmpty()) {
        $sections[] = ['meta' => null, 'players' => $buckets['legacy']];
    } else {
        foreach ($bucketMeta as $key => $meta) {
            if ($buckets[$key]->isNotEmpty()) {
                $sections[] = ['meta' => $meta, 'players' => $buckets[$key]];
            }
        }
    }
@endphp

<div class="p-4 md:p-6">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 pb-4 border-b border-border-strong">
        <div>
            <h3 class="font-semibold text-lg text-text-primary">{{ __('transfers.scout_results') }}</h3>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-sm text-text-muted">
                <span><span class="font-medium text-text-body">{{ $positionLabel }}</span></span>
                <span class="text-text-body">&middot;</span>
                <span>{{ $scopeLabel }}</span>
                <span class="text-text-body">&middot;</span>
                <span>{{ __('transfers.results_count', ['count' => $totalResults]) }}</span>
            </div>
        </div>
        @if($inline)
        {{-- Inline on the hub: the header carries the new-search CTA (in place of the modal close button). --}}
        <x-primary-button type="button" size="sm" class="gap-1.5 shrink-0"
            onclick="window.dispatchEvent(new CustomEvent('open-modal', {detail: 'scout-search'}))">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            {{ __('transfers.new_scout_search') }}
        </x-primary-button>
        @else
        <x-icon-button size="sm" onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'scout-results'}))">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </x-icon-button>
        @endif
    </div>

    @if($totalResults === 0)
        {{-- Full empty state: nothing cleared the three-pass bar --}}
        <div class="flex flex-col items-center py-10 text-center gap-3 text-text-secondary">
            <svg class="w-10 h-10 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <p class="font-medium">{{ __('transfers.no_players_found') }}</p>
            <p class="text-sm">{{ __('transfers.scouting_empty_three_pass_hint') }}</p>
            <a href="{{ route('game.explore', $game->id) }}" class="inline-flex items-center gap-1.5 text-sm text-accent-blue hover:text-accent-blue/80 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                {{ __('transfers.scouting_empty_explore_cta') }}
            </a>
        </div>
    @else
        {{-- One table per section. Rows reuse the shared <x-explore-player-row>:
             exact OVR, inline offer + shortlist star, and click-to-open dossier. --}}
        @foreach($sections as $section)
            <section class="mt-6 -mx-4 md:-mx-6" x-data="sortableTable()">
                @if($section['meta'])
                    <header class="px-4 md:px-6 pb-2 border-b border-border-default">
                        <h4 class="text-sm font-semibold {{ $section['meta']['accent'] }}">
                            {{ $section['meta']['title'] }}
                            <span class="text-text-muted font-normal ml-1">({{ $section['players']->count() }})</span>
                        </h4>
                        <p class="text-xs text-text-muted mt-0.5">{{ $section['meta']['description'] }}</p>
                    </header>
                @endif
                <div class="md:hidden px-4 pt-3">
                    <x-sortable-pills :columns="[
                        ['col' => 'pos', 'label' => __('squad.pos')],
                        ['col' => 'name', 'label' => __('squad.player')],
                        ['col' => 'age', 'label' => __('transfers.explore_age')],
                        ['col' => 'ovr', 'label' => __('transfers.explore_overall')],
                        ['col' => 'value', 'label' => __('transfers.explore_value')],
                        ['col' => 'asking', 'label' => __('transfers.asking_price')],
                    ]" />
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-border-default">
                                <th class="py-2.5 pl-4 w-12"></th>
                                <x-sortable-th col="name" align="left">{{ __('squad.player') }}</x-sortable-th>
                                <x-sortable-th col="age" class="hidden md:table-cell">{{ __('transfers.explore_age') }}</x-sortable-th>
                                <x-sortable-th col="ovr" class="hidden md:table-cell">{{ __('transfers.explore_overall') }}</x-sortable-th>
                                <x-sortable-th col="value" align="left" class="hidden md:table-cell">{{ __('transfers.explore_value') }}</x-sortable-th>
                                <x-sortable-th col="asking" align="right" class="hidden md:table-cell">{{ __('transfers.asking_price') }}</x-sortable-th>
                                <th class="py-2.5 w-10"></th>
                                <th class="py-2.5 pr-4 w-10"></th>
                            </tr>
                        </thead>
                        <tbody data-sortable>
                            @foreach($section['players'] as $player)
                                @php $detail = $playerDetails[$player->id] ?? []; @endphp
                                <x-explore-player-row
                                    :player="$player"
                                    :game="$game"
                                    :show-team="true"
                                    team-placement="inline"
                                    :show-ovr="true"
                                    :asking-price="($detail['is_free_agent'] ?? false) ? null : ($detail['asking_price'] ?? null)"
                                    :show-asking-price="true"
                                    :scouting-detail="$detail" />
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</div>
