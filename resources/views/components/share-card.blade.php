@props([
    'team',
    'competition',
    'resultLabel',
    'yourRecord',
    'squadHighlights',
    'isChampion' => false,
])

@php
$resultColorMap = [
    'champion'          => ['bg' => '#d97706', 'text' => '#FFFFFF', 'accent' => '#fbbf24'],
    'runner_up'         => ['bg' => '#475569', 'text' => '#FFFFFF', 'accent' => '#94a3b8'],
    'third_place'       => ['bg' => '#c2410c', 'text' => '#FFFFFF', 'accent' => '#fb923c'],
    'semi_finalist'     => ['bg' => '#1d4ed8', 'text' => '#FFFFFF', 'accent' => '#60a5fa'],
    'quarter_finalist'  => ['bg' => '#1e40af', 'text' => '#FFFFFF', 'accent' => '#93c5fd'],
];
$colors = $resultColorMap[$resultLabel] ?? ['bg' => '#334155', 'text' => '#FFFFFF', 'accent' => '#94a3b8'];
$boldPicks = $squadHighlights['bold_picks'] ?? [];
$topScorer = $squadHighlights['top_scorer'] ?? null;
@endphp

{{-- Share Card: self-contained with inline styles for html2canvas --}}
<div style="width: 440px; height: 660px; background: linear-gradient(145deg, {{ $colors['bg'] }}, #0f172a 60%); border-radius: 20px; overflow: hidden; font-family: 'Barlow Semi Condensed', sans-serif; position: relative; color: {{ $colors['text'] }};">

    {{-- Decorative circles --}}
    <div style="position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.06);"></div>
    <div style="position: absolute; bottom: -40px; left: -20px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,0.04);"></div>

    {{-- Content --}}
    <div style="position: relative; z-index: 1; padding: 28px 28px 20px;">

        {{-- Header: Competition --}}
        <div style="text-align: center; margin-bottom: 6px;">
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 3px; color: {{ $colors['accent'] }}; font-weight: 600;">
                {{ __($competition->name ?? 'game.wc2026_name') }}
            </span>
        </div>

        {{-- Team Crest + Name --}}
        <div style="text-align: center; margin-bottom: 16px;">
            <img src="{{ $team->image }}" style="width: 72px; height: auto; aspect-ratio: 4/3; border-radius: 15%; margin: 0 auto 8px; display: block;" alt="{{ $team->name }}" crossorigin="anonymous">
            <div style="font-size: 26px; font-weight: 800; letter-spacing: 0.5px;">{{ $team->name }}</div>
        </div>

        {{-- Result Badge --}}
        <div style="text-align: center; margin-bottom: 20px;">
            <span style="display: inline-block; padding: 5px 20px; background: {{ $colors['accent'] }}; color: {{ $colors['bg'] }}; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; border-radius: 20px;">
                {{ __('season.result_' . $resultLabel) }}
            </span>
        </div>

        {{-- Stats Row --}}
        <div style="display: flex; justify-content: center; gap: 4px; margin-bottom: 22px;">
            @foreach([
                ['value' => $yourRecord['played'], 'label' => __('season.played_abbr')],
                ['value' => $yourRecord['won'], 'label' => __('season.won')],
                ['value' => $yourRecord['drawn'], 'label' => __('season.drawn')],
                ['value' => $yourRecord['lost'], 'label' => __('season.lost')],
                ['value' => $yourRecord['goalsFor'], 'label' => __('season.goals_for')],
                ['value' => $yourRecord['goalsAgainst'], 'label' => __('season.goals_against')],
            ] as $stat)
            <div style="flex: 1; text-align: center; background: rgba(255,255,255,0.08); border-radius: 8px; padding: 8px 4px;">
                <div style="font-size: 20px; font-weight: 800;">{{ $stat['value'] }}</div>
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.5); margin-top: 2px;">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>

        {{-- Top Scorer --}}
        @if($topScorer)
        <div style="background: rgba(255,255,255,0.08); border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; color: {{ $colors['accent'] }}; font-weight: 600; margin-bottom: 2px;">{{ __('season.top_scorer') }}</div>
                <div style="font-size: 15px; font-weight: 700;">{{ $topScorer['name'] }}</div>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 28px; font-weight: 800; color: {{ $colors['accent'] }};">{{ $topScorer['goals'] }}</span>
                <span style="font-size: 10px; color: rgba(255,255,255,0.5); margin-left: 2px;">{{ __('season.goals') }}</span>
            </div>
        </div>
        @endif

        {{-- Bold Picks --}}
        @if(count($boldPicks) > 0)
        <div style="margin-bottom: 16px;">
            <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: {{ $colors['accent'] }}; font-weight: 600; margin-bottom: 8px;">
                {{ __('season.bold_picks') }}
            </div>
            @foreach($boldPicks as $pick)
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 6px 0; {{ !$loop->last ? 'border-bottom: 1px solid rgba(255,255,255,0.06);' : '' }}">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 13px; font-weight: 600;">{{ $pick['name'] }}</span>
                    <span style="font-size: 10px; color: rgba(255,255,255,0.35); background: rgba(255,255,255,0.08); padding: 1px 6px; border-radius: 4px;">{{ $pick['overall'] }} OVR</span>
                </div>
                <div style="font-size: 12px; color: rgba(255,255,255,0.6);">
                    @if($pick['goals'] > 0){{ $pick['goals'] }}G @endif
                    @if($pick['assists'] > 0){{ $pick['assists'] }}A @endif
                    {{ $pick['appearances'] }}{{ __('season.played_abbr') }}
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Footer --}}
        <div style="position: absolute; bottom: 20px; left: 28px; right: 28px; display: flex; align-items: center; justify-content: space-between;">
            <span style="font-size: 16px; font-weight: 800; letter-spacing: 1px; color: rgba(255,255,255,0.3);">VIRTUAFC</span>
            <span style="font-size: 10px; color: rgba(255,255,255,0.2);">virtuafc.com</span>
        </div>
    </div>
</div>
