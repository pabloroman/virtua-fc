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
                <div class="p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">Contract Management</h3>
                        <a href="{{ route('game.squad', $game->id) }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                            &larr; Back to Squad
                        </a>
                    </div>

                    {{-- Pre-Contract Offers (being poached) --}}
                    @if($preContractOffers->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                            Pre-Contract Offers Received
                            <span class="text-sm font-normal text-slate-500">(other clubs want your players)</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($preContractOffers as $offer)
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
                                                Contract expires: {{ $offer->gamePlayer->contract_until->format('M Y') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="text-xl font-bold text-amber-600">Free Transfer</div>
                                            <div class="text-xs text-slate-500">Expires in {{ $offer->days_until_expiry }} days</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <form method="post" action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}">
                                                @csrf
                                                <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                                    Let Go
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

                    {{-- Players Leaving (agreed pre-contracts) --}}
                    @if($agreedPreContracts->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            Leaving on Free Transfer
                            <span class="text-sm font-normal text-slate-500">(at end of season)</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($agreedPreContracts as $transfer)
                            <div class="border border-red-200 bg-red-50 rounded-lg p-4">
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
                                        <div class="text-xl font-bold text-red-600">Free Transfer</div>
                                        <div class="text-xs text-red-700">Pre-contract signed</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Pending Renewals --}}
                    @if($pendingRenewals->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            Renewals Agreed
                            <span class="text-sm font-normal text-slate-500">(new wages take effect next season)</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($pendingRenewals as $player)
                            <div class="border border-green-200 bg-green-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-sm text-slate-600">
                                                {{ $player->position }} &middot; {{ $player->age }} years &middot;
                                                Contract until: {{ $player->contract_until->format('M Y') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm text-slate-600">
                                            {{ $player->formatted_wage }} &rarr;
                                            <span class="font-semibold text-green-600">{{ $player->formatted_pending_wage }}</span>
                                        </div>
                                        <div class="text-xs text-green-700">New wage from next season</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Expiring Contracts (Offer Renewals) --}}
                    @if($renewalEligiblePlayers->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            Expiring Contracts
                            <span class="text-sm font-normal text-slate-500">(offer renewals or risk losing players)</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($renewalEligiblePlayers as $player)
                            @php
                                $demand = $renewalDemands[$player->id] ?? null;
                                $hasPendingOffer = $preContractOffers->where('game_player_id', $player->id)->isNotEmpty();
                            @endphp
                            <div class="border {{ $hasPendingOffer ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50' }} rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-sm text-slate-600">
                                                {{ $player->position }} &middot; {{ $player->age }} years &middot;
                                                Value: {{ $player->formatted_market_value }} &middot;
                                                Current: {{ $player->formatted_wage }}/yr
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="text-sm font-semibold text-red-600">
                                                Expires: {{ $player->contract_until->format('M Y') }}
                                            </div>
                                            @if($hasPendingOffer)
                                            <div class="text-xs text-amber-600">Has pre-contract offers!</div>
                                            @elseif($demand)
                                            <div class="text-xs text-slate-500">
                                                Wants: {{ $demand['formattedWage'] }}/yr for {{ $demand['contractYears'] }}yr
                                            </div>
                                            @endif
                                        </div>
                                        @if($demand)
                                        <form method="post" action="{{ route('game.transfers.renew', [$game->id, $player->id]) }}">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                                Renew
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Contracts Overview by Year --}}
                    @if($contractsByYear->isNotEmpty())
                    <div class="border-t pt-6">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4">Contract Overview</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            @foreach($contractsByYear as $year => $players)
                            <div class="border rounded-lg p-4 {{ $year <= (int)$game->season + 1 ? 'border-amber-200 bg-amber-50' : 'bg-slate-50' }}">
                                <div class="text-2xl font-bold {{ $year <= (int)$game->season + 1 ? 'text-amber-600' : 'text-slate-700' }}">{{ $year }}</div>
                                <div class="text-sm text-slate-600">{{ $players->count() }} player(s)</div>
                                <div class="mt-2 text-xs text-slate-500">
                                    @foreach($players->take(3) as $p)
                                        {{ $p->player->name }}@if(!$loop->last), @endif
                                    @endforeach
                                    @if($players->count() > 3)
                                        <span class="text-slate-400">+{{ $players->count() - 3 }} more</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Empty State --}}
                    @if($renewalEligiblePlayers->isEmpty() && $pendingRenewals->isEmpty() && $preContractOffers->isEmpty() && $agreedPreContracts->isEmpty())
                    <div class="text-center py-12 text-slate-500">
                        <p class="text-lg">No contract actions needed at this time.</p>
                        <p class="text-sm mt-2">Players with expiring contracts will appear here when they need renewal.</p>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
