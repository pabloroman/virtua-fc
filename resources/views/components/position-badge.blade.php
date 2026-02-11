@props(['position' => null, 'abbreviation' => null, 'size' => 'md', 'tooltip' => null])

@php
    if ($abbreviation) {
        $positionDisplay = [
            'abbreviation' => $abbreviation,
            ...\App\Support\PositionMapper::getColors($abbreviation),
        ];
    } else {
        $positionDisplay = \App\Support\PositionMapper::getPositionDisplay($position);
    }

    $sizeClasses = match($size) {
        'sm' => 'w-5 h-5 text-[10px]',
        'md' => 'w-8 h-8 text-xs',
        'lg' => 'px-2 py-0.5 text-xs',
        default => 'w-8 h-8 text-xs',
    };
@endphp

<span
    @if($tooltip) x-data="" x-tooltip.raw="{{ $tooltip }}" @endif
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center {$sizeClasses} -skew-x-12 font-bold {$positionDisplay['bg']} {$positionDisplay['text']}"]) }}
>
    <span class="skew-x-12">{{ $positionDisplay['abbreviation'] }}</span>
</span>
