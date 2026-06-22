@php
/** @var \Illuminate\Support\Collection<App\Models\GamePlayer> $players */
/** @var App\Models\Game $game */
/** @var string $query */
/** @var int $total */
/** @var bool $truncated */
/** @var bool $hasCriteria */
@endphp

{{-- Header --}}
<div class="flex items-center gap-4 mb-5 pb-4 border-b border-border-default">
    <div class="w-14 h-14 md:w-16 md:h-16 shrink-0 flex items-center justify-center bg-surface-700 rounded-xl">
        <svg class="w-8 h-8 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
    </div>
    <div class="min-w-0">
        <h3 class="text-lg font-bold text-text-primary">{{ __('transfers.explore_search_results_title') }}</h3>
        <p class="text-sm text-text-muted">
            {{ ($total ?? $players->count()) }} {{ __('app.players') }}
            @if(!empty($truncated))
                <span class="text-text-muted">&middot; {{ __('transfers.explore_search_showing_first', ['count' => \App\Modules\Transfer\Services\ExploreService::ADVANCED_SEARCH_LIMIT]) }}</span>
            @endif
        </p>
    </div>
</div>

@if($players->isEmpty())
    @if(!empty($hasCriteria))
        <div class="flex flex-col items-center py-8 text-center gap-3">
            <p class="text-sm text-text-secondary">{{ __('transfers.explore_search_no_results') }}</p>
            <a href="{{ route('game.scouting', $game->id) }}" class="inline-flex items-center gap-1.5 text-sm text-accent-blue hover:text-accent-blue/80 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                {{ __('transfers.explore_empty_scout_cta') }}
            </a>
        </div>
    @else
        <p class="text-sm text-text-secondary text-center py-8">{{ __('transfers.explore_advanced_criteria_hint') }}</p>
    @endif
@else
    <div x-data="sortableTable()">
        <x-sortable-pills :columns="[
            ['col' => 'pos', 'label' => __('squad.pos')],
            ['col' => 'name', 'label' => __('squad.player')],
            ['col' => 'team', 'label' => __('transfers.explore_search_team')],
            ['col' => 'age', 'label' => __('transfers.explore_age')],
            ['col' => 'ovr', 'label' => __('transfers.explore_overall')],
            ['col' => 'value', 'label' => __('transfers.explore_value')],
        ]" />
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-border-default">
                        <th class="py-2.5 pl-4 w-12"></th>
                        <x-sortable-th col="name" align="left">{{ __('squad.player') }}</x-sortable-th>
                        <x-sortable-th col="team" align="left" class="hidden md:table-cell">{{ __('transfers.explore_search_team') }}</x-sortable-th>
                        <x-sortable-th col="age" class="hidden md:table-cell">{{ __('transfers.explore_age') }}</x-sortable-th>
                        <x-sortable-th col="ovr" class="hidden md:table-cell">{{ __('transfers.explore_overall') }}</x-sortable-th>
                        <x-sortable-th col="value" align="left" class="hidden md:table-cell">{{ __('transfers.explore_value') }}</x-sortable-th>
                        <th class="py-2.5 w-10"></th>
                        <th class="py-2.5 pr-4 w-10"></th>
                    </tr>
                </thead>
                <tbody data-sortable>
                    @foreach($players as $player)
                    <x-explore-player-row :player="$player" :game="$game" :show-team="true" :show-ovr="true" :is-own-team="$game->ownsTeam($player->team_id)" />
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if(!empty($truncated))
        <p class="text-xs text-text-muted mt-4 text-center">
            {{ __('transfers.explore_search_refine_hint') }}
        </p>
    @endif
@endif
