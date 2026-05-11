@props(['role'])

@php
    /** @var \App\Modules\Squad\Enums\SquadRole $role */
@endphp

<span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wider whitespace-nowrap {{ $role->tone() }}">
    {{ $role->label() }}
</span>
