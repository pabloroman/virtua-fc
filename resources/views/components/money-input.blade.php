@props(['name' => '', 'value' => 0, 'min' => 0, 'size' => 'md', 'presets' => null])

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

    $presetValues = $presets ?? [];
@endphp

<div x-data="{
    euros: {{ $euros }},
    min: {{ (int) $min }},
    holdTimer: null,
    holdInterval: null,
    presets: @js($presetValues),
    get step() {
        return this.euros >= 1_000_000 ? 100_000 : 10_000;
    },
    get display() {
        return '€ ' + new Intl.NumberFormat('es-ES').format(this.euros);
    },
    get atMin() {
        return this.euros <= this.min;
    },
    formatPreset(v) {
        if (v >= 1_000_000) return (v / 1_000_000) + 'M';
        if (v >= 1_000) return (v / 1_000) + 'K';
        return v.toString();
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
}" x-modelable="euros" {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    <div class="inline-flex items-stretch border border-border-strong rounded-lg overflow-hidden {{ $componentClasses }}">
        @if($name)
            <input type="hidden" name="{{ $name }}" :value="euros">
        @endif

        {{-- Minus button --}}
        <button type="button"
            :disabled="atMin"
            :class="atMin ? 'opacity-40 cursor-not-allowed' : 'hover:bg-surface-600 active:bg-surface-700'"
            class="{{ $btnClasses }} flex items-center justify-center bg-surface-700 text-text-body font-bold select-none transition-colors"
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
            class="{{ $inputClasses }} text-center font-semibold text-text-primary bg-surface-800 border-x border-y-0 border-border-strong outline-hidden cursor-default focus:outline-hidden focus:ring-0 focus:border-border-strong"
        >

        {{-- Plus button --}}
        <button type="button"
            class="{{ $btnClasses }} flex items-center justify-center bg-surface-700 hover:bg-surface-600 active:bg-surface-700 text-text-body font-bold select-none transition-colors"
            @mousedown.prevent="startHold(() => increment())"
            @mouseup="stopHold()"
            @mouseleave="stopHold()"
            @touchstart.prevent="startHold(() => increment())"
            @touchend="stopHold()"
        >+</button>
    </div>

    {{-- Preset buttons --}}
    <template x-if="presets.length > 0">
        <div class="flex flex-wrap gap-1">
            <template x-for="p in presets" :key="p">
                <button type="button"
                    @click="euros = p"
                    :class="euros === p ? 'bg-accent-blue/20 text-accent-blue border-accent-blue/30' : 'bg-surface-700/50 text-text-muted border-border-default hover:bg-surface-700 hover:text-text-secondary'"
                    class="px-2 py-0.5 text-[11px] font-medium rounded border transition-colors"
                    x-text="formatPreset(p)"
                ></button>
            </template>
        </div>
    </template>
</div>
