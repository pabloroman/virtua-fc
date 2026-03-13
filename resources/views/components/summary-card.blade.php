@props(['label', 'value' => null, 'valueClass' => 'text-text-primary'])

<div {{ $attributes->merge(['class' => 'shrink-0 bg-surface-700/50 border border-border-default rounded-lg px-3.5 py-2.5 min-w-[110px]']) }}>
    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ $label }}</div>
    @if($value)<div class="font-heading text-xl font-bold {{ $valueClass }} mt-0.5">{{ $value }}</div>@endif
    {{ $slot }}
</div>
