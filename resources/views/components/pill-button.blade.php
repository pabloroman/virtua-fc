@props(['size' => 'default'])

@php
$sizeClasses = match($size) {
    'xs' => 'px-2 py-1 text-[10px]',
    'sm' => 'px-3 py-1.5 text-xs',
    default => 'px-4 py-2 text-sm',
};
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "inline-flex items-center justify-center {$sizeClasses} font-medium rounded-md transition-colors whitespace-nowrap focus:outline-hidden"]) }}>
    {{ $slot }}
</button>
