@props(['size' => 'default'])

@php
$sizeClasses = match($size) {
    'xs' => 'px-2.5 py-1 text-xs rounded-md',
    'sm' => 'px-3 py-1.5 min-h-[36px] text-xs rounded-lg',
    default => 'px-4 py-2 min-h-[44px] text-sm rounded-lg',
};
@endphp

<a {{ $attributes->merge(['class' => "inline-flex items-center justify-center {$sizeClasses} bg-surface-700 border border-border-strong font-semibold text-text-body shadow-xs hover:bg-surface-600 hover:text-text-primary uppercase tracking-wider focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 transition ease-in-out duration-150"]) }}>
    {{ $slot }}
</a>
