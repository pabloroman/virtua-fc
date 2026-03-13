@props([
    'model',          // Alpine expression for the current value (used for comparison)
    'set' => null,    // Alpine variable to set on click (defaults to model)
    'options',        // Alpine array variable name containing { value, label, tooltip? }
    'columns' => 3,   // Grid columns: 2, 3, or 4
    'callback' => null, // Extra JS to run on click (e.g. "updateAutoLineup()")
    'summaryField' => null, // Property name to show summary text below (e.g. "summary" or "tooltip")
])

@php
    $setVar = $set ?? $model;
    $gridClass = match ((int) $columns) {
        2 => 'grid-cols-2',
        4 => 'grid-cols-2 sm:grid-cols-4',
        default => 'grid-cols-3',
    };
    $clickExpr = "{$setVar} = option.value";
    if ($callback) {
        $clickExpr .= "; {$callback}";
    }
@endphp

<div class="grid {{ $gridClass }} gap-1.5">
    <template x-for="option in {{ $options }}" :key="option.value">
        <button type="button"
            @click="{{ $clickExpr }}"
            class="px-2 py-1.5 text-xs font-medium rounded-lg border-2 transition-all duration-150 min-h-[44px]"
            :class="{{ $model }} === option.value
                ? 'bg-accent-blue/10 text-accent-blue border-accent-blue/30 shadow-xs'
                : 'bg-surface-700 text-text-secondary border-border-strong hover:border-white/20 hover:text-text-primary'"
            x-text="option.label"
            x-tooltip="option.tooltip"
        ></button>
    </template>
</div>

@if($summaryField)
<p class="mt-1 text-[10px] text-text-muted leading-relaxed"
   x-text="{{ $options }}.find(o => o.value === {{ $model }})?.{{ $summaryField }}"></p>
@endif
