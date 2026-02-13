@props(['color' => 'red'])

@php
$colors = [
    'red' => 'bg-red-600 hover:bg-red-700 focus:ring-red-500 active:bg-red-800',
    'green' => 'bg-green-600 hover:bg-green-700 focus:ring-green-500 active:bg-green-800',
    'sky' => 'bg-sky-600 hover:bg-sky-700 focus:ring-sky-500 active:bg-sky-800',
    'emerald' => 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 active:bg-emerald-800',
    'amber' => 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500 active:bg-amber-800',
];
$colorClasses = $colors[$color] ?? $colors['red'];
@endphp

<button
    :disabled="loading"
    {{ $attributes->merge(['type' => 'submit', 'class' => "inline-flex items-center justify-center px-4 py-2 min-h-[44px] sm:min-h-0 {$colorClasses} border border-transparent rounded-lg font-semibold text-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150"]) }}>
    <span x-show="!loading">{{ $slot }}</span>
    <span x-show="loading" class="p-0.5">
        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </span>
</button>
