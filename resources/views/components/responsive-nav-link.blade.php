@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-accent-blue text-start text-base font-medium text-text-primary bg-accent-blue/10 focus:outline-hidden focus:text-text-primary focus:bg-accent-blue/20 focus:border-blue-400 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 hover:border-border-strong focus:outline-hidden focus:text-text-primary focus:bg-surface-700 focus:border-border-strong transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
