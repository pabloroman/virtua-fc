@props(['disabled' => false])

<input type="checkbox" @disabled($disabled) {{ $attributes->merge(['class' => 'rounded border-slate-300 text-sky-600 focus:ring-sky-500']) }}>
