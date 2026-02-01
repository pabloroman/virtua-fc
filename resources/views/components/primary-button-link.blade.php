<a {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-white uppercase tracking-wide hover:bg-red-700 focus:bg-red-700 active:bg-red-900 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</a>
