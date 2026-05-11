@props([
    'offer',           // ManagerJobOffer
    'acceptRoute',     // route name used by the form action
    'acceptParams',    // route parameters (array)
    'highlight' => false, // true to outline the card (e.g. recommended)
])

@php
    $team = $offer->team;
    $competition = $offer->competition;
    $clubProfile = $team?->clubProfile;
    $reputationLevel = $offer->target_reputation_level
        ?? ($clubProfile?->reputation_level ?? \App\Models\ClubProfile::REPUTATION_LOCAL);
    $countryCode = strtolower($team?->country ?? 'es');
    $flagUrl = \Illuminate\Support\Facades\Storage::disk('assets')->url('flags/' . $countryCode . '.svg');
    $borderClass = $highlight
        ? 'border-accent-blue/60'
        : 'border-border-default';
@endphp

<div class="bg-surface-700 border {{ $borderClass }} rounded-xl overflow-hidden flex flex-col">
    <div class="px-5 py-4 flex items-start gap-4">
        @if($team)
            <div class="shrink-0 w-14 h-14 flex items-center justify-center bg-surface-800 rounded-lg overflow-hidden">
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
            <div class="mt-2">
                <span class="inline-block px-2 py-0.5 text-[10px] uppercase tracking-widest font-semibold bg-surface-800 text-text-secondary rounded">
                    {{ __('finances.reputation.' . $reputationLevel) }}
                </span>
            </div>
        </div>
    </div>

    <div class="px-5 pb-4 mt-auto">
        <form method="POST" action="{{ route($acceptRoute, $acceptParams) }}">
            @csrf
            <x-action-button color="blue" class="w-full justify-center">
                {{ __('manager.accept_offer') }}
            </x-action-button>
        </form>
    </div>
</div>
