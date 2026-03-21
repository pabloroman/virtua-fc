@props(['value' => null, 'range' => null, 'max' => 99, 'showValue' => true, 'size' => 'md'])

@php
    $displayValue = $value ?? (int) round(($range[0] + $range[1]) / 2);
    $percentage = $max > 0 ? min(100, ($displayValue / $max) * 100) : 0;
    $colorClass = match(true) {
        $displayValue >= 80 => 'bg-accent-green',
        $displayValue >= 70 => 'bg-lime-500',
        $displayValue >= 60 => 'bg-accent-gold',
        default => 'bg-accent-orange',
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
        <span {{ $attributes }}>{{ $range ? $range[0] . '-' . $range[1] : $displayValue }}</span>
    @endif
    <div class="{{ $barWidth }} {{ $barHeight }} bg-surface-600 rounded-full overflow-hidden shrink-0">
        <div class="{{ $barHeight }} {{ $colorClass }} rounded-full fitness-bar" style="width: {{ $percentage }}%"></div>
    </div>
</div>
