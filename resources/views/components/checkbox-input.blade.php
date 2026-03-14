@props(['disabled' => false])

<input type="checkbox" @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-sm border border-border-strong text-accent-blue focus:ring-accent-blue focus:ring-offset-surface-800']) }}>
