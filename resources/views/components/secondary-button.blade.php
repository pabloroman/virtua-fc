@props(['size' => 'default'])

@php
$sizeClasses = match($size) {
    'xs' => 'px-2.5 py-1 text-xs rounded-md',
    default => 'px-4 py-2 min-h-[44px] text-sm rounded-lg',
};
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "inline-flex items-center justify-center {$sizeClasses} bg-surface-700 border border-border-strong font-semibold text-text-body shadow-xs hover:bg-surface-600 hover:text-text-primary focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150"]) }}>
    {{ $slot }}
</button>
