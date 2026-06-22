@props(['value', 'size' => 'md'])

@php
    $ratingClass = \App\Support\RatingPalette::classFor($value);

    $sizeClasses = match($size) {
        'sm' => 'w-7 h-7 rounded-md text-xs',
        'lg' => 'w-12 h-12 rounded-lg text-lg',
        default => 'w-9 h-9 rounded-lg text-sm',
    };
@endphp

<div {{ $attributes->merge(['class' => "rating-badge {$sizeClasses} {$ratingClass} flex items-center justify-center"]) }}>
    <span class="font-heading font-bold">{{ $value }}</span>
</div>
