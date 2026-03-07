@props(['color' => 'sky', 'size' => 'default'])

@php
$colors = [
    'sky' => 'text-sky-600 hover:text-sky-800 hover:bg-sky-50',
    'red' => 'text-red-600 hover:text-red-800 hover:bg-red-50',
    'amber' => 'text-amber-600 hover:text-amber-800 hover:bg-amber-50',
    'emerald' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
    'slate' => 'bg-slate-100 text-slate-500 hover:bg-slate-200',
];
$colorClasses = $colors[$color] ?? $colors['sky'];

$sizeClasses = match($size) {
    'xs' => 'px-2.5 py-1 text-xs rounded-md',
    default => 'px-2 py-1.5 text-sm',
};
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "inline-flex items-center {$sizeClasses} {$colorClasses} rounded transition-colors whitespace-nowrap focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed"]) }}>
    {{ $slot }}
</button>
