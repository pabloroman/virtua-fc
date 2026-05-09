@props(['value', 'showLabel' => false, 'showPercentage' => true, 'size' => 'md', 'reverse' => false])

@php
    $fillColor = match(true) {
        $value >= 80 => 'bg-accent-green',
        $value >= 60 => 'bg-accent-gold',
        $value >= 40 => 'bg-accent-orange',
        default => 'bg-accent-red',
    };

    [$barWidth, $barHeight, $textSize, $pctWidth] = match($size) {
        'xs' => ['w-8', 'h-1', 'text-[8px]', 'w-6'],
        'sm' => ['w-14', 'h-1.5', 'text-[10px]', 'w-7'],
        default => ['w-16', 'h-1.5', 'text-[10px]', 'w-7'],
    };

    $rowClass = $reverse ? 'flex items-center gap-1.5 flex-row-reverse' : 'flex items-center gap-1.5';
    $pctAlign = $reverse ? 'text-left' : 'text-right';
@endphp

<div {{ $attributes->merge(['class' => $rowClass]) }}>
    @if($showLabel)
        <span class="{{ $textSize }} text-text-muted">ENE</span>
    @endif
    <div class="{{ $barWidth }} {{ $barHeight }} rounded-full bg-surface-600 overflow-hidden">
        <div class="h-full rounded-full {{ $fillColor }} fitness-bar" style="width: {{ $value }}%"></div>
    </div>
    @if($showPercentage)
        <span class="{{ $textSize }} text-text-muted {{ $pctWidth }} {{ $pctAlign }} tabular-nums">{{ $value }}%</span>
    @endif
</div>
