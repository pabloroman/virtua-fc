@props(['label', 'value' => null, 'valueClass' => 'text-white'])

<div {{ $attributes->merge(['class' => 'flex-shrink-0 bg-surface-700/50 border border-white/5 rounded-lg px-3.5 py-2.5 min-w-[110px]']) }}>
    <div class="text-[10px] text-slate-500 uppercase tracking-widest">{{ $label }}</div>
    @if($value)<div class="font-heading text-xl font-bold {{ $valueClass }} mt-0.5">{{ $value }}</div>@endif
    {{ $slot }}
</div>
