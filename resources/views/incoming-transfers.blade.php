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
                            ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => true],
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
                        $hasLeftContent = $activeNegotiations->isNotEmpty()
                            || $incomingAgreedTransfers->isNotEmpty()
                            || $loansIn->isNotEmpty()
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
                            {{-- ACTIVE NEGOTIATIONS — amber/gold accent --}}
                            {{-- ============================================= --}}
                            @if($activeNegotiations->isNotEmpty())
                            <div x-data class="border-l-4 border-l-accent-gold pl-5">
                                <h4 class="font-semibold text-lg text-text-primary mb-1">{{ __('transfers.active_negotiations') }}</h4>
                                <p class="text-sm text-text-muted mb-3">{{ __('transfers.active_negotiations_description') }}</p>
                                <div class="space-y-3">
                                    @foreach($activeNegotiations as $offer)
                                    <div class="bg-accent-gold/10 border border-accent-gold/20 rounded-xl p-4">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div class="flex items-center gap-4">
                                                @if($offer->sellingTeam)
                                                <x-team-crest :team="$offer->sellingTeam" class="w-10 h-10 shrink-0" />
                                                @endif
                                                <div>
                                                    <div class="font-semibold text-text-primary">
                                                        {{ $offer->gamePlayer->player->name }} &larr; {{ $offer->selling_team_name ?? 'Unknown' }}
                                                    </div>
                                                    <div class="text-sm text-text-secondary">
                                                        {{ $offer->gamePlayer->position_name }} &middot; {{ $offer->gamePlayer->age($game->current_date) }} {{ __('app.years') }}
                                                    </div>
                                                    <div class="mt-1">
                                                        @if($offer->isFeeAgreed())
                                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-accent-green bg-accent-green/10 px-2 py-0.5 rounded-full">{{ __('transfers.chat_club_agreement') }}</span>
                                                        @elseif($offer->asking_price && $offer->asking_price > $offer->transfer_fee)
                                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-accent-gold bg-accent-gold/10 px-2 py-0.5 rounded-full">{{ __('transfers.counter_offer_received') }}</span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-accent-blue bg-accent-blue/10 px-2 py-0.5 rounded-full">{{ __('transfers.bid_awaiting_response') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="text-right mr-2">
                                                    @if($offer->isFeeAgreed())
                                                        <div class="text-lg font-bold text-accent-green">{{ $offer->formatted_transfer_fee }}</div>
                                                    @elseif($offer->asking_price && $offer->asking_price > $offer->transfer_fee)
                                                        <div class="text-lg font-bold text-accent-gold">{{ $offer->formatted_asking_price }}</div>
                                                    @else
                                                        <div class="text-lg font-bold text-text-primary">{{ $offer->formatted_transfer_fee }}</div>
                                                    @endif
                                                </div>
                                                <div class="flex gap-2">
                                                    @php
                                                        $gp = $offer->gamePlayer;
                                                        $posDisp = $gp->position_display;
                                                        $negotiationDetail = \Illuminate\Support\Js::from([
                                                            'playerName' => $gp->player->name,
                                                            'negotiateUrl' => route('game.negotiate.transfer', [$game->id, $gp->id]),
                                                            'mode' => 'transfer_fee',
                                                            'phase' => 'club_fee',
                                                            'chatTitle' => __('transfers.chat_transfer_title'),
                                                            'playerInfo' => [
                                                                'age' => $gp->age($game->current_date),
                                                                'position' => $posDisp['abbreviation'],
                                                                'positionBg' => $posDisp['bg'],
                                                                'positionText' => $posDisp['text'],
                                                                'marketValue' => $gp->formatted_market_value,
                                                                'contractYear' => $gp->contract_expiry_year,
                                                            ],
                                                        ]);
                                                    @endphp
                                                    <x-primary-button size="xs" type="button" x-on:click="$dispatch('open-negotiation', {{ $negotiationDetail }})">
                                                        {{ __('transfers.resume_negotiation') }}
                                                    </x-primary-button>
                                                    <form method="post" action="{{ route('game.transfers.withdraw', [$game->id, $offer->id]) }}">
                                                        @csrf
                                                        <x-action-button color="red" type="submit" onclick="return confirm({{ \Illuminate\Support\Js::from(__('transfers.confirm_withdraw')) }})">
                                                            {{ __('transfers.withdraw_offer') }}
                                                        </x-action-button>
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
                                                    {{ $transfer->gamePlayer->player->name }} &larr; {{ $transfer->sellingTeam?->name ?? __('squad.free_transfer') }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-3 md:text-right">
                                                <span class="text-xs text-text-muted">{{ $transfer->resolved_at->format('d M Y') }}</span>
                                                <span class="font-semibold text-accent-green">{{ $transfer->formatted_transfer_fee }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </x-section-card>
                            @endif

                    </div>

    </div>

    <x-scout-results-modal />
    <x-negotiation-chat-modal />

</x-app-layout>
