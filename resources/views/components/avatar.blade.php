@props(['name', 'size' => 'md'])

@php
    $initials = collect(explode(' ', $name))->map(fn($w) => mb_substr($w, 0, 1))->join('');
    $initials = mb_strlen($initials) > 2 ? mb_substr($initials, 0, 1) . mb_substr($initials, -1) : $initials;

    $colorPairs = [
        ['from' => 'from-blue-500', 'to' => 'to-blue-700'],
        ['from' => 'from-emerald-500', 'to' => 'to-emerald-700'],
        ['from' => 'from-violet-500', 'to' => 'to-violet-700'],
        ['from' => 'from-amber-500', 'to' => 'to-amber-700'],
        ['from' => 'from-rose-500', 'to' => 'to-rose-700'],
        ['from' => 'from-cyan-500', 'to' => 'to-cyan-700'],
        ['from' => 'from-orange-500', 'to' => 'to-orange-700'],
        ['from' => 'from-fuchsia-500', 'to' => 'to-fuchsia-700'],
    ];
    $color = $colorPairs[crc32($name) % count($colorPairs)];

    $circleSize = match($size) {
        'sm' => 'w-8 h-8',
        'lg' => 'w-14 h-14',
        'xl' => 'w-20 h-20',
        default => 'w-10 h-10',
    };
    $textSize = match($size) {
        'sm' => 'text-xs',
        'lg' => 'text-lg',
        'xl' => 'text-2xl',
        default => 'text-sm',
    };
@endphp

<div {{ $attributes->merge(['class' => "{$circleSize} rounded-full bg-linear-to-br {$color['from']} {$color['to']} flex items-center justify-center shrink-0"]) }}>
    <span class="font-heading font-bold {{ $textSize }} text-white">{{ $initials }}</span>
</div>
