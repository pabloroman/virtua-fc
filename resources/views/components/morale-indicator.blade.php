@props(['value'])

@php
    $label = match(true) {
        $value >= 90 => __('squad.morale_ecstatic'),
        $value >= 75 => __('squad.morale_happy'),
        $value >= 60 => __('squad.morale_content'),
        $value >= 40 => __('squad.morale_frustrated'),
        default => __('squad.morale_unhappy'),
    };

    $dotColor = match(true) {
        $value >= 75 => 'bg-accent-green',
        $value >= 60 => 'bg-accent-gold',
        $value >= 40 => 'bg-accent-orange',
        default => 'bg-accent-red',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }}>
    <div class="morale-dot {{ $dotColor }}"></div>
    <span class="text-[10px] text-text-secondary">{{ $label }}</span>
</div>
