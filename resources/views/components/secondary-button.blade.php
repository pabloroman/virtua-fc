@props(['size' => 'default'])

@php
$sizeClasses = match($size) {
    'xs' => 'px-2.5 py-1 text-xs rounded-md',
    default => 'px-4 py-2 min-h-[44px] sm:min-h-0 text-sm rounded-lg',
};
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => "inline-flex items-center justify-center {$sizeClasses} bg-white border border-slate-300 font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150"]) }}>
    {{ $slot }}
</button>
