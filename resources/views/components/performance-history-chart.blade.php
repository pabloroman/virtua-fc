@props([
    'seasons' => [],
    'tiersPresent' => [],
])

@php
    if (empty($seasons) || empty($tiersPresent)) {
        return;
    }

    // --- Layout (SVG user units) ---
    // Width scales to container via viewBox; we give each season a fixed slot
    // so long histories get a horizontal scroll container rather than cramming.
    $slotWidth = 56;
    $leftGutter = 44;   // room for tier labels
    $rightGutter = 12;
    $topPadding = 14;
    $bottomGutter = 22; // room for season labels
    $bandHeight = 84;
    $bandGap = 10;

    $bandCount = count($tiersPresent);
    $plotHeight = $bandCount * $bandHeight + max(0, $bandCount - 1) * $bandGap;
    $plotWidth = count($seasons) * $slotWidth;
    $svgWidth = $leftGutter + $plotWidth + $rightGutter;
    $svgHeight = $topPadding + $plotHeight + $bottomGutter;

    // Map tier -> band top Y coordinate (higher tier = higher on chart = smaller Y).
    $tiersAsc = $tiersPresent; // already sorted ascending (tier 1 first)
    $bandTopByTier = [];
    foreach ($tiersAsc as $idx => $tier) {
        $bandTopByTier[$tier] = $topPadding + $idx * ($bandHeight + $bandGap);
    }

    // Convert a (season slot index, position, tier, team_count) into (x, y).
    $pointFor = function (int $slotIndex, int $position, int $tier, int $teamCount) use (
        $leftGutter, $slotWidth, $bandTopByTier, $bandHeight
    ) {
        $x = $leftGutter + $slotWidth * $slotIndex + $slotWidth / 2;
        $bandTop = $bandTopByTier[$tier] ?? $bandTopByTier[array_key_first($bandTopByTier)];
        // Position 1 at top of band, team_count at bottom. Guard div-by-zero.
        $denominator = max(1, $teamCount - 1);
        $normalized = max(0, min(1, ($position - 1) / $denominator));
        $y = $bandTop + $normalized * $bandHeight;
        return [round($x, 2), round($y, 2)];
    };

    // Precompute point coords and enrich season rows with an x/y pair for Alpine.
    $points = [];
    foreach (array_values($seasons) as $i => $row) {
        [$x, $y] = $pointFor($i, $row['position'], $row['tier'], $row['team_count']);
        $points[] = [
            'x' => $x,
            'y' => $y,
            'season' => $row['season'],
            'position' => $row['position'],
            'team_count' => $row['team_count'],
            'league_short_name' => $row['league_short_name'],
            'promoted' => $row['promoted'],
            'relegated' => $row['relegated'],
            'is_current' => $row['is_current'],
            'tier' => $row['tier'],
        ];
    }

    // Segment colour classes by transition type.
    $segmentStrokeClass = function (array $from, array $to): string {
        if ($to['promoted']) return 'stroke-accent-green';
        if ($to['relegated']) return 'stroke-accent-red';
        return 'stroke-accent-blue';
    };

    // Spanish has "Liga" / "Liga 2"; English short names fall through from
    // Competition::shortName(). Band label is the competition short name of
    // the first season found in that tier (all clubs in that tier share it).
    $bandLabelByTier = [];
    foreach ($seasons as $row) {
        if (!isset($bandLabelByTier[$row['tier']])) {
            $bandLabelByTier[$row['tier']] = $row['league_short_name'];
        }
    }
@endphp

<div
    x-data="{ hoveredIndex: null }"
    class="relative w-full overflow-x-auto"
>
    <svg
        viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
        preserveAspectRatio="xMidYMid meet"
        class="block"
        style="min-width: {{ min(640, $svgWidth) }}px; width: 100%; height: auto;"
        xmlns="http://www.w3.org/2000/svg"
    >
        {{-- Tier bands --}}
        @foreach ($tiersAsc as $tier)
            @php $bandTop = $bandTopByTier[$tier]; @endphp
            <rect
                x="{{ $leftGutter }}"
                y="{{ $bandTop }}"
                width="{{ $plotWidth }}"
                height="{{ $bandHeight }}"
                class="fill-surface-700/40 stroke-border-default"
                stroke-width="0.5"
                rx="4"
            />
            {{-- Top-of-band "1st" guideline --}}
            <line
                x1="{{ $leftGutter }}" y1="{{ $bandTop }}"
                x2="{{ $leftGutter + $plotWidth }}" y2="{{ $bandTop }}"
                class="stroke-border-default"
                stroke-width="0.5"
                stroke-dasharray="2,3"
            />
            {{-- Band label (league short name + tier marker) --}}
            <text
                x="{{ $leftGutter - 6 }}"
                y="{{ $bandTop + $bandHeight / 2 }}"
                text-anchor="end"
                dominant-baseline="central"
                class="fill-text-muted font-heading"
                style="font-size: 9px; letter-spacing: 0.08em;"
            >{{ strtoupper($bandLabelByTier[$tier] ?? ('T' . $tier)) }}</text>
            {{-- "1st" hint on the top-left of the band, so readers know
                 why higher-on-band = better regardless of team count. --}}
            <text
                x="{{ $leftGutter - 6 }}"
                y="{{ $bandTop + 4 }}"
                text-anchor="end"
                dominant-baseline="hanging"
                class="fill-text-faint"
                style="font-size: 7px;"
            >1</text>
        @endforeach

        {{-- Segments connecting consecutive seasons --}}
        @for ($i = 1; $i < count($points); $i++)
            @php
                $from = $points[$i - 1];
                $to = $points[$i];
                $class = $segmentStrokeClass($from, $to);
                $dash = $to['is_current'] ? 'stroke-dasharray="4,3"' : '';
            @endphp
            <line
                x1="{{ $from['x'] }}" y1="{{ $from['y'] }}"
                x2="{{ $to['x'] }}" y2="{{ $to['y'] }}"
                class="{{ $class }}"
                stroke-width="2"
                stroke-linecap="round"
                {!! $dash !!}
            />
        @endfor

        {{-- Transition markers: small arrow near the tier boundary --}}
        @foreach ($points as $i => $p)
            @if ($p['promoted'] || $p['relegated'])
                @php
                    $glyph = $p['promoted'] ? '▲' : '▼';
                    $colorClass = $p['promoted'] ? 'fill-accent-green' : 'fill-accent-red';
                @endphp
                <text
                    x="{{ $p['x'] }}"
                    y="{{ $p['y'] - 18 }}"
                    text-anchor="middle"
                    dominant-baseline="central"
                    class="{{ $colorClass }}"
                    style="font-size: 9px;"
                >{{ $glyph }}</text>
            @endif
        @endforeach

        {{-- Data points --}}
        @foreach ($points as $i => $p)
            @php
                $fillClass = $p['is_current'] ? 'fill-surface-800' : 'fill-accent-blue';
                $strokeClass = 'stroke-accent-blue';
            @endphp
            <circle
                cx="{{ $p['x'] }}"
                cy="{{ $p['y'] }}"
                r="4"
                class="{{ $fillClass }} {{ $strokeClass }} transition-all duration-150 cursor-default"
                stroke-width="1.8"
                :r="hoveredIndex === {{ $i }} ? 5.5 : 4"
                @mouseenter="hoveredIndex = {{ $i }}"
                @mouseleave="hoveredIndex = null"
            />
            {{-- Position label above marker --}}
            <text
                x="{{ $p['x'] }}"
                y="{{ $p['y'] - 8 }}"
                text-anchor="middle"
                dominant-baseline="baseline"
                class="fill-text-primary font-heading font-bold pointer-events-none"
                style="font-size: 8.5px;"
            >{{ $p['position'] }}</text>
        @endforeach

        {{-- Season labels on X axis --}}
        @foreach ($points as $i => $p)
            <text
                x="{{ $p['x'] }}"
                y="{{ $svgHeight - 6 }}"
                text-anchor="middle"
                dominant-baseline="alphabetic"
                class="fill-text-muted"
                style="font-size: 8.5px;"
            >{{ $p['season'] }}</text>
        @endforeach
    </svg>

    {{-- One tooltip element per point, toggled via x-show. --}}
    @foreach ($points as $i => $p)
        <div
            x-show="hoveredIndex === {{ $i }}"
            x-cloak
            x-transition.opacity.duration.150ms
            class="absolute left-1/2 -translate-x-1/2 -bottom-1 bg-surface-800/95 border border-border-default text-text-primary text-[10px] px-2.5 py-1.5 rounded-md shadow-lg whitespace-nowrap z-10 flex items-center gap-2"
        >
            <span class="font-semibold">
                {{ $p['season'] }}@if($p['is_current']) {{ __('club.reputation.history.current_suffix') }}@endif
            </span>
            <span class="text-text-muted">{{ $p['league_short_name'] }}</span>
            <span class="text-text-body">{{ $p['position'] }} / {{ $p['team_count'] }}</span>
            @if ($p['promoted'])
                <span class="text-accent-green">▲ {{ __('club.reputation.history.promoted') }}</span>
            @elseif ($p['relegated'])
                <span class="text-accent-red">▼ {{ __('club.reputation.history.relegated') }}</span>
            @endif
        </div>
    @endforeach
</div>
