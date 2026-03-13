@props(['label', 'value', 'max' => 99])

@php
    $pct = $max > 0 ? min(100, ($value / $max) * 100) : 0;

    $barColor = match(true) {
        $value >= 80 => 'bg-accent-green',
        $value >= 70 => 'bg-lime-500',
        $value >= 60 => 'bg-accent-gold',
        default => 'bg-surface-600',
    };

    $textColor = match(true) {
        $value >= 80 => 'text-accent-green',
        $value >= 70 => 'text-lime-500',
        $value >= 60 => 'text-accent-gold',
        default => 'text-text-secondary',
    };
@endphp

<div class="flex items-center justify-between gap-3">
    <span class="text-[11px] text-text-muted uppercase tracking-wide w-20 shrink-0">{{ $label }}</span>
    <div class="flex items-center gap-2.5 flex-1 justify-end">
        <div class="w-full h-1.5 bg-surface-700 rounded-full overflow-hidden">
            <div class="h-1.5 rounded-full {{ $barColor }}" style="width: {{ $pct }}%"></div>
        </div>
        <span class="text-xs font-semibold tabular-nums w-6 text-right {{ $textColor }}">{{ $value }}</span>
    </div>
</div>
