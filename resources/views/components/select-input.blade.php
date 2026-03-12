@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-700 border border-white/10 text-white focus:border-accent-blue/50 focus:ring-accent-blue rounded-lg shadow-sm text-sm']) }}>
    {{ $slot }}
</select>
