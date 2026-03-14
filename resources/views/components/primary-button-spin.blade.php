@props(['color' => 'blue', 'size' => 'default', 'loading' => 'loading'])

@php
$colors = [
    'blue' => 'bg-accent-blue hover:bg-blue-600 focus:ring-accent-blue active:bg-blue-700',
    'red' => 'bg-accent-red hover:bg-red-500 focus:ring-accent-red active:bg-red-700',
    'green' => 'bg-accent-green hover:bg-green-600 focus:ring-accent-green active:bg-green-700',
    'amber' => 'bg-accent-gold hover:bg-amber-600 focus:ring-accent-gold active:bg-amber-700',
];
$colorClasses = $colors[$color] ?? $colors['blue'];

$sizeClasses = match($size) {
    'xs' => 'px-2.5 py-1 text-xs rounded-md',
    default => 'px-4 py-2 min-h-[44px] text-sm rounded-lg',
};
@endphp

<button
    :disabled="{{ $loading }}"
    {{ $attributes->merge(['type' => 'submit', 'class' => "inline-flex items-center justify-center {$sizeClasses} {$colorClasses} border border-transparent font-semibold text-white uppercase tracking-wider focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150"]) }}>
    <span x-show="!{{ $loading }}">{{ $slot }}</span>
    <span x-show="{{ $loading }}" class="p-0.5">
        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </span>
</button>
