{{--
    Empty placeholder slot for a future tie in a predetermined bracket.
    Matches the outer geometry of <x-cup-tie-card> (two stacked rows of py-2 + 1.25rem
    crest height = ~2.25rem each) so bracket connectors align with real and ghost
    slots interchangeably.
--}}
<div {{ $attributes->merge(['class' => 'rounded-lg overflow-hidden border border-dashed border-border-strong']) }}>
    <div class="px-2.5 py-2 h-[2.25rem]"></div>
    <div class="px-2.5 py-2 h-[2.25rem] border-t border-dashed border-border-default"></div>
</div>
