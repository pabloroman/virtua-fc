@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var App\Models\GameInvestment|null $investment */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ __('finances.title', ['team' => $game->team->name, 'season' => $game->season]) }}</h3>

                    @if($finances)
                    {{-- Overview Cards --}}
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                        {{-- Projected Position --}}
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">{{ __('finances.projected_position') }}</div>
                            <div class="text-2xl font-bold text-blue-700">
                                {{ $finances->projected_position }}
                            </div>
                        </div>

                        {{-- Squad Value --}}
                        <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">{{ __('finances.squad_value') }}</div>
                            <div class="text-2xl font-bold text-slate-700">
                                {{ \App\Support\Money::format($squadValue) }}
                            </div>
                        </div>

                        {{-- Wage Bill --}}
                        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">{{ __('finances.annual_wage_bill') }}</div>
                            <div class="text-2xl font-bold text-amber-700">
                                {{ \App\Support\Money::format($wageBill) }}
                            </div>
                        </div>

                        {{-- Transfer Budget --}}
                        <div class="bg-gradient-to-br from-sky-50 to-sky-100 rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">{{ __('finances.transfer_budget') }}</div>
                            <div class="text-2xl font-bold text-sky-700">
                                {{ $investment?->formatted_transfer_budget ?? '€0' }}
                            </div>
                        </div>
                    </div>

                    {{-- Projected Revenue & Actual (if available) --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        {{-- Projected Revenue --}}
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-slate-700 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                {{ __('finances.projected_revenue') }}
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.tv_rights') }}</span>
                                    <span class="font-semibold">{{ $finances->formatted_projected_tv_revenue }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.matchday') }}</span>
                                    <span class="font-semibold">{{ $finances->formatted_projected_matchday_revenue }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.commercial') }}</span>
                                    <span class="font-semibold">{{ $finances->formatted_projected_commercial_revenue }}</span>
                                </div>
                                @if($finances->projected_solidarity_funds_revenue > 0)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.solidarity_funds') }}</span>
                                    <span class="font-semibold">{{ $finances->formatted_projected_solidarity_funds_revenue }}</span>
                                </div>
                                @endif
                                @if($finances->projected_subsidy_revenue > 0)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.public_subsidy') }}</span>
                                    <span class="font-semibold">{{ $finances->formatted_projected_subsidy_revenue }}</span>
                                </div>
                                @endif
                                <div class="flex justify-between items-center pt-3 border-t border-slate-200">
                                    <span class="font-semibold text-slate-700">{{ __('finances.total_revenue') }}</span>
                                    <span class="font-bold text-slate-700 text-lg">{{ $finances->formatted_projected_total_revenue }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Projected Surplus --}}
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-slate-700 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ __('finances.surplus_calculation') }}
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.projected_revenue') }}</span>
                                    <span class="font-semibold text-green-600">{{ $finances->formatted_projected_total_revenue }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.projected_wages') }}</span>
                                    <span class="font-semibold text-red-600">-{{ $finances->formatted_projected_wages }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.operating_expenses') }}</span>
                                    <span class="font-semibold text-red-600">-{{ $finances->formatted_projected_operating_expenses }}</span>
                                </div>
                                @if($finances->carried_debt > 0)
                                <div class="flex justify-between items-center text-red-600">
                                    <span>{{ __('finances.carried_debt') }}</span>
                                    <span class="font-semibold">-{{ $finances->formatted_carried_debt }}</span>
                                </div>
                                @endif
                                @if($investment)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">{{ __('finances.infrastructure_investment') }}</span>
                                    <span class="font-semibold text-red-600">-{{ $investment->formatted_total_infrastructure }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-3 border-t border-slate-200">
                                    <span class="font-semibold text-slate-700">{{ __('finances.transfer_budget') }}</span>
                                    <span class="font-bold text-green-700 text-lg">{{ $investment->formatted_transfer_budget }}</span>
                                </div>
                                @else
                                <div class="flex justify-between items-center pt-3 border-t border-slate-200">
                                    <span class="font-semibold text-slate-700">{{ __('finances.projected_surplus') }}</span>
                                    <span class="font-bold text-green-700 text-lg">{{ $finances->formatted_projected_surplus }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Actual Results (if season ended) --}}
                    @if($finances->actual_total_revenue > 0)
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4">{{ __('finances.season_results') }}</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="border rounded-lg p-4">
                                <div class="text-sm text-slate-500">{{ __('finances.actual_revenue') }}</div>
                                <div class="text-xl font-bold text-slate-900">{{ $finances->formatted_actual_total_revenue }}</div>
                            </div>
                            <div class="border rounded-lg p-4">
                                <div class="text-sm text-slate-500">{{ __('finances.actual_surplus') }}</div>
                                <div class="text-xl font-bold text-slate-900">{{ $finances->formatted_actual_surplus }}</div>
                            </div>
                            <div class="border rounded-lg p-4 {{ $finances->variance >= 0 ? 'bg-green-50' : 'bg-red-50' }}">
                                <div class="text-sm text-slate-500">{{ __('finances.variance') }}</div>
                                <div class="text-xl font-bold {{ $finances->variance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $finances->formatted_variance }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @else
                    <div class="text-center py-12 text-slate-500">
                        <p>{{ __('finances.no_financial_data') }}</p>
                    </div>
                    @endif

                    {{-- Investment Tiers --}}
                    @if($investment)
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold text-lg text-slate-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                {{ __('finances.infrastructure_investment') }}
                            </h4>
                            <a href="{{ route('game.budget', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                {{ __('finances.adjust_allocation') }} &rarr;
                            </a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            {{-- Youth Academy --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm font-medium text-slate-700">{{ __('finances.youth_academy') }}</div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-0.5">
                                        @for($i = 1; $i <= 4; $i++)
                                            <div class="w-3 h-3 rounded-full {{ $i <= $investment->youth_academy_tier ? 'bg-purple-500' : 'bg-slate-200' }}"></div>
                                        @endfor
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $investment->formatted_youth_academy_amount }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-2">
                                    {{ __('finances.youth_tier_' . $investment->youth_academy_tier) }}
                                </div>
                            </div>

                            {{-- Medical --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm font-medium text-slate-700">{{ __('finances.medical') }}</div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-0.5">
                                        @for($i = 1; $i <= 4; $i++)
                                            <div class="w-3 h-3 rounded-full {{ $i <= $investment->medical_tier ? 'bg-red-500' : 'bg-slate-200' }}"></div>
                                        @endfor
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $investment->formatted_medical_amount }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-2">
                                    {{ __('finances.medical_tier_' . $investment->medical_tier) }}
                                </div>
                            </div>

                            {{-- Scouting --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm font-medium text-slate-700">{{ __('finances.scouting') }}</div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-0.5">
                                        @for($i = 1; $i <= 4; $i++)
                                            <div class="w-3 h-3 rounded-full {{ $i <= $investment->scouting_tier ? 'bg-blue-500' : 'bg-slate-200' }}"></div>
                                        @endfor
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $investment->formatted_scouting_amount }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-2">
                                    {{ __('finances.scouting_tier_' . $investment->scouting_tier) }}
                                </div>
                            </div>

                            {{-- Facilities --}}
                            <div class="border rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm font-medium text-slate-700">{{ __('finances.facilities') }}</div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-0.5">
                                        @for($i = 1; $i <= 4; $i++)
                                            <div class="w-3 h-3 rounded-full {{ $i <= $investment->facilities_tier ? 'bg-green-500' : 'bg-slate-200' }}"></div>
                                        @endfor
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $investment->formatted_facilities_amount }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-2">
                                    {{ __('finances.facilities_tier_' . $investment->facilities_tier) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="mb-8">
                        <a href="{{ route('game.budget', $game->id) }}" class="block">
                            <div class="bg-gradient-to-r from-sky-500 to-sky-600 rounded-lg p-6 text-white hover:from-sky-600 hover:to-sky-700 transition">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-semibold text-lg mb-1">{{ __('finances.setup_season_budget') }}</h4>
                                        <p class="text-sky-100 text-sm">{{ __('finances.allocate_surplus') }}</p>
                                    </div>
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    </div>
                    @endif

                    {{-- Transaction History --}}
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            {{ __('finances.transaction_history') }}
                        </h4>
                        @if($transactions->isNotEmpty())
                        <div class="border rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('finances.date') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('finances.type') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('finances.description') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('finances.amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-200">
                                    @foreach($transactions as $transaction)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-500">
                                            {{ $transaction->transaction_date->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $transaction->isIncome() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $transaction->category_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-900">
                                            {{ $transaction->description }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-right {{ $transaction->amount == 0 ? 'text-slate-400' : ($transaction->isIncome() ? 'text-green-600' : 'text-red-600') }}">
                                            @if($transaction->amount == 0)
                                                {{ __('finances.free') }}
                                            @else
                                                {{ $transaction->signed_amount }}
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="border rounded-lg p-6 text-center text-slate-500">
                            <p>{{ __('finances.no_transactions') }}</p>
                            <p class="text-sm mt-1">{{ __('finances.transactions_hint') }}</p>
                        </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
