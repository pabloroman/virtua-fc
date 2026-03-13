@props(['color' => 'blue', 'size' => 'default'])

@php
$colors = [
    'blue' => 'text-accent-blue hover:text-blue-400 hover:bg-accent-blue/10',
    'red' => 'text-accent-red hover:text-red-400 hover:bg-accent-red/10',
    'amber' => 'text-accent-gold hover:text-amber-400 hover:bg-accent-gold/10',
    'green' => 'text-accent-green hover:text-green-400 hover:bg-accent-green/10',
    'slate' => 'text-text-secondary hover:text-text-body hover:bg-surface-700',
];
$colorClasses = $colors[$color] ?? $colors['blue'];

$sizeClasses = match($size) {
    'xs' => 'px-2.5 py-1 text-xs rounded-md',
    default => 'px-2 py-1.5 text-sm',
};
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "inline-flex items-center {$sizeClasses} {$colorClasses} rounded-sm transition-colors whitespace-nowrap focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-1 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed"]) }}>
    {{ $slot }}
</button>
