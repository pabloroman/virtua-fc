@props(['color' => 'sky'])

@php
$colors = [
    'sky' => 'text-sky-600 hover:text-sky-800 hover:bg-sky-50',
    'red' => 'text-red-600 hover:text-red-800 hover:bg-red-50',
    'amber' => 'text-amber-600 hover:text-amber-800 hover:bg-amber-50',
];
$colorClasses = $colors[$color] ?? $colors['sky'];
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "inline-flex items-center px-2 py-1.5 text-xs {$colorClasses} rounded transition-colors whitespace-nowrap focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed"]) }}>
    {{ $slot }}
</button>
