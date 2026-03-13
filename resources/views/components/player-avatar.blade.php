@props(['name', 'positionGroup', 'positionAbbrev' => null, 'number' => null, 'size' => 'md'])

@php
    $colors = match($positionGroup) {
        'Goalkeeper' => ['from' => 'from-amber-500/20', 'to' => 'to-amber-600/10', 'border' => 'border-amber-500/20', 'text' => 'text-amber-400', 'badge_bg' => 'bg-amber-500/20'],
        'Defender'   => ['from' => 'from-blue-500/20', 'to' => 'to-blue-600/10', 'border' => 'border-blue-500/20', 'text' => 'text-blue-400', 'badge_bg' => 'bg-blue-500/20'],
        'Forward'    => ['from' => 'from-rose-500/20', 'to' => 'to-rose-600/10', 'border' => 'border-rose-500/20', 'text' => 'text-rose-400', 'badge_bg' => 'bg-rose-500/20'],
        default      => ['from' => 'from-green-500/20', 'to' => 'to-green-600/10', 'border' => 'border-green-500/20', 'text' => 'text-green-400', 'badge_bg' => 'bg-green-500/20'],
    };

    $display = $number ?: (function() use ($name) {
        $initials = collect(explode(' ', $name))->map(fn($w) => mb_substr($w, 0, 1))->join('');
        return mb_strlen($initials) > 2 ? mb_substr($initials, 0, 1) . mb_substr($initials, -1) : $initials;
    })();

    $circleSize = match($size) {
        'sm' => 'w-8 h-8',
        'lg' => 'w-12 h-12',
        default => 'w-10 h-10',
    };
    $textSize = match($size) {
        'sm' => 'text-xs',
        'lg' => 'text-base',
        default => 'text-sm',
    };
    $badgeSize = match($size) {
        'sm' => 'w-3.5 h-3.5 text-[6px]',
        'lg' => 'w-5 h-5 text-[8px]',
        default => 'w-4 h-4 text-[7px]',
    };
@endphp

<div class="relative shrink-0">
    <div class="{{ $circleSize }} rounded-full bg-linear-to-br {{ $colors['from'] }} {{ $colors['to'] }} border {{ $colors['border'] }} flex items-center justify-center">
        <span class="font-heading font-bold {{ $textSize }} {{ $colors['text'] }}">{{ $display }}</span>
    </div>
    @if($positionAbbrev)
    <span class="absolute -bottom-0.5 -right-0.5 {{ $badgeSize }} rounded-full {{ $colors['badge_bg'] }} border border-surface-800 flex items-center justify-center">
        <span class="font-bold {{ $colors['text'] }}">{{ $positionAbbrev }}</span>
    </span>
    @endif
</div>
