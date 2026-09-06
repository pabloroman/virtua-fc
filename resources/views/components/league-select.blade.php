@props([
    'model',            // Alpine expression holding the selected value
    'options',          // Alpine expression for an array of { value, label, flag }
    'label' => null,    // Optional caption rendered above the control
])

@php
    // Stable per-instance id so the trigger can point at its own listbox.
    $uid = 'league-select-' . substr(md5($model . (string) $label), 0, 8);
@endphp

{{--
    A select whose options carry a flag, which a native <select> cannot render.

    $model and $options are Alpine expression names written by the caller, not
    user data, so interpolating them is safe — the @js() rule covers PHP values,
    and the caller passes those through @js() when it builds the options array.
--}}
<div
    x-data="{
        open: false,
        active: 0,
        get items() { return {{ $options }} ?? [] },
        get selected() { return this.items.find(o => o.value === {{ $model }}) },
        openList() {
            this.open = true;
            this.active = Math.max(0, this.items.findIndex(o => o.value === {{ $model }}));
            this.$nextTick(() => this.scrollActiveIntoView());
        },
        close(refocus = true) {
            this.open = false;
            if (refocus) this.$refs.trigger.focus();
        },
        move(step) {
            if (! this.open) { this.openList(); return; }
            this.active = (this.active + step + this.items.length) % this.items.length;
            this.scrollActiveIntoView();
        },
        jumpTo(index) {
            if (! this.open) { this.openList(); }
            this.active = index;
            this.scrollActiveIntoView();
        },
        scrollActiveIntoView() {
            this.$refs.list?.children[this.active]?.scrollIntoView({ block: 'nearest' });
        },
        choose(index) {
            const option = this.items[index];
            if (! option) return;
            {{ $model }} = option.value;
            this.close();
        },
    }"
    class="relative mb-4"
>
    @if($label)
        <span class="block mb-1 text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $label }}</span>
    @endif

    <button
        type="button"
        x-ref="trigger"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open"
        aria-controls="{{ $uid }}"
        @click="open ? close(false) : openList()"
        @keydown.arrow-down.prevent="move(1)"
        @keydown.arrow-up.prevent="move(-1)"
        @keydown.home.prevent="jumpTo(0)"
        @keydown.end.prevent="jumpTo(items.length - 1)"
        @keydown.enter.prevent="open ? choose(active) : openList()"
        @keydown.space.prevent="open ? choose(active) : openList()"
        @keydown.escape.prevent="open && close()"
        {{ $attributes->merge(['class' => 'w-full min-h-[44px] px-3 py-2 flex items-center justify-between gap-2 rounded-lg border border-border-strong bg-surface-700 text-sm font-medium text-text-body hover:bg-surface-600 focus:outline-none focus:ring-2 focus:ring-accent-blue transition-colors']) }}
    >
        <span class="flex items-center gap-2 min-w-0">
            <template x-if="selected?.flag">
                <img class="w-5 h-4 rounded-sm shadow-sm shrink-0" :src="selected.flag" alt="">
            </template>
            <span class="truncate" x-text="selected?.label ?? '—'"></span>
        </span>
        <svg class="w-4 h-4 shrink-0 text-text-muted transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        x-ref="list"
        id="{{ $uid }}"
        role="listbox"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        @click.outside="open = false"
        @keydown.escape.window="open && close()"
        class="absolute left-0 right-0 mt-1 z-30 bg-surface-800 rounded-lg shadow-xl border border-border-strong py-1 max-h-72 overflow-y-auto"
    >
        <template x-for="(option, index) in items" :key="option.value">
            <button
                type="button"
                role="option"
                :aria-selected="{{ $model }} === option.value"
                @click="choose(index)"
                @mouseenter="active = index"
                class="w-full min-h-[44px] px-3 py-2 text-left text-sm flex items-center gap-2 transition-colors"
                :class="{{ $model }} === option.value
                    ? 'bg-accent-blue/10 text-accent-blue font-medium'
                    : (active === index ? 'bg-surface-700 text-text-primary' : 'text-text-secondary')"
            >
                <template x-if="option.flag">
                    <img class="w-5 h-4 rounded-sm shadow-sm shrink-0" :src="option.flag" alt="">
                </template>
                <span class="truncate" x-text="option.label"></span>
                <svg x-show="{{ $model }} === option.value" x-cloak class="w-4 h-4 ml-auto text-accent-blue shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
            </button>
        </template>
    </div>
</div>
