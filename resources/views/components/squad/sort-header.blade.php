@props(['col', 'align' => 'center', 'labelClass' => ''])
@php
    $justify = match ($align) {
        'left' => 'justify-start text-left',
        'right' => 'justify-end text-right',
        default => 'justify-center text-center',
    };
@endphp
<button type="button"
    @click="toggleSort('{{ $col }}')"
    {{ $attributes->merge(['class' => "group inline-flex items-center gap-0.5 w-full min-w-0 uppercase tracking-widest font-semibold transition-colors $justify"]) }}
    :class="sortCol === '{{ $col }}' ? 'text-text-primary' : 'text-text-muted hover:text-text-body'"
    :aria-sort="sortCol === '{{ $col }}' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
    <span class="truncate {{ $labelClass }}">{{ $slot }}</span>
    {{-- Arrow only occupies layout when the column is active, so narrow columns don't get cramped. --}}
    <svg x-show="sortCol === '{{ $col }}'" class="w-3 h-3 shrink-0 transition-transform"
         :class="sortDir === 'desc' ? 'rotate-180' : ''"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
</button>
