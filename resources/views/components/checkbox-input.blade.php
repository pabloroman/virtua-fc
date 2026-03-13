@props(['disabled' => false])

<input type="checkbox" @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-sm bg-surface-700 border-border-strong text-accent-blue focus:ring-accent-blue focus:ring-offset-surface-900']) }}>
