@props(['disabled' => false, 'readonly' => false])

<input @disabled($disabled) @readonly($readonly) {{ $attributes->merge(['class' => 'bg-surface-700 border border-white/10 text-white placeholder-slate-500 focus:border-accent-blue/50 focus:ring-accent-blue rounded-lg shadow-sm text-sm']) }}>
