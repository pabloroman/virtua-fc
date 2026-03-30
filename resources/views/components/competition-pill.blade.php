@props(['competition', 'roundName' => null, 'roundNumber' => null, 'short' => false])

@php
    $isPreseason = \App\Support\CompetitionColors::category($competition) === 'preseason';
    $badge = \App\Support\CompetitionColors::badge($competition);
    $label = $isPreseason
        ? __('game.pre_season_friendly')
        : ($short ? $competition->shortName() : __($competition->name ?? 'League'));
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $badge['bg'] }} {{ $badge['text'] }}">
        {{ $label }}
    </span>
    @if($roundName)
        <span class="text-xs text-text-muted">&middot; {{ __($roundName) }}</span>
    @elseif($roundNumber)
        <span class="text-xs text-text-muted">&middot; {{ __('game.matchday_n', ['number' => $roundNumber]) }}</span>
    @endif
</div>
