@props(['col'])
{{-- Mobile sort trigger. Reuses the same toggleSort/sortCol/sortDir Alpine state
     as the desktop <x-squad.sort-header>. --}}
<button type="button"
    @click="toggleSort('{{ $col }}')"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 shrink-0 px-2 py-1 text-[10px] font-medium rounded-md transition-colors whitespace-nowrap focus:outline-hidden']) }}
    :class="sortCol === '{{ $col }}' ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-body'"
    :aria-sort="sortCol === '{{ $col }}' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'">
    <span>{{ $slot }}</span>
    {{-- Active column: directional arrow for the current sort direction. --}}
    <svg x-show="sortCol === '{{ $col }}'" class="w-3 h-3 shrink-0 transition-transform"
         :class="sortDir === 'desc' ? 'rotate-180' : ''"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
    </svg>
    {{-- Inactive column: neutral glyph with the same footprint, so the pill width
         never shifts when it becomes active (prevents the strip from jittering). --}}
    <svg x-show="sortCol !== '{{ $col }}'" class="w-3 h-3 shrink-0 text-text-faint" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" />
    </svg>
</button>
