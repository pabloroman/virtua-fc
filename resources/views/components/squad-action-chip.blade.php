@props(['action', 'href' => null])

@php
    /** @var \App\Modules\Squad\Enums\SquadAction|null $action */
    $classes = "inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] font-semibold whitespace-nowrap transition-colors";
@endphp

@if($action)
    @if($href)
        <a href="{{ $href }}"
           @click.stop
           class="{{ $classes }} hover:brightness-110 {{ $action->tone() }}">
            {{ $action->label() }}
        </a>
    @else
        <span class="{{ $classes }} {{ $action->tone() }}">
            {{ $action->label() }}
        </span>
    @endif
@endif
