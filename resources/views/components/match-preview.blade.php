@props([
    'narratives' => [],
    'limit' => null,
])

@php
    $items = $limit ? array_slice($narratives, 0, $limit) : $narratives;
@endphp

@if(!empty($items))
<div {{ $attributes }}>
    <div class="flex items-center gap-1.5 mb-2">
        <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
        </svg>
        <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wide">{{ __('game.match_preview') }}</span>
    </div>
    <div class="space-y-1.5">
        @foreach($items as $narrative)
            <p class="text-xs text-text-secondary leading-relaxed">{{ $narrative->text }}</p>
        @endforeach
    </div>
</div>
@endif
