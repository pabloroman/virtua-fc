@php
/** @var App\Models\Team $team */
/** @var \Illuminate\Support\Collection<App\Models\GamePlayer> $players */
/** @var App\Models\Game $game */
/** @var bool $isOwnTeam */
@endphp

{{-- Team header --}}
<div class="flex items-center gap-4 mb-5 pb-4 border-b border-border-default">
    <img src="{{ $team->image }}" alt="{{ $team->name }}" class="w-14 h-14 md:w-16 md:h-16 shrink-0 object-contain">
    <div class="min-w-0">
        <h3 class="text-lg font-bold text-text-primary truncate">{{ $team->name }}</h3>
    </div>
</div>

{{-- Offer hint (hidden when browsing your own first or reserve team — no offers possible) --}}
@unless($isOwnTeam)
<div class="flex items-center gap-2 px-3 py-2 bg-accent-gold/10 border border-accent-gold/20 rounded-lg text-sm text-accent-gold mb-5">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span>{{ __('transfers.explore_offer_hint') }}</span>
</div>
@endunless

{{-- Squad table --}}
<div x-data="sortableTable()">
    <x-sortable-pills :columns="[
        ['col' => 'pos', 'label' => __('squad.pos')],
        ['col' => 'name', 'label' => __('squad.player')],
        ['col' => 'age', 'label' => __('transfers.explore_age')],
        ['col' => 'ovr', 'label' => __('transfers.explore_overall')],
        ['col' => 'value', 'label' => __('transfers.explore_value')],
        ['col' => 'contract', 'label' => __('transfers.explore_contract_year')],
    ]" />
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-border-default">
                    <th class="py-2.5 pl-4 w-12"></th>
                    <x-sortable-th col="name" align="left">{{ __('squad.player') }}</x-sortable-th>
                    <x-sortable-th col="age" class="hidden md:table-cell">{{ __('transfers.explore_age') }}</x-sortable-th>
                    <x-sortable-th col="ovr" class="hidden md:table-cell">{{ __('transfers.explore_overall') }}</x-sortable-th>
                    <x-sortable-th col="value" align="left" class="hidden md:table-cell">{{ __('transfers.explore_value') }}</x-sortable-th>
                    <x-sortable-th col="contract" class="hidden md:table-cell">{{ __('transfers.explore_contract_year') }}</x-sortable-th>
                    <th class="py-2.5 w-10"></th>
                    <th class="py-2.5 pr-4 w-10"></th>
                </tr>
            </thead>
            <tbody data-sortable>
                @foreach($players as $player)
                <x-explore-player-row :player="$player" :game="$game" :show-ovr="true" :is-own-team="$isOwnTeam" />
                @endforeach
            </tbody>
        </table>
    </div>
</div>
