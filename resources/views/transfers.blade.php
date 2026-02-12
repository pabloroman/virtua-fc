@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8">
                    @include('partials.transfers-header')

                    {{-- Tab Navigation --}}
                    @php
                        $salidaBadge = $unsolicitedOffers->count() + $preContractOffers->count() + $listedOffers->count();
                    @endphp
                    <x-section-nav :items="[
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.outgoing'), 'active' => true, 'badge' => $salidaBadge > 0 ? $salidaBadge : null],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.incoming'), 'active' => false, 'badge' => $counterOfferCount > 0 ? $counterOfferCount : null],
                    ]" />

                    @php
                        $hasLeftContent = $unsolicitedOffers->isNotEmpty()
                            || $preContractOffers->isNotEmpty()
                            || $listedOffers->isNotEmpty()
                            || $agreedTransfers->isNotEmpty()
                            || $agreedPreContracts->isNotEmpty()
                            || $loanSearches->isNotEmpty();
                        $hasRightContent = $renewalEligiblePlayers->isNotEmpty()
                            || $negotiatingPlayers->isNotEmpty()
                            || $pendingRenewals->isNotEmpty()
                            || $declinedRenewals->isNotEmpty()
                            || $listedPlayers->isNotEmpty()
                            || $loansOut->isNotEmpty();
                    @endphp

                    {{-- 2-Column Grid --}}
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">

                        {{-- ============================== --}}
                        {{-- LEFT COLUMN (2/3) — Action Items --}}
                        {{-- ============================== --}}
                        <div class="md:col-span-2 space-y-6">

                            @if(!$hasLeftContent)
                            <div class="text-center py-12 text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <p class="font-medium">{{ __('transfers.no_outgoing_activity') }}</p>
                            </div>
                            @endif

                            {{-- UNSOLICITED OFFERS — red accent --}}
                            @if($unsolicitedOffers->isNotEmpty())
                            <div class="border-l-4 border-l-red-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-3 flex items-center gap-2">
                                    {{ __('transfers.unsolicited_offers') }}
                                    <span class="text-sm font-normal text-slate-500">({{ __('transfers.clubs_want_your_players') }})</span>
                                </h4>
                                <div class="space-y-3">
                                    @foreach($unsolicitedOffers as $offer)
                                    <div class="bg-red-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <img src="{{ $offer->offeringTeam->image }}" class="w-10 h-10 shrink-0">
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $offer->offeringTeam->name }} {{ __('transfers.wants') }} {{ $offer->gamePlayer->player->name }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $offer->gamePlayer->position }} &middot; {{ $offer->gamePlayer->age }} {{ __('app.years') }} &middot;
                                                        {{ __('app.value') }}: {{ $offer->gamePlayer->formatted_market_value }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <div class="md:text-right">
                                                    <div class="text-xl font-bold text-green-600">{{ $offer->formatted_transfer_fee }}</div>
                                                    <div class="text-xs text-slate-500">{{ __('transfers.expires_in_days', ['days' => $offer->days_until_expiry]) }}</div>
                                                </div>
                                                <div class="flex gap-2">
                                                    <form method="post" action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}">
                                                        @csrf
                                                        <x-primary-button color="green">{{ __('app.accept') }}</x-primary-button>
                                                    </form>
                                                    <form method="post" action="{{ route('game.transfers.reject', [$game->id, $offer->id]) }}">
                                                        @csrf
                                                        <x-secondary-button type="submit">{{ __('app.reject') }}</x-secondary-button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- PRE-CONTRACT OFFERS — red accent --}}
                            @if($preContractOffers->isNotEmpty())
                            <div class="border-l-4 border-l-red-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-3">
                                    {{ __('transfers.pre_contract_offers_received') }}
                                </h4>
                                <div class="space-y-3">
                                    @foreach($preContractOffers as $offer)
                                    <div class="bg-red-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-3">
                                                <img src="{{ $offer->offeringTeam->image }}" class="w-8 h-8 shrink-0">
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
                                                <span class="text-sm font-semibold text-red-600 mr-2">{{ __('squad.free_transfer') }}</span>
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
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- OFFERS FOR LISTED PLAYERS — amber accent --}}
                            @if($listedOffers->isNotEmpty())
                            <div class="border-l-4 border-l-amber-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-3">
                                    {{ __('transfers.offers_received') }}
                                </h4>
                                <div class="space-y-3">
                                    @foreach($listedOffers as $offer)
                                    <div class="bg-amber-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <img src="{{ $offer->offeringTeam->image }}" class="w-10 h-10 shrink-0">
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $offer->offeringTeam->name }} {{ __('transfers.offers_for') }} {{ $offer->gamePlayer->player->name }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $offer->gamePlayer->position }} &middot; {{ $offer->gamePlayer->age }} {{ __('app.years') }} &middot;
                                                        {{ __('app.value') }}: {{ $offer->gamePlayer->formatted_market_value }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <div class="md:text-right">
                                                    <div class="text-xl font-bold text-green-600">{{ $offer->formatted_transfer_fee }}</div>
                                                    <div class="text-xs text-slate-500">{{ __('transfers.expires_in_days', ['days' => $offer->days_until_expiry]) }}</div>
                                                </div>
                                                <div class="flex gap-2">
                                                    <form method="post" action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}">
                                                        @csrf
                                                        <x-primary-button color="green">{{ __('app.accept') }}</x-primary-button>
                                                    </form>
                                                    <form method="post" action="{{ route('game.transfers.reject', [$game->id, $offer->id]) }}">
                                                        @csrf
                                                        <x-secondary-button type="submit">{{ __('app.reject') }}</x-secondary-button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- AGREED OUTGOING TRANSFERS — emerald accent --}}
                            @if($agreedTransfers->isNotEmpty())
                            <div class="border-l-4 border-l-emerald-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-3 flex items-center gap-2">
                                    {{ __('transfers.agreed_transfers') }}
                                    <span class="text-sm font-normal text-slate-500">({{ __('transfers.completing_when_window', ['window' => $game->getNextWindowName()]) }})</span>
                                </h4>
                                <div class="space-y-3">
                                    @foreach($agreedTransfers as $transfer)
                                    <div class="bg-emerald-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <img src="{{ $transfer->offeringTeam->image }}" class="w-10 h-10 shrink-0">
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $transfer->gamePlayer->player->name }} &rarr; {{ $transfer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $transfer->gamePlayer->position }} &middot; {{ $transfer->gamePlayer->age }} {{ __('app.years') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-xl font-bold text-green-600">{{ $transfer->formatted_transfer_fee }}</div>
                                                <div class="text-xs text-emerald-700">{{ __('transfers.deal_agreed') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- PLAYERS LEAVING ON FREE — emerald accent --}}
                            @if($agreedPreContracts->isNotEmpty())
                            <div class="border-l-4 border-l-emerald-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-3">
                                    {{ __('transfers.players_leaving_free') }}
                                </h4>
                                <div class="space-y-3">
                                    @foreach($agreedPreContracts as $transfer)
                                    <div class="bg-emerald-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                            <div class="flex items-center gap-3">
                                                <img src="{{ $transfer->offeringTeam->image }}" class="w-8 h-8 shrink-0">
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
                                                <span class="text-xs text-slate-500">{{ __('squad.pre_contract_signed') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- LOAN SEARCHES — sky accent --}}
                            @if($loanSearches->isNotEmpty())
                            <div class="border-l-4 border-l-sky-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-3 flex items-center gap-2">
                                    {{ __('transfers.loan_searches_section') }}
                                    <span class="text-sm font-normal text-slate-500">({{ $loanSearches->count() }})</span>
                                </h4>
                                <div class="space-y-3">
                                    @foreach($loanSearches as $gamePlayer)
                                    <div class="bg-sky-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <svg class="w-5 h-5 text-sky-500 animate-pulse shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                                <div>
                                                    <div class="font-semibold text-slate-900">{{ $gamePlayer->name }}</div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $gamePlayer->position }} &middot; {{ $gamePlayer->age }} {{ __('transfers.years') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">
                                                    <span class="w-1.5 h-1.5 bg-sky-500 rounded-full animate-pulse"></span>
                                                    {{ __('transfers.searching_destination') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                        </div>

                        {{-- ============================== --}}
                        {{-- RIGHT COLUMN (1/3) — Planning --}}
                        {{-- ============================== --}}
                        <div class="space-y-6">

                            {{-- EXPIRING CONTRACTS + ACTIVE NEGOTIATIONS --}}
                            @if($renewalEligiblePlayers->isNotEmpty() || $negotiatingPlayers->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-4 py-3 bg-slate-50 border-b">
                                    <h4 class="font-semibold text-sm text-slate-900 flex items-center gap-2">
                                        {{ __('transfers.expiring_contracts_section') }}
                                        <span class="text-xs font-normal text-slate-400">({{ $renewalEligiblePlayers->count() + $negotiatingPlayers->count() }})</span>
                                    </h4>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    {{-- Players in active negotiation --}}
                                    @foreach($negotiatingPlayers as $player)
                                    @php
                                        $negotiation = $activeNegotiations->get($player->id);
                                        $mood = $renewalMoods[$player->id] ?? null;
                                    @endphp
                                    @if($negotiation)
                                    <div class="px-4 py-3" x-data="{ showForm: false }">
                                        <div class="flex items-center gap-2 mb-1">
                                            <x-position-badge :position="$player->position" size="sm" />
                                            <span class="font-medium text-sm text-slate-900 truncate">{{ $player->player->name }}</span>
                                        </div>

                                        @if($negotiation->isPending())
                                            {{-- PENDING STATE — waiting for matchday --}}
                                            <div class="flex items-center gap-1.5 mb-1.5">
                                                <svg class="w-3.5 h-3.5 text-amber-500 animate-pulse shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-xs font-medium text-amber-600">{{ __('transfers.negotiating') }}</span>
                                            </div>
                                            <div class="text-xs text-slate-500 mb-1">
                                                {{ __('transfers.your_offer_label') }}: {{ $negotiation->formatted_user_offer }}{{ __('transfers.per_year_short') }} &middot; {{ $negotiation->offered_years }} {{ $negotiation->offered_years === 1 ? __('transfers.year_singular') : __('transfers.years_plural') }}
                                            </div>
                                            <div class="text-xs text-slate-400">{{ __('transfers.response_next_matchday') }}</div>

                                        @elseif($negotiation->isCountered())
                                            {{-- COUNTERED STATE — player responded with counter --}}
                                            @if($mood)
                                            <div class="flex items-center gap-1.5 mb-1.5">
                                                <span class="w-2 h-2 rounded-full shrink-0 {{ $mood['color'] === 'green' ? 'bg-green-500' : ($mood['color'] === 'amber' ? 'bg-amber-500' : 'bg-red-500') }}"></span>
                                                <span class="text-xs text-slate-500">{{ __('transfers.round_of', ['round' => $negotiation->round, 'max' => 3]) }}</span>
                                            </div>
                                            @endif
                                            <div class="text-xs text-slate-500 mb-1">
                                                {{ __('transfers.your_offer_label') }}: {{ $negotiation->formatted_user_offer }} &middot; {{ $negotiation->offered_years }}{{ __('transfers.years_short') }}
                                            </div>
                                            <div class="text-xs font-medium text-amber-600 mb-2">
                                                {{ __('transfers.player_asks') }}: {{ $negotiation->formatted_counter_offer }}{{ __('transfers.per_year_short') }} &middot; {{ $negotiation->preferred_years }}{{ __('transfers.years_short') }}
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                <form method="post" action="{{ route('game.transfers.accept-renewal-counter', [$game->id, $player->id]) }}">
                                                    @csrf
                                                    <x-secondary-button type="submit" class="text-xs !px-3 !py-1.5">
                                                        {{ __('transfers.accept_counter') }} {{ $negotiation->formatted_counter_offer }} &middot; {{ $negotiation->preferred_years }}{{ __('transfers.years_short') }}
                                                    </x-secondary-button>
                                                </form>

                                                @if($negotiation->round < 3)
                                                <button @click="showForm = !showForm" class="px-2 py-1.5 text-xs text-sky-600 hover:text-sky-800 hover:bg-sky-50 rounded transition-colors whitespace-nowrap min-h-[44px]">
                                                    {{ __('transfers.new_offer') }}
                                                </button>
                                                @endif

                                                <form method="post" action="{{ route('game.transfers.decline-renewal', [$game->id, $player->id]) }}"
                                                      onsubmit="return confirm('{{ __('transfers.decline_renewal_confirm', ['player' => $player->player->name]) }}')">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-1.5 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors whitespace-nowrap min-h-[44px]">
                                                        {{ __('transfers.decline_renewal') }}
                                                    </button>
                                                </form>
                                            </div>

                                            {{-- New offer form (inline, toggled) --}}
                                            <div x-show="showForm" x-cloak class="mt-3 pt-3 border-t border-slate-100">
                                                @php
                                                    $demand = $renewalDemands[$player->id] ?? null;
                                                    $midpoint = $demand ? (int)(($player->annual_wage + $demand['wage']) / 2 / 100) : (int)($negotiation->counter_offer / 100);
                                                @endphp
                                                <form method="post" action="{{ route('game.transfers.renew', [$game->id, $player->id]) }}">
                                                    @csrf
                                                    <div class="mb-2">
                                                        <label class="text-xs text-slate-500 block mb-1">{{ __('transfers.your_offer') }}</label>
                                                        <input type="number" name="offer_wage" value="{{ $midpoint }}" min="1"
                                                               class="w-full px-3 py-2 text-sm border border-slate-300 rounded-md focus:ring-red-500 focus:border-red-500">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="text-xs text-slate-500 block mb-1">{{ __('transfers.duration') }}</label>
                                                        <div class="flex gap-1">
                                                            @for($y = 1; $y <= 3; $y++)
                                                            <label class="flex-1">
                                                                <input type="radio" name="offered_years" value="{{ $y }}" class="peer sr-only"
                                                                       {{ $y === $negotiation->preferred_years ? 'checked' : '' }}>
                                                                <div class="text-center py-1.5 px-2 text-xs border rounded cursor-pointer transition-colors
                                                                    peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600
                                                                    hover:border-slate-400
                                                                    {{ $y === $negotiation->preferred_years ? 'ring-1 ring-red-300' : '' }}">
                                                                    {{ $y }} {{ $y === 1 ? __('transfers.year_singular') : __('transfers.years_plural') }}
                                                                    @if($y === $negotiation->preferred_years)
                                                                        <span class="text-[10px] opacity-75 block">{{ __('transfers.preferred_years') }}</span>
                                                                    @endif
                                                                </div>
                                                            </label>
                                                            @endfor
                                                        </div>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <x-secondary-button type="submit" class="text-xs !px-3 !py-1.5">{{ __('transfers.offer_button') }}</x-secondary-button>
                                                        <button type="button" @click="showForm = false" class="px-3 py-1.5 text-xs text-slate-500 hover:text-slate-700">{{ __('transfers.cancel') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                    @endif
                                    @endforeach

                                    {{-- Players eligible for renewal (not yet negotiating) --}}
                                    @foreach($renewalEligiblePlayers as $player)
                                    @php
                                        $demand = $renewalDemands[$player->id] ?? null;
                                        $mood = $renewalMoods[$player->id] ?? null;
                                        $hasPendingOffer = $preContractOffers->where('game_player_id', $player->id)->isNotEmpty();
                                        $midpoint = $demand ? (int)(($player->annual_wage + $demand['wage']) / 2 / 100) : 0;
                                    @endphp
                                    <div class="px-4 py-3 {{ $hasPendingOffer ? 'bg-red-50' : '' }}" x-data="{ showForm: false }">
                                        <div class="flex items-center gap-2 mb-1">
                                            <x-position-badge :position="$player->position" size="sm" />
                                            <span class="font-medium text-sm text-slate-900 truncate">{{ $player->player->name }}</span>
                                        </div>
                                        <div class="text-xs text-slate-500 mb-1">
                                            {{ $player->formatted_wage }}{{ __('squad.per_year') }}
                                        </div>
                                        @if($mood)
                                        <div class="flex items-center gap-1.5 mb-1.5">
                                            <span class="w-2 h-2 rounded-full shrink-0 {{ $mood['color'] === 'green' ? 'bg-green-500' : ($mood['color'] === 'amber' ? 'bg-amber-500' : 'bg-red-500') }}"></span>
                                            <span class="text-xs {{ $mood['color'] === 'green' ? 'text-green-600' : ($mood['color'] === 'amber' ? 'text-amber-600' : 'text-red-600') }}">{{ $mood['label'] }}</span>
                                        </div>
                                        @endif
                                        @if($hasPendingOffer)
                                            <div class="text-xs text-amber-600 mb-2">{{ __('squad.has_pre_contract_offers') }}</div>
                                        @elseif($demand)
                                            <div class="text-xs text-slate-400 mb-2">
                                                {{ __('transfers.player_demand') }}: {{ $demand['formattedWage'] }}{{ __('transfers.per_year_short') }} &middot; {{ $demand['contractYears'] }} {{ $demand['contractYears'] === 1 ? __('transfers.year_singular') : __('transfers.years_plural') }}
                                            </div>
                                        @endif

                                        {{-- Default state: Negotiate / Decline buttons --}}
                                        @if($demand)
                                        <div x-show="!showForm" class="flex items-center gap-2">
                                            <button @click="showForm = true" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors min-h-[44px]">
                                                {{ __('transfers.negotiate') }}
                                            </button>
                                            <form method="post" action="{{ route('game.transfers.decline-renewal', [$game->id, $player->id]) }}"
                                                  onsubmit="return confirm('{{ __('transfers.decline_renewal_confirm', ['player' => $player->player->name]) }}')">
                                                @csrf
                                                <button type="submit" class="px-2 py-1.5 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors whitespace-nowrap min-h-[44px]">
                                                    {{ __('transfers.decline_renewal') }}
                                                </button>
                                            </form>
                                        </div>

                                        {{-- Inline offer form --}}
                                        <div x-show="showForm" x-cloak class="mt-2">
                                            <div class="text-xs text-slate-400 mb-2">
                                                {{ __('transfers.current_wage') }}: {{ $player->formatted_wage }} | {{ __('transfers.player_demand') }}: {{ $demand['formattedWage'] }}{{ __('transfers.per_year_short') }}
                                            </div>
                                            <form method="post" action="{{ route('game.transfers.renew', [$game->id, $player->id]) }}">
                                                @csrf
                                                <div class="mb-2">
                                                    <label class="text-xs text-slate-500 block mb-1">{{ __('transfers.your_offer') }}</label>
                                                    <input type="number" name="offer_wage" value="{{ $midpoint }}" min="1"
                                                           class="w-full px-3 py-2 text-sm border border-slate-300 rounded-md focus:ring-red-500 focus:border-red-500">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-xs text-slate-500 block mb-1">{{ __('transfers.duration') }}</label>
                                                    <div class="flex gap-1">
                                                        @for($y = 1; $y <= 3; $y++)
                                                        <label class="flex-1">
                                                            <input type="radio" name="offered_years" value="{{ $y }}" class="peer sr-only"
                                                                   {{ $y === $demand['contractYears'] ? 'checked' : '' }}>
                                                            <div class="text-center py-1.5 px-2 text-xs border rounded cursor-pointer transition-colors
                                                                peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600
                                                                hover:border-slate-400
                                                                {{ $y === $demand['contractYears'] ? 'ring-1 ring-red-300' : '' }}">
                                                                {{ $y }} {{ $y === 1 ? __('transfers.year_singular') : __('transfers.years_plural') }}
                                                                @if($y === $demand['contractYears'])
                                                                    <span class="text-[10px] opacity-75 block">{{ __('transfers.preferred_years') }}</span>
                                                                @endif
                                                            </div>
                                                        </label>
                                                        @endfor
                                                    </div>
                                                </div>
                                                <div class="flex gap-2">
                                                    <x-secondary-button type="submit" class="text-xs !px-3 !py-1.5">{{ __('transfers.offer_button') }}</x-secondary-button>
                                                    <button type="button" @click="showForm = false" class="px-3 py-1.5 text-xs text-slate-500 hover:text-slate-700">{{ __('transfers.cancel') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- DECLINED RENEWALS --}}
                            @if($declinedRenewals->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden opacity-60">
                                <div class="px-4 py-3 bg-slate-50 border-b">
                                    <h4 class="font-semibold text-sm text-slate-500 flex items-center gap-2">
                                        {{ __('transfers.declined_renewals') }}
                                        <span class="text-xs font-normal text-slate-400">({{ $declinedRenewals->count() }})</span>
                                    </h4>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    @foreach($declinedRenewals as $player)
                                    <div class="px-4 py-2.5">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <x-position-badge :position="$player->position" size="sm" />
                                                <span class="text-sm text-slate-500 truncate">{{ $player->player->name }}</span>
                                            </div>
                                            <form method="post" action="{{ route('game.transfers.reconsider-renewal', [$game->id, $player->id]) }}">
                                                @csrf
                                                <button type="submit" class="text-xs text-sky-600 hover:text-sky-800 hover:underline whitespace-nowrap min-h-[44px]">
                                                    {{ __('transfers.reconsider_renewal') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- PENDING RENEWALS --}}
                            @if($pendingRenewals->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-4 py-3 bg-slate-50 border-b">
                                    <h4 class="font-semibold text-sm text-slate-900">{{ __('transfers.pending_renewals_section') }}</h4>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    @foreach($pendingRenewals as $player)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-2 mb-1">
                                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span class="font-medium text-sm text-slate-900 truncate">{{ $player->player->name }}</span>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ $player->formatted_wage }} <span class="text-slate-300">&rarr;</span>
                                            <span class="font-semibold text-green-600">{{ $player->formatted_pending_wage }}</span>
                                        </div>
                                        <div class="text-xs text-green-600 mt-0.5">{{ __('squad.new_wage_from_next') }}</div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- LISTED PLAYERS --}}
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-4 py-3 bg-slate-50 border-b flex items-center justify-between">
                                    <h4 class="font-semibold text-sm text-slate-900">{{ __('transfers.listed_players') }}</h4>
                                    <a href="{{ route('game.squad', $game->id) }}" class="text-xs text-sky-600 hover:text-sky-800">
                                        + {{ __('transfers.list_more_from_squad') }}
                                    </a>
                                </div>
                                @if($listedPlayers->isEmpty())
                                <div class="px-4 py-6 text-center text-sm text-slate-400">
                                    {{ __('transfers.no_players_listed') }}
                                </div>
                                @else
                                <div class="divide-y divide-slate-100">
                                    @foreach($listedPlayers as $player)
                                    @php
                                        $playerOffers = $player->activeOffers;
                                        $bestOffer = $playerOffers->sortByDesc('transfer_fee')->first();
                                    @endphp
                                    <div class="px-4 py-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="font-medium text-sm text-slate-900 truncate">{{ $player->player->name }}</div>
                                                <div class="text-xs text-slate-500">
                                                    {{ $player->position }} &middot; {{ __('app.value') }}: {{ $player->formatted_market_value }}
                                                </div>
                                                @if($playerOffers->isNotEmpty())
                                                <div class="text-xs text-slate-600 mt-0.5">
                                                    {{ __('transfers.offers_count', ['count' => $playerOffers->count()]) }} &middot;
                                                    {{ __('transfers.best') }}: <span class="font-semibold text-green-600">{{ $bestOffer->formatted_transfer_fee }}</span>
                                                </div>
                                                @else
                                                <div class="text-xs text-slate-400 mt-0.5">{{ __('transfers.no_offers_yet') }}</div>
                                                @endif
                                            </div>
                                            <form method="post" action="{{ route('game.transfers.unlist', [$game->id, $player->id]) }}">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors whitespace-nowrap min-h-[44px]">
                                                    {{ __('app.remove') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>

                            {{-- LOANS OUT --}}
                            @if($loansOut->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-4 py-3 bg-slate-50 border-b">
                                    <h4 class="font-semibold text-sm text-slate-900 flex items-center gap-2">
                                        {{ __('transfers.loans_out_section') }}
                                        <span class="text-xs font-normal text-slate-400">({{ $loansOut->count() }})</span>
                                    </h4>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    @foreach($loansOut as $loan)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $loan->loanTeam->image }}" class="w-7 h-7 shrink-0">
                                            <div class="min-w-0">
                                                <div class="font-medium text-sm text-slate-900 truncate">{{ $loan->gamePlayer->name }}</div>
                                                <div class="text-xs text-slate-500">
                                                    {{ $loan->gamePlayer->position }} &middot;
                                                    {{ __('transfers.loaned_to', ['team' => $loan->loanTeam->name]) }}
                                                </div>
                                                <div class="text-xs text-slate-400 mt-0.5">
                                                    {{ __('transfers.returns') }}: {{ $loan->return_at->format('M j, Y') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>

                    {{-- ============================== --}}
                    {{-- FULL-WIDTH: Recent Sales --}}
                    {{-- ============================== --}}
                    @if($recentTransfers->isNotEmpty())
                    <div class="mt-8 border-t pt-6">
                        <h4 class="font-semibold text-sm text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.recent_sales') }}</h4>
                        <div class="space-y-1">
                            @foreach($recentTransfers as $transfer)
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 py-2 text-sm">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $transfer->offeringTeam->image }}" class="w-6 h-6 shrink-0">
                                    <span class="text-slate-600">
                                        {{ $transfer->gamePlayer->player->name }} &rarr; {{ $transfer->offeringTeam->name }}
                                    </span>
                                </div>
                                <span class="font-semibold text-green-600">{{ $transfer->formatted_transfer_fee }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
