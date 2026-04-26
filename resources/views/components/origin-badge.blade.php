@props(['player', 'currentSeason' => null])

@php
    $record = $player->careerRecord ?? null;
    $currentSeason = $currentSeason ?? $player->game?->season;
@endphp

@if($record)
    @php
        $isAcademy = $record->joined_from === \App\Models\UserSquadCareerRecord::ORIGIN_ACADEMY;
        $isCurrentSeason = $currentSeason !== null && (string) $record->joined_season === (string) $currentSeason;
        $label = $isAcademy
            ? ($isCurrentSeason ? __('squad.origin_academy') : '')
            : ($record->joined_from ?? '');
        $title = trim(($label !== '' ? $label . ' · ' : '') . __('squad.joined') . ' ' . \App\Models\Game::formatSeason((string) $record->joined_season));
        $classes = $isAcademy
            ? 'bg-accent-green/10 text-accent-green'
            : 'bg-accent-blue/10 text-accent-blue';
    @endphp
    @if($label !== '')
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wide {{ $classes }} shrink-0"
              title="{{ $title }}">
            {{ $label }}
        </span>
    @endif
@endif
