@props(['game', 'preContractOffers', 'agreedPreContracts', 'pendingRenewals', 'renewalEligiblePlayers', 'renewalDemands'])

@php
    $hasPreContractOffers = $preContractOffers->isNotEmpty();
    $hasAgreedPreContracts = $agreedPreContracts->isNotEmpty();
    $hasPendingRenewals = $pendingRenewals->isNotEmpty();
    $hasExpiringContracts = $renewalEligiblePlayers->isNotEmpty();
    $hasAnything = $hasPreContractOffers || $hasAgreedPreContracts || $hasPendingRenewals || $hasExpiringContracts;
@endphp

@if($hasAnything)
<div x-data="{ open: false }" class="mb-6">
    {{-- Summary bar --}}
    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 rounded-lg border transition-colors
        {{ $hasPreContractOffers ? 'bg-amber-50 border-amber-200 hover:bg-amber-100' : ($hasExpiringContracts ? 'bg-slate-50 border-slate-200 hover:bg-slate-100' : 'bg-green-50 border-green-200 hover:bg-green-100') }}">
        <div class="flex flex-wrap items-center gap-2 md:gap-4 text-sm">
            @if($hasPreContractOffers)
                <span class="flex items-center gap-1.5 font-medium text-amber-700">
                    <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                    {{ $preContractOffers->count() }} {{ trans_choice('squad.pre_contract_offers_count', $preContractOffers->count()) }}
                </span>
            @endif
            @if($hasAgreedPreContracts)
                <span class="flex items-center gap-1.5 font-medium text-red-700">
                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                    {{ $agreedPreContracts->count() }} {{ __('squad.leaving_on_free') }}
                </span>
            @endif
            @if($hasExpiringContracts)
                <span class="flex items-center gap-1.5 font-medium text-slate-700">
                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                    {{ $renewalEligiblePlayers->count() }} {{ __('squad.expiring_contracts') }}
                </span>
            @endif
            @if($hasPendingRenewals)
                <span class="flex items-center gap-1.5 font-medium text-green-700">
                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                    {{ $pendingRenewals->count() }} {{ __('squad.renewals_agreed') }}
                </span>
            @endif
        </div>
        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Expandable detail section --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="mt-2 border rounded-lg divide-y divide-slate-100 overflow-hidden">

        {{-- Pre-Contract Offers (being poached) --}}
        @foreach($preContractOffers as $offer)
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 px-4 py-3 bg-amber-50">
            <div class="flex items-center gap-3">
                <img src="{{ $offer->offeringTeam->image }}" class="w-8 h-8">
                <div>
                    <span class="font-medium text-slate-900">{{ $offer->gamePlayer->player->name }}</span>
                    <span class="text-slate-400 mx-1">&larr;</span>
                    <span class="text-sm text-slate-600">{{ $offer->offeringTeam->name }}</span>
                    <div class="text-xs text-slate-500">
                        {{ $offer->gamePlayer->position }} &middot; {{ $offer->gamePlayer->age }} {{ __('app.years') }} &middot;
                        {{ __('squad.expires_in_days', ['days' => $offer->days_until_expiry]) }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-amber-600 mr-2">{{ __('squad.free_transfer') }}</span>
                <form method="post" action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}">
                    @csrf
                    <x-primary-button color="amber" size="sm">{{ __('squad.let_go') }}</x-primary-button>
                </form>
                <form method="post" action="{{ route('game.transfers.reject', [$game->id, $offer->id]) }}">
                    @csrf
                    <x-secondary-button type="submit" size="sm">{{ __('app.reject') }}</x-secondary-button>
                </form>
            </div>
        </div>
        @endforeach

        {{-- Players Leaving (agreed pre-contracts) --}}
        @foreach($agreedPreContracts as $transfer)
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 px-4 py-3 bg-red-50">
            <div class="flex items-center gap-3">
                <img src="{{ $transfer->offeringTeam->image }}" class="w-8 h-8">
                <div>
                    <span class="font-medium text-slate-900">{{ $transfer->gamePlayer->player->name }}</span>
                    <span class="text-slate-400 mx-1">&rarr;</span>
                    <span class="text-sm text-slate-600">{{ $transfer->offeringTeam->name }}</span>
                    <div class="text-xs text-slate-500">
                        {{ $transfer->gamePlayer->position }} &middot; {{ $transfer->gamePlayer->age }} {{ __('app.years') }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-red-600">{{ __('squad.free_transfer') }}</span>
                <span class="text-xs text-red-500">{{ __('squad.pre_contract_signed') }}</span>
            </div>
        </div>
        @endforeach

        {{-- Expiring Contracts (offer renewal) --}}
        @foreach($renewalEligiblePlayers as $player)
        @php
            $demand = $renewalDemands[$player->id] ?? null;
            $hasPendingOffer = $preContractOffers->where('game_player_id', $player->id)->isNotEmpty();
        @endphp
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 px-4 py-3 {{ $hasPendingOffer ? 'bg-red-50' : '' }}">
            <div class="flex items-center gap-3">
                <x-position-badge :position="$player->position" />
                <div>
                    <span class="font-medium text-slate-900">{{ $player->player->name }}</span>
                    <div class="text-xs text-slate-500">
                        {{ $player->position }} &middot; {{ $player->age }} {{ __('app.years') }} &middot;
                        {{ __('squad.current_wage') }}: {{ $player->formatted_wage }}{{ __('squad.per_year') }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-sm font-medium text-red-600">
                        {{ __('squad.expires') }}: {{ $player->contract_until->format('M Y') }}
                    </div>
                    @if($hasPendingOffer)
                        <div class="text-xs text-amber-600">{{ __('squad.has_pre_contract_offers') }}</div>
                    @elseif($demand)
                        <div class="text-xs text-slate-500">
                            {{ __('squad.wants') }}: {{ $demand['formattedWage'] }}{{ __('squad.per_year') }} {{ __('squad.for_years', ['years' => $demand['contractYears']]) }}
                        </div>
                    @endif
                </div>
                @if($demand)
                <form method="post" action="{{ route('game.transfers.renew', [$game->id, $player->id]) }}">
                    @csrf
                    <x-primary-button color="green" size="sm">{{ __('squad.renew') }}</x-primary-button>
                </form>
                @endif
            </div>
        </div>
        @endforeach

        {{-- Pending Renewals --}}
        @foreach($pendingRenewals as $player)
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 px-4 py-3 bg-green-50">
            <div class="flex items-center gap-3">
                <x-position-badge :position="$player->position" />
                <div>
                    <span class="font-medium text-slate-900">{{ $player->player->name }}</span>
                    <div class="text-xs text-slate-500">
                        {{ $player->position }} &middot; {{ $player->age }} {{ __('app.years') }} &middot;
                        {{ __('squad.contract_until') }}: {{ $player->contract_until->format('M Y') }}
                    </div>
                </div>
            </div>
            <div class="text-right text-sm">
                <span class="text-slate-600">{{ $player->formatted_wage }}</span>
                <span class="text-slate-400 mx-1">&rarr;</span>
                <span class="font-semibold text-green-600">{{ $player->formatted_pending_wage }}</span>
                <div class="text-xs text-green-700">{{ __('squad.new_wage_from_next') }}</div>
            </div>
        </div>
        @endforeach

    </div>
</div>
@endif
