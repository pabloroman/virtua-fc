@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GamePlayer $gamePlayer */

    $isCareerMode = $game->isCareerMode();
    $isListed = $gamePlayer->isTransferListed();
    $canManage = $isCareerMode
        && !$gamePlayer->isRetiring()
        && !$gamePlayer->isLoanedIn($game->team_id)
        && !$gamePlayer->hasPreContractAgreement()
        && !$gamePlayer->hasRenewalAgreed()
        && !$gamePlayer->hasAgreedTransfer()
        && !$gamePlayer->hasActiveLoanSearch();
    $isTransferWindow = $isCareerMode && $game->isTransferWindowOpen();
    $showActions = $isCareerMode && ($isListed || $canManage);

    $positionDisplay = $gamePlayer->position_display;
    $nationalityFlag = $gamePlayer->nationality_flag;
    $devStatus = $gamePlayer->development_status;

    $devLabels = [
        'growing' => __('squad.growing'),
        'peak' => __('squad.peak'),
        'declining' => __('squad.declining'),
    ];
@endphp

<div class="p-5 md:p-8">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 pb-5 border-b border-slate-200">
        {{-- Left: avatar + player info --}}
        <div class="flex items-start gap-4 min-w-0">
            <img src="/img/player-avatar.svg" class="w-16 h-20 md:w-20 md:h-24 rounded-lg border border-slate-200 shrink-0 bg-slate-100" alt="">
            <div class="min-w-0">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h3 class="font-bold text-xl md:text-2xl text-slate-900 truncate">{{ $gamePlayer->name }}</h3>
                    @if($gamePlayer->number)
                        <span class="text-slate-400 text-lg font-medium">#{{ $gamePlayer->number }}</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5 text-sm text-slate-500">
                    @if($nationalityFlag)
                        <img src="/flags/{{ $nationalityFlag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm">
                        <span>{{ $nationalityFlag['name'] }}</span>
                        <span class="text-slate-300">&middot;</span>
                    @endif
                    <span>{{ $gamePlayer->age }} {{ __('app.years') }}</span>
                </div>
                <div class="flex items-center gap-2 mt-1.5">
                    <x-position-badge :position="$gamePlayer->position" />
                    <span class="text-sm font-medium text-slate-600">{{ $gamePlayer->position_name }}</span>
                    @if($devStatus)
                        <span class="text-slate-300">&middot;</span>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold
                            @if($devStatus === 'growing') text-green-600
                            @elseif($devStatus === 'peak') text-sky-600
                            @else text-orange-600
                            @endif">
                            @if($devStatus === 'growing')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                            @elseif($devStatus === 'declining')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            @else
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                            @endif
                            {{ $devLabels[$devStatus] ?? $devStatus }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
        {{-- Right: overall badge + close --}}
        <div class="flex items-start gap-3 shrink-0">
            <div class="w-14 h-14 md:w-16 md:h-16 rounded-full flex items-center justify-center text-xl md:text-2xl font-bold
                @if($gamePlayer->overall_score >= 80) bg-emerald-500 text-white
                @elseif($gamePlayer->overall_score >= 70) bg-lime-500 text-white
                @elseif($gamePlayer->overall_score >= 60) bg-amber-500 text-white
                @else bg-slate-300 text-slate-700
                @endif">{{ $gamePlayer->overall_score }}</div>
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
            <div class="space-y-3.5">
                {{-- Technical --}}
                @php $val = $gamePlayer->technical_ability; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-400 uppercase tracking-wide w-20 shrink-0">{{ __('squad.technical_full') }}</span>
                    <div class="flex items-center gap-2.5 flex-1 justify-end">
                        <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-2 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-amber-500 @else bg-slate-400 @endif" style="width: {{ $val / 99 * 100 }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums w-7 text-right @if($val >= 80) text-green-600 @elseif($val >= 70) text-lime-600 @elseif($val >= 60) text-amber-600 @else text-slate-400 @endif">{{ $val }}</span>
                    </div>
                </div>
                {{-- Physical --}}
                @php $val = $gamePlayer->physical_ability; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-400 uppercase tracking-wide w-20 shrink-0">{{ __('squad.physical_full') }}</span>
                    <div class="flex items-center gap-2.5 flex-1 justify-end">
                        <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-2 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-amber-500 @else bg-slate-400 @endif" style="width: {{ $val / 99 * 100 }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums w-7 text-right @if($val >= 80) text-green-600 @elseif($val >= 70) text-lime-600 @elseif($val >= 60) text-amber-600 @else text-slate-400 @endif">{{ $val }}</span>
                    </div>
                </div>
                {{-- Fitness --}}
                @php $val = $gamePlayer->fitness; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-400 uppercase tracking-wide w-20 shrink-0">{{ __('squad.fitness_full') }}</span>
                    <div class="flex items-center gap-2.5 flex-1 justify-end">
                        <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-2 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-amber-500 @else bg-slate-400 @endif" style="width: {{ $val }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums w-7 text-right @if($val >= 90) text-green-600 @elseif($val >= 80) text-lime-600 @elseif($val >= 70) text-yellow-600 @else text-red-500 @endif">{{ $val }}</span>
                    </div>
                </div>
                {{-- Morale --}}
                @php $val = $gamePlayer->morale; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-400 uppercase tracking-wide w-20 shrink-0">{{ __('squad.morale_full') }}</span>
                    <div class="flex items-center gap-2.5 flex-1 justify-end">
                        <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-2 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-amber-500 @else bg-slate-400 @endif" style="width: {{ $val }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums w-7 text-right @if($val >= 85) text-green-600 @elseif($val >= 75) text-lime-600 @elseif($val >= 65) text-yellow-600 @else text-red-500 @endif">{{ $val }}</span>
                    </div>
                </div>
                {{-- Average --}}
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <span class="text-xs text-slate-500 uppercase tracking-wide font-semibold">{{ __('squad.overall') }}</span>
                    <span class="text-sm font-bold tabular-nums
                        @if($gamePlayer->overall_score >= 80) text-emerald-600
                        @elseif($gamePlayer->overall_score >= 70) text-lime-600
                        @elseif($gamePlayer->overall_score >= 60) text-amber-600
                        @else text-slate-500
                        @endif">{{ $gamePlayer->overall_score }}</span>
                </div>
            </div>
        </div>

        {{-- Details / Contract --}}
        <div>
            <h4 class="text-sm font-semibold text-slate-900 pb-2 border-b border-slate-200 mb-4">{{ __('app.details') }}</h4>
            <div class="space-y-3">
                @if($isCareerMode)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('app.value') }}</span>
                        <span class="text-sm font-bold text-slate-900">{{ $gamePlayer->formatted_market_value }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('app.wage') }}</span>
                        <span class="text-sm font-bold text-slate-900">{{ $gamePlayer->formatted_wage }}{{ __('squad.per_year') }}</span>
                    </div>
                    @if($gamePlayer->contract_expiry_year)
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('app.contract') }}</span>
                            <span class="text-sm font-bold text-slate-900">{{ $gamePlayer->contract_expiry_year }}</span>
                        </div>
                    @endif
                    <div class="border-t border-slate-100 pt-3"></div>
                @endif
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('game.potential') }}</span>
                    <span class="text-sm font-bold text-slate-900">{{ $gamePlayer->potential_range }}</span>
                </div>
                {{-- Status indicators --}}
                @if($gamePlayer->isInjured())
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('app.status') }}</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                            {{ __('game.injured') }}
                        </span>
                    </div>
                @endif
                @if($gamePlayer->isRetiring())
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 uppercase tracking-wide">{{ __('app.status') }}</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                            {{ __('squad.retiring') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Season Stats --}}
    <div class="mt-5 pt-4 border-t border-slate-100">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-500">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('squad.season_stats') }}</span>
            <span><span class="font-semibold text-slate-700">{{ $gamePlayer->appearances }}</span> {{ __('squad.appearances') }}</span>
            <span><span class="font-semibold text-slate-700">{{ $gamePlayer->goals }}</span> {{ __('squad.legend_goals') }}</span>
            <span><span class="font-semibold text-slate-700">{{ $gamePlayer->assists }}</span> {{ __('squad.legend_assists') }}</span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2 h-3 bg-yellow-400 rounded-sm"></span>
                <span class="font-semibold text-slate-700">{{ $gamePlayer->yellow_cards }}</span>
                <span class="w-2 h-3 bg-red-500 rounded-sm ml-1"></span>
                <span class="font-semibold text-slate-700">{{ $gamePlayer->red_cards }}</span>
            </span>
            @if($gamePlayer->position_group === 'Goalkeeper')
                <span><span class="font-semibold text-slate-700">{{ $gamePlayer->clean_sheets }}</span> {{ __('squad.clean_sheets_full') }}</span>
                <span><span class="font-semibold text-slate-700">{{ $gamePlayer->goals_conceded }}</span> {{ __('squad.goals_conceded_full') }}</span>
            @endif
        </div>
    </div>

    {{-- Actions --}}
    @if($showActions)
        <div class="mt-6 pt-4 border-t border-slate-200 flex flex-wrap gap-2">
            @if(!$isListed && $canManage)
                <form method="POST" action="{{ route('game.transfers.list', [$game->id, $gamePlayer->id]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-sky-200 text-sky-700 bg-sky-50 hover:bg-sky-100 transition-colors min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" /></svg>
                        {{ __('squad.list_for_sale') }}
                    </button>
                </form>
            @endif
            @if($isListed)
                <form method="POST" action="{{ route('game.transfers.unlist', [$game->id, $gamePlayer->id]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-red-200 text-red-700 bg-red-50 hover:bg-red-100 transition-colors min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                        {{ __('squad.unlist_from_sale') }}
                    </button>
                </form>
            @endif
            @if($isTransferWindow && $canManage)
                <form method="POST" action="{{ route('game.loans.out', [$game->id, $gamePlayer->id]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-amber-200 text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                        {{ __('squad.loan_out') }}
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
