@props(['col', 'align' => 'center', 'labelClass' => ''])
{{-- Sortable table-header cell for tables driven by the sortableTable() Alpine
     component. Reuses the shared <x-squad.sort-header> button (active arrow +
     neutral glyph + aria-sort) inside a real <th>. Pass-through $attributes
     carry per-column width / `hidden md:table-cell` / alignment classes. --}}
<th {{ $attributes->merge(['class' => 'py-2.5 text-[10px] uppercase tracking-wider']) }}>
    <x-squad.sort-header :col="$col" :align="$align" :labelClass="$labelClass">{{ $slot }}</x-squad.sort-header>
</th>
