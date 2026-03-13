@php
    /** @var App\Models\Game $game */
    /** @var App\Models\AcademyPlayer $academyPlayer */
    /** @var int $revealPhase */

    $positionDisplay = $academyPlayer->position_display;
    $nationalityFlag = $academyPlayer->nationality_flag;

    $overallColor = match(true) {
        $revealPhase < 1 => 'bg-surface-600',
        $academyPlayer->overall >= 80 => 'bg-accent-green',
        $academyPlayer->overall >= 70 => 'bg-lime-500',
        $academyPlayer->overall >= 60 => 'bg-accent-gold',
        default => 'bg-surface-600',
    };
@endphp

{{-- Header --}}
<div class="px-5 py-4 border-b border-border-default flex items-center justify-between">
    <div class="flex items-center gap-3 min-w-0">
        <x-position-badge :position="$academyPlayer->position" />
        <h3 class="font-heading text-lg font-semibold text-text-primary truncate">{{ $academyPlayer->name }}</h3>
    </div>
    <x-icon-button size="sm" onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'player-detail'}))">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </x-icon-button>
</div>

{{-- Player Banner --}}
<div class="px-5 py-4 bg-surface-900/50 border-b border-border-default">
    <div class="flex items-center gap-4">
        <div class="relative shrink-0">
            <img src="/img/default-player.jpg" class="h-20 w-auto md:h-24 rounded-lg border border-border-default bg-surface-700" alt="">
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-muted">
                @if($nationalityFlag)
                    <span class="inline-flex items-center gap-1.5">
                        <img src="/flags/{{ $nationalityFlag['code'] }}.svg" class="w-4 h-3 rounded-sm shadow-xs">
                        {{ __('countries.' . $nationalityFlag['name']) }}
                    </span>
                @endif
                <span>{{ $academyPlayer->age }} {{ __('app.years') }}</span>
            </div>
            <div class="text-[11px] text-text-faint mt-1">{{ \App\Support\PositionMapper::toDisplayName($academyPlayer->position) }}</div>

            @if($academyPlayer->is_on_loan)
                <span class="inline-block mt-2 text-[10px] font-semibold bg-violet-500/10 text-violet-400 px-2 py-0.5 rounded-full">{{ __('squad.academy_on_loan') }}</span>
            @endif
        </div>

        <div class="w-14 h-14 md:w-16 md:h-16 rounded-xl {{ $overallColor }} flex items-center justify-center shrink-0">
            <span class="text-xl md:text-2xl font-bold {{ $revealPhase >= 1 ? 'text-white' : 'text-text-secondary' }}">{{ $revealPhase >= 1 ? $academyPlayer->overall : '?' }}</span>
        </div>
    </div>
</div>

{{-- Content Grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-border-default">

    {{-- Abilities --}}
    <div class="p-5">
        <h4 class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-secondary mb-4">{{ __('squad.abilities') }}</h4>

        @if($revealPhase >= 1)
            <div class="space-y-3">
                <x-stat-bar :label="__('squad.technical_full')" :value="$academyPlayer->technical_ability" />
                <x-stat-bar :label="__('squad.physical_full')" :value="$academyPlayer->physical_ability" />

                <div class="flex items-center justify-between pt-3 border-t border-border-default">
                    <span class="text-[11px] text-text-muted uppercase tracking-wide font-semibold">{{ __('squad.overall') }}</span>
                    <span class="text-xs font-semibold tabular-nums
                        @if($academyPlayer->overall >= 80) text-accent-green
                        @elseif($academyPlayer->overall >= 70) text-lime-500
                        @elseif($academyPlayer->overall >= 60) text-accent-gold
                        @else text-text-muted
                        @endif">{{ $academyPlayer->overall }}</span>
                </div>
            </div>
        @else
            <div class="py-8 text-center">
                <span class="text-2xl text-text-body">?</span>
                <p class="text-xs text-text-secondary mt-2">{{ __('squad.academy_phase_unknown') }}</p>
            </div>
        @endif
    </div>

    {{-- Details --}}
    <div class="p-5">
        <h4 class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-secondary mb-4">{{ __('app.details') }}</h4>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('game.potential') }}</span>
                @if($revealPhase >= 2)
                    <span class="text-xs font-semibold text-text-primary">{{ $academyPlayer->potential_range }}</span>
                @else
                    <span class="text-xs font-semibold text-text-body">?</span>
                @endif
            </div>
            <div class="flex items-center justify-between">
                <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.discovered') }}</span>
                <span class="text-xs font-semibold text-text-primary">{{ $academyPlayer->appeared_at->format('d M Y') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.academy') }}</span>
                <span class="text-xs font-semibold text-text-primary">{{ trans_choice('squad.academy_seasons', $academyPlayer->seasons_in_academy, ['count' => $academyPlayer->seasons_in_academy]) }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Actions --}}
@unless($academyPlayer->is_on_loan)
    <div class="px-5 py-4 border-t border-border-default flex flex-wrap gap-2">
        <form method="POST" action="{{ route('game.academy.promote', [$game->id, $academyPlayer->id]) }}">
            @csrf
            <x-action-button color="blue">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                {{ __('squad.promote_to_first_team') }}
            </x-action-button>
        </form>
        <form method="POST" action="{{ route('game.academy.loan', [$game->id, $academyPlayer->id]) }}">
            @csrf
            <x-action-button color="violet">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                {{ __('squad.academy_loan_out') }}
            </x-action-button>
        </form>
        <form method="POST" action="{{ route('game.academy.dismiss', [$game->id, $academyPlayer->id]) }}" x-data x-on:submit="if (!confirm('{{ __('squad.academy_dismiss_confirm') }}')) $event.preventDefault()">
            @csrf
            <x-action-button color="red">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                {{ __('squad.academy_dismiss') }}
            </x-action-button>
        </form>
    </div>
@endunless
