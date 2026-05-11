@props(['advisories'])

@php
    /** @var array<int, \App\Modules\Squad\Services\Advisory> $advisories */
@endphp

@if(empty($advisories))
    <div class="px-4 py-4 text-[12px] text-text-muted">
        {{ __('planner.advisory_empty') }}
    </div>
@else
    <ul class="p-4 space-y-2">
        @foreach($advisories as $advisory)
            <li class="flex items-start gap-2 text-[12px] leading-snug px-2.5 py-2 rounded-md border {{ $advisory->tone() }}">
                <span class="mt-1 w-1.5 h-1.5 rounded-full bg-current opacity-70 shrink-0"></span>
                <span>{{ $advisory->message }}</span>
            </li>
        @endforeach
    </ul>
@endif
