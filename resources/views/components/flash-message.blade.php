@props([
    'type' => 'success',
    'message' => null,
])

@php
    $content = $message ?? ($slot->isNotEmpty() ? $slot : null);
    if (!$content) return;

    $styles = match ($type) {
        'success' => [
            'border' => 'border-l-emerald-500',
            'bg' => 'bg-emerald-500/10',
            'text' => 'text-emerald-400',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'error' => [
            'border' => 'border-l-red-500',
            'bg' => 'bg-red-500/10',
            'text' => 'text-red-400',
            'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'warning' => [
            'border' => 'border-l-amber-500',
            'bg' => 'bg-amber-500/10',
            'text' => 'text-amber-400',
            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        ],
        'info' => [
            'border' => 'border-l-accent-blue',
            'bg' => 'bg-accent-blue/10',
            'text' => 'text-blue-400',
            'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
    };
@endphp

<div {{ $attributes->merge(['class' => "flex items-start gap-3 border-l-4 {$styles['border']} {$styles['bg']} py-3 pl-4 pr-4 rounded-r-lg"]) }}>
    <svg class="w-5 h-5 {{ $styles['text'] }} shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $styles['icon'] }}"/>
    </svg>
    <span class="text-sm {{ $styles['text'] }}">{{ $content }}</span>
</div>
