@props(['disabled' => false, 'readonly' => false])

<input @disabled($disabled) @readonly($readonly) {{ $attributes->merge(['class' => 'border-slate-300 focus:border-sky-500 focus:ring-sky-500 rounded-lg shadow-sm text-sm']) }}>
