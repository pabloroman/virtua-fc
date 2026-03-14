@props(['size' => 'default'])

@php
$sizeClasses = match($size) {
    'xs' => 'px-2.5 py-1 text-xs rounded-md',
    default => 'px-4 py-2 min-h-[44px] text-sm rounded-lg',
};
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => "inline-flex items-center justify-center {$sizeClasses} bg-accent-red border border-transparent font-semibold text-white hover:bg-red-500 active:bg-red-700 focus:outline-hidden focus:ring-2 focus:ring-accent-red focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150"]) }}>
    {{ $slot }}
</button>
