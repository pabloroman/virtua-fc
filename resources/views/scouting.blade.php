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
                    <x-section-nav :items="[
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => true, 'badge' => $counterOfferCount > 0 ? $counterOfferCount : null],
                        ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => false],
                    ]" />

                    @php
                        $hasLeftContent = $counterOffers->isNotEmpty()
                            || $pendingBids->isNotEmpty()
                            || $incomingAgreedTransfers->isNotEmpty()
                            || $loansIn->isNotEmpty()
                            || $rejectedBids->isNotEmpty();
                    @endphp

                    <div class="mt-6 space-y-6">

                            @if(!$hasLeftContent)
                            <div class="text-center py-12 text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <p class="font-medium">{{ __('transfers.no_incoming_activity') }}</p>
                            </div>
                            @endif

                            {{-- ============================================= --}}
                            {{-- COUNTER-OFFERS — red accent --}}
                            {{-- ============================================= --}}
                            @if($counterOffers->isNotEmpty())
                            <div class="border-l-4 border-l-red-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.counter_offers_received') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.counter_offers_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($counterOffers as $bid)
                                    <div class="bg-red-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                @if($bid->sellingTeam)
                                                <img src="{{ $bid->sellingTeam->image }}" class="w-10 h-10 shrink-0">
                                                @endif
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $bid->gamePlayer->player->name }} &larr; {{ $bid->sellingTeam?->name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $bid->gamePlayer->position_name }} &middot; {{ $bid->gamePlayer->age }} {{ __('app.years') }}
                                                    </div>
                                                    <div class="text-sm mt-1">
                                                        <span class="text-slate-500 line-through">{{ __('transfers.your_bid_amount', ['amount' => $bid->formatted_transfer_fee]) }}</span>
                                                        <span class="text-red-700 font-semibold ml-2">{{ __('transfers.they_ask', ['amount' => \App\Support\Money::format($bid->asking_price)]) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <div class="md:text-right">
                                                    <div class="text-xl font-bold text-red-600">{{ \App\Support\Money::format($bid->asking_price) }}</div>
                                                </div>
                                                <form method="post" action="{{ route('game.scouting.counter.accept', [$game->id, $bid->id]) }}">
                                                    @csrf
                                                    <x-primary-button color="green">{{ __('transfers.accept_counter') }}</x-primary-button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- ============================================= --}}
                            {{-- PENDING BIDS — amber accent --}}
                            {{-- ============================================= --}}
                            @if($pendingBids->isNotEmpty())
                            <div class="border-l-4 border-l-amber-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.your_pending_bids') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.pending_bids_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($pendingBids as $bid)
                                    <div class="bg-amber-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                @if($bid->sellingTeam)
                                                <img src="{{ $bid->sellingTeam->image }}" class="w-10 h-10 shrink-0">
                                                @endif
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $bid->gamePlayer->player->name }} &larr; {{ $bid->sellingTeam?->name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $bid->gamePlayer->position_name }} &middot; {{ $bid->gamePlayer->age }} {{ __('app.years') }}
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

                            {{-- ============================================= --}}
                            {{-- INCOMING AGREED TRANSFERS — emerald accent --}}
                            {{-- ============================================= --}}
                            @if($incomingAgreedTransfers->isNotEmpty())
                            <div class="border-l-4 border-l-emerald-500 pl-5">
                                <h4 class="font-semibold text-lg text-slate-900 mb-1">{{ __('transfers.incoming_transfers') }}</h4>
                                <p class="text-sm text-slate-500 mb-3">{{ __('transfers.completing_when_window', ['window' => $game->getNextWindowName()]) }}</p>
                                <div class="space-y-3">
                                    @foreach($incomingAgreedTransfers as $transfer)
                                    <div class="bg-emerald-50 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                @if($transfer->sellingTeam)
                                                <img src="{{ $transfer->sellingTeam->image }}" class="w-10 h-10 shrink-0">
                                                @endif
                                                <div>
                                                    <div class="font-semibold text-slate-900">
                                                        {{ $transfer->gamePlayer->player->name }} &larr; {{ $transfer->selling_team_name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-sm text-slate-600">
                                                        {{ $transfer->gamePlayer->position_name }} &middot; {{ $transfer->gamePlayer->age }} {{ __('app.years') }}
                                                        @if($transfer->offer_type === 'loan_in')
                                                            &middot; <span class="text-emerald-600 font-medium">{{ __('transfers.loans') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                @if($transfer->transfer_fee > 0)
                                                    <div class="text-xl font-bold text-emerald-600">{{ $transfer->formatted_transfer_fee }}</div>
                                                @elseif($transfer->isPreContract())
                                                    <div class="text-sm font-semibold text-emerald-600">{{ __('transfers.free_transfer') }}</div>
                                                @elseif($transfer->isLoanIn())
                                                    <div class="text-sm font-semibold text-emerald-600">{{ __('transfers.loan_no_fee') }}</div>
                                                @else
                                                    <div class="text-sm font-semibold text-emerald-600">{{ __('finances.free') }}</div>
                                                @endif
                                                <div class="text-xs text-emerald-700">{{ __('transfers.deal_agreed') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- ============================================= --}}
                            {{-- CONTEXT: Loans In --}}
                            {{-- ============================================= --}}
                            @if($loansIn->isNotEmpty())
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-5 py-3 bg-slate-50 border-b">
                                    <h4 class="font-semibold text-sm text-slate-900 flex items-center gap-2">
                                        {{ __('transfers.active_loans_in') }}
                                        <span class="text-xs font-normal text-slate-400">({{ $loansIn->count() }})</span>
                                    </h4>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    @foreach($loansIn as $loan)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $loan->parentTeam->image }}" class="w-7 h-7 shrink-0">
                                            <div class="min-w-0">
                                                <div class="font-medium text-sm text-slate-900 truncate">{{ $loan->gamePlayer->name }}</div>
                                                <div class="text-xs text-slate-500">
                                                    {{ $loan->gamePlayer->position_name }} &middot; {{ $loan->gamePlayer->age }} {{ __('app.years') }}
                                                    &middot; {{ __('transfers.loaned_from', ['team' => $loan->parentTeam->name]) }}
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

                            {{-- ============================================= --}}
                            {{-- CONTEXT: Rejected Bids --}}
                            {{-- ============================================= --}}
                            @if($rejectedBids->isNotEmpty())
                            <div class="opacity-60">
                                <h4 class="font-semibold text-sm text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.rejected_bids') }}</h4>
                                <div class="space-y-2">
                                    @foreach($rejectedBids as $bid)
                                    <div class="border border-slate-200 rounded-lg p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                            <div class="flex items-center gap-4">
                                                @if($bid->sellingTeam)
                                                <img src="{{ $bid->sellingTeam->image }}" class="w-8 h-8 grayscale shrink-0">
                                                @endif
                                                <div>
                                                    <div class="font-medium text-slate-700">
                                                        {{ $bid->gamePlayer->player->name }}
                                                        <span class="text-slate-400 font-normal">{{ __('transfers.from') }}</span>
                                                        {{ $bid->sellingTeam?->name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-xs text-slate-500">
                                                        {{ $bid->gamePlayer->position_name }} &middot; {{ $bid->gamePlayer->age }} {{ __('app.years') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-bold text-red-600 line-through">{{ $bid->formatted_transfer_fee }}</div>
                                                <div class="text-xs text-red-600">{{ __('transfers.bid_rejected') }}</div>
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

    <x-scout-results-modal />

</x-app-layout>
