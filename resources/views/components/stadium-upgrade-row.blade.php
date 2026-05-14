@props([
    'state'          => 'available_cash',
    'actionable'     => false,
    'modal'          => null,
    'label'          => '',
    'title'          => '',
    'costLabel'      => null,
    'durationLabel'  => null,
    'lockedReason'   => null,
])

@php
    // Green = reachable (cash or loan); we don't distinguish the financing
    // route on the row itself — the modal handles that choice.
    $borderClass = match ($state) {
        'available_cash', 'available_loan'            => 'border-l-accent-green',
        'locked', 'locked_affordability',
        'locked_reputation'                           => 'border-l-accent-gold',
        default                                       => 'border-l-border-default',
    };

    // Subtitle: cost + duration when available, locked reason when blocked,
    // "En obra" when an active project is in flight.
    $subtitle = match (true) {
        $state === 'in_progress'              => __('club.stadium.upgrades.status_in_progress'),
        $lockedReason !== null                => $lockedReason,
        $costLabel && $durationLabel          => $costLabel.' · '.$durationLabel,
        $costLabel                            => $costLabel,
        default                               => null,
    };
@endphp

<button
    type="button"
    @if(! $actionable) disabled @endif
    @if($modal) x-on:click="$dispatch('open-modal', '{{ $modal }}')" @endif
    class="group relative flex items-center gap-4 w-full text-left p-4 rounded-lg border border-border-strong border-l-4 {{ $borderClass }} bg-surface-700 enabled:hover:bg-surface-600 disabled:cursor-not-allowed {{ $state === 'in_progress' ? 'opacity-60' : '' }} transition-colors"
>
    <div class="flex-1 min-w-0">
        <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ $label }}</div>
        <div class="font-heading text-lg font-semibold text-text-primary">{{ $title }}</div>
        @if($subtitle)
            <div class="mt-1 text-sm text-text-muted tabular-nums">{{ $subtitle }}</div>
        @endif
    </div>

    <div class="shrink-0 text-sm font-semibold tabular-nums">
        @if($actionable)
            <span class="text-accent-blue group-hover:text-accent-blue/80 transition-colors">{{ __('club.stadium.upgrades.cta_planificar') }}</span>
        @elseif($state === 'in_progress')
            <span class="text-text-faint">{{ __('club.stadium.upgrades.status_in_progress') }}</span>
        @else
            <span class="text-text-faint">{{ __('club.stadium.upgrades.status_locked') }}</span>
        @endif
    </div>
</button>
