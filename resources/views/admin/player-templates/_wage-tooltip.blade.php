<div x-data="{ open: false }" class="relative inline-block">
    <button type="button" @click="open = !open" @click.outside="open = false"
            class="text-text-muted hover:text-accent-blue transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
    </button>
    <div x-show="open" x-cloak x-transition.opacity
         class="absolute z-30 bottom-full left-1/2 -translate-x-1/2 mb-2 w-72 p-3 bg-surface-700 border border-border-default rounded-lg shadow-lg text-xs text-text-secondary">
        <div class="font-semibold text-text-primary mb-1.5">{{ __('admin.wage_tooltip_title') }}</div>
        <div class="space-y-1.5">
            <div>
                <span class="text-text-muted">{{ __('admin.wage_tooltip_tiers') }}</span>
                <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 mt-0.5 text-[11px]">
                    <span>€100M+</span><span class="text-right">17.5%</span>
                    <span>€50–100M</span><span class="text-right">15%</span>
                    <span>€20–50M</span><span class="text-right">12.5%</span>
                    <span>€10–20M</span><span class="text-right">11%</span>
                    <span>€5–10M</span><span class="text-right">10%</span>
                    <span>€2–5M</span><span class="text-right">9%</span>
                    <span>&lt;€2M</span><span class="text-right">8%</span>
                </div>
            </div>
            <div class="border-t border-border-default pt-1.5">
                <span class="text-text-muted">{{ __('admin.wage_tooltip_age') }}</span>
                <div class="text-[11px] mt-0.5">
                    17–22: &times;0.4–0.9 &middot;
                    23–31: &times;1.0 &middot;
                    32+: &times;1.3–7.0
                </div>
            </div>
            <div class="border-t border-border-default pt-1.5 text-[11px] text-text-muted">
                {{ __('admin.wage_tooltip_variance') }}
            </div>
        </div>
        {{-- Arrow --}}
        <div class="absolute top-full left-1/2 -translate-x-1/2 w-2 h-2 bg-surface-700 border-r border-b border-border-default rotate-45 -mt-1"></div>
    </div>
</div>
