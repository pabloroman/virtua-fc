@props(['mode'])

@php
    $label = match ($mode) {
        \App\Models\Game::MODE_CAREER => __('game.mode_career'),
        \App\Models\Game::MODE_CAREER_PRO => __('game.mode_career_pro'),
        \App\Models\Game::MODE_TOURNAMENT => __('game.mode_tournament'),
        default => null,
    };
    $classes = match ($mode) {
        \App\Models\Game::MODE_CAREER_PRO => 'bg-accent-gold/10 text-accent-gold',
        \App\Models\Game::MODE_TOURNAMENT => 'bg-accent-green/10 text-accent-green',
        default => 'bg-accent-blue/10 text-accent-blue',
    };
@endphp

@if($label)
    <span {{ $attributes->merge(['class' => "inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wide shrink-0 {$classes}"]) }}>
        {{ $label }}
    </span>
@endif
