@props(['color' => 'red'])

@php
$colors = [
    'red' => 'bg-red-600 hover:bg-red-700 focus:ring-red-500 active:bg-red-800',
    'green' => 'bg-green-600 hover:bg-green-700 focus:ring-green-500 active:bg-green-800',
    'sky' => 'bg-sky-600 hover:bg-sky-700 focus:ring-sky-500 active:bg-sky-800',
    'emerald' => 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 active:bg-emerald-800',
    'amber' => 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500 active:bg-amber-800',
];
$colorClasses = $colors[$color] ?? $colors['red'];
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => "inline-flex items-center justify-center px-4 py-2 min-h-[44px] {$colorClasses} border border-transparent rounded-lg font-semibold text-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150"]) }}>
    {{ $slot }}
</button>
