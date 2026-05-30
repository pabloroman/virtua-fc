@props([
    'bill',           // committed wage bill, in cents
    'cap',            // salary cap, in cents
    'status',         // 'healthy' | 'warning' | 'over'
    'room' => null,   // remaining cap room, in cents (optional)
    'tooltip' => null, // optional explanatory tooltip shown beside the figures
])

@php
    $pct = $cap > 0 ? (int) round($bill / $cap * 100) : 0;
    $barWidth = min($pct, 100);
    [$barColor, $textColor] = match ($status) {
        'over' => ['bg-accent-red', 'text-accent-red'],
        'warning' => ['bg-accent-gold', 'text-accent-gold'],
        default => ['bg-accent-green', 'text-text-primary'],
    };
    $overBy = $bill - $cap;
@endphp

<div class="mt-1">
    <div class="flex items-center gap-2">
        <div class="flex-1 h-1.5 bg-surface-600 rounded-full overflow-hidden">
            <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $barWidth }}%"></div>
        </div>
        <span class="font-heading text-xl font-bold {{ $textColor }}">{{ $pct }}%</span>
    </div>
    <div class="mt-1 text-[11px] text-text-muted">
        {{ \App\Support\Money::format($bill) }} / {{ \App\Support\Money::format($cap) }}
        @if($tooltip)<x-info-icon :tooltip="$tooltip" />@endif
        @if($status === 'over')
            <span class="text-accent-red font-semibold">· {{ __('finances.over_cap') }} {{ \App\Support\Money::format($overBy) }}</span>
        @elseif($room !== null)
            <span class="text-text-faint">· {{ \App\Support\Money::format($room) }} {{ __('finances.wage_room') }}</span>
        @endif
    </div>
</div>
