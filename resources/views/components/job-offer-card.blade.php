@props([
    'offer',                       // ManagerJobOffer
    'acceptRoute',                 // route name used by the form action
    'acceptParams',                // route parameters (array)
    'highlight' => false,          // true to outline the card (e.g. recommended)
    'lastSeasonPosition' => null,  // int|null — finishing position in the league
                                   // for the just-ended season; hidden when null
    'seasonGoalLabel' => null,     // string|null — translated season-goal label
                                   // expected by the offering club; hidden when null
])

@php
    $team = $offer->team;
    $competition = $offer->competition;
    $flagCode = $team?->flag ?: 'es';
    $flagUrl = \Illuminate\Support\Facades\Storage::disk('assets')->url('flags/' . $flagCode . '.svg');
    $borderClass = $highlight
        ? 'border-accent-blue/60'
        : 'border-border-default';
@endphp

<div class="bg-surface-700 border {{ $borderClass }} rounded-xl overflow-hidden flex flex-col">
    {{-- Header: crest + team name + competition --}}
    <div class="px-5 pt-5 pb-4 flex items-start gap-4">
        @if($team)
            <div class="shrink-0 w-14 h-14 flex items-center justify-center bg-surface-600 rounded-lg overflow-hidden">
                <x-team-crest :team="$team" class="w-12 h-12 object-contain" />
            </div>
        @endif
        <div class="flex-1 min-w-0">
            <div class="font-heading text-base font-semibold text-text-primary truncate">
                {{ $team?->name ?? '—' }}
            </div>
            <div class="mt-1 flex items-center gap-2 text-xs text-text-secondary">
                <img src="{{ $flagUrl }}" alt="{{ $team?->country }}" class="w-4 h-3 rounded-xs shadow-xs" />
                <span class="truncate">{{ $competition ? __($competition->name) : '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Stats: last-season position + season goal --}}
    @if($lastSeasonPosition || $seasonGoalLabel)
        <div class="px-5 pb-5 space-y-1.5 text-sm">
            @if($lastSeasonPosition)
                <div>
                    <span class="font-semibold uppercase tracking-wide text-[10px] text-text-muted">{{ __('manager.league_position') }}:</span>
                    <span class="text-text-primary">{{ $lastSeasonPosition }}</span>
                </div>
            @endif
            @if($seasonGoalLabel)
                <div>
                    <span class="font-semibold uppercase tracking-wide text-[10px] text-text-muted">{{ __('season.target') }}:</span>
                    <span class="text-text-primary">{{ $seasonGoalLabel }}</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Accept CTA --}}
    <div class="px-5 pb-5 mt-auto">
        <form method="POST" action="{{ route($acceptRoute, $acceptParams) }}">
            @csrf
            <x-primary-button color="blue" size="sm" class="w-full">
                {{ __('manager.accept_offer') }}
            </x-primary-button>
        </form>
    </div>
</div>
