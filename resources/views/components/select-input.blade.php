@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-700 border border-border-strong text-text-primary focus:border-accent-blue/50 focus:ring-accent-blue rounded-lg shadow-xs text-sm']) }}>
    {{ $slot }}
</select>
