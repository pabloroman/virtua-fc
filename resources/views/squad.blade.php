@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ __('squad.title', ['team' => $game->team->name]) }}</h3>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('game.squad.contracts', $game->id) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium {{ $expiringContractsCount > 0 ? 'text-red-600 bg-red-50 hover:bg-red-100' : 'text-slate-600 bg-slate-50 hover:bg-slate-100' }} rounded-lg transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ __('squad.contracts') }}
                                @if($expiringContractsCount > 0)
                                <span class="ml-1.5 px-1.5 py-0.5 text-xs font-bold bg-red-600 text-white rounded-full">{{ $expiringContractsCount }}</span>
                                @endif
                            </a>
                            <a href="{{ route('game.squad.development', $game->id) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                {{ __('squad.development') }}
                            </a>
                            <a href="{{ route('game.squad.stats', $game->id) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-600 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                {{ __('squad.stats') }}
                            </a>
                        </div>
                    </div>

                    <table class="w-full text-sm">
                        <thead class="text-left border-b">
                            <tr>
                                <th class="font-semibold py-2 w-10"></th>
                                <th class="font-semibold py-2">{{ __('app.name') }}</th>
                                <th class="font-semibold py-2 text-center w-12">{{ __('app.country') }}</th>
                                <th class="font-semibold py-2 text-center w-12">{{ __('app.age') }}</th>

                                <th class="font-semibold py-2 pr-4 text-right w-20">{{ __('app.value') }}</th>
                                <th class="font-semibold py-2 pr-4 text-right w-20">{{ __('app.wage') }}</th>
                                <th class="font-semibold py-2 pr-4 text-right w-20">{{ __('app.contract') }}</th>

                                <th class="font-semibold py-2 text-center w-12">{{ __('squad.technical') }}</th>
                                <th class="font-semibold py-2 text-center w-12">{{ __('squad.physical') }}</th>
                                <th class="font-semibold py-2 text-center w-12">{{ __('squad.fitness') }}</th>
                                <th class="font-semibold py-2 text-center w-12">{{ __('squad.morale') }}</th>
                                <th class="font-semibold py-2 text-center w-12">{{ __('squad.overall') }}</th>
                                <th class="font-semibold py-2 text-right w-24">{{ __('app.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([
                                ['name' => __('squad.goalkeepers'), 'players' => $goalkeepers],
                                ['name' => __('squad.defenders'), 'players' => $defenders],
                                ['name' => __('squad.midfielders'), 'players' => $midfielders],
                                ['name' => __('squad.forwards'), 'players' => $forwards],
                            ] as $group)
                                @if($group['players']->isNotEmpty())
                                    <tr class="bg-slate-200">
                                        <td colspan="14" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                            {{ $group['name'] }}
                                        </td>
                                    </tr>
                                    @foreach($group['players'] as $gamePlayer)
                                        @php
                                            $nextMatchday = $game->current_matchday + 1;
                                            $isUnavailable = !$gamePlayer->isAvailable($game->current_date, $nextMatchday);
                                            $unavailabilityReason = $gamePlayer->getUnavailabilityReason($game->current_date, $nextMatchday);
                                            $positionDisplay = $gamePlayer->position_display;
                                        @endphp
                                        <tr class="border-b border-slate-200 @if($isUnavailable) text-slate-400 @endif hover:bg-slate-50">
                                            {{-- Position --}}
                                            <td class="py-2 text-center">
                                                <span x-data="" x-tooltip.raw="{{ $gamePlayer->position }}" class="inline-flex items-center justify-center w-8 h-8 rounded text-xs font-bold cursor-help {{ $positionDisplay['bg'] }} {{ $positionDisplay['text'] }}">
                                                    {{ $positionDisplay['abbreviation'] }}
                                                </span>
                                            </td>
                                            {{-- Name --}}
                                            <td class="py-2">
                                                <div class="font-medium text-slate-900 @if($isUnavailable) text-slate-400 @endif">
                                                    {{ $gamePlayer->player->name }}
                                                </div>
                                                @if($unavailabilityReason)
                                                    <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                                @endif
                                            </td>
                                            {{-- Nationality --}}
                                            <td class="py-2 text-center">
                                                @if($gamePlayer->nationality_flag)
                                                    <img src="/flags/{{ $gamePlayer->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded shadow-sm" title="{{ $gamePlayer->nationality_flag['name'] }}">
                                                @endif
                                            </td>
                                            {{-- Age --}}
                                            <td class="py-2 text-center">{{ $gamePlayer->player->age }}</td>

                                            {{-- Market Value --}}
                                            <td class="border-l border-slate-200 py-2 pr-4 text-right text-slate-600">{{ $gamePlayer->formatted_market_value }}</td>
                                            {{-- Annual Wage --}}
                                            <td class="py-2 pr-4 text-right text-slate-600">{{ $gamePlayer->formatted_wage }}</td>
                                            {{-- Contract --}}
                                            <td class="py-2 pr-4 text-center text-slate-600">
                                                @if($gamePlayer->contract_until)
                                                    @if($gamePlayer->isContractExpiring())
                                                        <span class="text-red-600 font-medium" title="Contract expiring">
                                                            {{ $gamePlayer->contract_expiry_year }}
                                                        </span>
                                                    @else
                                                        {{ $gamePlayer->contract_expiry_year }}
                                                    @endif
                                                @endif
                                            </td>

                                            {{-- Technical --}}
                                            <td class="border-l border-slate-200 py-2 text-center @if($gamePlayer->technical_ability >= 80) text-green-600 @elseif($gamePlayer->technical_ability >= 70) text-lime-600 @elseif($gamePlayer->technical_ability < 60) text-slate-400 @endif">
                                                {{ $gamePlayer->technical_ability }}
                                            </td>
                                            {{-- Physical --}}
                                            <td class="py-2 text-center @if($gamePlayer->physical_ability >= 80) text-green-600 @elseif($gamePlayer->physical_ability >= 70) text-lime-600 @elseif($gamePlayer->physical_ability < 60) text-slate-400 @endif">
                                                {{ $gamePlayer->physical_ability }}
                                            </td>
                                            {{-- Fitness --}}
                                            <td class="py-2 text-center">
                                                <span class="@if($gamePlayer->fitness >= 90) text-green-600 @elseif($gamePlayer->fitness >= 80) text-lime-600 @elseif($gamePlayer->fitness < 50) text-red-500 font-medium @elseif($gamePlayer->fitness < 70) text-yellow-600 @endif">
                                                    {{ $gamePlayer->fitness }}
                                                </span>
                                            </td>
                                            {{-- Morale --}}
                                            <td class="py-2 text-center">
                                                <span class="@if($gamePlayer->morale >= 85) text-green-600 @elseif($gamePlayer->morale >= 75) text-lime-600 @elseif($gamePlayer->morale < 50) text-red-500 font-medium @elseif($gamePlayer->morale < 65) text-yellow-600 @endif">
                                                    {{ $gamePlayer->morale }}
                                                </span>
                                            </td>
                                            {{-- Overall --}}
                                            <td class="py-2 text-center">
                                                <span class="font-bold @if($gamePlayer->overall_score >= 80) text-green-600 @elseif($gamePlayer->overall_score >= 70) text-lime-600 @elseif($gamePlayer->overall_score >= 60) text-yellow-600 @else text-slate-500 @endif">
                                                    {{ $gamePlayer->overall_score }}
                                                </span>
                                            </td>
                                            {{-- Actions --}}
                                            <td class="py-2 text-right">
                                                @if($gamePlayer->isRetiring())
                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-orange-700 bg-orange-100 rounded">
                                                        {{ __('squad.retiring') }}
                                                    </span>
                                                @elseif($gamePlayer->isLoanedIn($game->team_id))
                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-sky-700 bg-sky-100 rounded">
                                                        {{ __('squad.on_loan') }}
                                                    </span>
                                                @elseif($gamePlayer->hasPreContractAgreement())
                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-700 bg-red-100 rounded">
                                                        {{ __('squad.leaving_free') }}
                                                    </span>
                                                @elseif($gamePlayer->hasRenewalAgreed())
                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded">
                                                        {{ __('squad.renewed') }}
                                                    </span>
                                                @elseif($gamePlayer->hasAgreedTransfer())
                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded">
                                                        {{ __('squad.sale_agreed') }}
                                                    </span>
                                                @elseif($gamePlayer->isTransferListed())
                                                    <div class="flex items-center justify-end gap-2">
                                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-amber-700 bg-amber-100 rounded">
                                                            {{ __('squad.listed') }}
                                                        </span>
                                                        <div x-data="{ open: false }" class="relative">
                                                            <button @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100">
                                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                                                            </button>
                                                            <div x-show="open" @click.away="open = false" x-transition
                                                                 class="absolute right-0 mt-1 w-36 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-10">
                                                                <form method="post" action="{{ route('game.transfers.unlist', [$game->id, $gamePlayer->id]) }}">
                                                                    @csrf
                                                                    <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50">
                                                                        {{ __('squad.unlist_from_sale') }}
                                                                    </button>
                                                                </form>
                                                                @if($isTransferWindow)
                                                                <form method="post" action="{{ route('game.loans.out', [$game->id, $gamePlayer->id]) }}">
                                                                    @csrf
                                                                    <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-amber-600 hover:bg-amber-50">
                                                                        {{ __('squad.loan_out') }}
                                                                    </button>
                                                                </form>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div x-data="{ open: false }" class="relative inline-block">
                                                        <button @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100">
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                                                        </button>
                                                        <div x-show="open" @click.away="open = false" x-transition
                                                             class="absolute right-0 mt-1 w-36 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-10">
                                                            <form method="post" action="{{ route('game.transfers.list', [$game->id, $gamePlayer->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-sky-600 hover:bg-sky-50">
                                                                    {{ __('squad.list_for_sale') }}
                                                                </button>
                                                            </form>
                                                            @if($isTransferWindow)
                                                            <form method="post" action="{{ route('game.loans.out', [$game->id, $gamePlayer->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-amber-600 hover:bg-amber-50">
                                                                    {{ __('squad.loan_out') }}
                                                                </button>
                                                            </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                    </table>

                    {{-- Squad summary --}}
                    @php
                        $allPlayers = $goalkeepers->concat($defenders)->concat($midfielders)->concat($forwards);
                        $avgFitness = $allPlayers->avg('fitness');
                        $avgMorale = $allPlayers->avg('morale');
                        $lowFitnessCount = $allPlayers->filter(fn($p) => $p->fitness < 70)->count();
                        $lowMoraleCount = $allPlayers->filter(fn($p) => $p->morale < 65)->count();
                        $totalWageBill = $allPlayers->sum('annual_wage');
                        $formattedWageBill = \App\Support\Money::format($totalWageBill);
                    @endphp
                    <div class="pt-6 border-t">
                        <div class="flex flex-wrap gap-8 text-sm text-slate-600">
                            <div>
                                <span class="font-semibold text-slate-900">{{ $allPlayers->count() }}</span>
                                <span class="text-slate-400 ml-1">{{ __('app.players') }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400">{{ __('squad.wage_bill') }}:</span>
                                <span class="font-semibold text-slate-900">{{ $formattedWageBill }}{{ __('squad.per_year') }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-amber-100 text-amber-700">GK</span>
                                <span class="font-medium">{{ $goalkeepers->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-blue-100 text-blue-700">DF</span>
                                <span class="font-medium">{{ $defenders->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-green-100 text-green-700">MF</span>
                                <span class="font-medium">{{ $midfielders->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-red-100 text-red-700">FW</span>
                                <span class="font-medium">{{ $forwards->count() }}</span>
                            </div>
                            <div class="border-l pl-8 flex items-center gap-1">
                                <span class="text-slate-400">{{ __('squad.avg_fitness') }}:</span>
                                <span class="font-semibold @if($avgFitness >= 85) text-green-600 @elseif($avgFitness < 70) text-yellow-600 @else text-slate-900 @endif">{{ round($avgFitness) }}</span>
                                @if($lowFitnessCount > 0)
                                    <span class="text-xs text-yellow-600">({{ $lowFitnessCount }} {{ __('squad.low') }})</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-slate-400">{{ __('squad.avg_morale') }}:</span>
                                <span class="font-semibold @if($avgMorale >= 80) text-green-600 @elseif($avgMorale < 65) text-yellow-600 @else text-slate-900 @endif">{{ round($avgMorale) }}</span>
                                @if($lowMoraleCount > 0)
                                    <span class="text-xs text-yellow-600">({{ $lowMoraleCount }} {{ __('squad.low') }})</span>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
