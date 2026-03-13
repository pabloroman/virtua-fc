@php
    /** @var App\Models\Game $game */
    /** @var App\Models\AcademyPlayer $academyPlayer */
    /** @var int $revealPhase */

    $positionDisplay = $academyPlayer->position_display;
    $nationalityFlag = $academyPlayer->nationality_flag;
@endphp

<div class="p-5 md:p-8">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 pb-5 border-b border-border-strong">
        {{-- Left: avatar + player info --}}
        <div class="flex items-start gap-4 min-w-0">
            <img src="/img/default-player.jpg" class="h-24 w-auto md:h-32 md:w-auto rounded-lg border border-border-strong shrink-0 bg-surface-700" alt="">
            <div class="min-w-0">
                <div class="flex items-center gap-2 mt-1.5">
                    <x-position-badge :position="$academyPlayer->position" />
                    <span class="text-sm font-medium text-text-secondary">{{ \App\Support\PositionMapper::toDisplayName($academyPlayer->position) }}</span>
                </div>
                <h3 class="font-bold text-xl md:text-2xl text-text-primary truncate">{{ $academyPlayer->name }}</h3>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5 text-sm text-text-muted">
                    @if($nationalityFlag)
                        <img src="/flags/{{ $nationalityFlag['code'] }}.svg" class="w-5 h-4 rounded-sm shadow-xs">
                        <span>{{ __('countries.' . $nationalityFlag['name']) }}</span>
                        <span class="text-text-body">&middot;</span>
                    @endif
                    <span>{{ $academyPlayer->age }} {{ __('app.years') }}</span>
                </div>
                @if($academyPlayer->is_on_loan)
                    <span class="inline-block mt-1.5 text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-sm font-medium">{{ __('squad.academy_on_loan') }}</span>
                @endif
            </div>
        </div>
        {{-- Right: overall badge + close --}}
        <div class="flex items-start gap-3 shrink-0">
            @if($revealPhase >= 1)
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full flex items-center justify-center text-xl md:text-2xl font-bold
                    @if($academyPlayer->overall >= 80) bg-emerald-500 text-white
                    @elseif($academyPlayer->overall >= 70) bg-lime-500 text-white
                    @elseif($academyPlayer->overall >= 60) bg-accent-gold text-white
                    @else bg-slate-300 text-text-body
                    @endif">{{ $academyPlayer->overall }}</div>
            @else
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full flex items-center justify-center text-xl md:text-2xl font-bold bg-slate-200 text-text-secondary">?</div>
            @endif
            <button onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'player-detail'}))" class="p-1 text-text-secondary hover:text-text-secondary rounded-sm hover:bg-surface-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Two columns: Parameters + Details --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 mt-5">

        {{-- Parameters --}}
        <div>
            <h4 class="text-sm font-semibold text-text-primary pb-2 border-b border-border-strong mb-4">{{ __('squad.abilities') }}</h4>

            @if($revealPhase >= 1)
                <div class="space-y-3.5">
                    {{-- Technical --}}
                    @php $val = $academyPlayer->technical_ability; @endphp
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs text-text-secondary uppercase tracking-wide w-20 shrink-0">{{ __('squad.technical_full') }}</span>
                        <div class="flex items-center gap-2.5 flex-1 justify-end">
                            <div class="w-28 h-2 bg-surface-700 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full @if($val >= 80) bg-accent-green @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-accent-gold @else bg-slate-400 @endif" style="width: {{ $val / 99 * 100 }}%"></div>
                            </div>
                            <span class="text-sm font-semibold tabular-nums w-7 text-right @if($val >= 80) text-accent-green @elseif($val >= 70) text-lime-600 @elseif($val >= 60) text-amber-600 @else text-text-secondary @endif">{{ $val }}</span>
                        </div>
                    </div>
                    {{-- Physical --}}
                    @php $val = $academyPlayer->physical_ability; @endphp
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs text-text-secondary uppercase tracking-wide w-20 shrink-0">{{ __('squad.physical_full') }}</span>
                        <div class="flex items-center gap-2.5 flex-1 justify-end">
                            <div class="w-28 h-2 bg-surface-700 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full @if($val >= 80) bg-accent-green @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-accent-gold @else bg-slate-400 @endif" style="width: {{ $val / 99 * 100 }}%"></div>
                            </div>
                            <span class="text-sm font-semibold tabular-nums w-7 text-right @if($val >= 80) text-accent-green @elseif($val >= 70) text-lime-600 @elseif($val >= 60) text-amber-600 @else text-text-secondary @endif">{{ $val }}</span>
                        </div>
                    </div>
                    {{-- Average --}}
                    <div class="flex items-center justify-between pt-3 border-t border-border-default">
                        <span class="text-xs text-text-muted uppercase tracking-wide font-semibold">{{ __('squad.overall') }}</span>
                        <span class="text-sm font-semibold tabular-nums
                            @if($academyPlayer->overall >= 80) text-emerald-600
                            @elseif($academyPlayer->overall >= 70) text-lime-600
                            @elseif($academyPlayer->overall >= 60) text-amber-600
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
        <div>
            <h4 class="text-sm font-semibold text-text-primary pb-2 border-b border-border-strong mb-4">{{ __('app.details') }}</h4>
            <div class="space-y-3">
                @if($revealPhase >= 2)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-text-secondary uppercase tracking-wide">{{ __('game.potential') }}</span>
                        <span class="text-sm font-semibold text-text-primary">{{ $academyPlayer->potential_range }}</span>
                    </div>
                @else
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-text-secondary uppercase tracking-wide">{{ __('game.potential') }}</span>
                        <span class="text-sm font-semibold text-text-body">?</span>
                    </div>
                @endif
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text-secondary uppercase tracking-wide">{{ __('squad.discovered') }}</span>
                    <span class="text-sm font-semibold text-text-primary">{{ $academyPlayer->appeared_at->format('d M Y') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text-secondary uppercase tracking-wide">{{ __('squad.academy') }}</span>
                    <span class="text-sm font-semibold text-text-primary">{{ trans_choice('squad.academy_seasons', $academyPlayer->seasons_in_academy, ['count' => $academyPlayer->seasons_in_academy]) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    @unless($academyPlayer->is_on_loan)
        <div class="mt-6 pt-4 border-t border-border-strong flex flex-wrap gap-2">
            <form method="POST" action="{{ route('game.academy.promote', [$game->id, $academyPlayer->id]) }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-accent-blue/20 text-accent-blue bg-accent-blue/10 hover:bg-accent-blue/10 transition-colors min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                    {{ __('squad.promote_to_first_team') }}
                </button>
            </form>
            <form method="POST" action="{{ route('game.academy.loan', [$game->id, $academyPlayer->id]) }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    {{ __('squad.academy_loan_out') }}
                </button>
            </form>
            <form method="POST" action="{{ route('game.academy.dismiss', [$game->id, $academyPlayer->id]) }}" x-data x-on:submit="if (!confirm('{{ __('squad.academy_dismiss_confirm') }}')) $event.preventDefault()">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-accent-red/20 text-accent-red bg-accent-red/10 hover:bg-red-100 transition-colors min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    {{ __('squad.academy_dismiss') }}
                </button>
            </form>
        </div>
    @endunless
</div>
