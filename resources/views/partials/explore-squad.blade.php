@php
/** @var App\Models\Team $team */
/** @var \Illuminate\Support\Collection<App\Models\GamePlayer> $players */
/** @var App\Models\Game $game */
@endphp

{{-- Team header --}}
<div class="flex items-center gap-4 mb-5 pb-4 border-b border-border-default">
    <img src="{{ $team->image }}" alt="{{ $team->name }}" class="w-14 h-14 md:w-16 md:h-16 shrink-0 object-contain">
    <div class="min-w-0">
        <h3 class="text-lg font-bold text-text-primary truncate">{{ $team->name }}</h3>
    </div>
</div>

{{-- Scouting nudge --}}
<div class="flex items-center gap-2 px-3 py-2 bg-accent-gold/10 border border-accent-gold/20 rounded-lg text-sm text-accent-gold mb-5">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span>{{ __('transfers.explore_scouting_nudge') }}</span>
</div>

{{-- Squad table --}}
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left border-b border-border-default">
                <th class="py-2.5 pl-4 w-12"></th>
                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider"></th>
                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell">{{ __('transfers.explore_age') }}</th>
                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('transfers.explore_value') }}</th>
                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell">{{ __('transfers.explore_contract_year') }}</th>
                <th class="py-2.5 pr-4 w-10"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($players as $player)
            <x-explore-player-row :player="$player" :game="$game" />
            @endforeach
        </tbody>
    </table>
</div>
