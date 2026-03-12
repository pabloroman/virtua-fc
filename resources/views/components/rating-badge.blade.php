@props(['value', 'size' => 'md'])

@php
    $ratingClass = match(true) {
        $value >= 80 => 'rating-elite',
        $value >= 70 => 'rating-good',
        $value >= 60 => 'rating-average',
        $value >= 50 => 'rating-below',
        default => 'rating-poor',
    };

    $sizeClasses = match($size) {
        'sm' => 'w-7 h-7 rounded-md text-[10px]',
        'lg' => 'w-12 h-12 rounded-lg text-lg',
        default => 'w-9 h-9 rounded-lg text-sm',
    };
@endphp

<div {{ $attributes->merge(['class' => "rating-badge {$sizeClasses} {$ratingClass} flex items-center justify-center"]) }}>
    <span class="font-heading font-bold">{{ $value }}</span>
</div>
