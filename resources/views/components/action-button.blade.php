@props(['color' => 'blue'])

@php
$colors = [
    'blue' => 'border-accent-blue/20 text-accent-blue bg-accent-blue/10 hover:bg-accent-blue/20',
    'red' => 'border-accent-red/20 text-accent-red bg-accent-red/10 hover:bg-accent-red/20',
    'green' => 'border-accent-green/20 text-accent-green bg-accent-green/10 hover:bg-accent-green/20',
    'amber' => 'border-accent-gold/20 text-accent-gold bg-accent-gold/10 hover:bg-accent-gold/20',
    'violet' => 'border-violet-500/20 text-violet-400 bg-violet-500/10 hover:bg-violet-500/20',
];
$colorClasses = $colors[$color] ?? $colors['blue'];
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => "inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border {$colorClasses} transition-colors min-h-[44px]"]) }}>
    {{ $slot }}
</button>
