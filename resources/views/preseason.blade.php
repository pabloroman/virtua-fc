@php /** @var App\Models\Game $game */ @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}" class="w-12 h-12">
                <div>
                    <h2 class="font-semibold text-xl text-slate-800">{{ __('game.preseason_title', ['season' => $game->season]) }}</h2>
                    <p class="text-sm text-slate-500">{{ $game->team->name }}</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Time Progress Bar --}}
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <span class="text-lg font-semibold text-slate-900">{{ $game->current_date->format('F j, Y') }}</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                            {{ __('game.summer_window_open') }}
                        </span>
                    </div>
                    <div class="text-sm text-slate-600">
                        @if($firstMatch)
                            {{ __('game.season_starts', ['date' => $firstMatch->scheduled_date->format('M j')]) }}
                            ({{ trans_choice('game.weeks_remaining', $weeksRemaining, ['count' => $weeksRemaining]) }})
                        @else
                            {{ trans_choice('game.weeks_remaining', $weeksRemaining, ['count' => $weeksRemaining]) }}
                        @endif
                    </div>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2">
                    <div class="bg-sky-500 h-2 rounded-full transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                </div>
                <div class="flex justify-between text-xs text-slate-400 mt-1">
                    <span>{{ __('game.july_1') }}</span>
                    <span>{{ __('game.week_of', ['current' => $currentWeek, 'total' => $totalWeeks]) }}</span>
                    <span>{{ __('game.season_start') }}</span>
                </div>
            </div>

            {{-- Budget Allocation Banner (if not yet allocated) --}}
            @if(!$game->currentInvestment)
            <a href="{{ route('game.budget', $game->id) }}" class="block mb-6">
                <div class="bg-gradient-to-r from-sky-500 to-sky-600 rounded-lg shadow-sm p-6 text-white hover:from-sky-600 hover:to-sky-700 transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-xl mb-1">{{ __('game.allocate_budget') }}</h3>
                            <p class="text-sky-100">
                                {{ __('game.allocate_budget_desc', ['amount' => $game->currentFinances?->formatted_available_surplus ?? '€0']) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 text-white">
                            <span class="font-semibold">{{ __('game.set_up_budget') }}</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </a>
            @endif

            {{-- Financial Projections Cards --}}
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="text-sm text-slate-500 mb-1">{{ __('game.projected_position') }}</div>
                    <div class="text-2xl font-bold text-slate-900">{{ $game->currentFinances?->projected_position ?? '-' }}</div>
                </div>
                <a href="{{ route('game.budget', $game->id) }}" class="bg-white rounded-lg shadow-sm p-4 hover:bg-slate-50 transition">
                    <div class="text-sm text-slate-500 mb-1">{{ __('game.available_surplus') }}</div>
                    <div class="text-2xl font-bold text-slate-900">{{ $game->currentFinances?->formatted_available_surplus ?? '€0' }}</div>
                    @if($game->currentInvestment)
                    <div class="text-xs text-sky-600 mt-1">{{ __('game.click_to_adjust') }}</div>
                    @else
                    <div class="text-xs text-sky-600 mt-1">{{ __('game.click_to_allocate') }}</div>
                    @endif
                </a>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="text-sm text-slate-500 mb-1">{{ __('game.transfer_budget') }}</div>
                    <div class="text-2xl font-bold text-slate-900">{{ $game->currentInvestment?->formatted_transfer_budget ?? '€0' }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="text-sm text-slate-500 mb-1">{{ __('game.projected_wages') }}</div>
                    <div class="text-2xl font-bold text-slate-900">{{ $game->currentFinances?->formatted_projected_wages ?? '€0' }}</div>
                </div>
            </div>

            {{-- Main Content: Offers + Squad --}}
            <div class="grid grid-cols-12 gap-6">

                {{-- Left Column: Incoming Offers --}}
                <div class="col-span-5">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
                            {{ __('game.offers_for_your_players') }}
                            @if($incomingOffers->isNotEmpty())
                                <span class="text-sm font-normal text-slate-500">({{ $incomingOffers->count() }})</span>
                            @endif
                        </h3>

                        @if($incomingOffers->isEmpty())
                            <div class="text-center py-8 text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p class="text-sm">{{ __('game.no_offers_yet') }}</p>
                                <p class="text-xs mt-1">{{ __('game.list_players_hint') }}</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($incomingOffers as $offer)
                                    <div class="border border-amber-200 bg-amber-50 rounded-lg p-4">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ $offer->gamePlayer->player->name }}</div>
                                                <div class="text-sm text-slate-600">{{ $offer->offeringTeam->name }}</div>
                                                <div class="text-lg font-bold text-green-600 mt-1">{{ $offer->formatted_transfer_fee }}</div>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <form action="{{ route('game.transfers.accept', [$game->id, $offer->id]) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700">
                                                        {{ __('app.accept') }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('game.transfers.reject', [$game->id, $offer->id]) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-slate-600 bg-slate-200 rounded hover:bg-slate-300">
                                                        {{ __('app.reject') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        @if($offer->expires_at)
                                            <div class="text-xs text-slate-500 mt-2">
                                                {{ __('game.expires', ['date' => $offer->expires_at->format('M j')]) }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Youth Academy Prospects --}}
                    @if($youthProspects->isNotEmpty())
                        <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
                            <h3 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                                <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                                {{ __('game.youth_academy_prospects') }}
                                <span class="text-sm font-normal text-slate-500">({{ $youthProspects->count() }})</span>
                            </h3>
                            <p class="text-sm text-slate-500 mb-4">{{ __('game.youth_prospects_desc') }}</p>
                            <div class="space-y-3">
                                @foreach($youthProspects as $prospect)
                                    <div class="border border-purple-200 bg-purple-50 rounded-lg p-4">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ $prospect->player->name }}</div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="px-1.5 py-0.5 text-xs font-medium rounded {{ $prospect->position_display['bg'] }} {{ $prospect->position_display['text'] }}">
                                                        {{ $prospect->position_display['abbreviation'] }}
                                                    </span>
                                                    <span class="text-sm text-slate-600">{{ __('app.age') }} {{ $prospect->age }}</span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm text-slate-500">{{ __('game.potential') }}</div>
                                                <div class="flex items-center gap-1 mt-0.5">
                                                    @for($i = 0; $i < 5; $i++)
                                                        @if($prospect->potential >= 80 - ($i * 5))
                                                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                                        @else
                                                            <span class="w-2 h-2 rounded-full bg-slate-200"></span>
                                                        @endif
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4 mt-3 text-xs text-slate-500">
                                            <span>{{ __('app.tech') }}: {{ $prospect->game_technical_ability }}</span>
                                            <span>{{ __('app.phys') }}: {{ $prospect->game_physical_ability }}</span>
                                            <span>{{ __('app.contract') }}: {{ $prospect->contract_until->format('Y') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Loan Returns --}}
                    @if($loanReturns->isNotEmpty())
                        <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
                            <h3 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                                <span class="w-2 h-2 bg-sky-500 rounded-full"></span>
                                {{ __('game.loan_returns') }}
                                <span class="text-sm font-normal text-slate-500">({{ $loanReturns->count() }})</span>
                            </h3>
                            <p class="text-sm text-slate-500 mb-4">{{ __('game.loan_returns_desc') }}</p>
                            <div class="space-y-3">
                                @foreach($loanReturns as $loan)
                                    <div class="border border-sky-200 bg-sky-50 rounded-lg p-4">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ $loan->gamePlayer->player->name }}</div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="px-1.5 py-0.5 text-xs font-medium rounded {{ $loan->gamePlayer->position_display['bg'] }} {{ $loan->gamePlayer->position_display['text'] }}">
                                                        {{ $loan->gamePlayer->position_display['abbreviation'] }}
                                                    </span>
                                                    <span class="text-sm text-slate-600">{{ __('app.age') }} {{ $loan->gamePlayer->age }}</span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-xs text-slate-500">{{ __('game.returned_from') }}</div>
                                                <div class="text-sm font-medium text-slate-700 mt-0.5">{{ $loan->loanTeam->name }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Completed Transfers --}}
                    @if($transfersIn->isNotEmpty() || $transfersOut->isNotEmpty())
                        <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
                            <h3 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                {{ __('game.completed_this_window') }}
                            </h3>
                            <div class="space-y-2 text-sm">
                                @foreach($transfersIn as $transfer)
                                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                                        <div class="flex items-center gap-2">
                                            <span class="text-green-600 font-medium">{{ __('game.in') }}</span>
                                            <span class="text-slate-900">{{ $transfer->gamePlayer->player->name }}</span>
                                            <span class="text-slate-400">({{ $transfer->selling_team_name ?? $transfer->offeringTeam?->name ?? '' }})</span>
                                        </div>
                                        <span class="font-medium text-slate-700">
                                            @if($transfer->offer_type === 'loan_in')
                                                {{ __('game.loan') }}
                                            @else
                                                {{ $transfer->formatted_transfer_fee }}
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                                @foreach($transfersOut as $transfer)
                                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                                        <div class="flex items-center gap-2">
                                            <span class="text-red-600 font-medium">{{ __('game.out') }}</span>
                                            <span class="text-slate-900">{{ $transfer->gamePlayer->player->name }}</span>
                                            <span class="text-slate-400">({{ $transfer->offeringTeam->name }})</span>
                                        </div>
                                        <span class="font-medium text-green-600">+{{ $transfer->formatted_transfer_fee }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Right Column: Squad --}}
                <div class="col-span-7">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-lg text-slate-900">{{ __('game.your_squad') }}</h3>
                            <span class="text-sm text-slate-500">{{ $squad->count() }} {{ __('app.players') }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-slate-500 border-b border-slate-200">
                                        <th class="pb-2 font-medium">{{ __('app.player') }}</th>
                                        <th class="pb-2 font-medium">{{ __('app.pos') }}</th>
                                        <th class="pb-2 font-medium">{{ __('app.age') }}</th>
                                        <th class="pb-2 font-medium text-right">{{ __('app.value') }}</th>
                                        <th class="pb-2 font-medium text-right">{{ __('app.wage') }}</th>
                                        <th class="pb-2 font-medium text-center">{{ __('app.fitness') }}</th>
                                        <th class="pb-2 font-medium text-right">{{ __('app.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($squad as $player)
                                        @php
                                            $hasOffer = in_array($player->id, $playersWithOffers);
                                            $isListed = $player->isTransferListed();
                                        @endphp
                                        <tr class="border-b border-slate-100 {{ $hasOffer ? 'bg-amber-50' : '' }}">
                                            <td class="py-2.5">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-slate-900">{{ $player->name }}</span>
                                                    @if($hasOffer)
                                                        <span class="w-2 h-2 bg-amber-500 rounded-full" title="{{ __('game.offer_received') }}"></span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-2.5">
                                                <span class="px-1.5 py-0.5 text-xs font-medium rounded {{ $player->position_display['bg'] }} {{ $player->position_display['text'] }}">
                                                    {{ $player->position_display['abbreviation'] }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 text-slate-600">{{ $player->age }}</td>
                                            <td class="py-2.5 text-right text-slate-900">{{ $player->formatted_market_value }}</td>
                                            <td class="py-2.5 text-right text-slate-600">{{ $player->formatted_wage }}{{ __('app.per_year') }}</td>
                                            <td class="py-2.5">
                                                <div class="flex items-center justify-center">
                                                    <div class="w-12 bg-slate-200 rounded-full h-1.5">
                                                        <div class="h-1.5 rounded-full {{ $player->fitness >= 80 ? 'bg-green-500' : ($player->fitness >= 60 ? 'bg-amber-500' : 'bg-red-500') }}"
                                                             style="width: {{ $player->fitness }}%"></div>
                                                    </div>
                                                    <span class="ml-2 text-xs text-slate-500">{{ $player->fitness }}</span>
                                                </div>
                                            </td>
                                            <td class="py-2.5 text-right">
                                                @if($hasOffer)
                                                    <span class="text-xs text-amber-600 font-medium">{{ __('game.offer_received') }}</span>
                                                @elseif($isListed)
                                                    <span class="text-xs text-sky-600 font-medium">{{ __('game.listed') }}</span>
                                                @else
                                                    <form action="{{ route('game.transfers.list', [$game->id, $player->id]) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-xs text-slate-500 hover:text-sky-600">
                                                            {{ __('game.list_for_sale') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Bottom Action Bar --}}
            <div class="mt-6 bg-white rounded-lg shadow-sm p-4 flex items-center justify-between">
                <div class="text-sm text-slate-600">
                    @if($currentWeek === 0)
                        {{ __('game.advance_week_hint') }}
                    @elseif($weeksRemaining <= 2)
                        <span class="text-amber-600 font-medium">{{ __('game.window_closing_soon') }}</span>
                    @else
                        {{ __('game.build_squad_hint') }}
                    @endif
                </div>
                <form action="{{ route('game.preseason.advance', $game->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 text-white font-medium rounded-lg hover:bg-sky-700 transition-colors">
                        {{ __('game.advance_week') }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
