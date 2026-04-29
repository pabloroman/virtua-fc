@props([
    /** @var array<int, array<string, mixed>> */
    'areas',
    /** Optional Alpine state name; when provided, sold/fill come from JS. */
    'alpine' => null,
    /** Show price next to the area label. */
    'showPrice' => true,
])

@php
    // Stand-position taxonomy. Each slug maps to one or more anchors on the
    // schematic (a slug can render in multiple positions, e.g. `lateral` on
    // both short ends when no fondos exist).
    //
    // The pitch is drawn landscape (wider than tall), with the halfway line
    // vertical. So:
    //   - `north` / `south` are the long sides (touchlines): tribuna + lateral
    //   - `east_*` / `west_*` are the short ends (behind goals): fondos
    //   - corners sit above the long sides at top-left / top-right
    $slugs = array_column($areas, 'slug');
    $hasFondos = in_array('fondo_norte', $slugs, true) || in_array('fondo_sur', $slugs, true);
    $hasLateral = in_array('lateral', $slugs, true)
        || in_array('lateral_alta', $slugs, true)
        || in_array('lateral_baja', $slugs, true);

    $anchors = [
        'general'       => ['south'],
        'fondo_norte'   => ['east_inner'],
        'fondo_sur'     => ['west_inner'],
        'tribuna'       => ['north'],
        'tribuna_alta'  => ['north'],   // outer (rendered first, top of stack)
        'tribuna_baja'  => ['north'],   // inner (rendered second, closer to pitch)
        'lateral'       => ['south'],
        'lateral_alta'  => ['south'],
        'lateral_baja'  => ['south'],
        'vip'           => ['corner_tr'],
        'palco'         => ['corner_tl'],
    ];

    // No fondos → lateral takes the short ends instead of a long side.
    if (! $hasFondos) {
        $anchors['lateral']      = ['east_inner', 'west_inner'];
        $anchors['lateral_alta'] = ['east_outer', 'west_outer'];
        $anchors['lateral_baja'] = ['east_inner', 'west_inner'];
    }

    // Tier 1 (only general + tribuna): general wraps three sides so the
    // schematic doesn't look half-empty.
    if (! $hasLateral && ! $hasFondos && in_array('general', $slugs, true)) {
        $anchors['general'] = ['south', 'east_inner', 'west_inner'];
    }

    $byAnchor = [];
    foreach ($areas as $idx => $area) {
        foreach ((array) ($anchors[$area['slug']] ?? ['south']) as $anchor) {
            $byAnchor[$anchor][] = ['index' => $idx] + $area;
        }
    }

    // Total stadium capacity for share-based sizing. Each block is sized by
    // its own capacity share (not the anchor's combined share) so multiple
    // areas in the same anchor stack at proportional sizes.
    $totalCapacity = max(1, array_sum(array_column($areas, 'capacity')));

    $palette = [
        'general'        => 'bg-accent-blue/15 border-accent-blue/40 text-accent-blue',
        'fondo_norte'    => 'bg-accent-blue/15 border-accent-blue/40 text-accent-blue',
        'fondo_sur'      => 'bg-accent-blue/15 border-accent-blue/40 text-accent-blue',
        'tribuna'        => 'bg-accent-gold/15 border-accent-gold/40 text-accent-gold',
        'tribuna_alta'   => 'bg-accent-gold/10 border-accent-gold/30 text-accent-gold',
        'tribuna_baja'   => 'bg-accent-gold/20 border-accent-gold/50 text-accent-gold',
        'lateral'        => 'bg-accent-green/15 border-accent-green/40 text-accent-green',
        'lateral_alta'   => 'bg-accent-green/10 border-accent-green/30 text-accent-green',
        'lateral_baja'   => 'bg-accent-green/20 border-accent-green/50 text-accent-green',
        'vip'            => 'bg-accent-red/20 border-accent-red/50 text-accent-red',
        'palco'          => 'bg-accent-red/30 border-accent-red/60 text-accent-red',
    ];

    // Render a single area block. Always shows label + capacity; when an
    // Alpine state name is passed, sold/fill numbers come from the live
    // prediction (via x-text) so the same component drives both the
    // editable preview and the locked read-only view.
    //
    // Emits raw HTML via echo: this closure runs inside a @php block, where
    // Blade does NOT compile `{{ }}` or `@if` directives — so the markup must
    // be assembled in plain PHP.
    $renderBlock = function (array $area, string $sizeMode) use ($palette, $alpine, $showPrice, $totalCapacity) {
        $slug = $area['slug'];
        $idx = $area['index'];
        $colour = $palette[$slug] ?? 'bg-surface-700 border-border-default text-text-secondary';
        $label = __('club.stadium.season_tickets.area.' . $slug);
        $capacity = (int) $area['capacity'];
        $price = (int) ($area['price_cents'] ?? $area['baseline_price_cents']);
        $sold = (int) ($area['sold'] ?? 0);
        $fillRate = (float) ($area['fill_rate'] ?? 0);

        $sharePct = $capacity / $totalCapacity;
        $style = match ($sizeMode) {
            'height' => 'min-height: ' . max(30, round($sharePct * 220)) . 'px;',
            'width' => 'min-width: ' . max(40, round($sharePct * 240)) . 'px;',
            default => '',
        };

        // Wrap nullish-coalescing in parens before calling .toLocaleString
        // — `.toLocaleString` binds tighter than `??`, so without parens the
        // formatter only runs on the fallback branch and live values render
        // unformatted.
        $alpineSold = $alpine ? "(({$alpine}.areas[{$idx}]?.sold) ?? 0).toLocaleString('es-ES')" : null;
        $alpineFill = $alpine ? "Math.round((({$alpine}.areas[{$idx}]?.fill_rate) ?? 0) * 100)" : null;
        $alpinePrice = $alpine ? "{$alpine}.formatPrice({$alpine}.prices[{$idx}] ?? 0)" : null;

        $capacityFmt = number_format($capacity);

        $priceHtml = '';
        if ($showPrice) {
            $priceInner = $alpine
                ? '<span x-text="' . e($alpinePrice) . '"></span>'
                : '€ ' . number_format($price / 100, 0, ',', '.');
            $priceHtml = '<div class="text-[10px] mt-0.5 opacity-90">' . $priceInner . '</div>';
        }

        $fillBarHtml = $alpine
            ? '<div class="h-full bg-current rounded-full" :style="`width: ${' . $alpineFill . '}%`"></div>'
            : '<div class="h-full bg-current rounded-full" style="width: ' . min(100, (int) round($fillRate * 100)) . '%"></div>';

        $soldHtml = $alpine
            ? '<span x-text="' . e($alpineSold) . '"></span>'
            : number_format($sold);

        echo '<div class="relative border rounded-md px-2 py-1.5 flex flex-col justify-center ' . e($colour) . '" style="' . e($style) . '">'
            . '<div class="flex items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-wider">'
            . '<span class="truncate">' . e($label) . '</span>'
            . '<span class="text-[9px] opacity-80">' . $capacityFmt . '</span>'
            . '</div>'
            . $priceHtml
            . '<div class="mt-1 h-1 bg-surface-900/40 rounded-full overflow-hidden">' . $fillBarHtml . '</div>'
            . '<div class="mt-0.5 text-[9px] opacity-70">' . $soldHtml . ' / ' . $capacityFmt . '</div>'
            . '</div>';
    };

    // South stacks alta-then-baja by data order; visually we want baja on top
    // (closer to the pitch above) and alta below it, so reverse for south.
    $southAreas = array_reverse($byAnchor['south'] ?? []);
@endphp

<div class="relative w-full max-w-2xl mx-auto" {{ $attributes }}>
    {{-- Corner badges: palco top-left, vip top-right --}}
    @if(!empty($byAnchor['corner_tl']))
        <div class="absolute top-0 left-0 z-10 w-20 sm:w-24">
            @foreach($byAnchor['corner_tl'] as $a) @php $renderBlock($a, 'none'); @endphp @endforeach
        </div>
    @endif
    @if(!empty($byAnchor['corner_tr']))
        <div class="absolute top-0 right-0 z-10 w-20 sm:w-24">
            @foreach($byAnchor['corner_tr'] as $a) @php $renderBlock($a, 'none'); @endphp @endforeach
        </div>
    @endif

    {{-- Rows are `auto` (not 1fr) so the middle row sizes itself to the
         pitch's aspect-derived height; otherwise grid-row stretching would
         override `aspect-ratio` on the pitch element. --}}
    <div class="grid gap-1.5 items-stretch" style="grid-template-columns: auto 1fr auto; grid-template-rows: auto auto auto;">

        {{-- Top row: empty | north stand | empty --}}
        <div></div>
        <div class="space-y-1">
            @foreach($byAnchor['north'] ?? [] as $a) @php $renderBlock($a, 'height'); @endphp @endforeach
        </div>
        <div></div>

        {{-- Middle row: west stack | pitch | east stack --}}
        <div class="flex flex-col gap-1 w-24 sm:w-28">
            @foreach($byAnchor['west_outer'] ?? [] as $a) @php $renderBlock($a, 'width'); @endphp @endforeach
            @foreach($byAnchor['west_inner'] ?? [] as $a) @php $renderBlock($a, 'width'); @endphp @endforeach
        </div>

        {{-- Pitch (decorative). Real-world football pitch ratio is ~105 × 68 m
             (height/width ≈ 0.65); we use 5/3 ≈ 0.6 so it stays stable
             regardless of how tall the side stacks grow. `self-center` opts
             out of grid row-stretching — without it the row's height could
             override `aspect-ratio` and squash/stretch the pitch. --}}
        <div class="relative self-center w-full bg-accent-green/25 border border-accent-green/60 rounded-md aspect-[5/3] flex items-center justify-center">
            <div class="absolute inset-2 border-2 border-accent-green/40 rounded-sm"></div>
            <div class="absolute left-1/2 top-2 bottom-2 -translate-x-1/2 w-px bg-accent-green/40"></div>
            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-10 h-10 border-2 border-accent-green/40 rounded-full"></div>
            <span class="text-[9px] uppercase tracking-widest text-accent-green/70 font-semibold relative z-1">{{ __('club.stadium.season_tickets.pitch') }}</span>
        </div>

        <div class="flex flex-col gap-1 w-24 sm:w-28">
            @foreach($byAnchor['east_outer'] ?? [] as $a) @php $renderBlock($a, 'width'); @endphp @endforeach
            @foreach($byAnchor['east_inner'] ?? [] as $a) @php $renderBlock($a, 'width'); @endphp @endforeach
        </div>

        {{-- Bottom row: empty | south stand | empty --}}
        <div></div>
        <div class="space-y-1">
            @foreach($southAreas as $a) @php $renderBlock($a, 'height'); @endphp @endforeach
        </div>
        <div></div>

    </div>
</div>
