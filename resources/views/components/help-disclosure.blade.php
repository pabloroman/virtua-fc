@props(['label' => null])

{{-- Collapsible help panel. Pairs with <x-help-toggle> (auto-binds to helpOpen). --}}
{{-- If no `trigger` slot is provided, the default toggle is rendered inline. --}}
<div x-data="{ helpOpen: false }" {{ $attributes }}>
    @isset($trigger)
        {{ $trigger }}
    @else
        <x-help-toggle :label="$label" />
    @endisset

    <div x-show="helpOpen" x-transition x-cloak class="mt-3 bg-surface-800 border border-border-default rounded-lg p-4 text-sm">
        {{ $slot }}
    </div>
</div>
