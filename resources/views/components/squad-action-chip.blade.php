@props(['action', 'href' => null])

@php
    /** @var \App\Modules\Squad\Enums\SquadAction|null $action */
    // KEEP is the implicit default — when paired with a role icon it just
    // restates "no action needed", so we hide it and let an empty cell speak
    // for itself. Only state-dependent actions (renew, list, replace, etc.)
    // surface as an alert tag.
    $visible = $action && $action !== \App\Modules\Squad\Enums\SquadAction::KEEP;
    $label = $action?->label();
    $classes = "inline-flex items-center gap-1.5 px-2 py-1 rounded-md border shrink-0 text-[10px] font-semibold uppercase tracking-wider whitespace-nowrap transition-colors";
@endphp

@if($visible)
    @php
        $iconSvg = match($action->value) {
            'play_often' => '<svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>',
            'loan_out' => '<svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>',
            'keep' => '<svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>',
            'renew' => '<svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>',
            'list' => '<svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>',
            'replace' => '<svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>',
            default => '',
        };
    @endphp

    @if($href)
        <a href="{{ $href }}"
           @click.stop
           class="{{ $classes }} hover:brightness-110 cursor-pointer {{ $action->tone() }}">
            {!! $iconSvg !!}
            <span>{{ $label }}</span>
        </a>
    @else
        <span class="{{ $classes }} {{ $action->tone() }}">
            {!! $iconSvg !!}
            <span>{{ $label }}</span>
        </span>
    @endif
@endif
