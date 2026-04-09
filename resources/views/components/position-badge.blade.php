@props(['position' => null, 'group' => null, 'size' => 'md', 'tooltip' => null, 'variant' => 'primary'])

@php
    if ($position) {
        $positionDisplay = $variant === 'secondary'
            ? \App\Support\PositionMapper::getSecondaryPositionDisplay($position)
            : \App\Support\PositionMapper::getPositionDisplay($position);
    } elseif ($group) {
        $colors = $variant === 'secondary'
            ? \App\Support\PositionMapper::getSecondaryColorsForGroup($group)
            : \App\Support\PositionMapper::getColorsForGroup($group);
        $positionDisplay = [
            'abbreviation' => \App\Support\PositionMapper::getGroupAbbreviation($group),
            ...$colors,
        ];
    } else {
        $positionDisplay = ['abbreviation' => '?', 'bg' => 'bg-surface-700/500', 'text' => 'text-white', 'ring' => ''];
    }

    $sizeClasses = match($size) {
        'sm' => 'w-5 h-5 text-[10px]',
        'md' => 'w-7 h-7 text-xs',
        'lg' => 'px-2 py-0.5 text-xs',
        default => 'w-7 h-7 text-xs',
    };

    $ringClass = $positionDisplay['ring'] ?? '';
@endphp

<span
    @if($tooltip) x-data="" x-tooltip.raw="{{ $tooltip }}" @endif
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center {$sizeClasses} -skew-x-12 font-semibold {$positionDisplay['bg']} {$positionDisplay['text']} {$ringClass}"]) }}
>
    <span class="skew-x-12">{{ $positionDisplay['abbreviation'] }}</span>
</span>
