@props([
    'player',
    'matchDate' => null,
    'competitionId' => null,
    'reason' => null,
])

@php
    /** @var \App\Models\GamePlayer $player */
    $tooltip = $reason ?? $player->getUnavailabilityReason($matchDate, $competitionId);

    if ($competitionId !== null) {
        $isSuspended = $player->isSuspendedInCompetition($competitionId);
    } else {
        // Fall back to any active suspension when the caller doesn't know the competition (e.g. squad view).
        $isSuspended = $player->relationLoaded('suspensions')
            ? $player->suspensions->where('matches_remaining', '>', 0)->isNotEmpty()
            : \App\Models\PlayerSuspension::where('game_player_id', $player->id)->where('matches_remaining', '>', 0)->exists();
    }
    $isInjured = !$isSuspended && $player->isInjured($matchDate);
@endphp

@if($tooltip)
    @if($isSuspended)
        {{-- Red card icon for suspension --}}
        <svg x-data="" x-tooltip.raw="{{ $tooltip }}" class="w-3.5 h-3.5 text-accent-red shrink-0 cursor-help" viewBox="0 0 12 16" fill="currentColor" aria-label="{{ $tooltip }}">
            <rect x="1" y="1" width="10" height="14" rx="1.5" />
        </svg>
    @elseif($isInjured)
        {{-- Medical cross icon for injury --}}
        <svg x-data="" x-tooltip.raw="{{ $tooltip }}" class="w-3.5 h-3.5 text-accent-red shrink-0 cursor-help" viewBox="0 0 20 20" fill="currentColor" aria-label="{{ $tooltip }}">
            <path fill-rule="evenodd" d="M8 2a1 1 0 0 0-1 1v4H3a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h4v4a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-4h4a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-4V3a1 1 0 0 0-1-1H8z" clip-rule="evenodd"/>
        </svg>
    @else
        {{-- Fallback: warning triangle (e.g. not registered) --}}
        <x-warning-triangle :tooltip="$tooltip" class="w-3.5 h-3.5 text-accent-orange" />
    @endif
@endif
