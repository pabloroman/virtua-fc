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

                    {{-- Post-season results banner --}}
                    @if($finances->actual_total_revenue > 0)
                    <div class="border rounded-lg overflow-hidden bg-slate-50 mb-6">
                        <div class="px-5 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-6">
                                <div>
                                    <div class="text-xs text-slate-500">{{ __('finances.projected_revenue') }}</div>
                                    <div class="font-semibold text-slate-700">{{ $finances->formatted_projected_total_revenue }}</div>
                                </div>
                                <div class="text-slate-300">&rarr;</div>
                                <div>
                                    <div class="text-xs text-slate-500">{{ __('finances.actual_revenue') }}</div>
                                    <div class="font-semibold text-slate-700">{{ $finances->formatted_actual_total_revenue }}</div>
                                </div>
                                <div class="text-slate-300">&rarr;</div>
                                <div>
                                    <div class="text-xs text-slate-500">{{ __('finances.variance') }}</div>
                                    <div class="font-semibold {{ $finances->variance >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $finances->formatted_variance }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-slate-500">{{ __('finances.actual_surplus') }}</div>
                                <div class="text-xl font-bold text-slate-900">{{ $finances->formatted_actual_surplus }}</div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- 2-Column Layout --}}
                    <div class="grid grid-cols-3 gap-8">

                        {{-- LEFT COLUMN (2/3) --}}
                        <div class="col-span-2 space-y-8">

                            {{-- Budget Flow / Budget Not Set --}}
                            @if($investment)
                            <div class="border rounded-lg overflow-hidden">
                                <div class="px-5 py-3 bg-slate-50 border-b flex items-center justify-between">
                                    <h4 class="font-semibold text-sm text-slate-900">{{ __('finances.budget_flow') }}</h4>
                                    <span class="text-xs text-slate-400">{{ __('finances.season_budget', ['season' => $game->season]) }}</span>
                                </div>
                                <div class="px-5 py-4 space-y-0 text-sm">
                                    {{-- Revenue line items --}}
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.tv_rights') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_tv_rights') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-green-600">+{{ $finances->formatted_projected_tv_revenue }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.commercial') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_commercial') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-green-600">+{{ $finances->formatted_projected_commercial_revenue }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.matchday') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_matchday') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-green-600">+{{ $finances->formatted_projected_matchday_revenue }}</span>
                                    </div>
                                    @if($finances->projected_solidarity_funds_revenue > 0)
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.solidarity_funds') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_solidarity_funds') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-green-600">+{{ $finances->formatted_projected_solidarity_funds_revenue }}</span>
                                    </div>
                                    @endif
                                    @if($finances->projected_subsidy_revenue > 0)
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.public_subsidy') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_public_subsidy') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-green-600">+{{ $finances->formatted_projected_subsidy_revenue }}</span>
                                    </div>
                                    @endif
                                    <div class="border-t pt-2 mt-1">
                                        <div class="flex items-center justify-between py-1">
                                            <span class="font-semibold text-slate-700 pl-5">{{ __('finances.total_revenue') }}</span>
                                            <span class="font-semibold text-green-600">+{{ $finances->formatted_projected_total_revenue }}</span>
                                        </div>
                                    </div>

                                    {{-- Deductions --}}
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.projected_wages') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_wages') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-red-600">-{{ $finances->formatted_projected_wages }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.operating_expenses') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_operating_expenses') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-red-600">-{{ $finances->formatted_projected_operating_expenses }}</span>
                                    </div>
                                    @if($finances->projected_taxes > 0)
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.taxes') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_taxes') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-red-600">-{{ \App\Support\Money::format($finances->projected_taxes) }}</span>
                                    </div>
                                    @endif

                                    {{-- Surplus line --}}
                                    <div class="border-t pt-2 mt-1">
                                        <div class="flex items-center justify-between py-1">
                                            <span class="font-semibold text-slate-700 pl-5 flex items-center gap-1.5">{{ __('finances.projected_surplus') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_surplus') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                            <span class="font-semibold text-slate-700">{{ $finances->formatted_projected_surplus }}</span>
                                        </div>
                                    </div>

                                    {{-- Carried debt --}}
                                    @if($finances->carried_debt > 0)
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-red-600 pl-5 flex items-center gap-1.5">{{ __('finances.carried_debt') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_carried_debt') }}" class="w-3.5 h-3.5 text-red-300 hover:text-red-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-red-600">-{{ $finances->formatted_carried_debt }}</span>
                                    </div>
                                    @endif

                                    {{-- Infrastructure deduction --}}
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-slate-500 pl-5 flex items-center gap-1.5">{{ __('finances.infrastructure_investment') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_infrastructure') }}" class="w-3.5 h-3.5 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                        <span class="text-red-600">-{{ $investment->formatted_total_infrastructure }}</span>
                                    </div>

                                    {{-- Final: Transfer Budget --}}
                                    <div class="border-t-2 border-slate-900 pt-2 mt-1">
                                        <div class="flex items-center justify-between py-1">
                                            <span class="font-semibold text-lg text-slate-900 flex items-center gap-1.5">= {{ __('finances.transfer_budget') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_transfer_budget') }}" class="w-3.5 h-3.5 text-slate-400 hover:text-slate-600 cursor-help flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                            <span class="font-semibold text-lg text-slate-900">{{ $investment->formatted_transfer_budget }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @else
                            {{-- Budget not allocated --}}
                            <div class="text-center py-6 border-2 border-dashed border-amber-300 rounded-lg bg-amber-50">
                                <div class="text-sm text-amber-700 font-medium mb-2">{{ __('finances.budget_not_set') }}</div>
                                <div class="text-3xl font-bold text-slate-900 mb-1">{{ $finances->formatted_available_surplus }}</div>
                                <div class="text-sm text-slate-500 mb-4">{{ __('finances.surplus_to_allocate') }}</div>
                                <a href="{{ route('game.budget', $game->id) }}" class="inline-flex items-center gap-2 px-5 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition-colors">
                                    {{ __('finances.setup_season_budget') }} &rarr;
                                </a>
                            </div>
                            @endif

                            {{-- Transaction History --}}
                            <div class="border rounded-lg overflow-hidden" x-data="{ filter: 'all' }">
                                <div class="px-5 py-3 bg-slate-50 border-b flex items-center justify-between">
                                    <h4 class="font-semibold text-sm text-slate-900">{{ __('finances.transaction_history') }}</h4>
                                    @if($transactions->isNotEmpty())
                                    <div class="flex items-center gap-4 text-xs">
                                        <span class="text-green-600 font-medium">+{{ \App\Support\Money::format($totalIncome) }} {{ __('finances.income') }}</span>
                                        <span class="text-red-600 font-medium">-{{ \App\Support\Money::format($totalExpenses) }} {{ __('finances.expenses') }}</span>
                                    </div>
                                    @endif
                                </div>

                                @if($transactions->isNotEmpty())
                                {{-- Filter tabs --}}
                                <div class="px-5 pt-3 flex gap-2 border-b">
                                    <button @click="filter = 'all'" class="px-3 py-1.5 text-xs font-medium rounded-t border-b-2 transition-colors"
                                            :class="filter === 'all' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-400 hover:text-slate-600'">
                                        {{ __('finances.filter_all') }}
                                    </button>
                                    <button @click="filter = 'income'" class="px-3 py-1.5 text-xs font-medium rounded-t border-b-2 transition-colors"
                                            :class="filter === 'income' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-400 hover:text-slate-600'">
                                        {{ __('finances.filter_income') }}
                                    </button>
                                    <button @click="filter = 'expense'" class="px-3 py-1.5 text-xs font-medium rounded-t border-b-2 transition-colors"
                                            :class="filter === 'expense' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-400 hover:text-slate-600'">
                                        {{ __('finances.filter_expenses') }}
                                    </button>
                                </div>

                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-slate-400 border-b">
                                            <th class="px-5 py-2 font-medium">{{ __('finances.date') }}</th>
                                            <th class="py-2 font-medium">{{ __('finances.type') }}</th>
                                            <th class="py-2 font-medium">{{ __('finances.description') }}</th>
                                            <th class="py-2 pr-5 font-medium text-right">{{ __('finances.amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($transactions as $transaction)
                                        <tr class="border-b border-slate-100"
                                            x-show="filter === 'all' || filter === '{{ $transaction->type }}'"
                                            x-transition>
                                            <td class="px-5 py-2.5 text-slate-500">{{ $transaction->transaction_date->format('d M') }}</td>
                                            <td class="py-2.5">
                                                <span class="px-2 py-0.5 text-xs rounded-full {{ $transaction->isIncome() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                    {{ $transaction->category_label }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 text-slate-700">{{ $transaction->description }}</td>
                                            <td class="py-2.5 pr-5 text-right font-medium {{ $transaction->amount == 0 ? 'text-slate-400' : ($transaction->isIncome() ? 'text-green-600' : 'text-red-600') }}">
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
                                @else
                                <div class="p-6 text-center text-slate-500">
                                    <p>{{ __('finances.no_transactions') }}</p>
                                    <p class="text-sm mt-1">{{ __('finances.transactions_hint') }}</p>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- RIGHT COLUMN (1/3) --}}
                        <div class="space-y-8">

                            {{-- Club Finances Overview --}}
                            <div class="rounded-lg overflow-hidden border border-slate-200">
                                <div class="bg-gradient-to-br from-slate-800 to-slate-900 px-4 py-5">
                                    <div class="text-xs text-slate-400 uppercase mb-1">{{ __('finances.squad_value') }}</div>
                                    <div class="text-2xl font-bold text-white">{{ \App\Support\Money::format($squadValue) }}</div>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    <div class="px-4 py-3 flex items-center justify-between">
                                        <span class="text-sm text-slate-500">{{ __('finances.annual_wage_bill') }}</span>
                                        <span class="text-sm font-semibold text-slate-900">{{ \App\Support\Money::format($wageBill) }}{{ __('squad.per_year') }}</span>
                                    </div>
                                    <div class="px-4 py-3 flex items-center justify-between">
                                        <span class="text-sm text-slate-500">{{ __('finances.wage_revenue_ratio') }}</span>
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full {{ $wageRevenueRatio > 70 ? 'bg-red-500' : ($wageRevenueRatio > 55 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min($wageRevenueRatio, 100) }}%"></div>
                                            </div>
                                            <span class="text-sm font-semibold {{ $wageRevenueRatio > 70 ? 'text-red-600' : ($wageRevenueRatio > 55 ? 'text-amber-600' : 'text-slate-900') }}">{{ $wageRevenueRatio }}%</span>
                                        </div>
                                    </div>
                                    @if($investment)
                                    <div class="px-4 py-3 flex items-center justify-between">
                                        <span class="text-sm text-slate-500">{{ __('finances.transfer_budget') }}</span>
                                        <span class="text-sm font-semibold text-slate-900">{{ $investment->formatted_transfer_budget }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Infrastructure --}}
                            @if($investment)
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="font-semibold text-sm text-slate-900">{{ __('finances.infrastructure_investment') }}</h4>
{{--                                    <a href="{{ route('game.budget', $game->id) }}" class="text-xs text-sky-600 hover:text-sky-800 font-medium">{{ __('finances.adjust_allocation') }} &rarr;</a>--}}
                                </div>
                                <div class="space-y-4">
                                    @foreach([
                                        ['key' => 'youth_academy', 'tier' => $investment->youth_academy_tier, 'amount' => $investment->formatted_youth_academy_amount],
                                        ['key' => 'medical', 'tier' => $investment->medical_tier, 'amount' => $investment->formatted_medical_amount],
                                        ['key' => 'scouting', 'tier' => $investment->scouting_tier, 'amount' => $investment->formatted_scouting_amount],
                                        ['key' => 'facilities', 'tier' => $investment->facilities_tier, 'amount' => $investment->formatted_facilities_amount],
                                    ] as $area)
                                    <div class="border rounded-lg p-3">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-medium text-slate-700">{{ __('finances.' . $area['key']) }}</span>
                                            <span class="text-xs text-slate-400">{{ $area['amount'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            @for($i = 1; $i <= 4; $i++)
                                                <span class="w-2.5 h-2.5 rounded-full {{ $i <= $area['tier'] ? 'bg-emerald-500' : 'bg-slate-200' }}"></span>
                                            @endfor
                                            <span class="text-xs text-slate-500 ml-1">{{ __('finances.tier', ['level' => $area['tier']]) }}</span>
                                        </div>
                                        <div class="text-xs text-slate-400 mt-1">{{ __('finances.' . $area['key'] . '_tier_' . $area['tier']) }}</div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>

                    @else
                    <div class="text-center py-12 text-slate-500">
                        <p>{{ __('finances.no_financial_data') }}</p>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
