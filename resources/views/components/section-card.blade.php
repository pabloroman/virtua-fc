@props([
    'title' => null,
    'badge' => null,
    'overflow' => 'hidden',
])

<div {{ $attributes->merge(['class' => 'bg-surface-800 border border-border-default rounded-lg ' . ($overflow === 'visible' ? 'overflow-visible' : 'overflow-hidden')]) }}>
    @if($title)
    <div class="px-5 py-3 border-b border-border-default flex items-center justify-between">
        <h4 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary">{{ $title }}</h4>
        @if(isset($badge) && $badge instanceof \Illuminate\View\ComponentSlot)
            {{ $badge }}
        @elseif($badge)
            <span class="text-[11px] text-text-muted">{{ $badge }}</span>
        @endif
    </div>
    @endif

    {{ $slot }}
</div>
