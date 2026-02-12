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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ __('transfers.title') }}</h3>
                        <div class="flex flex-wrap items-center gap-3 md:gap-6 text-sm">
                            <div class="text-slate-600">
                                @if($isTransferWindow)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                        {{ __('transfers.window_open', ['window' => $currentWindow]) }}
                                    </span>
                                @else
                                    {{ __('transfers.window') }}: <span class="font-semibold text-slate-900">{{ __('app.window_closed') }}</span>
                                @endif
                            </div>
                            @if($game->currentInvestment)
                            <div class="text-slate-600">
                                {{ __('transfers.budget') }}: <span class="font-semibold text-slate-900">{{ $game->currentInvestment->formatted_transfer_budget }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tab Navigation --}}
                    <x-section-nav :items="[
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.market'), 'active' => true],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting'), 'active' => false],
                        ['href' => route('game.loans', $game->id), 'label' => __('transfers.loans'), 'active' => false],
                    ]" />

                    <div class="mt-6"></div>

                    {{-- Pending Bids (User's offers awaiting response) --}}
                    @if($pendingBids->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>
                            {{ __('transfers.your_pending_bids') }}
                            <span class="text-sm font-normal text-slate-500">({{ __('transfers.awaiting_response') }})</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($pendingBids as $bid)
                            <div class="border border-amber-200 bg-amber-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        @if($bid->sellingTeam)
                                        <img src="{{ $bid->sellingTeam->image }}" class="w-10 h-10">
                                        @endif
                                        <div>
                                            <div class="font-semibold text-slate-900">
                                                {{ $bid->gamePlayer->player->name }}
                                                <span class="text-slate-500 font-normal">{{ __('transfers.from') }}</span>
                                                {{ $bid->sellingTeam?->name ?? 'Unknown' }}
                                            </div>
                                            <div class="text-sm text-slate-600">
                                                {{ $bid->gamePlayer->position }} &middot; {{ $bid->gamePlayer->age }} {{ __('app.years') }}
                                                @if($bid->offer_type === 'loan_in')
                                                    &middot; <span class="text-emerald-600 font-medium">{{ __('transfers.loan_request') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        @if($bid->transfer_fee > 0)
                                            <div class="text-xl font-bold text-amber-600">{{ $bid->formatted_transfer_fee }}</div>
                                        @elseif($bid->isPreContract())
                                            <div class="text-sm font-semibold text-emerald-600">{{ __('transfers.free_transfer') }}</div>
                                        @elseif($bid->isLoanIn())
                                            <div class="text-sm font-semibold text-emerald-600">{{ __('transfers.loan_no_fee') }}</div>
                                        @else
                                            <div class="text-sm font-semibold text-emerald-600">{{ __('finances.free') }}</div>
                                        @endif
                                        <div class="text-xs text-amber-700">{{ __('transfers.response_next_matchday') }}</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Rejected Bids (User's declined offers) --}}
                    @if($rejectedBids->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-400 rounded-full"></span>
                            {{ __('transfers.rejected_bids') }}
                        </h4>
                        <div class="space-y-3">
                            @foreach($rejectedBids as $bid)
                            <div class="border border-red-200 bg-red-50 rounded-lg p-4 opacity-75">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        @if($bid->sellingTeam)
                                        <img src="{{ $bid->sellingTeam->image }}" class="w-10 h-10 grayscale">
                                        @endif
                                        <div>
                                            <div class="font-semibold text-slate-700">
                                                {{ $bid->gamePlayer->player->name }}
                                                <span class="text-slate-500 font-normal">{{ __('transfers.from') }}</span>
                                                {{ $bid->sellingTeam?->name ?? 'Unknown' }}
                                            </div>
                                            <div class="text-sm text-slate-500">
                                                {{ $bid->gamePlayer->position }} &middot; {{ $bid->gamePlayer->age }} {{ __('app.years') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-red-600 line-through">{{ $bid->formatted_transfer_fee }}</div>
                                        <div class="text-xs text-red-600">{{ __('transfers.bid_rejected') }}</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Incoming Agreed Transfers (User buying players) --}}
                    @if($incomingAgreedTransfers->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-sky-500 rounded-full"></span>
                            {{ __('transfers.incoming_transfers') }}
                            <span class="text-sm font-normal text-slate-500">({{ __('transfers.completing_when_window', ['window' => $game->getNextWindowName()]) }})</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($incomingAgreedTransfers as $transfer)
                            <div class="border border-sky-200 bg-sky-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        @if($transfer->sellingTeam)
                                        <img src="{{ $transfer->sellingTeam->image }}" class="w-10 h-10">
                                        @endif
                                        <div>
                                            <div class="font-semibold text-slate-900">
                                                {{ $transfer->gamePlayer->player->name }} &larr; {{ $transfer->selling_team_name ?? 'Unknown' }}
                                            </div>
                                            <div class="text-sm text-slate-600">
                                                {{ $transfer->gamePlayer->position }} &middot; {{ $transfer->gamePlayer->age }} {{ __('app.years') }}
                                                @if($transfer->offer_type === 'loan_in')
                                                    &middot; <span class="text-emerald-600 font-medium">{{ __('transfers.loans') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        @if($transfer->transfer_fee > 0)
                                            <div class="text-xl font-bold text-sky-600">{{ $transfer->formatted_transfer_fee }}</div>
                                        @elseif($transfer->isPreContract())
                                            <div class="text-sm font-semibold text-emerald-600">{{ __('transfers.free_transfer') }}</div>
                                        @elseif($transfer->isLoanIn())
                                            <div class="text-sm font-semibold text-emerald-600">{{ __('transfers.loan_no_fee') }}</div>
                                        @else
                                            <div class="text-sm font-semibold text-emerald-600">{{ __('finances.free') }}</div>
                                        @endif
                                        <div class="text-xs text-sky-700">{{ __('transfers.deal_agreed') }}</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Unsolicited Offers (Poaching) --}}
                    @if($unsolicitedOffers->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                            {{ __('transfers.unsolicited_offers') }}
                            <span class="text-sm font-normal text-slate-500">({{ __('transfers.clubs_want_your_players') }})</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($unsolicitedOffers as $offer)
                            <div class="border border-amber-200 bg-amber-50 rounded-lg p-4">
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

                    {{-- Offers for Listed Players --}}
                    @if($listedOffers->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-slate-400 rounded-full"></span>
                            {{ __('transfers.offers_received') }}
                        </h4>
                        <div class="space-y-3">
                            @foreach($listedOffers as $offer)
                            <div class="border rounded-lg p-4">
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

                    {{-- Agreed Transfers (Waiting for Window) --}}
                    @if($agreedTransfers->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            {{ __('transfers.agreed_transfers') }}
                            <span class="text-sm font-normal text-slate-500">({{ __('transfers.completing_when_window', ['window' => $game->getNextWindowName()]) }})</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($agreedTransfers as $transfer)
                            <div class="border border-green-200 bg-green-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <img src="{{ $transfer->offeringTeam->image }}" class="w-10 h-10">
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
                                        <div class="text-xs text-green-700">{{ __('transfers.deal_agreed') }}</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Listed Players --}}
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold text-lg text-slate-900 flex items-center gap-2">
                                <span class="w-2 h-2 bg-slate-400 rounded-full"></span>
                                {{ __('transfers.listed_players') }}
                            </h4>
                            <a href="{{ route('game.squad', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                {{ __('transfers.list_more_from_squad') }} &rarr;
                            </a>
                        </div>

                        @if($listedPlayers->isEmpty())
                        <div class="text-center py-8 text-slate-500 border rounded-lg bg-slate-50">
                            <p>{{ __('transfers.no_players_listed') }}</p>
                            <a href="{{ route('game.squad', $game->id) }}" class="text-sky-600 hover:text-sky-800 text-sm">
                                {{ __('transfers.go_to_squad') }}
                            </a>
                        </div>
                        @else
                        <div class="space-y-3">
                            @foreach($listedPlayers as $player)
                            @php
                                $playerOffers = $player->activeOffers;
                                $bestOffer = $playerOffers->sortByDesc('transfer_fee')->first();
                            @endphp
                            <div class="border rounded-lg p-4 {{ $playerOffers->isEmpty() ? 'bg-slate-50' : '' }}">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-sm text-slate-600">
                                                {{ $player->position }} &middot; {{ $player->age }} {{ __('app.years') }} &middot;
                                                {{ __('app.value') }}: {{ $player->formatted_market_value }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        @if($playerOffers->isNotEmpty())
                                        <div class="text-sm text-slate-600">
                                            {{ __('transfers.offers_count', ['count' => $playerOffers->count()]) }} &middot;
                                            {{ __('transfers.best') }}: <span class="font-semibold text-green-600">{{ $bestOffer->formatted_transfer_fee }}</span>
                                        </div>
                                        @else
                                        <div class="text-sm text-slate-500">
                                            {{ __('transfers.no_offers_yet') }}
                                        </div>
                                        @endif
                                        <form method="post" action="{{ route('game.transfers.unlist', [$game->id, $player->id]) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors">
                                                {{ __('app.remove') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Recent Transfers --}}
                    @if($recentTransfers->isNotEmpty())
                    <div class="border-t pt-6">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4">{{ __('transfers.recent_sales') }}</h4>
                        <div class="space-y-2">
                            @foreach($recentTransfers as $transfer)
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 py-2 text-sm">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $transfer->offeringTeam->image }}" class="w-6 h-6">
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
