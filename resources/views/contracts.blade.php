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
                        <h3 class="font-semibold text-xl text-slate-900">{{ __('squad.contract_management') }}</h3>
                        <a href="{{ route('game.squad', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                            &larr; {{ __('squad.back_to_squad') }}
                        </a>
                    </div>

                    {{-- Pre-Contract Offers (being poached) --}}
                    @if($preContractOffers->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                            {{ __('squad.pre_contract_offers') }}
                            <span class="text-sm font-normal text-slate-500">({{ __('squad.other_clubs_want_players') }})</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($preContractOffers as $offer)
                            <div class="border border-amber-200 bg-amber-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <img src="{{ $offer->offeringTeam->image }}" class="w-10 h-10">
                                        <div>
                                            <div class="font-semibold text-slate-900">
                                                {{ __('squad.team_wants_player', ['team' => $offer->offeringTeam->name, 'player' => $offer->gamePlayer->player->name]) }}
                                            </div>
                                            <div class="text-sm text-slate-600">
                                                {{ $offer->gamePlayer->position }} &middot; {{ $offer->gamePlayer->age }} {{ __('app.years') }} &middot;
                                                {{ __('squad.contract_expires') }}: {{ $offer->gamePlayer->contract_until->format('M Y') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="text-xl font-bold text-amber-600">{{ __('squad.free_transfer') }}</div>
                                            <div class="text-xs text-slate-500">{{ __('squad.expires_in_days', ['days' => $offer->days_until_expiry]) }}</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <form method="post" action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}">
                                                @csrf
                                                <x-primary-button color="amber">{{ __('squad.let_go') }}</x-primary-button>
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

                    {{-- Players Leaving (agreed pre-contracts) --}}
                    @if($agreedPreContracts->isNotEmpty())
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            {{ __('squad.leaving_on_free') }}
                            <span class="text-sm font-normal text-slate-500">({{ __('squad.at_end_of_season') }})</span>
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
                                                {{ $transfer->gamePlayer->position }} &middot; {{ $transfer->gamePlayer->age }} {{ __('app.years') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-red-600">{{ __('squad.free_transfer') }}</div>
                                        <div class="text-xs text-red-700">{{ __('squad.pre_contract_signed') }}</div>
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
                            {{ __('squad.renewals_agreed') }}
                            <span class="text-sm font-normal text-slate-500">({{ __('squad.new_wages_next_season') }})</span>
                        </h4>
                        <div class="space-y-3">
                            @foreach($pendingRenewals as $player)
                            <div class="border border-green-200 bg-green-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-sm text-slate-600">
                                                {{ $player->position }} &middot; {{ $player->age }} {{ __('app.years') }} &middot;
                                                {{ __('squad.contract_until') }}: {{ $player->contract_until->format('M Y') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm text-slate-600">
                                            {{ $player->formatted_wage }} &rarr;
                                            <span class="font-semibold text-green-600">{{ $player->formatted_pending_wage }}</span>
                                        </div>
                                        <div class="text-xs text-green-700">{{ __('squad.new_wage_from_next') }}</div>
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
                            {{ __('squad.expiring_contracts') }}
                            <span class="text-sm font-normal text-slate-500">({{ __('squad.offer_renewals_hint') }})</span>
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
                                                {{ $player->position }} &middot; {{ $player->age }} {{ __('app.years') }} &middot;
                                                {{ __('app.value') }}: {{ $player->formatted_market_value }} &middot;
                                                {{ __('squad.current_wage') }}: {{ $player->formatted_wage }}{{ __('squad.per_year') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <div class="text-sm font-semibold text-red-600">
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
                                            <x-primary-button color="green">{{ __('squad.renew') }}</x-primary-button>
                                        </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Two Column: Highest Earners & Most Valuable --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        {{-- Highest Earners --}}
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-slate-900 mb-4">{{ __('squad.highest_earners') }}</h4>
                            @if($highestEarners->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($highestEarners as $player)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600">
                                            {{ $loop->iteration }}
                                        </span>
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $player->position }} &middot; {{ $player->age }} {{ __('app.years') }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-slate-900">{{ $player->formatted_wage }}{{ __('squad.per_year') }}</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-4 pt-4 border-t text-sm text-slate-600">
                                <div class="flex justify-between">
                                    <span>{{ __('squad.total_wage_bill') }}</span>
                                    <span class="font-semibold">{{ \App\Support\Money::format($wageBill) }}</span>
                                </div>
                            </div>
                            @else
                            <p class="text-slate-500">{{ __('squad.no_players_found') }}</p>
                            @endif
                        </div>

                        {{-- Most Valuable --}}
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-slate-900 mb-4">{{ __('squad.most_valuable') }}</h4>
                            @if($mostValuable->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($mostValuable as $player)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600">
                                            {{ $loop->iteration }}
                                        </span>
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $player->position }} &middot; {{ $player->age }} {{ __('app.years') }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-blue-700">{{ $player->formatted_market_value }}</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-4 pt-4 border-t text-sm text-slate-600">
                                <div class="flex justify-between">
                                    <span>{{ __('squad.total_squad_value') }}</span>
                                    <span class="font-semibold text-blue-700">{{ \App\Support\Money::format($squadValue) }}</span>
                                </div>
                            </div>
                            @else
                            <p class="text-slate-500">{{ __('squad.no_players_found') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Contracts Overview by Year --}}
                    @if($contractsByYear->isNotEmpty())
                    <div class="border-t pt-6">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4">{{ __('squad.contract_overview') }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            @foreach($contractsByYear as $year => $players)
                            <div class="border rounded-lg p-4 {{ $year <= (int)$game->season + 1 ? 'border-amber-200 bg-amber-50' : 'bg-slate-50' }}">
                                <div class="text-2xl font-bold {{ $year <= (int)$game->season + 1 ? 'text-amber-600' : 'text-slate-700' }}">{{ $year }}</div>
                                <div class="text-sm text-slate-600">{{ __('squad.players_count', ['count' => $players->count()]) }}</div>
                                <div class="mt-2 text-xs text-slate-500">
                                    @foreach($players->take(3) as $p)
                                        {{ $p->player->name }}@if(!$loop->last), @endif
                                    @endforeach
                                    @if($players->count() > 3)
                                        <span class="text-slate-400">+{{ $players->count() - 3 }} {{ __('squad.more') }}</span>
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
                        <p class="text-lg">{{ __('squad.no_contract_actions') }}</p>
                        <p class="text-sm mt-2">{{ __('squad.expiring_contracts_hint') }}</p>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
