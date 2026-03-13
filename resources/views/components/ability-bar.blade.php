@props(['value', 'max' => 99, 'showValue' => true, 'size' => 'md'])

@php
    $percentage = $max > 0 ? min(100, ($value / $max) * 100) : 0;
    $colorClass = match(true) {
        $value >= 80 => 'bg-accent-green',
        $value >= 70 => 'bg-lime-500',
        $value >= 60 => 'bg-accent-gold',
        default => 'bg-surface-600',
    };
    $barHeight = match($size) {
        'sm' => 'h-1.5',
        default => 'h-1.5',
    };
    $barWidth = match($size) {
        'sm' => 'w-10',
        default => 'w-16',
    };
@endphp

<div class="flex items-center gap-1.5">
    @if($showValue)
        <span {{ $attributes }}>{{ $value }}</span>
    @endif
    <div class="{{ $barWidth }} {{ $barHeight }} bg-surface-600 rounded-full overflow-hidden shrink-0">
        <div class="{{ $barHeight }} {{ $colorClass }} rounded-full fitness-bar" style="width: {{ $percentage }}%"></div>
    </div>
</div>
