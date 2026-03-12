@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-accent-blue text-start text-base font-medium text-white bg-accent-blue/10 focus:outline-none focus:text-white focus:bg-accent-blue/20 focus:border-blue-400 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-slate-400 hover:text-white hover:bg-surface-700 hover:border-slate-600 focus:outline-none focus:text-white focus:bg-surface-700 focus:border-slate-600 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
