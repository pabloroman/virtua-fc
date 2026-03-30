@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GamePlayer $gamePlayer */

    $isCareerMode = $game->isCareerMode();
    $isListed = $gamePlayer->isTransferListed();
    $canManage = $isCareerMode
        && !$gamePlayer->isRetiring()
        && !$gamePlayer->isLoanedIn($game->team_id)
        && !$gamePlayer->isLoanedOut($game->team_id)
        && !$gamePlayer->hasPreContractAgreement()
        && !$gamePlayer->hasRenewalAgreed()
        && !$gamePlayer->hasAgreedTransfer()
        && !$gamePlayer->hasActiveLoanSearch();
    $isTransferWindow = $isCareerMode && $game->isTransferWindowOpen();
    $showActions = $isCareerMode && ($isListed || $canManage);

    $positionDisplay = $gamePlayer->position_display;
    $nationalityFlag = $gamePlayer->nationality_flag;
    $devStatus = $gamePlayer->developmentStatus($game->current_date);

    $devLabels = [
        'growing' => __('squad.growing'),
        'peak' => __('squad.peak'),
        'declining' => __('squad.declining'),
    ];

    $overallColor = match(true) {
        $gamePlayer->overall_score >= 80 => 'bg-accent-green',
        $gamePlayer->overall_score >= 70 => 'bg-lime-500',
        $gamePlayer->overall_score >= 60 => 'bg-accent-gold',
        default => 'bg-surface-600',
    };
@endphp

{{-- Header --}}
<div class="px-5 py-4 border-b border-border-default flex items-center justify-between">
    <div class="flex items-center gap-3 min-w-0">
        <x-position-badge :position="$gamePlayer->position" />
        <h3 class="font-heading text-lg font-semibold text-text-primary truncate">{{ $gamePlayer->name }}</h3>
        @if($gamePlayer->number)
            <span class="text-sm text-text-muted font-medium">#{{ $gamePlayer->number }}</span>
        @endif
    </div>
    <x-icon-button size="sm" onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'player-detail'}))">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </x-icon-button>
</div>

{{-- Player Banner --}}
<div class="px-5 py-4 bg-surface-900/50 border-b border-border-default">
    <div class="flex items-center gap-4">
        {{-- Avatar --}}
        <div class="relative shrink-0">
            <img src="{{ Storage::disk('assets')->url('img/default-player.jpg') }}" class="h-20 w-auto md:h-24 rounded-lg border border-border-default bg-surface-700" alt="">
        </div>

        {{-- Info --}}
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-muted">
                @if($nationalityFlag)
                    <span class="inline-flex items-center gap-1.5">
                        <img src="{{ Storage::disk('assets')->url('flags/' . $nationalityFlag['code'] . '.svg') }}" class="w-4 h-3 rounded-sm shadow-xs">
                        {{ __('countries.' . $nationalityFlag['name']) }}
                    </span>
                @endif
                @if($gamePlayer->team && $isCareerMode)
                    <span class="inline-flex items-center gap-1.5">
                        <x-team-crest :team="$gamePlayer->team" class="w-4 h-4" />
                        {{ $gamePlayer->team->name }}
                    </span>
                @endif
                <span>{{ $gamePlayer->age($game->current_date) }} {{ __('app.years') }}@if($gamePlayer->player->height) · {{ $gamePlayer->player->height }}@endif</span>
            </div>
            <div class="text-[11px] text-text-faint mt-1">{{ $gamePlayer->position_name }}</div>

            {{-- Status badges --}}
            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                @if($gamePlayer->isInjured())
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-accent-red/10 text-accent-red">{{ __('game.injured') }}</span>
                @endif
                @if($gamePlayer->isRetiring())
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-accent-orange/10 text-accent-orange">{{ __('squad.retiring') }}</span>
                @endif
                @if($isListed)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-accent-blue/10 text-accent-blue">{{ __('squad.listed') }}</span>
                @endif
            </div>
        </div>

        {{-- Overall score --}}
        <div class="w-14 h-14 md:w-16 md:h-16 rounded-xl {{ $overallColor }} flex items-center justify-center shrink-0">
            <span class="text-xl md:text-2xl font-bold text-white">{{ $gamePlayer->overall_score }}</span>
        </div>
    </div>
</div>

{{-- Content Grid --}}
<div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-border-default">

    {{-- Abilities --}}
    <div class="p-5">
        <h4 class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-secondary mb-4">{{ __('squad.abilities') }}</h4>
        <div class="space-y-3">
            <x-stat-bar :label="__('squad.technical_full')" :value="$gamePlayer->technical_ability" />
            <x-stat-bar :label="__('squad.physical_full')" :value="$gamePlayer->physical_ability" />
            <x-stat-bar :label="__('squad.fitness_full')" :value="$gamePlayer->fitness" :max="100" />
            <x-stat-bar :label="__('squad.morale_full')" :value="$gamePlayer->morale" :max="100" />

            @if($devStatus)
                <div class="flex items-center justify-between pt-3 border-t border-border-default">
                    <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.projection') }}</span>
                    <span class="inline-flex items-center gap-1 text-xs font-semibold
                        @if($devStatus === 'growing') text-accent-green
                        @elseif($devStatus === 'peak') text-accent-blue
                        @else text-accent-orange
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
                </div>
            @endif

            <div class="flex items-center justify-between">
                <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('game.potential') }}</span>
                <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->potential_range }}</span>
            </div>
        </div>
    </div>

    {{-- Details / Contract --}}
    <div class="p-5">
        <h4 class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-secondary mb-4">{{ __('app.details') }}</h4>
        <div class="space-y-3">
            @if($isCareerMode)
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('app.value') }}</span>
                    <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->formatted_market_value }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('app.wage') }}</span>
                    <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->formatted_wage }}{{ __('squad.per_year') }}</span>
                </div>
                @if($gamePlayer->contract_expiry_year)
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('app.contract') }}</span>
                        <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->contract_expiry_year }}</span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Season Stats --}}
    <div class="p-5">
        <h4 class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-secondary mb-4">{{ __('squad.season_stats') }}</h4>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.appearances') }}</span>
                <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->appearances }}</span>
            </div>
            @if($gamePlayer->position_group === 'Goalkeeper')
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.clean_sheets_full') }}</span>
                    <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->clean_sheets }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.goals_conceded_full') }}</span>
                    <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->goals_conceded }}</span>
                </div>
            @else
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.legend_goals') }}</span>
                    <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->goals }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.legend_assists') }}</span>
                    <span class="text-xs font-semibold text-text-primary">{{ $gamePlayer->assists }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-[11px] text-text-muted uppercase tracking-wide">{{ __('squad.bookings') }}</span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-3 bg-yellow-400 rounded-xs"></span>
                    <span class="text-xs font-semibold text-text-body">{{ $gamePlayer->yellow_cards }}</span>
                    <span class="w-2 h-3 bg-accent-red rounded-xs ml-1"></span>
                    <span class="text-xs font-semibold text-text-body">{{ $gamePlayer->red_cards }}</span>
                </span>
            </div>
        </div>
    </div>
</div>

{{-- Actions --}}
@if($showActions || $canRenew || $renewalNegotiation || $renewalCooldown)
    <div class="px-5 py-4 border-t border-border-default flex flex-wrap items-center gap-2">
        @if(!$isListed && $canManage)
            <form method="POST" action="{{ route('game.transfers.list', [$game->id, $gamePlayer->id]) }}">
                @csrf
                <x-action-button color="blue">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" /></svg>
                    {{ __('squad.list_for_sale') }}
                </x-action-button>
            </form>
        @endif
        @if($isListed)
            <form method="POST" action="{{ route('game.transfers.unlist', [$game->id, $gamePlayer->id]) }}">
                @csrf
                <x-action-button color="red">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                    {{ __('squad.unlist_from_sale') }}
                </x-action-button>
            </form>
        @endif
        @if($canManage)
            <form method="POST" action="{{ route('game.loans.out', [$game->id, $gamePlayer->id]) }}">
                @csrf
                <x-action-button color="amber">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    {{ __('squad.loan_out') }}
                </x-action-button>
            </form>
        @endif
        @if($canRelease ?? false)
            <div x-data="{ showReleaseConfirm: false }">
                <x-action-button color="red" type="button" @click="showReleaseConfirm = true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" /></svg>
                    {{ __('squad.release_player') }}
                </x-action-button>

                <template x-teleport="body">
                    <div x-show="showReleaseConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div x-show="showReleaseConfirm" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="showReleaseConfirm = false" class="fixed inset-0 bg-black/80"></div>
                        <div x-show="showReleaseConfirm" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-surface-800 rounded-xl shadow-xl max-w-sm w-full p-6 z-10" @keydown.escape.window="showReleaseConfirm = false">
                            <h3 class="text-lg font-semibold text-text-primary mb-3">{{ __('squad.release_confirm_title') }}</h3>
                            <p class="text-sm text-text-secondary mb-4">{{ __('squad.release_confirm_message', ['player' => $gamePlayer->name]) }}</p>

                            <div class="space-y-2 mb-5 p-3 bg-surface-700/50 rounded-lg">
                                @if($gamePlayer->contract_until)
                                    @php
                                        $remainingYears = max(0, round($game->current_date->floatDiffInYears($gamePlayer->contract_until), 1));
                                    @endphp
                                    <div class="flex justify-between text-sm">
                                        <span class="text-text-muted">{{ __('squad.release_remaining_contract') }}</span>
                                        <span class="font-semibold text-text-primary">{{ __('squad.release_years_remaining', ['years' => $remainingYears]) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-muted">{{ __('squad.release_severance_label') }}</span>
                                    <span class="font-semibold text-accent-red">{{ \App\Support\Money::format($severance) }}</span>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <x-secondary-button @click="showReleaseConfirm = false" class="flex-1">
                                    {{ __('app.cancel') }}
                                </x-secondary-button>
                                <form method="POST" action="{{ route('game.squad.release', [$game->id, $gamePlayer->id]) }}" class="flex-1">
                                    @csrf
                                    <x-danger-button class="w-full">
                                        {{ __('squad.release_confirm_button') }}
                                    </x-danger-button>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        @endif
        @if($canRenew || $renewalNegotiation?->isCountered())
            @php
                $posDisp = $gamePlayer->position_display;
                $renewalDetail = \Illuminate\Support\Js::from([
                    'playerName' => $gamePlayer->name,
                    'negotiateUrl' => route('game.negotiate.renewal', [$game->id, $gamePlayer->id]),
                    'playerInfo' => [
                        'age' => $gamePlayer->age($game->current_date),
                        'wage' => $gamePlayer->formatted_wage,
                        'tec' => $gamePlayer->technical_ability,
                        'fis' => $gamePlayer->physical_ability,
                        'position' => $posDisp['abbreviation'],
                        'positionBg' => $posDisp['bg'],
                        'positionText' => $posDisp['text'],
                    ],
                ]);
            @endphp
            <x-action-button color="green" type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-negotiation', { detail: {{ $renewalDetail }} }))">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                {{ $renewalNegotiation?->isCountered() ? __('transfers.chat_continue') : __('squad.renew') }}
            </x-action-button>
        @elseif($renewalCooldown)
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-surface-700 text-text-muted border border-border-default min-h-[44px]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ __('transfers.renewal_cooldown_short') }}
            </div>
        @endif
    </div>
@endif
