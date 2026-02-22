@php
    /** @var App\Models\GamePlayer $gp */
    /** @var App\Models\Game $game */
    /** @var bool $isCareerMode */
    $nationalityFlag = $gp->nationality_flag;
    $devStatus = $gp->dev_status;
    $projection = $gp->projection;

    $canManage = $isCareerMode
        && !$gp->isRetiring()
        && !$gp->isLoanedIn($game->team_id)
        && !$gp->hasPreContractAgreement()
        && !$gp->hasRenewalAgreed()
        && !$gp->hasAgreedTransfer()
        && !$gp->hasActiveLoanSearch();
    $isListed = $gp->isTransferListed();
    $canRenew = $isCareerMode && isset($renewalData[$gp->id]);
    $renewal = $renewalData[$gp->id] ?? null;
    $hasActiveNeg = $gp->activeRenewalNegotiation !== null;
@endphp

<div class="bg-slate-50 border-t border-slate-200 p-4 md:p-5" @click.stop>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        {{-- Column 1: Abilities & Condition --}}
        <div>
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide pb-2 border-b border-slate-200 mb-3">{{ __('squad.abilities') }}</h4>
            <div class="space-y-2.5">
                {{-- Technical --}}
                @php $val = $gp->technical_ability; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-500 w-16 shrink-0">{{ __('squad.technical_full') }}</span>
                    <div class="flex items-center gap-2 flex-1 justify-end">
                        <div class="w-24 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-1.5 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-amber-500 @else bg-slate-400 @endif" style="width: {{ $val / 99 * 100 }}%"></div>
                        </div>
                        <span class="text-sm font-semibold tabular-nums w-6 text-right @if($val >= 80) text-green-600 @elseif($val >= 70) text-lime-600 @elseif($val >= 60) text-amber-600 @else text-slate-400 @endif">{{ $val }}</span>
                    </div>
                </div>
                {{-- Physical --}}
                @php $val = $gp->physical_ability; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-500 w-16 shrink-0">{{ __('squad.physical_full') }}</span>
                    <div class="flex items-center gap-2 flex-1 justify-end">
                        <div class="w-24 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-1.5 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 60) bg-amber-500 @else bg-slate-400 @endif" style="width: {{ $val / 99 * 100 }}%"></div>
                        </div>
                        <span class="text-sm font-semibold tabular-nums w-6 text-right @if($val >= 80) text-green-600 @elseif($val >= 70) text-lime-600 @elseif($val >= 60) text-amber-600 @else text-slate-400 @endif">{{ $val }}</span>
                    </div>
                </div>
                {{-- Fitness --}}
                @php $val = $gp->fitness; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-500 w-16 shrink-0">{{ __('squad.fitness_full') }}</span>
                    <div class="flex items-center gap-2 flex-1 justify-end">
                        <div class="w-24 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-1.5 rounded-full @if($val >= 85) bg-green-500 @elseif($val >= 70) bg-lime-500 @elseif($val >= 50) bg-amber-500 @else bg-red-500 @endif" style="width: {{ $val }}%"></div>
                        </div>
                        <span class="text-sm font-semibold tabular-nums w-6 text-right @if($val >= 90) text-green-600 @elseif($val >= 80) text-lime-600 @elseif($val >= 70) text-slate-700 @else text-red-500 @endif">{{ $val }}</span>
                    </div>
                </div>
                {{-- Morale --}}
                @php $val = $gp->morale; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-500 w-16 shrink-0">{{ __('squad.morale_full') }}</span>
                    <div class="flex items-center gap-2 flex-1 justify-end">
                        <div class="w-24 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-1.5 rounded-full @if($val >= 80) bg-green-500 @elseif($val >= 65) bg-lime-500 @elseif($val >= 50) bg-amber-500 @else bg-red-500 @endif" style="width: {{ $val }}%"></div>
                        </div>
                        <span class="text-sm font-semibold tabular-nums w-6 text-right @if($val >= 85) text-green-600 @elseif($val >= 75) text-lime-600 @elseif($val >= 65) text-slate-700 @else text-red-500 @endif">{{ $val }}</span>
                    </div>
                </div>

                {{-- Development & Potential --}}
                <div class="pt-2 border-t border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">{{ __('squad.projection') }}</span>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold
                            @if($devStatus === 'growing') text-green-600
                            @elseif($devStatus === 'peak') text-sky-600
                            @else text-orange-600
                            @endif">
                            @if($devStatus === 'growing')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                {{ __('squad.growing') }}
                                @if($projection > 0)
                                    <span class="text-green-500 ml-0.5">(+{{ $projection }})</span>
                                @endif
                            @elseif($devStatus === 'declining')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                {{ __('squad.declining') }}
                                @if($projection < 0)
                                    <span class="text-orange-500 ml-0.5">({{ $projection }})</span>
                                @endif
                            @else
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                {{ __('squad.peak') }}
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">{{ __('game.potential') }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $gp->potential_range }}</span>
                    </div>
                    {{-- Playing time progress (for growing players) --}}
                    @if($devStatus === 'growing')
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">{{ __('squad.playing_time') }}</span>
                            <span class="text-xs @if($gp->season_appearances >= 15) text-green-600 font-medium @else text-slate-500 @endif">
                                {{ $gp->season_appearances }}/15
                                @if($gp->season_appearances >= 15)
                                    <span class="text-green-500">{{ __('squad.starter_bonus') }}</span>
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Column 2: Profile & Season Stats --}}
        <div>
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide pb-2 border-b border-slate-200 mb-3">{{ __('app.details') }}</h4>
            <div class="space-y-2.5">
                {{-- Bio --}}
                <div class="flex items-center gap-2 text-sm text-slate-700">
                    @if($nationalityFlag)
                        <img src="/flags/{{ $nationalityFlag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm shrink-0">
                    @endif
                    <span>{{ $gp->age }} {{ __('app.years') }}</span>
                    @if($gp->player->height)
                        <span class="text-slate-300">&middot;</span>
                        <span>{{ $gp->player->height }}</span>
                    @endif
                    <span class="text-slate-300">&middot;</span>
                    <span class="text-slate-500">{{ $gp->position_name }}</span>
                </div>

                @if($isCareerMode)
                <div class="pt-2 border-t border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">{{ __('app.value') }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $gp->formatted_market_value }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">{{ __('app.wage') }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $gp->formatted_wage }}{{ __('squad.per_year') }}</span>
                    </div>
                    @if($gp->contract_expiry_year)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">{{ __('app.contract') }}</span>
                        <span class="text-sm font-semibold @if($gp->isContractExpiring($seasonEndDate)) text-red-600 @else text-slate-900 @endif">
                            {{ $gp->contract_expiry_year }}
                            @if($gp->isContractExpiring($seasonEndDate))
                                <span class="text-xs font-normal text-red-500 ml-1">{{ __('squad_v2.expiring') }}</span>
                            @endif
                        </span>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Season Stats --}}
                <div class="pt-2 border-t border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">{{ __('squad.appearances') }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $gp->appearances }}</span>
                    </div>
                    @if($gp->position_group === 'Goalkeeper')
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">{{ __('squad.clean_sheets_full') }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $gp->clean_sheets }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">{{ __('squad.goals_conceded_full') }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $gp->goals_conceded }}</span>
                        </div>
                    @else
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">{{ __('squad.legend_goals') }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $gp->goals }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">{{ __('squad.legend_assists') }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $gp->assists }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">{{ __('squad.bookings') }}</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-3 bg-yellow-400 rounded-sm"></span>
                            <span class="text-sm font-semibold text-slate-700">{{ $gp->yellow_cards }}</span>
                            <span class="w-2 h-3 bg-red-500 rounded-sm ml-1"></span>
                            <span class="text-sm font-semibold text-slate-700">{{ $gp->red_cards }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Column 3: Quick Actions --}}
        <div>
            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide pb-2 border-b border-slate-200 mb-3">{{ __('squad_v2.actions') }}</h4>
            <div class="space-y-2">
                {{-- Status badges (non-actionable states) --}}
                @if($gp->isRetiring())
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-orange-100 text-orange-700">
                        {{ __('squad.retiring') }}
                    </div>
                @elseif($gp->hasPreContractAgreement())
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-red-100 text-red-700">
                        {{ __('squad.leaving_free') }}
                    </div>
                @elseif($gp->hasAgreedTransfer())
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-green-100 text-green-700">
                        {{ __('squad.sale_agreed') }}
                    </div>
                @elseif($gp->hasActiveLoanSearch())
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-sky-100 text-sky-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                        {{ __('squad.loan_searching') }}
                    </div>
                @elseif($gp->isLoanedIn($game->team_id))
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-sky-100 text-sky-700">
                        {{ __('squad.on_loan') }}
                    </div>
                @endif

                {{-- Actionable buttons --}}
                @if($isCareerMode)
                    @if(!$isListed && $canManage)
                        <form method="POST" action="{{ route('game.transfers.list', [$game->id, $gp->id]) }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-sky-200 text-sky-700 bg-sky-50 hover:bg-sky-100 transition-colors min-h-[44px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" /></svg>
                                {{ __('squad.list_for_sale') }}
                            </button>
                        </form>
                    @endif

                    @if($isListed)
                        <form method="POST" action="{{ route('game.transfers.unlist', [$game->id, $gp->id]) }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-red-200 text-red-700 bg-red-50 hover:bg-red-100 transition-colors min-h-[44px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                {{ __('squad.unlist_from_sale') }}
                            </button>
                        </form>
                    @endif

                    @if($canManage)
                        <form method="POST" action="{{ route('game.loans.out', [$game->id, $gp->id]) }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-amber-200 text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors min-h-[44px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                {{ __('squad.loan_out') }}
                            </button>
                        </form>
                    @endif

                    @if($canRenew && $renewal)
                        <x-renewal-modal
                            :game="$game"
                            :game-player="$gp"
                            :renewal-demand="$renewal['demand']"
                            :renewal-midpoint="$renewal['midpoint']"
                            :renewal-mood="$renewal['mood']"
                        />
                    @elseif($hasActiveNeg)
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium
                                {{ $gp->activeRenewalNegotiation->isPending() ? 'bg-amber-100 text-amber-700' : 'bg-orange-100 text-orange-700' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $gp->activeRenewalNegotiation->isPending() ? 'bg-amber-500 animate-pulse' : 'bg-orange-500' }}"></span>
                                {{ $gp->activeRenewalNegotiation->isPending() ? __('transfers.negotiating') : __('transfers.player_countered') }}
                            </span>
                            <a href="{{ route('game.transfers.outgoing', $game->id) }}" class="text-xs text-slate-500 hover:text-slate-700 underline underline-offset-2">
                                {{ __('app.view_details') }} &rarr;
                            </a>
                        </div>
                    @endif
                @endif

                {{-- View Full Profile link --}}
                <button x-data @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gp->id]) }}')" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-slate-50 transition-colors min-h-[44px] mt-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    {{ __('squad_v2.full_profile') }}
                </button>
            </div>
        </div>
    </div>
</div>
