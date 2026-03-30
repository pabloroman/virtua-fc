@props([
    'color' => 'blue',
    'icon' => null,
    'title' => null,
    'description' => null,
])

@php
    $colorMap = [
        'blue'  => ['banner' => 'bg-accent-blue/10 border-accent-blue/20 text-accent-blue',  'icon-bg' => 'bg-accent-blue/10'],
        'gold'  => ['banner' => 'bg-accent-gold/10 border-accent-gold/20 text-accent-gold',  'icon-bg' => 'bg-accent-gold/10'],
        'red'   => ['banner' => 'bg-accent-red/10 border-accent-red/20 text-accent-red',     'icon-bg' => 'bg-accent-red/10'],
        'green' => ['banner' => 'bg-accent-green/10 border-accent-green/20 text-accent-green','icon-bg' => 'bg-accent-green/10'],
    ];
    $colors = $colorMap[$color] ?? $colorMap['blue'];
@endphp

<div {{ $attributes->merge(['class' => "p-3 md:p-4 border rounded-xl flex items-center justify-between gap-2 md:gap-3 {$colors['banner']}"]) }}>
    <div class="flex items-center gap-2 md:gap-3 min-w-0">
        @if($icon)
        <div class="w-8 h-8 md:w-10 md:h-10 rounded-full {{ $colors['icon-bg'] }} flex items-center justify-center shrink-0 [&>svg]:w-4 [&>svg]:h-4 md:[&>svg]:w-5 md:[&>svg]:h-5">
            {{ $icon }}
        </div>
        @endif
        <div class="flex flex-col justify-center">
            @if($title)
            <h4 class="font-semibold text-xs md:text-sm">{{ $title }}</h4>
            @endif
            @if($description)
            <p class="text-xs md:text-sm mt-0.5 opacity-80">{{ $description }}</p>
            @endif
        </div>
    </div>

    @if($slot->isNotEmpty())
    <div class="shrink-0">
        {{ $slot }}
    </div>
    @endif
</div>
