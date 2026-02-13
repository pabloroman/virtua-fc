@props(['name', 'value' => 0, 'min' => 0])

@php
    $euros = max((int) $value, (int) $min);
@endphp

<div x-data="{
    euros: {{ $euros }},
    min: {{ (int) $min }},
    holdTimer: null,
    holdInterval: null,
    get step() {
        return this.euros >= 1_000_000 ? 100_000 : 10_000;
    },
    get display() {
        return '€ ' + new Intl.NumberFormat('es-ES').format(this.euros);
    },
    get atMin() {
        return this.euros <= this.min;
    },
    increment() {
        this.euros += this.step;
    },
    decrement() {
        const next = this.euros - this.step;
        this.euros = Math.max(next, this.min);
    },
    startHold(fn) {
        fn();
        this.holdTimer = setTimeout(() => {
            this.holdInterval = setInterval(() => fn(), 80);
        }, 400);
    },
    stopHold() {
        clearTimeout(this.holdTimer);
        clearInterval(this.holdInterval);
        this.holdTimer = null;
        this.holdInterval = null;
    }
}" class="inline-flex items-stretch border border-slate-300 rounded-lg overflow-hidden">
    <input type="hidden" name="{{ $name }}" :value="euros">

    {{-- Minus button --}}
    <button type="button"
        :disabled="atMin"
        :class="atMin ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 active:bg-slate-200'"
        class="min-h-[40px] sm:min-h-0 min-w-[40px] flex items-center justify-center bg-slate-50 text-slate-700 font-bold text-lg select-none transition-colors"
        @mousedown.prevent="startHold(() => decrement())"
        @mouseup="stopHold()"
        @mouseleave="stopHold()"
        @touchstart.prevent="startHold(() => decrement())"
        @touchend="stopHold()"
    >&minus;</button>

    {{-- Formatted display --}}
    <input type="text"
        readonly
        :value="display"
        class="min-h-[40px] sm:min-h-0 w-32 text-center text-sm font-semibold text-slate-800 bg-white border-x border-y-0 border-slate-300 outline-none cursor-default focus:outline-none focus:ring-0 focus:border-x"
    >

    {{-- Plus button --}}
    <button type="button"
        class="min-h-[40px] sm:min-h-0 min-w-[40px] flex items-center justify-center bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-700 font-bold text-lg select-none transition-colors"
        @mousedown.prevent="startHold(() => increment())"
        @mouseup="stopHold()"
        @mouseleave="stopHold()"
        @touchstart.prevent="startHold(() => increment())"
        @touchend="stopHold()"
    >+</button>
</div>
