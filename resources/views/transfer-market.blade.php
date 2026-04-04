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

        <div class="mt-6" x-data="{ posFilter: 'all' }">
            @if(!$isTransferWindow)
                {{-- Window closed state --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-text-muted mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                    <p class="text-text-secondary text-sm">{{ __('transfers.market_closed') }}</p>
                </div>
            @elseif($listings->isEmpty())
                {{-- No listings --}}
                <div class="bg-surface-800 border border-border-default rounded-xl p-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-text-muted mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                    <p class="text-text-secondary text-sm">{{ __('transfers.market_empty') }}</p>
                </div>
            @else
                {{-- Position filter pills --}}
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach([
                        'all' => __('transfers.explore_filter_all'),
                        'gk' => __('squad.goalkeepers'),
                        'def' => __('squad.defenders'),
                        'mid' => __('squad.midfielders'),
                        'fwd' => __('squad.forwards'),
                    ] as $filterKey => $filterLabel)
                        <button
                            @click="posFilter = '{{ $filterKey }}'"
                            :class="posFilter === '{{ $filterKey }}'
                                ? 'bg-accent-blue/15 text-accent-blue border-accent-blue/30'
                                : 'bg-surface-800 text-text-secondary border-border-default hover:bg-surface-700'"
                            class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors"
                        >
                            {{ $filterLabel }}
                        </button>
                    @endforeach
                </div>

                {{-- Budget info --}}
                <div class="mb-4 text-xs text-text-secondary">
                    {{ __('transfers.budget_available') }}: <span class="font-semibold text-text-primary">{{ \App\Support\Money::format($availableBudget) }}</span>
                </div>

                {{-- Desktop table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border-default text-text-muted text-xs uppercase tracking-wider">
                                <th class="text-left py-3 px-3 font-medium">{{ __('transfers.transfer_activity_player') }}</th>
                                <th class="text-center py-3 px-2 font-medium">{{ __('transfers.transfer_activity_position') }}</th>
                                <th class="text-center py-3 px-2 font-medium">{{ __('transfers.transfer_activity_age') }}</th>
                                <th class="text-center py-3 px-2 font-medium">OVR</th>
                                <th class="text-right py-3 px-2 font-medium">{{ __('transfers.market_value') }}</th>
                                <th class="text-right py-3 px-2 font-medium">{{ __('transfers.market_asking_price') }}</th>
                                <th class="text-left py-3 px-3 font-medium">{{ __('transfers.explore_search_team') }}</th>
                                <th class="text-right py-3 px-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($listings as $listing)
                                @php
                                    $gp = $listing->gamePlayer;
                                    $posGroup = strtolower(match($gp->position_group) {
                                        'Goalkeeper' => 'gk',
                                        'Defender' => 'def',
                                        'Midfielder' => 'mid',
                                        'Forward' => 'fwd',
                                        default => 'mid',
                                    });
                                    $posDisp = $gp->position_display;
                                    $playerInfo = \Illuminate\Support\Js::from([
                                        'age' => $gp->age($game->current_date),
                                        'position' => $posDisp['abbreviation'],
                                        'positionBg' => $posDisp['bg'],
                                        'positionText' => $posDisp['text'],
                                        'marketValue' => $gp->formatted_market_value,
                                        'contractYear' => $gp->contract_expiry_year,
                                    ]);
                                @endphp
                                <tr x-show="posFilter === 'all' || posFilter === '{{ $posGroup }}'"
                                    class="border-b border-border-default/50 hover:bg-surface-700/50 transition-colors">
                                    {{-- Player name --}}
                                    <td class="py-3 px-3">
                                        <span class="font-medium text-text-primary">{{ $gp->name }}</span>
                                    </td>

                                    {{-- Position --}}
                                    <td class="py-3 px-2 text-center">
                                        <x-position-badge :position="$gp->position" size="sm" />
                                    </td>

                                    {{-- Age --}}
                                    <td class="py-3 px-2 text-center text-text-secondary">{{ $gp->age($game->current_date) }}</td>

                                    {{-- OVR --}}
                                    <td class="py-3 px-2 text-center">
                                        <x-rating-badge :value="$gp->overall_score" size="sm" />
                                    </td>

                                    {{-- Market Value --}}
                                    <td class="py-3 px-2 text-right text-text-secondary">{{ $gp->formatted_market_value }}</td>

                                    {{-- Asking Price --}}
                                    <td class="py-3 px-2 text-right font-medium text-text-primary">{{ \App\Support\Money::format($listing->asking_price) }}</td>

                                    {{-- Team --}}
                                    <td class="py-3 px-3">
                                        @if($gp->team)
                                            <div class="flex items-center gap-1.5">
                                                <x-team-crest :team="$gp->team" class="w-4 h-4 shrink-0" />
                                                <span class="text-text-secondary truncate">{{ $gp->team->name }}</span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Bid button --}}
                                    <td class="py-3 px-3 text-right">
                                        @if($availableBudget > 0)
                                            <x-primary-button size="xs"
                                                @click="$dispatch('open-negotiation', {
                                                    playerName: {{ \Illuminate\Support\Js::from($gp->name) }},
                                                    negotiateUrl: {{ \Illuminate\Support\Js::from(route('game.negotiate.transfer', [$game->id, $gp->id])) }},
                                                    mode: 'transfer_fee',
                                                    phase: 'club_fee',
                                                    chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_transfer_title')) }},
                                                    playerInfo: {{ $playerInfo }}
                                                })">
                                                {{ __('transfers.market_bid') }}
                                            </x-primary-button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards --}}
                <div class="md:hidden space-y-3">
                    @foreach($listings as $listing)
                        @php
                            $gp = $listing->gamePlayer;
                            $posGroup = strtolower(match($gp->position_group) {
                                'Goalkeeper' => 'gk',
                                'Defender' => 'def',
                                'Midfielder' => 'mid',
                                'Forward' => 'fwd',
                                default => 'mid',
                            });
                            $posDisp = $gp->position_display;
                            $playerInfo = \Illuminate\Support\Js::from([
                                'age' => $gp->age($game->current_date),
                                'position' => $posDisp['abbreviation'],
                                'positionBg' => $posDisp['bg'],
                                'positionText' => $posDisp['text'],
                                'marketValue' => $gp->formatted_market_value,
                                'contractYear' => $gp->contract_expiry_year,
                            ]);
                        @endphp
                        <div x-show="posFilter === 'all' || posFilter === '{{ $posGroup }}'"
                             class="bg-surface-800 border border-border-default rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-position-badge :position="$gp->position" size="sm" />
                                    <span class="font-medium text-text-primary truncate">{{ $gp->name }}</span>
                                </div>
                                <x-rating-badge :value="$gp->overall_score" size="sm" />
                            </div>

                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs mb-3">
                                <div class="flex justify-between">
                                    <span class="text-text-muted">{{ __('transfers.transfer_activity_age') }}</span>
                                    <span class="text-text-secondary">{{ $gp->age($game->current_date) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-text-muted">{{ __('transfers.explore_search_team') }}</span>
                                    <span class="text-text-secondary truncate ml-1">{{ $gp->team?->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-text-muted">{{ __('transfers.market_value') }}</span>
                                    <span class="text-text-secondary">{{ $gp->formatted_market_value }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-text-muted">{{ __('transfers.market_asking_price') }}</span>
                                    <span class="font-medium text-text-primary">{{ \App\Support\Money::format($listing->asking_price) }}</span>
                                </div>
                            </div>

                            @if($availableBudget > 0)
                                <x-primary-button size="xs" class="w-full justify-center"
                                    @click="$dispatch('open-negotiation', {
                                        playerName: {{ \Illuminate\Support\Js::from($gp->name) }},
                                        negotiateUrl: {{ \Illuminate\Support\Js::from(route('game.negotiate.transfer', [$game->id, $gp->id])) }},
                                        mode: 'transfer_fee',
                                        phase: 'club_fee',
                                        chatTitle: {{ \Illuminate\Support\Js::from(__('transfers.chat_transfer_title')) }},
                                        playerInfo: {{ $playerInfo }}
                                    })">
                                    {{ __('transfers.market_bid') }}
                                </x-primary-button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <x-negotiation-chat-modal />
</x-app-layout>
