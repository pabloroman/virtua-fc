@props(['game', 'active'])

@php
    $items = [
        [
            'href' => route('game.club.finances', $game->id),
            'label' => __('club.nav.finances'),
            'active' => $active === 'finances',
        ],
        [
            'href' => route('game.club.stadium', $game->id),
            'label' => __('club.nav.stadium'),
            'active' => $active === 'stadium',
        ],
        [
            'href' => route('game.club.reputation', $game->id),
            'label' => __('club.nav.reputation'),
            'active' => $active === 'reputation',
        ],
    ];
@endphp

<x-section-nav :items="$items">
    {{ $slot }}
</x-section-nav>
