@props(['size' => 'default', 'align' => 'center'])

@php
$sizeClasses = match($size) {
    'xs' => 'px-2 py-1 text-[10px]',
    'sm' => 'px-3 py-1.5 text-xs',
    default => 'px-4 py-2 text-sm',
};

// A pill stretched to fill its cell (w-full in a grid) reads better with its
// content left-aligned, so leading icons line up down the column instead of
// floating at a different offset in every row. Content-width pills stay centred.
$alignClasses = match($align) {
    'start' => 'justify-start text-left',
    default => 'justify-center',
};
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "inline-flex items-center {$alignClasses} {$sizeClasses} font-medium rounded-md transition-colors whitespace-nowrap focus:outline-hidden"]) }}>
    {{ $slot }}
</button>
