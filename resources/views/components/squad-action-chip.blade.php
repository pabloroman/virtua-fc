@props(['action'])

@php
    /** @var \App\Modules\Squad\Enums\SquadAction|null $action */
@endphp

@if($action)
<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-[11px] font-semibold whitespace-nowrap {{ $action->tone() }}">
    {{ $action->label() }}
</span>
@endif
