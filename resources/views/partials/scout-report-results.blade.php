@php
    /** @var App\Models\Game $game */
    /** @var App\Models\ScoutReport $report */
    /** @var array $buckets */
    /** @var int $totalResults */
    /** @var array $playerDetails */

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
        <x-icon-button size="sm" onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'scout-results'}))">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </x-icon-button>
    </div>

    {{-- Scout filter explainer: only shown when the report uses the three-bucket format --}}
    @if($buckets['legacy']->isEmpty())
        <div class="mt-4 px-4 md:px-6 text-xs text-text-muted">
            {{ __('transfers.scout_filtered_by_three_pass_hint') }}
        </div>
    @endif

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
    @elseif($buckets['legacy']->isNotEmpty())
        {{-- Legacy report (pre-three-pass format): render as a single flat list. --}}
        <div class="divide-y divide-border-default -mx-4 md:-mx-6 mt-4">
            @foreach($buckets['legacy'] as $player)
                @include('partials.scout-report-player-row', [
                    'game' => $game,
                    'player' => $player,
                    'detail' => $playerDetails[$player->id] ?? [],
                    'isPreContractPeriod' => $isPreContractPeriod,
                    'shortlistedPlayerIds' => $shortlistedPlayerIds,
                ])
            @endforeach
        </div>
    @else
        {{-- Three-bucket layout: primary, ambitious, persuasion. Empty buckets are hidden. --}}
        @foreach($bucketMeta as $key => $meta)
            @php $players = $buckets[$key]; @endphp
            @continue($players->isEmpty())
            <section class="mt-6 -mx-4 md:-mx-6">
                <header class="px-4 md:px-6 pb-2 border-b border-border-default">
                    <h4 class="text-sm font-semibold {{ $meta['accent'] }}">
                        {{ $meta['title'] }}
                        <span class="text-text-muted font-normal ml-1">({{ $players->count() }})</span>
                    </h4>
                    <p class="text-xs text-text-muted mt-0.5">{{ $meta['description'] }}</p>
                </header>
                <div class="divide-y divide-border-default">
                    @foreach($players as $player)
                        @include('partials.scout-report-player-row', [
                            'game' => $game,
                            'player' => $player,
                            'detail' => $playerDetails[$player->id] ?? [],
                            'isPreContractPeriod' => $isPreContractPeriod,
                            'shortlistedPlayerIds' => $shortlistedPlayerIds,
                        ])
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
