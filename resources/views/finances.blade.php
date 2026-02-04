@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ $game->team->name }} Finances</h3>

                    {{-- Overview Cards --}}
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                        {{-- Balance --}}
                        <div class="bg-gradient-to-br {{ $finances->balance >= 0 ? 'from-green-50 to-green-100' : 'from-red-50 to-red-100' }} rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">Club Balance</div>
                            <div class="text-2xl font-bold {{ $finances->balance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $finances->formatted_balance }}
                            </div>
                            @if($finances->isInDebt())
                                <div class="text-xs text-red-600 mt-1">In debt</div>
                            @endif
                        </div>

                        {{-- Squad Value --}}
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">Squad Value</div>
                            <div class="text-2xl font-bold text-blue-700">
                                {{ \App\Support\Money::format($squadValue) }}
                            </div>
                        </div>

                        {{-- Wage Budget --}}
                        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">Wage Budget</div>
                            <div class="text-2xl font-bold text-amber-700">
                                {{ $finances->formatted_wage_budget }}
                            </div>
                            <div class="mt-2">
                                <div class="flex justify-between text-xs text-slate-600 mb-1">
                                    <span>Used</span>
                                    <span>{{ $wageUsagePercent }}%</span>
                                </div>
                                <div class="w-full bg-amber-200 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $wageUsagePercent > 90 ? 'bg-red-500' : ($wageUsagePercent > 70 ? 'bg-amber-500' : 'bg-green-500') }}"
                                         style="width: {{ $wageUsagePercent }}%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Transfer Budget --}}
                        <div class="bg-gradient-to-br from-sky-50 to-sky-100 rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">Transfer Budget</div>
                            <div class="text-2xl font-bold text-sky-700">
                                {{ $finances->formatted_transfer_budget }}
                            </div>
                        </div>
                    </div>

                    {{-- Transaction History --}}
                    <div class="mb-8">
                        <h4 class="font-semibold text-lg text-slate-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Transaction History
                        </h4>
                        @if($transactions->isNotEmpty())
                        <div class="border rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Amount</th>
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
                                                Free
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
                            <p>No transactions recorded yet.</p>
                            <p class="text-sm mt-1">Transfers, wages, and other financial activities will appear here.</p>
                        </div>
                        @endif
                    </div>

                    {{-- Season Revenue & Expenses --}}
                    @if($finances->total_revenue > 0 || $finances->total_expense > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        {{-- Revenue --}}
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-green-700 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Season Revenue
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">TV Rights</span>
                                    <span class="font-semibold">{{ $finances->formatted_tv_revenue }}</span>
                                </div>
                                @if($finances->performance_bonus > 0)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">Performance Bonus</span>
                                    <span class="font-semibold">{{ \App\Support\Money::format($finances->performance_bonus) }}</span>
                                </div>
                                @endif
                                @if($finances->cup_bonus > 0)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">Cup Prize Money</span>
                                    <span class="font-semibold">{{ \App\Support\Money::format($finances->cup_bonus) }}</span>
                                </div>
                                @endif
                                <div class="flex justify-between items-center pt-3 border-t border-green-200">
                                    <span class="font-semibold text-green-700">Total Revenue</span>
                                    <span class="font-bold text-green-700 text-lg">{{ $finances->formatted_total_revenue }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Expenses --}}
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-red-700 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Season Expenses
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">Wages</span>
                                    <span class="font-semibold">{{ $finances->formatted_wage_expense }}</span>
                                </div>
                                @if($finances->transfer_expense > 0)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">Transfer Fees</span>
                                    <span class="font-semibold">{{ \App\Support\Money::format($finances->transfer_expense) }}</span>
                                </div>
                                @endif
                                <div class="flex justify-between items-center pt-3 border-t border-red-200">
                                    <span class="font-semibold text-red-700">Total Expenses</span>
                                    <span class="font-bold text-red-700 text-lg">{{ $finances->formatted_total_expense }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Season Profit/Loss --}}
                    @if($finances->season_profit_loss != 0)
                    <div class="mb-8 p-4 rounded-lg {{ $finances->season_profit_loss >= 0 ? 'bg-green-100' : 'bg-red-100' }}">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-lg {{ $finances->season_profit_loss >= 0 ? 'text-green-800' : 'text-red-800' }}">
                                Season {{ $finances->season_profit_loss >= 0 ? 'Profit' : 'Loss' }}
                            </span>
                            <span class="text-2xl font-bold {{ $finances->season_profit_loss >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $finances->formatted_season_profit_loss }}
                            </span>
                        </div>
                    </div>
                    @endif
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
