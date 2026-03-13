@props(['size' => 'default'])

@php
$sizeClasses = match($size) {
    'sm' => 'p-1 rounded-sm',
    default => 'p-2 min-h-[44px] min-w-[44px] rounded-sm',
};
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "inline-flex items-center justify-center {$sizeClasses} text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors shrink-0 focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-1 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed"]) }}>
    {{ $slot }}
</button>
