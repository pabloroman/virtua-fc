@props(['currentAbility', 'potentialLow' => null, 'potentialHigh' => null, 'projection' => null])

@php
    $max = 99;
    $currentPct = min(100, ($currentAbility / $max) * 100);
    $potLowPct = $potentialLow ? min(100, ($potentialLow / $max) * 100) : 0;
    $potHighPct = $potentialHigh ? min(100, ($potentialHigh / $max) * 100) : 0;
    $projectedAbility = $projection !== null ? $currentAbility + $projection : null;
    $projectedPct = $projectedAbility ? min(100, max(0, ($projectedAbility / $max) * 100)) : null;

    $fillColor = match(true) {
        $currentAbility >= 80 => 'bg-green-500',
        $currentAbility >= 70 => 'bg-lime-500',
        $currentAbility >= 60 => 'bg-amber-500',
        default => 'bg-slate-400',
    };

    $potentialGap = ($potentialHigh && $potentialLow) ? $potentialHigh - $currentAbility : 0;
@endphp

<div class="flex items-center gap-2 min-w-[140px]">
    {{-- Current ability number --}}
    <span class="text-sm font-bold w-6 text-right flex-shrink-0 @if($currentAbility >= 80) text-green-600 @elseif($currentAbility >= 70) text-lime-600 @elseif($currentAbility >= 60) text-amber-600 @else text-slate-500 @endif">{{ $currentAbility }}</span>

    {{-- Bar --}}
    <div class="relative w-full h-2.5 bg-slate-100 rounded-full overflow-hidden flex-grow">
        {{-- Potential range highlight --}}
        @if($potentialLow && $potentialHigh)
        <div class="absolute h-full bg-sky-100 rounded-full" style="left: {{ $potLowPct }}%; width: {{ $potHighPct - $potLowPct }}%"></div>
        @endif

        {{-- Current ability fill --}}
        <div class="absolute h-full {{ $fillColor }} rounded-full" style="width: {{ $currentPct }}%"></div>

        {{-- Projection marker --}}
        @if($projectedPct !== null && $projection != 0)
        <div class="absolute top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full border border-white shadow-sm {{ $projection > 0 ? 'bg-green-500' : 'bg-red-500' }}" style="left: {{ $projectedPct }}%"></div>
        @endif
    </div>

    {{-- Potential ceiling --}}
    @if($potentialHigh)
    <span class="text-xs text-sky-500 font-medium w-5 flex-shrink-0">{{ $potentialHigh }}</span>
    @else
    <span class="text-xs text-slate-300 w-5 flex-shrink-0">?</span>
    @endif
</div>
