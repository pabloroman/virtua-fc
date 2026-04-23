@php /** @var App\Models\Game $game */ @endphp

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
        <x-section-nav :items="[
            ['href' => route('game.transfers', $game->id), 'label' => __('transfers.incoming'), 'active' => false],
            ['href' => route('game.transfers.outgoing', $game->id), 'label' => __('transfers.outgoing'), 'active' => false, 'badge' => $salidaBadgeCount > 0 ? $salidaBadgeCount : null],
            ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting_tab'), 'active' => false],
            ['href' => route('game.explore', $game->id), 'label' => __('transfers.explore_tab'), 'active' => false],
            ['href' => route('game.transfers.market', $game->id), 'label' => __('transfers.market_tab'), 'active' => true],
        ]" />

        <div class="mt-6">
            @if(!$isTransferWindow)
                {{-- Window closed banner — listings remain browseable; agreed bids complete on next window open --}}
                <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-accent-blue/10 border border-accent-blue/20 rounded-lg text-sm text-accent-blue">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ __('transfers.market_window_closed_notice') }}</span>
                </div>
            @endif

            @if($listings->isEmpty())
                {{-- No listings --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-text-muted mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                    <p class="text-text-secondary text-sm">{{ __('transfers.market_empty') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-border-default">
                                <th class="py-2.5 pl-4 w-12"></th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider"></th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell">{{ __('transfers.explore_age') }}</th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell">{{ __('transfers.explore_overall') }}</th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('transfers.explore_value') }}</th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell">{{ __('transfers.explore_contract_year') }}</th>
                                <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-right hidden md:table-cell">{{ __('transfers.market_asking_price') }}</th>
                                <th class="py-2.5 w-10"></th>
                                <th class="py-2.5 pr-4 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($listings as $listing)
                                <x-explore-player-row
                                    :player="$listing->gamePlayer"
                                    :game="$game"
                                    :show-team="true"
                                    team-placement="inline"
                                    :show-ovr="true"
                                    :show-contract="true"
                                    :asking-price="$listing->asking_price" />
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <x-negotiation-chat-modal />
</x-app-layout>
