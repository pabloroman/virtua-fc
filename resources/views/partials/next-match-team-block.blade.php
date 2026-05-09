@php
    /** @var \App\Models\Team $team */
    /** @var ?\App\Models\GameStanding $standing */
    /** @var array<int, string> $form */
    $showStanding = ($showStanding ?? false) && $standing;
    $showForm = $showForm ?? false;
    $position = $standing?->position;
    $ordinal = $position === 1 ? 'st' : ($position === 2 ? 'nd' : ($position === 3 ? 'rd' : 'th'));
@endphp
<div class="flex-1 flex flex-col items-center text-center min-w-0">
    <x-team-crest :team="$team" class="w-14 h-14 md:w-20 md:h-20 mb-2" />
    <h4 class="text-sm md:text-xl font-bold text-text-primary truncate max-w-full">{{ $team->name }}</h4>

    @if($showStanding)
        <div class="text-xs text-text-muted mt-1.5">
            {{ $position }}{{ $ordinal }} &middot; {{ $standing->points }} {{ __('game.pts') }}
        </div>
    @endif

    @if($showForm)
        <div class="flex gap-1 mt-2">
            @forelse($form as $result)
                <span @class([
                    'w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center text-white',
                    'bg-accent-green' => $result === 'W',
                    'bg-slate-500' => $result === 'D',
                    'bg-accent-red' => $result === 'L',
                ])>{{ $result }}</span>
            @empty
                <span class="text-text-secondary text-xs">{{ __('game.no_form') }}</span>
            @endforelse
        </div>
    @endif
</div>
