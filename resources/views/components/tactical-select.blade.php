@props([
    'model',          // Alpine expression for the current value
    'options',        // Alpine array variable name containing { value, label, tooltip? }
    'callback' => null, // Extra JS to run on selection (e.g. "updateAutoLineup()")
    'label',          // Display label for the dropdown
    'summaryField' => null, // Property name to show summary text below (e.g. "summary")
])

@php
    $selectExpr = "{$model} = option.value";
    if ($callback) {
        $selectExpr .= "; {$callback}";
    }
    $uid = 'ts_' . md5($model);
@endphp

<div x-data="{ open: false }" class="relative">
    {{-- Trigger --}}
    <button
        type="button"
        @click="open = !open"
        @keydown.escape.window="open = false"
        class="w-full h-9 px-3 flex items-center justify-between gap-2 rounded-lg border border-border-strong bg-surface-700 text-sm font-medium text-text-body hover:border-white/20 hover:bg-surface-600 transition-colors"
    >
        <span class="truncate" x-text="{{ $options }}.find(o => o.value === {{ $model }})?.label || '—'"></span>
        <svg class="w-4 h-4 shrink-0 text-text-muted transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        @click.outside="open = false"
        class="absolute left-0 right-0 mt-1 z-30 bg-surface-800 rounded-lg shadow-xl border border-border-strong py-1 max-h-60 overflow-y-auto"
    >
        <template x-for="option in {{ $options }}" :key="'{{ $uid }}-' + option.value">
            <button
                type="button"
                @click="{{ $selectExpr }}; open = false"
                class="w-full px-3 py-2 text-left text-sm transition-colors flex items-center justify-between gap-2"
                :class="{{ $model }} === option.value
                    ? 'bg-accent-blue/10 text-accent-blue font-medium'
                    : 'text-text-secondary hover:bg-surface-700 hover:text-text-primary'"
            >
                <span x-text="option.label"></span>
                <svg x-show="{{ $model }} === option.value" x-cloak class="w-4 h-4 text-accent-blue shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
            </button>
        </template>
    </div>

    @if($summaryField)
    <p class="mt-1 text-[10px] text-text-muted leading-relaxed"
       x-text="{{ $options }}.find(o => o.value === {{ $model }})?.{{ $summaryField }}"></p>
    @endif
</div>
