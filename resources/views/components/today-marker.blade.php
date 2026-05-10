@props(['date' => null])

<div id="calendar-today" class="flex items-center gap-3 px-4 py-2 scroll-mt-32">
    <div class="flex-1 h-px bg-accent-blue/40"></div>
    <span class="px-2 py-0.5 rounded-full bg-accent-blue/15 text-[10px] font-semibold uppercase tracking-wider text-accent-blue whitespace-nowrap">
        {{ __('game.today') }}@if($date)
            <span class="text-accent-blue/70 font-normal ml-1">&middot; {{ $date->locale(app()->getLocale())->translatedFormat('d M') }}</span>
        @endif
    </span>
    <div class="flex-1 h-px bg-accent-blue/40"></div>
</div>
