@props(['game'])

@php
    $label = match (true) {
        $game->isTournamentMode() => __('game.mode_tournament_badge'),
        $game->isProManagerMode() => __('game.mode_career_pro'),
        $game->isCareerMode() => __('game.mode_career'),
        default => null,
    };

    // Career saves are pinned to the reference-data season they were created
    // from, so a save started before a data refresh stays distinguishable from
    // one started after it. The World Cup is a fixed real-world tournament —
    // a data vintage says nothing about it, so it keeps a bare mode label.
    $showSeason = $label !== null && ! $game->isTournamentMode();
@endphp

@if($label)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-accent-gold/10 px-2.5 py-0.5 text-xs font-medium text-accent-gold ring-1 ring-inset ring-amber-600/20']) }}>
        @if($showSeason)
            {{ __('game.mode_with_season', [
                'mode' => $label,
                'season' => \App\Models\Game::formatSeasonShort($game->base_season),
            ]) }}
        @else
            {{ $label }}
        @endif
    </span>
@endif
