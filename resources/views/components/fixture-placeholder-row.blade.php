@props(['placeholder'])

<div class="flex items-center gap-3 px-4 py-2.5 opacity-50">
    {{-- Date --}}
    <div class="w-10 shrink-0 text-center">
        <div class="text-[11px] font-medium text-text-body leading-tight">{{ $placeholder->scheduled_date->locale(app()->getLocale())->translatedFormat('d') }}</div>
        <div class="text-[9px] text-text-faint uppercase">{{ $placeholder->scheduled_date->locale(app()->getLocale())->translatedFormat('M') }}</div>
    </div>

    {{-- Round name --}}
    <div class="flex-1 min-w-0">
        <span class="text-xs font-medium text-text-secondary">{{ __($placeholder->round_name) }}</span>
    </div>

    {{-- TBD --}}
    <div class="shrink-0">
        <span class="text-[11px] text-text-faint italic">{{ __('game.tbd') }}</span>
    </div>
</div>
