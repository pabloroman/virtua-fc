@props(['team'])

{{-- A loan parent can be null (loaned in from a club outside the game), so
     every caller would otherwise need its own guard. --}}
@if($team)
@php
    $isNational = ($team->type ?? 'club') === 'national';
@endphp

@if($isNational)
{{-- Sizing classes (w-*, shrink-0, drop-shadow) land on the wrapper, which becomes the
     query container; the img fills it and its border scales with the container width
     (cqw) so the outline looks consistently thin at every crest size. --}}
<span {{ $attributes }} style="display: inline-block; container-type: inline-size; line-height: 0;">
<img
    src="{{ $team->image }}"
    alt="{{ $team->name }}"
    style="display: block; width: 100%; height: auto; aspect-ratio: 4/3; object-fit: cover; border-radius: 25% 0 25% 0; border: max(1px, 2.5cqw) solid var(--color-text-body);">
</span>
@else
<img
    src="{{ $team->image }}"
    {{ $attributes->merge(['alt' => $team->name]) }}>
@endif
@endif
