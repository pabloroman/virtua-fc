@props(['loyalty'])

{{-- Fan support / loyalty panel. Loyalty (0–100) drives, with reputation, how
     full the stadium gets on match days. Shared by the reputation hub and the
     stadium page sidebar. --}}
<x-section-card :title="__('club.stadium.fan_base')">
    <div class="px-5 py-4">
        <div class="flex items-baseline justify-between">
            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.current_loyalty') }}</div>
            <span class="font-heading text-lg font-bold text-text-primary">{{ $loyalty }}<span class="text-xs font-normal text-text-muted"> / 100</span></span>
        </div>
        <div class="w-full h-2 bg-surface-600 rounded-full overflow-hidden mt-1.5">
            <div class="h-full rounded-full bg-accent-blue" style="width: {{ max(0, min(100, $loyalty)) }}%"></div>
        </div>
        <p class="text-xs text-text-muted mt-4 leading-relaxed">{{ __('club.stadium.fan_base_help') }}</p>
    </div>
</x-section-card>
