@props(['columns' => []])
{{-- Mobile sort affordance for the explore-player-row tables. On mobile the rows
     collapse most columns into an inline detail line and the sortable <th>s are
     `hidden md:table-cell`, so the per-column headers are unreachable — this strip
     exposes the same sort via <x-squad.sort-pill>, bound to the same ambient
     sortableTable() state. Each item: ['col' => ..., 'label' => ...]. --}}
<div class="md:hidden flex items-center gap-1.5 overflow-x-auto scrollbar-hide mb-3">
    <span class="shrink-0 text-[10px] uppercase tracking-widest font-semibold text-text-muted">{{ __('transfers.sort_by') }}</span>
    @foreach($columns as $c)
        <x-squad.sort-pill :col="$c['col']">{{ $c['label'] }}</x-squad.sort-pill>
    @endforeach
</div>
