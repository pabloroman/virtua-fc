@props([
    'userValues' => [],    // [label => value (0-100)]
    'opponentValues' => [], // [label => value (0-100)]
    'labels' => [],         // [key => translated label]
    'size' => 200,
])

@php
    $axes = array_keys($labels);
    $numAxes = count($axes);
    if ($numAxes < 3) return;

    $cx = $size / 2;
    $cy = $size / 2;
    $radius = ($size / 2) - 28; // Leave room for labels

    // Calculate point positions for each axis
    $angleStep = (2 * M_PI) / $numAxes;
    // Start from top (-π/2) so first axis points up
    $startAngle = -M_PI / 2;

    $getPoint = function (int $index, float $value) use ($cx, $cy, $radius, $angleStep, $startAngle) {
        $angle = $startAngle + ($index * $angleStep);
        $r = ($value / 100) * $radius;
        return [
            round($cx + $r * cos($angle), 2),
            round($cy + $r * sin($angle), 2),
        ];
    };

    // Build polygon points for user and opponent
    $userPoints = [];
    $opponentPoints = [];
    $labelPositions = [];

    foreach ($axes as $i => $key) {
        $uv = $userValues[$key] ?? 0;
        $ov = $opponentValues[$key] ?? 0;
        $userPoints[] = $getPoint($i, $uv);
        $opponentPoints[] = $getPoint($i, $ov);

        // Label position (slightly beyond the outer ring)
        $angle = $startAngle + ($i * $angleStep);
        $labelR = $radius + 16;
        $labelPositions[] = [
            'x' => round($cx + $labelR * cos($angle), 2),
            'y' => round($cy + $labelR * sin($angle), 2),
            'label' => $labels[$key],
            'userValue' => $uv,
            'opponentValue' => $ov,
            'anchor' => abs(cos($angle)) < 0.01 ? 'middle' : (cos($angle) > 0 ? 'start' : 'end'),
        ];
    }

    $toPolygon = fn (array $points) => implode(' ', array_map(fn ($p) => "{$p[0]},{$p[1]}", $points));

    // Grid rings at 25%, 50%, 75%, 100%
    $rings = [25, 50, 75, 100];
@endphp

<div
    x-data="{
        hoveredAxis: null,
        userValues: @js($userValues),
        opponentValues: @js($opponentValues),
    }"
    class="relative"
>
    <svg
        viewBox="0 0 {{ $size }} {{ $size }}"
        class="w-full max-w-[280px] mx-auto"
        xmlns="http://www.w3.org/2000/svg"
    >
        {{-- Grid rings --}}
        @foreach ($rings as $ring)
            @php
                $ringPoints = [];
                for ($i = 0; $i < $numAxes; $i++) {
                    $ringPoints[] = $getPoint($i, $ring);
                }
            @endphp
            <polygon
                points="{{ $toPolygon($ringPoints) }}"
                fill="none"
                stroke="#e2e8f0"
                stroke-width="{{ $ring === 100 ? 1 : 0.5 }}"
            />
        @endforeach

        {{-- Axis lines --}}
        @foreach ($axes as $i => $key)
            @php
                $end = $getPoint($i, 100);
            @endphp
            <line
                x1="{{ $cx }}" y1="{{ $cy }}"
                x2="{{ $end[0] }}" y2="{{ $end[1] }}"
                stroke="#e2e8f0"
                stroke-width="0.5"
            />
        @endforeach

        {{-- Opponent polygon (behind) --}}
        <polygon
            points="{{ $toPolygon($opponentPoints) }}"
            fill="rgba(239, 68, 68, 0.1)"
            stroke="rgba(239, 68, 68, 0.6)"
            stroke-width="1.5"
        />

        {{-- User polygon (front) --}}
        <polygon
            points="{{ $toPolygon($userPoints) }}"
            fill="rgba(14, 165, 233, 0.15)"
            stroke="rgba(14, 165, 233, 0.8)"
            stroke-width="1.5"
        />

        {{-- Data points - user --}}
        @foreach ($userPoints as $i => $point)
            <circle
                cx="{{ $point[0] }}" cy="{{ $point[1] }}" r="2.5"
                fill="#0ea5e9"
                class="transition-all duration-150"
                :r="hoveredAxis === {{ $i }} ? 3.5 : 2.5"
            />
        @endforeach

        {{-- Data points - opponent --}}
        @foreach ($opponentPoints as $i => $point)
            <circle
                cx="{{ $point[0] }}" cy="{{ $point[1] }}" r="2"
                fill="#ef4444"
                class="transition-all duration-150"
                :r="hoveredAxis === {{ $i }} ? 3 : 2"
            />
        @endforeach

        {{-- Axis labels --}}
        @foreach ($labelPositions as $i => $pos)
            <text
                x="{{ $pos['x'] }}"
                y="{{ $pos['y'] }}"
                text-anchor="{{ $pos['anchor'] }}"
                dominant-baseline="central"
                class="fill-slate-500 transition-colors duration-150 cursor-default select-none"
                :class="hoveredAxis === {{ $i }} ? 'fill-slate-900 font-semibold' : 'fill-slate-500'"
                style="font-size: 7.5px;"
                @mouseenter="hoveredAxis = {{ $i }}"
                @mouseleave="hoveredAxis = null"
            >{{ $pos['label'] }}</text>
        @endforeach

        {{-- Invisible hover zones on each axis for easier interaction --}}
        @foreach ($axes as $i => $key)
            @php
                $end = $getPoint($i, 100);
            @endphp
            <line
                x1="{{ $cx }}" y1="{{ $cy }}"
                x2="{{ $end[0] }}" y2="{{ $end[1] }}"
                stroke="transparent"
                stroke-width="14"
                @mouseenter="hoveredAxis = {{ $i }}"
                @mouseleave="hoveredAxis = null"
                class="cursor-default"
            />
        @endforeach
    </svg>

    {{-- Tooltip on hover --}}
    @foreach ($labelPositions as $i => $pos)
        <div
            x-show="hoveredAxis === {{ $i }}"
            x-cloak
            x-transition.opacity.duration.150ms
            class="absolute left-1/2 -translate-x-1/2 bottom-0 bg-slate-800/95 text-white text-[10px] px-2.5 py-1.5 rounded-md shadow-lg whitespace-nowrap z-10 flex items-center gap-2.5"
        >
            <span class="font-medium">{{ $pos['label'] }}</span>
            <span class="flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-sky-400 inline-block"></span>
                {{ $pos['userValue'] }}
            </span>
            <span class="flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block"></span>
                {{ $pos['opponentValue'] }}
            </span>
        </div>
    @endforeach
</div>
