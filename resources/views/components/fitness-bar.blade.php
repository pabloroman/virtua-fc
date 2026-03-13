@props(['value', 'showLabel' => false, 'showPercentage' => true, 'size' => 'md'])

@php
    $fillColor = match(true) {
        $value >= 80 => 'bg-accent-green',
        $value >= 60 => 'bg-accent-gold',
        $value >= 40 => 'bg-accent-orange',
        default => 'bg-accent-red',
    };

    $barWidth = match($size) {
        'sm' => 'w-14',
        default => 'w-16',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }}>
    @if($showLabel)
        <span class="text-[10px] text-text-muted">FIT</span>
    @endif
    <div class="{{ $barWidth }} h-1.5 rounded-full bg-surface-600 overflow-hidden">
        <div class="h-full rounded-full {{ $fillColor }} fitness-bar" style="width: {{ $value }}%"></div>
    </div>
    @if($showPercentage)
        <span class="text-[10px] text-text-muted w-7 text-right tabular-nums">{{ $value }}%</span>
    @endif
</div>
