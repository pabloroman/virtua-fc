@props(['disabled' => false])

<input type="checkbox" @disabled($disabled) {{ $attributes->merge(['class' => 'rounded bg-surface-700 border-white/10 text-accent-blue focus:ring-accent-blue focus:ring-offset-surface-900']) }}>
