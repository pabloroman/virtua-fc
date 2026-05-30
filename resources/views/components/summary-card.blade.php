@props(['label', 'value' => null, 'valueClass' => 'text-text-primary', 'caption' => null, 'tooltip' => null])

<div {{ $attributes->merge(['class' => 'shrink-0 bg-surface-700/50 border border-border-default rounded-lg px-3.5 py-2.5 min-w-[110px]']) }}>
    <div class="text-[10px] text-text-muted uppercase tracking-widest flex items-center gap-1">{{ $label }}@if($tooltip)<x-info-icon :tooltip="$tooltip" />@endif</div>
    @if($value)<div class="font-heading text-xl font-bold {{ $valueClass }} mt-0.5">{{ $value }}</div>@endif
    {{ $slot }}
    @if($caption)<div class="text-[11px] text-text-muted mt-1">{{ $caption }}</div>@endif
</div>
