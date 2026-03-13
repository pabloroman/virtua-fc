@props(['size' => 'default'])

@php
$sizeClasses = match($size) {
    'xs' => 'px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider',
    default => 'px-4 py-2.5 text-sm font-medium',
};
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "{$sizeClasses} border-b-2 transition-colors whitespace-nowrap"]) }}>
    {{ $slot }}
</button>
