@props(['name', 'value' => 0, 'min' => 0, 'size' => 'md'])

@php
    $euros = max((int) $value, (int) $min);

    $componentClasses = match($size) {
        'sm' => 'h-[36px]',
        default => 'h-[42px]',
    };
    $btnClasses = match($size) {
        'sm' => 'min-h-[32px] sm:min-h-0 min-w-[32px] text-sm',
        default => 'min-h-[40px] sm:min-h-0 min-w-[40px] text-lg',
    };
    $inputClasses = match($size) {
        'sm' => 'min-h-[32px] sm:min-h-0 w-28 text-xs',
        default => 'min-h-[40px] sm:min-h-0 w-32 text-sm',
    };
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
}" class="inline-flex items-stretch border border-slate-300 rounded-lg overflow-hidden {{ $componentClasses }}">
    <input type="hidden" name="{{ $name }}" :value="euros">

    {{-- Minus button --}}
    <button type="button"
        :disabled="atMin"
        :class="atMin ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 active:bg-slate-200'"
        class="{{ $btnClasses }} flex items-center justify-center bg-slate-50 text-slate-700 font-bold select-none transition-colors"
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
        class="{{ $inputClasses }} text-center font-semibold text-slate-800 bg-white border-x border-y-0 border-slate-300 outline-none cursor-default focus:outline-none focus:ring-0 focus:border-slate-300"
    >

    {{-- Plus button --}}
    <button type="button"
        class="{{ $btnClasses }} flex items-center justify-center bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-700 font-bold select-none transition-colors"
        @mousedown.prevent="startHold(() => increment())"
        @mouseup="stopHold()"
        @mouseleave="stopHold()"
        @touchstart.prevent="startHold(() => increment())"
        @touchend="stopHold()"
    >+</button>
</div>
