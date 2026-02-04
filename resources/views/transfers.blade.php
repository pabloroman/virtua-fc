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
                <div class="p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">Transfers</h3>
                        <div class="flex items-center gap-6 text-sm">
                            <div class="text-slate-600">
                                Next Window: <span class="font-semibold text-slate-900">{{ $nextWindow }}</span>
                            </div>
                            @if($game->finances)
                            <div class="text-slate-600">
                                Budget: <span class="font-semibold text-slate-900">{{ $game->finances->formatted_transfer_budget }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Unsolicited Offers (Poaching) --}}
                    @if($unsolicitedOffers->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                            Unsolicited Offers
                            <span class="text-sm font-normal text-slate-500">(clubs want your players)</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($unsolicitedOffers as $offer)
                            <div class="border border-amber-200 bg-amber-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <img src="{{ $offer->offeringTeam->image }}" class="w-10 h-10">
                                        <div>
                                            <div class="font-semibold text-slate-900">
                                                {{ $offer->offeringTeam->name }} wants {{ $offer->gamePlayer->player->name }}
                                            </div>
                                            <div class="text-sm text-slate-600">
                                                {{ $offer->gamePlayer->position }} &middot; {{ $offer->gamePlayer->age }} years &middot;
                                                Value: {{ $offer->gamePlayer->formatted_market_value }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="text-xl font-bold text-green-600">{{ $offer->formatted_transfer_fee }}</div>
                                            <div class="text-xs text-slate-500">Expires in {{ $offer->days_until_expiry }} days</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <form method="post" action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}">
                                                @csrf
                                                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                                    Accept
                                                </button>
                                            </form>
                                            <form method="post" action="{{ route('game.transfers.reject', [$game->id, $offer->id]) }}">
                                                @csrf
                                                <button type="submit" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                                                    Reject
                                                </button>
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
                            Offers Received
                        </h4>
                        <div class="space-y-3">
                            @foreach($listedOffers as $offer)
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <img src="{{ $offer->offeringTeam->image }}" class="w-10 h-10">
                                        <div>
                                            <div class="font-semibold text-slate-900">
                                                {{ $offer->offeringTeam->name }} offers for {{ $offer->gamePlayer->player->name }}
                                            </div>
                                            <div class="text-sm text-slate-600">
                                                {{ $offer->gamePlayer->position }} &middot; {{ $offer->gamePlayer->age }} years &middot;
                                                Value: {{ $offer->gamePlayer->formatted_market_value }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="text-xl font-bold text-green-600">{{ $offer->formatted_transfer_fee }}</div>
                                            <div class="text-xs text-slate-500">Expires in {{ $offer->days_until_expiry }} days</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <form method="post" action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}">
                                                @csrf
                                                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                                    Accept
                                                </button>
                                            </form>
                                            <form method="post" action="{{ route('game.transfers.reject', [$game->id, $offer->id]) }}">
                                                @csrf
                                                <button type="submit" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                                                    Reject
                                                </button>
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
                            Agreed Transfers
                            <span class="text-sm font-normal text-slate-500">(completing at {{ $nextWindow }} window)</span>
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
                                                {{ $transfer->gamePlayer->position }} &middot; {{ $transfer->gamePlayer->age }} years
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-green-600">{{ $transfer->formatted_transfer_fee }}</div>
                                        <div class="text-xs text-green-700">Deal agreed</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Expiring Contracts Notice --}}
                    @if($expiringContractPlayers->isNotEmpty())
                    <div class="mb-8 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                <span class="font-medium text-slate-900">{{ $expiringContractPlayers->count() }} player(s) with expiring contracts</span>
                            </div>
                            <a href="{{ route('game.squad.contracts', $game->id) }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                Manage Contracts &rarr;
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- Listed Players --}}
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold text-lg text-slate-900 flex items-center gap-2">
                                <span class="w-2 h-2 bg-slate-400 rounded-full"></span>
                                Listed Players
                            </h4>
                            <a href="{{ route('game.squad', $game->id) }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                                List more players from Squad &rarr;
                            </a>
                        </div>

                        @if($listedPlayers->isEmpty())
                        <div class="text-center py-8 text-slate-500 border rounded-lg bg-slate-50">
                            <p>No players listed for transfer.</p>
                            <a href="{{ route('game.squad', $game->id) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                Go to Squad to list players
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
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-sm text-slate-600">
                                                {{ $player->position }} &middot; {{ $player->age }} years &middot;
                                                Value: {{ $player->formatted_market_value }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        @if($playerOffers->isNotEmpty())
                                        <div class="text-sm text-slate-600">
                                            {{ $playerOffers->count() }} offer(s) &middot;
                                            Best: <span class="font-semibold text-green-600">{{ $bestOffer->formatted_transfer_fee }}</span>
                                        </div>
                                        @else
                                        <div class="text-sm text-slate-500">
                                            No offers yet
                                        </div>
                                        @endif
                                        <form method="post" action="{{ route('game.transfers.unlist', [$game->id, $player->id]) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors">
                                                Remove
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
                        <h4 class="font-semibold text-lg text-slate-900 mb-4">Recent Sales</h4>
                        <div class="space-y-2">
                            @foreach($recentTransfers as $transfer)
                            <div class="flex items-center justify-between py-2 text-sm">
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
