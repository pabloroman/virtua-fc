@props([
    'match',
    'tournamentMode' => false,
    'compact' => false,
])

@php
    $venue = $match->venueName() ?? '';
    $date = $match->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y');
@endphp

@if($compact)
    {{-- Narrow dashboard column: abbreviate the competition so the pill never
         wraps, and give the date its own slot so it can't be truncated away
         by a long venue name (which gets its own line below). --}}
    <div class="flex flex-col gap-1.5">
        <div class="flex items-center justify-between gap-2">
            @if($tournamentMode)
                <span class="text-xs font-medium text-text-secondary truncate">{{ __($match->round_name ?? '') }}</span>
            @else
                <x-competition-pill :competition="$match->competition" :round-name="$match->round_name" :round-number="$match->round_number" :abbrev="true" class="min-w-0" />
            @endif
            <span class="text-xs text-text-muted shrink-0">{{ $date }}</span>
        </div>
        @if($venue !== '')
            <span class="text-xs text-text-muted truncate">{{ $venue }}</span>
        @endif
    </div>
@else
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        @if($tournamentMode)
            <span class="text-xs font-medium text-text-secondary">
                {{ __($match->round_name ?? '') }}
            </span>
        @else
            <x-competition-pill :competition="$match->competition" :round-name="$match->round_name" :round-number="$match->round_number" />
        @endif
        <span class="text-xs text-text-muted truncate">
            {{ $venue }} &middot; {{ $date }}
        </span>
    </div>
@endif
