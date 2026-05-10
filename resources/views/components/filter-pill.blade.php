@props([
    'active' => 'false',
    'size' => 'sm',
])

@php
$sizeClasses = match($size) {
    'xs' => 'px-2 py-1 text-[10px]',
    'sm' => 'px-3 py-1.5 text-xs',
    default => 'px-4 py-2 text-sm',
};
@endphp

<button
    type="button"
    :class="({!! $active !!}) ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'"
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center {$sizeClasses} font-medium rounded-lg transition-colors whitespace-nowrap focus:outline-hidden"]) }}
>
    {{ $slot }}
</button>
