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
                    <div x-data="{ helpOpen: false }">
                        <x-section-nav :items="[
                            ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => true, 'badge' => $counterOfferCount > 0 ? $counterOfferCount : null],
                            ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
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
                        $hasLeftContent = $counterOffers->isNotEmpty()
                            || $pendingBids->isNotEmpty()
                            || $incomingAgreedTransfers->isNotEmpty()
                            || $loansIn->isNotEmpty()
                            || $rejectedBids->isNotEmpty()
                            || $recentSignings->isNotEmpty();
                    @endphp

                    <div class="mt-6 space-y-6">

                            @if(!$hasLeftContent)
                            <div class="text-center py-12 text-text-secondary">
                                <svg class="w-12 h-12 mx-auto mb-3 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <p class="font-medium">{{ __('transfers.no_incoming_activity') }}</p>
                            </div>
                            @endif

                            {{-- ============================================= --}}
                            {{-- COUNTER-OFFERS — red accent --}}
                            {{-- ============================================= --}}
                            @if($counterOffers->isNotEmpty())
                            <div class="border-l-4 border-l-accent-red pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.counter_offers_received') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.counter_offers_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($counterOffers as $bid)
                                    <div class="bg-accent-red/10 border border-accent-red/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                @if($bid->sellingTeam)
                                                <x-team-crest :team="$bid->sellingTeam" class="w-10 h-10 shrink-0" />
                                                @endif
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $bid->gamePlayer->player->name }} &larr; {{ $bid->sellingTeam?->name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $bid->gamePlayer->position_name }} &middot; {{ $bid->gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                    </div>
                                                    <div class="text-sm mt-1">
                                                        <span class="text-text-muted line-through">{{ __('transfers.your_bid_amount', ['amount' => $bid->formatted_transfer_fee]) }}</span>
                                                        <span class="text-accent-red font-semibold ml-2">{{ __('transfers.they_ask', ['amount' => \App\Support\Money::format($bid->asking_price)]) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
                                                <div class="md:text-right">
                                                    <div class="text-xl font-bold text-accent-red">{{ \App\Support\Money::format($bid->asking_price) }}</div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <form method="post" action="{{ route('game.scouting.counter.reject', [$game->id, $bid->id]) }}">
                                                        @csrf
                                                        <x-ghost-button color="red">{{ __('transfers.reject_counter') }}</x-ghost-button>
                                                    </form>
                                                    <form method="post" action="{{ route('game.scouting.counter.accept', [$game->id, $bid->id]) }}">
                                                        @csrf
                                                        <x-primary-button color="green">{{ __('transfers.accept_counter') }}</x-primary-button>
                                                    </form>
                                                </div>
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
                            <div class="border-l-4 border-l-accent-gold pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.your_pending_bids') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.pending_bids_help') }}</p>
                                <div class="space-y-3">
                                    @foreach($pendingBids as $bid)
                                    <div class="bg-accent-gold/10 border border-accent-gold/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                @if($bid->sellingTeam)
                                                <x-team-crest :team="$bid->sellingTeam" class="w-10 h-10 shrink-0" />
                                                @endif
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $bid->gamePlayer->player->name }} &larr; {{ $bid->sellingTeam?->name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $bid->gamePlayer->position_name }} &middot; {{ $bid->gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                        @if($bid->offer_type === 'loan_in')
                                                            &middot; <span class="text-accent-green font-medium">{{ __('transfers.loan_request') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                @if($bid->transfer_fee > 0)
                                                    <div class="text-xl font-bold text-accent-gold">{{ $bid->formatted_transfer_fee }}</div>
                                                @elseif($bid->isPreContract())
                                                    <div class="text-sm font-semibold text-accent-green">{{ __('transfers.free_transfer') }}</div>
                                                @elseif($bid->isLoanIn())
                                                    <div class="text-sm font-semibold text-accent-green">{{ __('transfers.loan_no_fee') }}</div>
                                                @else
                                                    <div class="text-sm font-semibold text-accent-green">{{ __('finances.free') }}</div>
                                                @endif
                                                <div class="text-xs text-accent-gold">{{ __('transfers.response_next_matchday') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- ============================================= --}}
                            {{-- INCOMING AGREED TRANSFERS — green accent --}}
                            {{-- ============================================= --}}
                            @if($incomingAgreedTransfers->isNotEmpty())
                            <div class="border-l-4 border-l-accent-green pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.incoming_transfers') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.completing_when_window', ['window' => $game->getNextWindowName()]) }}</p>
                                <div class="space-y-3">
                                    @foreach($incomingAgreedTransfers as $transfer)
                                    <div class="bg-accent-green/10 border border-accent-green/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                @if($transfer->sellingTeam)
                                                <x-team-crest :team="$transfer->sellingTeam" class="w-10 h-10 shrink-0" />
                                                @endif
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $transfer->gamePlayer->player->name }} &larr; {{ $transfer->selling_team_name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $transfer->gamePlayer->position_name }} &middot; {{ $transfer->gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                        @if($transfer->offer_type === 'loan_in')
                                                            &middot; <span class="text-accent-green font-medium">{{ __('transfers.loans') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                @if($transfer->transfer_fee > 0)
                                                    <div class="text-xl font-bold text-accent-green">{{ $transfer->formatted_transfer_fee }}</div>
                                                @elseif($transfer->isPreContract())
                                                    <div class="text-sm font-semibold text-accent-green">{{ __('transfers.free_transfer') }}</div>
                                                @elseif($transfer->isLoanIn())
                                                    <div class="text-sm font-semibold text-accent-green">{{ __('transfers.loan_no_fee') }}</div>
                                                @else
                                                    <div class="text-sm font-semibold text-accent-green">{{ __('finances.free') }}</div>
                                                @endif
                                                <div class="text-xs text-accent-green">{{ __('transfers.deal_agreed') }}</div>
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
                            <x-section-card :title="__('transfers.active_loans_in')">
                                <x-slot name="badge">
                                    <span class="text-xs text-text-secondary">({{ $loansIn->count() }})</span>
                                </x-slot>
                                <div class="divide-y divide-border-default">
                                    @foreach($loansIn as $loan)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-team-crest :team="$loan->parentTeam" class="w-7 h-7 shrink-0" />
                                            <div class="min-w-0">
                                                <div class="font-medium text-sm text-text-primary truncate">{{ $loan->gamePlayer->name }}</div>
                                                <div class="text-xs text-text-muted">
                                                    {{ $loan->gamePlayer->position_name }} &middot; {{ $loan->gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                    &middot; {{ __('transfers.loaned_from', ['team_de' => $loan->parentTeam->nameWithDe()]) }}
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

                            {{-- ============================================= --}}
                            {{-- CONTEXT: Rejected Bids --}}
                            {{-- ============================================= --}}
                            @if($rejectedBids->isNotEmpty())
                            <div class="opacity-60">
                                <h4 class="font-heading text-sm font-semibold text-text-muted uppercase tracking-widest mb-3">{{ __('transfers.rejected_bids') }}</h4>
                                <div class="space-y-2">
                                    @foreach($rejectedBids as $bid)
                                    <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                            <div class="flex items-center gap-4">
                                                @if($bid->sellingTeam)
                                                <x-team-crest :team="$bid->sellingTeam" class="w-8 h-8 grayscale shrink-0" />
                                                @endif
                                                <div>
                                                    <div class="font-medium text-text-body">
                                                        {{ $bid->gamePlayer->player->name }}
                                                        <span class="text-text-secondary font-normal">{{ __('transfers.from') }}</span>
                                                        {{ $bid->sellingTeam?->name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-xs text-text-muted">
                                                        {{ $bid->gamePlayer->position_name }} &middot; {{ $bid->gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-bold text-accent-red line-through">{{ $bid->formatted_transfer_fee }}</div>
                                                <div class="text-xs text-accent-red">{{ __('transfers.bid_rejected') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- ============================================= --}}
                            {{-- FULL-WIDTH: Recent Signings --}}
                            {{-- ============================================= --}}
                            @if($recentSignings->isNotEmpty())
                            <x-section-card :title="__('transfers.recent_signings')">
                                <div class="divide-y divide-border-default">
                                    @foreach($recentSignings as $transfer)
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 px-5 py-2.5 text-sm">
                                            <div class="flex items-center gap-3">
                                                @if($transfer->sellingTeam)
                                                <x-team-crest :team="$transfer->sellingTeam" class="w-6 h-6 shrink-0" />
                                                @endif
                                                <span class="text-text-secondary">
                                                    {{ $transfer->gamePlayer->player->name }} &larr; {{ $transfer->sellingTeam?->name ?? 'Unknown' }}
                                                </span>
                                            </div>
                                            <span class="font-semibold text-accent-green">{{ $transfer->formatted_transfer_fee }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </x-section-card>
                            @endif

                    </div>

    </div>

    <x-scout-results-modal />

</x-app-layout>
