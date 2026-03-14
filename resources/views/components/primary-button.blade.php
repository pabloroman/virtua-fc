@props(['color' => 'blue', 'size' => 'default'])

@php
$colors = [
    'blue' => 'bg-accent-blue hover:bg-blue-600 focus:ring-accent-blue active:bg-blue-700',
    'red' => 'bg-accent-red hover:bg-red-500 focus:ring-accent-red active:bg-red-700',
    'green' => 'bg-accent-green hover:bg-green-600 focus:ring-accent-green active:bg-green-700',
    'amber' => 'bg-accent-gold hover:bg-amber-600 focus:ring-accent-gold active:bg-amber-700',
    'sky' => 'bg-sky-600 hover:bg-sky-500 focus:ring-sky-500 active:bg-sky-700',
    'teal' => 'bg-teal-600 hover:bg-teal-700 focus:ring-teal-500 active:bg-teal-800',
    'emerald' => 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 active:bg-emerald-800',
];
$colorClasses = $colors[$color] ?? $colors['blue'];

$sizeClasses = match($size) {
    'xs' => 'px-2.5 py-1 text-xs rounded-md',
    default => 'px-4 py-2 min-h-[44px] text-sm rounded-lg',
};
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => "inline-flex items-center justify-center {$sizeClasses} {$colorClasses} border border-transparent font-semibold text-white uppercase tracking-wider focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150"]) }}>
    {{ $slot }}
</button>
