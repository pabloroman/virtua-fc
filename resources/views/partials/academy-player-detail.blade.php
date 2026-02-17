@php
    /** @var App\Models\Game $game */
    /** @var App\Models\AcademyPlayer $academyPlayer */
    /** @var int $revealPhase */

    $positionDisplay = $academyPlayer->position_display;
    $nationalityFlag = $academyPlayer->nationality_flag;
@endphp

<div class="p-5 md:p-8">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 pb-5 border-b border-slate-200">
        {{-- Left: avatar + player info --}}
        <div class="flex items-start gap-4 min-w-0">
            <img src="/img/default-player.jpg" class="h-24 w-auto md:h-32 md:w-auto rounded-lg border border-slate-200 shrink-0 bg-slate-100" alt="">
            <div class="min-w-0">
                <div class="flex items-center gap-2 mt-1.5">
                    <x-position-badge :position="$academyPlayer->position" />
                    <span class="text-sm font-medium text-slate-600">{{ \App\Support\PositionMapper::toDisplayName($academyPlayer->position) }}</span>
                </div>
                <h3 class="font-bold text-xl md:text-2xl text-slate-900 truncate">{{ $academyPlayer->name }}</h3>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5 text-sm text-slate-500">
                    @if($nationalityFlag)
                        <img src="/flags/{{ $nationalityFlag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm">
                        <span>{{ __('countries.' . $nationalityFlag['name']) }}</span>
                        <span class="text-slate-300">&middot;</span>
                    @endif
                    <span>{{ $academyPlayer->age }} {{ __('app.years') }}</span>
                </div>
                @if($academyPlayer->is_on_loan)
                    <span class="inline-block mt-1.5 text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded font-medium">{{ __('squad.academy_on_loan') }}</span>
                @endif
            </div>
        </div>
        {{-- Right: overall badge + close --}}
        <div class="flex items-start gap-3 shrink-0">
            @if($revealPhase >= 1)
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full flex items-center justify-center text-xl md:text-2xl font-bold
                    @if($academyPlayer->overall >= 80) bg-emerald-500 text-white
                    @elseif($academyPlayer->overall >= 70) bg-lime-500 text-white
                    @elseif($academyPlayer->overall >= 60) bg-amber-500 text-white
                    @else bg-slate-300 text-slate-700
                    @endif">{{ $academyPlayer->overall }}</div>
            @else
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full flex items-center justify-center text-xl md:text-2xl font-bold bg-slate-200 text-slate-400">?</div>
            @endif
            <button onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'player-detail'}))" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100">
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
            <h4 class="text-sm font-semibold text-slate-900 pb-2 border-b border-slate-200 mb-4">{{ __('squad.abilities') }}</h4>

            @if($revealPhase >= 1)
                <div class="space-y-3.5">
                    {{-- Technical --}}
                    @php $val = $academyPlayer->technical_ability; @endphp
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs text-slate-400 uppercase tracking-wide w-20 shrink-0">{{ __('squad.technical_full') }}</span>
                        <div class="flex items-center gap-2.5 flex-1 justify-end">
                            <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-amber-500 @else bg-slate-400 @endif" style="width: {{ $val / 99 * 100 }}%"></div>
                            </div>
                            <span class="text-sm font-semibold tabular-nums w-7 text-right @if($val >= 80) text-green-600 @elseif($val >= 70) text-lime-600 @elseif($val >= 60) text-amber-600 @else text-slate-400 @endif">{{ $val }}</span>
                        </div>
                    </div>
                    {{-- Physical --}}
                    @php $val = $academyPlayer->physical_ability; @endphp
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs text-slate-400 uppercase tracking-wide w-20 shrink-0">{{ __('squad.physical_full') }}</span>
                        <div class="flex items-center gap-2.5 flex-1 justify-end">
                            <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-amber-500 @else bg-slate-400 @endif" style="width: {{ $val / 99 * 100 }}%"></div>
                            </div>
                            <span class="text-sm font-semibold tabular-nums w-7 text-right @if($val >= 80) text-green-600 @elseif($val >= 70) text-lime-600 @elseif($val >= 60) text-amber-600 @else text-slate-400 @endif">{{ $val }}</span>
                        </div>
                    </div>
                    {{-- Average --}}
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <span class="text-xs text-slate-500 uppercase tracking-wide font-semibold">{{ __('squad.overall') }}</span>
                        <span class="text-sm font-semibold tabular-nums
                            @if($academyPlayer->overall >= 80) text-emerald-600
                            @elseif($academyPlayer->overall >= 70) text-lime-600
                            @elseif($academyPlayer->overall >= 60) text-amber-600
                            @else text-slate-500
                            @endif">{{ $academyPlayer->overall }}</span>
                    </div>
                </div>
            @else
                <div class="py-8 text-center">
                    <span class="text-2xl text-slate-300">?</span>
                    <p class="text-xs text-slate-400 mt-2">{{ __('squad.academy_phase_unknown') }}</p>
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div>
            <h4 class="text-sm font-semibold text-slate-900 pb-2 border-b border-slate-200 mb-4">{{ __('app.details') }}</h4>
            <div class="space-y-3">
                @if($revealPhase >= 2)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('game.potential') }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $academyPlayer->potential_range }}</span>
                    </div>
                @else
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('game.potential') }}</span>
                        <span class="text-sm font-semibold text-slate-300">?</span>
                    </div>
                @endif
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('squad.discovered') }}</span>
                    <span class="text-sm font-semibold text-slate-900">{{ $academyPlayer->appeared_at->format('d M Y') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('squad.academy') }}</span>
                    <span class="text-sm font-semibold text-slate-900">{{ trans_choice('squad.academy_seasons', $academyPlayer->seasons_in_academy, ['count' => $academyPlayer->seasons_in_academy]) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    @unless($academyPlayer->is_on_loan)
        <div class="mt-6 pt-4 border-t border-slate-200 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('game.academy.promote', [$game->id, $academyPlayer->id]) }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-sky-200 text-sky-700 bg-sky-50 hover:bg-sky-100 transition-colors min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                    {{ __('squad.promote_to_first_team') }}
                </button>
            </form>
        </div>
    @endunless
</div>
