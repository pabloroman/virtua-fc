@props(['value', 'max' => 99, 'showValue' => true, 'size' => 'md'])

@php
    $percentage = $max > 0 ? min(100, ($value / $max) * 100) : 0;
    $colorClass = match(true) {
        $value >= 80 => 'bg-green-500',
        $value >= 70 => 'bg-lime-500',
        $value >= 60 => 'bg-amber-500',
        default => 'bg-slate-400',
    };
    $heightClass = $size === 'sm' ? 'h-1' : 'h-1.5';
    $widthClass = $size === 'sm' ? 'w-10' : 'w-14';
@endphp

<div class="flex items-center gap-1.5">
    @if($showValue)
        <span {{ $attributes }}>{{ $value }}</span>
    @endif
    <div class="{{ $widthClass }} {{ $heightClass }} bg-slate-200 rounded-full overflow-hidden flex-shrink-0">
        <div class="{{ $heightClass }} {{ $colorClass }} rounded-full" style="width: {{ $percentage }}%"></div>
    </div>
</div>
