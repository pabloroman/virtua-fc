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
                            || $loanSearches->isNotEmpty()
                            || $listedPlayers->isNotEmpty()
                            || $recentTransfers->isNotEmpty();
                        $hasRightContent = $renewalEligiblePlayers->isNotEmpty()
                            || $negotiatingPlayers->isNotEmpty()
                            || $pendingRenewals->isNotEmpty()
                            || $declinedRenewals->isNotEmpty()
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
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.unsolicited_offers') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.unsolicited_offers_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($unsolicitedOffers as $offer)
                                    <div class="bg-red-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <img src="{{ $offer->offeringTeam->image }}" class="w-10 h-10 shrink-0">
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $offer->gamePlayer->player->name }} &larr; {{ $offer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $offer->gamePlayer->position_name }} &middot; {{ $offer->gamePlayer->age }} {{ __('app.years') }} &middot;
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
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.pre_contract_offers_received') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.pre_contract_offers_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($preContractOffers as $offer)
                                    <div class="bg-red-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <img src="{{ $offer->offeringTeam->image }}" class="w-10 h-10 shrink-0">
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $offer->gamePlayer->player->name }} &larr; {{ $offer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $offer->gamePlayer->position_name }} &middot; {{ $offer->gamePlayer->age }} {{ __('app.years') }} &middot;
                                                        {{ __('squad.expires_in_days', ['days' => $offer->days_until_expiry]) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <span class="text-sm font-semibold text-red-600">{{ __('squad.free_transfer') }}</span>
                                                <div class="flex gap-2">
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
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- OFFERS FOR LISTED PLAYERS — amber accent --}}
                            @if($listedOffers->isNotEmpty())
                            <div class="border-l-4 border-l-amber-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.offers_received') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.offers_received_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($listedOffers as $offer)
                                    <div class="bg-amber-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <img src="{{ $offer->offeringTeam->image }}" class="w-10 h-10 shrink-0">
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $offer->gamePlayer->player->name }} &larr; {{ $offer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $offer->gamePlayer->position_name }} &middot; {{ $offer->gamePlayer->age }} {{ __('app.years') }} &middot;
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
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.agreed_transfers') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.completing_when_window', ['window' => $game->getNextWindowName()]) }}</p>
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
                                                        {{ $transfer->gamePlayer->position_name }} &middot; {{ $transfer->gamePlayer->age }} {{ __('app.years') }}
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
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.players_leaving_free') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.players_leaving_free_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($agreedPreContracts as $transfer)
                                    <div class="bg-emerald-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <img src="{{ $transfer->offeringTeam->image }}" class="w-10 h-10 shrink-0">
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $transfer->gamePlayer->player->name }} &rarr; {{ $transfer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $transfer->gamePlayer->position_name }} &middot; {{ $transfer->gamePlayer->age }} {{ __('app.years') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
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
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.loan_searches_section') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.loan_searches_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($loanSearches as $gamePlayer)
                                    <div class="bg-sky-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center shrink-0">
                                                    <svg class="w-5 h-5 text-sky-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-slate-900">{{ $gamePlayer->name }}</div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $gamePlayer->position_name }} &middot; {{ $gamePlayer->age }} {{ __('app.years') }}
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

                            {{-- LISTED PLAYERS FOR SALE — amber accent --}}
                            @if($listedPlayers->isNotEmpty())
                            <div class="border-l-4 border-l-amber-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.listed_players') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">
                                    {{ __('transfers.listed_players_help') }}
                                    <a href="{{ route('game.squad', $game->id) }}" class="text-sky-600 hover:text-sky-800 ml-2">+ {{ __('transfers.list_more_from_squad') }}</a>
                                </p>
                                <div class="space-y-3">
                                    @foreach($listedPlayers as $player)
                                    @php
                                        $playerOffers = $player->activeOffers;
                                        $bestOffer = $playerOffers->sortByDesc('transfer_fee')->first();
                                    @endphp
                                    <div class="bg-amber-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                                                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-slate-900">{{ $player->player->name }}</div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $player->position_name }} &middot; {{ $player->age }} {{ __('app.years') }} &middot;
                                                        {{ __('app.value') }}: {{ $player->formatted_market_value }}
                                                    </div>
                                                    @if($playerOffers->isNotEmpty())
                                                    <div class="text-sm text-slate-600 mt-0.5">
                                                        {{ __('transfers.offers_count', ['count' => $playerOffers->count()]) }} &middot;
                                                        {{ __('transfers.best') }}: <span class="font-semibold text-green-600">{{ $bestOffer->formatted_transfer_fee }}</span>
                                                    </div>
                                                    @else
                                                    <div class="text-sm text-slate-400 mt-0.5">{{ __('transfers.no_offers_yet') }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                            <form method="post" action="{{ route('game.transfers.unlist', [$game->id, $player->id]) }}">
                                                @csrf
                                                <x-ghost-button type="submit" color="red">
                                                    {{ __('app.remove') }}
                                                </x-ghost-button>
                                            </form>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- ============================== --}}
                            {{-- FULL-WIDTH: Recent Sales --}}
                            {{-- ============================== --}}
                            @if($recentTransfers->isNotEmpty())
                                <div class="mt-8 pt-6">
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

                        {{-- ============================== --}}
                        {{-- RIGHT COLUMN (1/3) — Planning --}}
                        {{-- ============================== --}}
                        <div class="space-y-6">

                            {{-- EXPIRING CONTRACTS + ACTIVE NEGOTIATIONS --}}
                            @if($renewalEligiblePlayers->isNotEmpty() || $negotiatingPlayers->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-5 py-3 bg-slate-50 border-b">
                                    <h4 class="font-semibold text-sm text-slate-900 flex items-center gap-2">
                                        {{ __('transfers.expiring_contracts_section') }}
                                        <span class="text-xs font-normal text-slate-400">({{ $renewalEligiblePlayers->count() + $negotiatingPlayers->count() }})</span>
                                    </h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="text-left bg-slate-50/50 border-b border-slate-100">
                                            <tr>
                                                <th class="font-medium py-2 pl-3 w-10 text-slate-400"></th>
                                                <th class="font-medium py-2 text-slate-500">{{ __('app.name') }}</th>
                                                <th class="font-medium py-2 text-center w-12 hidden md:table-cell text-slate-500">{{ __('app.age') }}</th>
                                                <th class="font-medium py-2 text-center hidden md:table-cell text-slate-500 pr-3">{{ __('app.wage') }}</th>
                                            </tr>
                                        </thead>

                                        {{-- Players in active negotiation --}}
                                        @foreach($negotiatingPlayers as $player)
                                        @php
                                            $negotiation = $activeNegotiations->get($player->id);
                                            $mood = $renewalMoods[$player->id] ?? null;
                                        @endphp
                                        @if($negotiation)
                                        <tbody>
                                            <tr class="border-t border-slate-100">
                                                <td class="py-2.5 pl-3 text-center">
                                                    <x-position-badge :position="$player->position" size="sm" />
                                                </td>
                                                <td class="py-2.5 pr-3">
                                                    <div class="flex items-center gap-1.5">
                                                        <button x-data @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $player->id]) }}')" class="p-1 text-slate-300 rounded hover:text-slate-400 shrink-0">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-4 h-4">
                                                                <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                        <span class="font-medium text-slate-900 truncate">{{ $player->player->name }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-2.5 text-center text-slate-500 hidden md:table-cell">{{ $player->age }}</td>
                                                <td class="py-2.5 text-center text-slate-500 hidden md:table-cell pr-3">{{ $player->formatted_wage }}</td>
                                            </tr>
                                        </tbody>
                                        @endif
                                        @endforeach

                                        {{-- Players eligible for renewal (not yet negotiating) --}}
                                        <tbody>
                                        @foreach($renewalEligiblePlayers as $player)
                                        @php
                                            $demand = $renewalDemands[$player->id] ?? null;
                                            $mood = $renewalMoods[$player->id] ?? null;
                                            $hasPendingOffer = $preContractOffers->where('game_player_id', $player->id)->isNotEmpty();
                                        @endphp
                                        <tr class="border-t border-slate-100 {{ $hasPendingOffer ? 'bg-red-50' : '' }}">
                                            <td class="py-2.5 pl-3 text-center">
                                                <x-position-badge :position="$player->position" size="sm" />
                                            </td>
                                            <td class="py-2.5 pr-3">
                                                <div class="flex items-center gap-1.5">
                                                    <button x-data @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $player->id]) }}')" class="p-1 text-slate-300 rounded hover:text-slate-400 shrink-0">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-4 h-4">
                                                            <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                    <div>
                                                        <span class="font-medium text-slate-900 truncate">{{ $player->player->name }}</span>
                                                        @if($hasPendingOffer)
                                                            <div class="text-xs text-amber-600">{{ __('squad.has_pre_contract_offers') }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2.5 text-center text-slate-500 hidden md:table-cell">{{ $player->age }}</td>
                                            <td class="py-2.5 text-center text-slate-500 hidden md:table-cell pr-3">{{ $player->formatted_wage }}</td>
                                        </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif

                            {{-- DECLINED RENEWALS --}}
                            @if($declinedRenewals->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden opacity-60">
                                <div class="px-5 py-3 bg-slate-50 border-b">
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
                                                <button type="submit" class="text-xs text-sky-600 hover:text-sky-800 hover:underline whitespace-nowrap min-h-[44px] sm:min-h-0 rounded focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1">
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
                                <div class="px-5 py-3 bg-slate-50 border-b">
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

                            {{-- LOANS OUT --}}
                            @if($loansOut->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-5 py-3 bg-slate-50 border-b">
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
                                                    {{ $loan->gamePlayer->position_name }} &middot;
                                                    {{ __('transfers.loaned_to', ['team_a' => $loan->loanTeam->nameWithA()]) }}
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

                </div>
            </div>
        </div>
    </div>

    <x-player-detail-modal />
</x-app-layout>
