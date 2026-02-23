@props(['team'])

@php
    $isNational = ($team->type ?? 'club') === 'national';
@endphp

@if($isNational)
<img
    src="{{ $team->image }}"
    style="height: auto; aspect-ratio: 4/3; border-radius: 15%;"
    {{ $attributes->merge(['alt' => $team->name]) }}>
@else
<img
    src="{{ $team->image }}"
    {{ $attributes->merge(['alt' => $team->name]) }}>
@endif
