@props(['disabled' => false, 'readonly' => false])

<input @disabled($disabled) @readonly($readonly) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}>
