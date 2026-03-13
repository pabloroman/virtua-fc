@props(['disabled' => false, 'readonly' => false])

<input @disabled($disabled) @readonly($readonly) {{ $attributes->merge(['class' => 'bg-surface-700 border border-border-strong text-text-primary placeholder-text-muted focus:border-accent-blue/50 focus:ring-accent-blue rounded-lg shadow-xs text-sm']) }}>
