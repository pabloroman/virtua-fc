@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
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

            {{-- Back link --}}
            <div class="mb-4">
                <a href="{{ route('game.scouting', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">&larr; {{ __('transfers.back_to_results') }}</a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8">
                    {{-- Player Header --}}
                    <div class="flex items-start justify-between mb-8">
                        <div class="flex items-center gap-4">
                            <div>
                                <h3 class="font-semibold text-2xl text-slate-900">{{ $player->name }}</h3>
                                <div class="flex items-center gap-3 mt-1 text-sm text-slate-600">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded {{ $player->position_display['bg'] }} {{ $player->position_display['text'] }}">
                                        {{ $player->position_display['abbreviation'] }}
                                    </span>
                                    <span>{{ $player->position }}</span>
                                    <span>&middot;</span>
                                    <span>{{ $player->age }} {{ __('transfers.years') }}</span>
                                    @if($player->nationality_flag)
                                        <span>&middot;</span>
                                        <img src="/flags/{{ $player->nationality_flag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm inline">
                                        <span>{{ $player->nationality_flag['name'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <img src="{{ $player->team->image }}" class="w-10 h-10">
                            <div class="text-right">
                                <div class="font-semibold text-slate-900">{{ $player->team->name }}</div>
                                <div class="text-sm text-slate-500">{{ __('transfers.contract_until') }} {{ $player->contract_expiry_year ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Scouting Report --}}
                    <div class="grid grid-cols-2 gap-6 mb-8">
                        {{-- Abilities --}}
                        <div class="border rounded-lg p-4">
                            <h4 class="font-semibold text-sm text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.scouting_assessment') }}</h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">{{ __('transfers.technical') }}</span>
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-slate-200 rounded-full h-2">
                                            <div class="bg-sky-500 h-2 rounded-full" style="width: {{ (($detail['tech_range'][0] + $detail['tech_range'][1]) / 2) }}%"></div>
                                        </div>
                                        <span class="text-sm font-semibold text-slate-700 w-16 text-right">{{ $detail['tech_range'][0] }}-{{ $detail['tech_range'][1] }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">{{ __('transfers.physical') }}</span>
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-slate-200 rounded-full h-2">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ (($detail['phys_range'][0] + $detail['phys_range'][1]) / 2) }}%"></div>
                                        </div>
                                        <span class="text-sm font-semibold text-slate-700 w-16 text-right">{{ $detail['phys_range'][0] }}-{{ $detail['phys_range'][1] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Financials --}}
                        <div class="border rounded-lg p-4">
                            <h4 class="font-semibold text-sm text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.financial_details') }}</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-600">{{ __('transfers.market_value') }}</span>
                                    <span class="font-semibold text-slate-900">{{ $player->formatted_market_value }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-600">{{ __('transfers.estimated_asking_price') }}</span>
                                    <span class="font-bold text-lg text-slate-900">{{ $detail['formatted_asking_price'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-600">{{ __('transfers.wage_demand') }}</span>
                                    <span class="font-semibold text-slate-900">{{ $detail['formatted_wage_demand'] }}/{{ __('transfers.year_abbr') }}</span>
                                </div>
                                <div class="border-t pt-2 mt-2">
                                    <div class="flex justify-between">
                                        <span class="text-slate-600">{{ __('transfers.your_transfer_budget') }}</span>
                                        <span class="font-semibold {{ $detail['can_afford_fee'] ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $detail['formatted_transfer_budget'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Affordability Warnings --}}
                    @if(!$detail['can_afford_fee'])
                        <div class="mb-6 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                            {{ __('transfers.transfer_fee_exceeds_budget') }}
                        </div>
                    @endif
                    @if(!$detail['can_afford_wage'])
                        <div class="mb-6 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
                            {{ __('transfers.wage_demand_warning') }}
                        </div>
                    @endif

                    {{-- Existing Offer Status --}}
                    @if($existingOffer)
                        <div class="mb-6 p-4 border rounded-lg {{ $existingOffer->isAgreed() ? 'bg-green-50 border-green-200' : 'bg-sky-50 border-sky-200' }}">
                            @if($existingOffer->isAgreed())
                                <div class="flex items-center gap-2 text-green-700 font-semibold">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ __('transfers.deal_agreed') }}!
                                </div>
                                <p class="text-sm text-green-600 mt-1">
                                    @if($existingOffer->offer_type === 'loan_in')
                                        {{ __('transfers.loan_deal_agreed', ['player' => $player->name]) }}
                                        {{ $game->isTransferWindowOpen() ? __('transfers.immediately') : __('transfers.next_transfer_window') }}.
                                    @else
                                        {{ __('transfers.transfer_fee') }}: {{ $existingOffer->formatted_transfer_fee }}.
                                        {{ __('transfers.player_will_join', ['player' => $player->name]) }}
                                        {{ $game->isTransferWindowOpen() ? __('transfers.immediately') : __('transfers.next_transfer_window') }}.
                                    @endif
                                </p>
                            @elseif($existingOffer->isPending() && $existingOffer->asking_price && $existingOffer->transfer_fee < $existingOffer->asking_price)
                                {{-- Counter-offer --}}
                                <div class="font-semibold text-sky-700">{{ __('transfers.counter_offer_received') }}</div>
                                <p class="text-sm text-sky-600 mt-1">
                                    {{ __('transfers.team_counter_with', ['team' => $player->team->name, 'amount' => \App\Support\Money::format($existingOffer->asking_price), 'your_bid' => $existingOffer->formatted_transfer_fee]) }}
                                </p>
                                <div class="flex gap-2 mt-3">
                                    <form method="post" action="{{ route('game.scouting.counter.accept', [$game->id, $existingOffer->id]) }}">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                            {{ __('transfers.accept_counter') }}
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="font-semibold text-sky-700">{{ __('transfers.bid_pending') }}</div>
                                <p class="text-sm text-sky-600 mt-1">{{ __('transfers.your_bid_being_considered', ['amount' => $existingOffer->formatted_transfer_fee]) }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    @if(!$existingOffer || (!$existingOffer->isAgreed() && !$existingOffer->isPending()))
                        <div class="grid grid-cols-2 gap-6">
                            {{-- Transfer Bid --}}
                            <div class="border rounded-lg p-6">
                                <h4 class="font-semibold text-slate-900 mb-3">{{ __('transfers.make_transfer_offer') }}</h4>
                                <p class="text-sm text-slate-600 mb-4">{{ __('transfers.submit_bid_description') }}</p>
                                @if($detail['can_afford_fee'])
                                    <form method="post" action="{{ route('game.scouting.bid', [$game->id, $player->id]) }}">
                                        @csrf
                                        <div class="mb-4">
                                            <label for="bid_amount" class="block text-sm font-medium text-slate-700 mb-1">{{ __('transfers.your_bid_euros') }}</label>
                                            <input type="number" name="bid_amount" id="bid_amount" min="0" step="100000"
                                                   value="{{ (int)($detail['asking_price'] / 100) }}"
                                                   class="w-full border-slate-300 rounded-lg shadow-sm text-sm focus:ring-sky-500 focus:border-sky-500">
                                            <p class="text-xs text-slate-500 mt-1">{{ __('transfers.asking_price') }}: {{ $detail['formatted_asking_price'] }}</p>
                                        </div>
                                        <button type="submit" class="w-full px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                            {{ __('transfers.submit_bid') }}
                                        </button>
                                    </form>
                                @else
                                    <p class="text-sm text-red-600">{{ __('transfers.insufficient_transfer_budget') }}</p>
                                @endif
                            </div>

                            {{-- Loan Request --}}
                            <div class="border rounded-lg p-6">
                                <h4 class="font-semibold text-slate-900 mb-3">{{ __('transfers.request_loan') }}</h4>
                                <p class="text-sm text-slate-600 mb-4">{{ __('transfers.request_loan_description') }}</p>
                                <form method="post" action="{{ route('game.scouting.loan', [$game->id, $player->id]) }}">
                                    @csrf
                                    <button type="submit" class="w-full px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                        {{ __('transfers.request_loan') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
