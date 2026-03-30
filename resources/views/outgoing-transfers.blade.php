@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('app.transfers') }}</h2>
        </div>

        {{-- Flash Messages --}}
        <x-flash-message type="success" :message="session('success')" class="mb-4" />
        <x-flash-message type="error" :message="session('error')" class="mb-4" />

        @include('partials.transfers-header')

                    {{-- Tab Navigation --}}
                    @php
                        $salidaBadge = $unsolicitedOffers->count() + $preContractOffers->count() + $listedOffers->count();
                    @endphp
                    <div x-data="{ helpOpen: false }">
                        <x-section-nav :items="[
                            ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => false],
                            ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => true, 'badge' => $salidaBadge > 0 ? $salidaBadge : null],
                            ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => false],
                            ['href' => route('game.explore', $game->id), 'label' => __('transfers.explore_tab'), 'active' => false],
                        ]">
                            <x-ghost-button color="slate" @click="helpOpen = !helpOpen" class="gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-text-secondary shrink-0">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                                </svg>
                                <span class="hidden md:inline">{{ __('transfers.transfers_help_toggle') }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform hidden md:block" :class="helpOpen ? 'rotate-180' : ''">
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </x-ghost-button>
                        </x-section-nav>

                        <div x-show="helpOpen" x-transition class="mt-3 bg-surface-800 border border-border-default rounded-xl p-4 text-sm">
                            <p class="text-text-secondary mb-4">{{ __('transfers.transfers_help_intro') }}</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                {{-- Selling --}}
                                <div>
                                    <p class="font-semibold text-text-body mb-2">{{ __('transfers.transfers_help_selling_title') }}</p>
                                    <ul class="space-y-1 text-text-secondary">
                                        <li class="flex gap-2"><span class="text-accent-gold shrink-0">&#9679;</span> {{ __('transfers.transfers_help_selling_list') }}</li>
                                        <li class="flex gap-2"><span class="text-accent-red shrink-0">&#9679;</span> {{ __('transfers.transfers_help_selling_unsolicited') }}</li>
                                        <li class="flex gap-2"><span class="text-accent-green shrink-0">&#9679;</span> {{ __('transfers.transfers_help_selling_accept') }}</li>
                                    </ul>
                                </div>

                                {{-- Contracts --}}
                                <div>
                                    <p class="font-semibold text-text-body mb-2">{{ __('transfers.transfers_help_contracts_title') }}</p>
                                    <ul class="space-y-1 text-text-secondary">
                                        <li class="flex gap-2"><span class="text-accent-red shrink-0">&#9679;</span> {{ __('transfers.transfers_help_contracts_expiring') }}</li>
                                        <li class="flex gap-2"><span class="text-accent-blue shrink-0">&#9679;</span> {{ __('transfers.transfers_help_contracts_renew') }}</li>
                                        <li class="flex gap-2"><span class="text-accent-gold shrink-0">&#9679;</span> {{ __('transfers.transfers_help_contracts_wages') }}</li>
                                    </ul>
                                </div>

                                {{-- Loans --}}
                                <div>
                                    <p class="font-semibold text-text-body mb-2">{{ __('transfers.transfers_help_loans_title') }}</p>
                                    <ul class="space-y-1 text-text-secondary">
                                        <li class="flex gap-2"><span class="text-accent-blue shrink-0">&#9679;</span> {{ __('transfers.transfers_help_loans_out') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

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
                            <div class="text-center py-12 text-text-secondary">
                                <svg class="w-12 h-12 mx-auto mb-3 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <p class="font-medium">{{ __('transfers.no_outgoing_activity') }}</p>
                            </div>
                            @endif

                            {{-- UNSOLICITED OFFERS — red accent --}}
                            @if($unsolicitedOffers->isNotEmpty())
                            <div x-data class="border-l-4 border-l-accent-red pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.unsolicited_offers') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.unsolicited_offers_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($unsolicitedOffers as $offer)
                                    <div class="bg-accent-red/10 border border-accent-red/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <x-team-crest :team="$offer->offeringTeam" class="w-10 h-10 shrink-0" />
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $offer->gamePlayer->player->name }} &larr; {{ $offer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $offer->gamePlayer->position_name }} &middot; {{ $offer->gamePlayer->age($game->current_date) }} {{ __('app.years') }} &middot;
                                                        {{ __('app.value') }}: {{ $offer->gamePlayer->formatted_market_value }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <div class="md:text-right">
                                                    <div class="text-xl font-bold text-accent-green">{{ $offer->formatted_transfer_fee }}</div>
                                                    <div class="text-xs text-text-muted">{{ __('transfers.expires_in_days', ['days' => $offer->days_until_expiry]) }}</div>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    @php
                                                        $gp = $offer->gamePlayer;
                                                        $posDisp = $gp->position_display;
                                                        $counterOfferDetail = \Illuminate\Support\Js::from([
                                                            'playerName' => $gp->player->name,
                                                            'negotiateUrl' => route('game.negotiate.counter-offer', [$game->id, $offer->id]),
                                                            'mode' => 'transfer_fee',
                                                            'phase' => 'counter_offer',
                                                            'chatTitle' => __('transfers.counter_offer_title'),
                                                            'playerInfo' => [
                                                                'age' => $gp->age($game->current_date),
                                                                'wage' => $gp->formatted_wage,
                                                                'tec' => $gp->technical_ability,
                                                                'fis' => $gp->physical_ability,
                                                                'position' => $posDisp['abbreviation'],
                                                                'positionBg' => $posDisp['bg'],
                                                                'positionText' => $posDisp['text'],
                                                            ],
                                                        ]);
                                                    @endphp
                                                    <x-primary-button type="button" x-on:click="$dispatch('open-negotiation', {{ $counterOfferDetail }})">
                                                        {{ __('transfers.negotiate') }}
                                                    </x-primary-button>
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
                            <div x-data class="border-l-4 border-l-accent-red pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.pre_contract_offers_received') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.pre_contract_offers_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($preContractOffers as $offer)
                                    <div class="bg-accent-red/10 border border-accent-red/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <x-team-crest :team="$offer->offeringTeam" class="w-10 h-10 shrink-0" />
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $offer->gamePlayer->player->name }} &larr; {{ $offer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $offer->gamePlayer->position_name }} &middot; {{ $offer->gamePlayer->age($game->current_date) }} {{ __('app.years') }} &middot;
                                                        {{ __('squad.expires_in_days', ['days' => $offer->days_until_expiry]) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <span class="text-sm font-semibold text-accent-red">{{ __('squad.free_transfer') }}</span>
                                                <div class="flex gap-2">
                                                    <form method="post" action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}">
                                                        @csrf
                                                        <x-primary-button color="amber" size="sm">{{ __('squad.let_go') }}</x-primary-button>
                                                    </form>
                                                    @php $offeredPlayer = $renewalEligiblePlayers->firstWhere('id', $offer->game_player_id); @endphp
                                                    @if($offeredPlayer)
                                                        @php
                                                            $posDisp = $offeredPlayer->position_display;
                                                            $renewalDetail = \Illuminate\Support\Js::from([
                                                                'playerName' => $offeredPlayer->name,
                                                                'negotiateUrl' => route('game.negotiate.renewal', [$game->id, $offeredPlayer->id]),
                                                                'playerInfo' => [
                                                                    'age' => $offeredPlayer->age($game->current_date),
                                                                    'wage' => $offeredPlayer->formatted_wage,
                                                                    'tec' => $offeredPlayer->technical_ability,
                                                                    'fis' => $offeredPlayer->physical_ability,
                                                                    'position' => $posDisp['abbreviation'],
                                                                    'positionBg' => $posDisp['bg'],
                                                                    'positionText' => $posDisp['text'],
                                                                ],
                                                            ]);
                                                        @endphp
                                                        <x-action-button color="green" type="button" x-on:click="$dispatch('open-negotiation', {{ $renewalDetail }})">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                            {{ __('squad.renew') }}
                                                        </x-action-button>
                                                    @else
                                                    <form method="post" action="{{ route('game.transfers.reject', [$game->id, $offer->id]) }}">
                                                        @csrf
                                                        <x-secondary-button type="submit" size="sm">{{ __('app.reject') }}</x-secondary-button>
                                                    </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- OFFERS FOR LISTED PLAYERS — gold accent --}}
                            @if($listedOffers->isNotEmpty())
                            <div x-data class="border-l-4 border-l-accent-gold pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.offers_received') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.offers_received_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($listedOffers as $offer)
                                    <div class="bg-accent-gold/10 border border-accent-gold/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <x-team-crest :team="$offer->offeringTeam" class="w-10 h-10 shrink-0" />
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $offer->gamePlayer->player->name }} &larr; {{ $offer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $offer->gamePlayer->position_name }} &middot; {{ $offer->gamePlayer->age($game->current_date) }} {{ __('app.years') }} &middot;
                                                        {{ __('app.value') }}: {{ $offer->gamePlayer->formatted_market_value }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <div class="md:text-right">
                                                    <div class="text-xl font-bold text-accent-green">{{ $offer->formatted_transfer_fee }}</div>
                                                    <div class="text-xs text-text-muted">{{ __('transfers.expires_in_days', ['days' => $offer->days_until_expiry]) }}</div>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    @php
                                                        $gp = $offer->gamePlayer;
                                                        $posDisp = $gp->position_display;
                                                        $counterOfferDetail = \Illuminate\Support\Js::from([
                                                            'playerName' => $gp->player->name,
                                                            'negotiateUrl' => route('game.negotiate.counter-offer', [$game->id, $offer->id]),
                                                            'mode' => 'transfer_fee',
                                                            'phase' => 'counter_offer',
                                                            'chatTitle' => __('transfers.counter_offer_title'),
                                                            'playerInfo' => [
                                                                'age' => $gp->age($game->current_date),
                                                                'wage' => $gp->formatted_wage,
                                                                'tec' => $gp->technical_ability,
                                                                'fis' => $gp->physical_ability,
                                                                'position' => $posDisp['abbreviation'],
                                                                'positionBg' => $posDisp['bg'],
                                                                'positionText' => $posDisp['text'],
                                                            ],
                                                        ]);
                                                    @endphp
                                                    <x-primary-button type="button" x-on:click="$dispatch('open-negotiation', {{ $counterOfferDetail }})">
                                                        {{ __('transfers.negotiate') }}
                                                    </x-primary-button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- AGREED OUTGOING TRANSFERS — green accent --}}
                            @if($agreedTransfers->isNotEmpty())
                            <div class="border-l-4 border-l-accent-green pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.agreed_transfers') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.completing_when_window', ['window' => $game->getNextWindowName()]) }}</p>
                                <div class="space-y-3">
                                    @foreach($agreedTransfers as $transfer)
                                    <div class="bg-accent-green/10 border border-accent-green/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <x-team-crest :team="$transfer->offeringTeam" class="w-10 h-10 shrink-0" />
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $transfer->gamePlayer->player->name }} &rarr; {{ $transfer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $transfer->gamePlayer->position_name }} &middot; {{ $transfer->gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-xl font-bold text-accent-green">{{ $transfer->formatted_transfer_fee }}</div>
                                                <div class="text-xs text-accent-green">{{ __('transfers.deal_agreed') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- PLAYERS LEAVING ON FREE — green accent --}}
                            @if($agreedPreContracts->isNotEmpty())
                            <div class="border-l-4 border-l-accent-green pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.players_leaving_free') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.players_leaving_free_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($agreedPreContracts as $transfer)
                                    <div class="bg-accent-green/10 border border-accent-green/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <x-team-crest :team="$transfer->offeringTeam" class="w-10 h-10 shrink-0" />
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $transfer->gamePlayer->player->name }} &rarr; {{ $transfer->offeringTeam->name }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $transfer->gamePlayer->position_name }} &middot; {{ $transfer->gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <span class="text-sm font-semibold text-accent-red">{{ __('squad.free_transfer') }}</span>
                                                <span class="text-xs text-text-muted">{{ __('squad.pre_contract_signed') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- LOAN SEARCHES — blue accent --}}
                            @if($loanSearches->isNotEmpty())
                            <div class="border-l-4 border-l-accent-blue pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.loan_searches_section') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.loan_searches_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($loanSearches as $gamePlayer)
                                    <div class="bg-accent-blue/10 border border-accent-blue/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-full bg-accent-blue/20 flex items-center justify-center shrink-0">
                                                    <svg class="w-5 h-5 text-accent-blue animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-text-primary">{{ $gamePlayer->name }}</div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $gamePlayer->position_name }} &middot; {{ $gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-accent-blue/10 text-accent-blue">
                                                    <span class="w-1.5 h-1.5 bg-accent-blue rounded-full animate-pulse"></span>
                                                    {{ __('transfers.searching_destination') }}
                                                </span>
                                                <form method="post" action="{{ route('game.loans.cancel', [$game->id, $gamePlayer->id]) }}">
                                                    @csrf
                                                    <x-ghost-button type="submit" color="red" size="xs">
                                                        {{ __('app.cancel') }}
                                                    </x-ghost-button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- LISTED PLAYERS FOR SALE — gold accent --}}
                            @if($listedPlayers->isNotEmpty())
                            <div class="border-l-4 border-l-accent-gold pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.listed_players') }}</h4>
                                <p class="text-sm text-text-muted mb-3">
                                    {{ __('transfers.listed_players_help') }}
                                    <a href="{{ route('game.squad', $game->id) }}" class="text-accent-blue hover:text-accent-blue ml-2">+ {{ __('transfers.list_more_from_squad') }}</a>
                                </p>
                                <div class="space-y-3">
                                    @foreach($listedPlayers as $player)
                                    <div class="bg-accent-gold/10 border border-accent-gold/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-full bg-accent-gold/20 flex items-center justify-center shrink-0">
                                                    <svg class="w-5 h-5 text-accent-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-text-primary">{{ $player->player->name }}</div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $player->position_name }} &middot; {{ $player->age($game->current_date) }} {{ __('app.years') }} &middot;
                                                        {{ __('app.value') }}: {{ $player->formatted_market_value }}
                                                    </div>
                                                </div>
                                            </div>
                                            <form method="post" action="{{ route('game.transfers.unlist', [$game->id, $player->id]) }}">
                                                @csrf
                                                <x-ghost-button type="submit" color="red" size="xs">
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
                            <x-section-card :title="__('transfers.recent_sales')">
                                <div class="divide-y divide-border-default">
                                    @foreach($recentTransfers as $transfer)
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 px-5 py-2.5 text-sm">
                                            <div class="flex items-center gap-3">
                                                <x-team-crest :team="$transfer->offeringTeam" class="w-6 h-6 shrink-0" />
                                                <span class="text-text-secondary">
                                                    {{ $transfer->gamePlayer->player->name }} &rarr; {{ $transfer->offeringTeam->name }}
                                                </span>
                                            </div>
                                            <span class="font-semibold text-accent-green">{{ $transfer->formatted_transfer_fee }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </x-section-card>
                            @endif

                        </div>

                        {{-- ============================== --}}
                        {{-- RIGHT COLUMN (1/3) — Planning --}}
                        {{-- ============================== --}}
                        <div class="space-y-6">

                            {{-- EXPIRING CONTRACTS --}}
                            @if($renewalEligiblePlayers->isNotEmpty() || $cooldownRenewals->isNotEmpty())
                            <x-section-card :title="__('transfers.expiring_contracts_section')">
                                <x-slot name="badge">
                                    <span class="text-xs text-text-secondary">({{ $renewalEligiblePlayers->count() + $cooldownRenewals->count() }})</span>
                                </x-slot>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="text-left border-b border-border-default">
                                            <tr>
                                                <th class="py-2.5 pl-4 w-10"></th>
                                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider">{{ __('app.name') }}</th>
                                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center w-12 hidden md:table-cell">{{ __('app.age') }}</th>
                                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell pr-4">{{ __('app.wage') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($renewalEligiblePlayers as $player)
                                        @php
                                            $hasPendingOffer = $preContractOffers->where('game_player_id', $player->id)->isNotEmpty();
                                        @endphp
                                        @php
                                            $posDisp = $player->position_display;
                                            $renewalPayload = \Illuminate\Support\Js::from([
                                                'playerName' => $player->name,
                                                'negotiateUrl' => route('game.negotiate.renewal', [$game->id, $player->id]),
                                                'playerInfo' => [
                                                    'age' => $player->age($game->current_date),
                                                    'wage' => $player->formatted_wage,
                                                    'tec' => $player->technical_ability,
                                                    'fis' => $player->physical_ability,
                                                    'position' => $posDisp['abbreviation'],
                                                    'positionBg' => $posDisp['bg'],
                                                    'positionText' => $posDisp['text'],
                                                ],
                                            ]);
                                        @endphp
                                        <tr x-data class="border-t border-border-default transition-colors cursor-pointer {{ $hasPendingOffer ? 'bg-accent-red/10' : 'hover:bg-[rgba(59,130,246,0.05)]' }}"
                                            x-on:click="$dispatch('open-negotiation', {{ $renewalPayload }})">
                                            <td class="py-2.5 pl-4 text-center">
                                                <x-position-badge :position="$player->position" size="sm" />
                                            </td>
                                            <td class="py-2.5 pl-2 pr-3">
                                                <div>
                                                    <span class="font-medium text-text-primary truncate">{{ $player->player->name }}</span>
                                                    @if($hasPendingOffer)
                                                        <div class="text-xs text-accent-gold">{{ __('squad.has_pre_contract_offers') }}</div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-2.5 text-center text-text-secondary tabular-nums hidden md:table-cell">{{ $player->age($game->current_date) }}</td>
                                            <td class="py-2.5 text-center text-text-secondary tabular-nums hidden md:table-cell pr-4">{{ $player->formatted_wage }}</td>
                                        </tr>
                                        @endforeach
                                        @foreach($cooldownRenewals as $player)
                                        <tr class="border-t border-border-default opacity-60">
                                            <td class="py-2.5 pl-4 text-center">
                                                <x-position-badge :position="$player->position" size="sm" />
                                            </td>
                                            <td class="py-2.5 pl-2 pr-3">
                                                <div>
                                                    <span class="font-medium text-text-primary truncate">{{ $player->player->name }}</span>
                                                    <div class="inline-flex items-center gap-1 text-xs text-text-muted">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        {{ __('transfers.renewal_cooldown_short') }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2.5 text-center text-text-secondary tabular-nums hidden md:table-cell">{{ $player->age($game->current_date) }}</td>
                                            <td class="py-2.5 text-center text-text-secondary tabular-nums hidden md:table-cell pr-4">{{ $player->formatted_wage }}</td>
                                        </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </x-section-card>
                            @endif

                            {{-- DECLINED RENEWALS --}}
                            @if($declinedRenewals->isNotEmpty())
                            <x-section-card class="opacity-60">
                                <div class="px-5 py-3 border-b border-border-default">
                                    <h4 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-muted flex items-center gap-2">
                                        {{ __('transfers.declined_renewals') }}
                                        <span class="text-xs font-normal text-text-secondary">({{ $declinedRenewals->count() }})</span>
                                    </h4>
                                </div>
                                <div class="divide-y divide-border-default">
                                    @foreach($declinedRenewals as $player)
                                    <div class="px-4 py-2.5">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <x-position-badge :position="$player->position" size="sm" />
                                                <span class="text-sm text-text-muted truncate">{{ $player->player->name }}</span>
                                            </div>
                                            <form method="post" action="{{ route('game.transfers.reconsider-renewal', [$game->id, $player->id]) }}">
                                                @csrf
                                                <x-ghost-button type="submit" color="blue" size="xs">
                                                    {{ __('transfers.reconsider_renewal') }}
                                                </x-ghost-button>
                                            </form>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </x-section-card>
                            @endif

                            {{-- PENDING RENEWALS --}}
                            @if($pendingRenewals->isNotEmpty())
                            <x-section-card :title="__('transfers.pending_renewals_section')">
                                <div class="divide-y divide-border-default">
                                    @foreach($pendingRenewals as $player)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-2 mb-1">
                                            <svg class="w-4 h-4 text-accent-green shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span class="font-medium text-sm text-text-primary truncate">{{ $player->player->name }}</span>
                                        </div>
                                        <div class="text-xs text-text-muted">
                                            {{ $player->formatted_wage }} <span class="text-text-body">&rarr;</span>
                                            <span class="font-semibold text-accent-green">{{ $player->formatted_pending_wage }}</span>
                                        </div>
                                        <div class="text-xs text-accent-green mt-0.5">{{ __('squad.new_wage_from_next') }}</div>
                                    </div>
                                    @endforeach
                                </div>
                            </x-section-card>
                            @endif

                            {{-- LOANS OUT --}}
                            @if($loansOut->isNotEmpty())
                            <x-section-card :title="__('transfers.loans_out_section')">
                                <x-slot name="badge">
                                    <span class="text-xs text-text-secondary">({{ $loansOut->count() }})</span>
                                </x-slot>
                                <div class="divide-y divide-border-default">
                                    @foreach($loansOut as $loan)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-team-crest :team="$loan->loanTeam" class="w-7 h-7 shrink-0" />
                                            <div class="min-w-0">
                                                <div class="font-medium text-sm text-text-primary truncate">{{ $loan->gamePlayer->name }}</div>
                                                <div class="text-xs text-text-muted">
                                                    {{ $loan->gamePlayer->position_name }} &middot;
                                                    {{ __('transfers.loaned_to', ['team_a' => $loan->loanTeam->nameWithA()]) }}
                                                </div>
                                                <div class="text-xs text-text-secondary mt-0.5">
                                                    {{ __('transfers.returns') }}: {{ $loan->return_at->format('M j, Y') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </x-section-card>
                            @endif

                        </div>
                    </div>

    </div>

    <x-player-detail-modal />
    <x-negotiation-chat-modal />
</x-app-layout>
