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
                                {{ \App\Game\Services\ContractService::formatWage($squadValue) }}
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
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4">
                            <div class="text-xs text-slate-500 uppercase tracking-wide">Transfer Budget</div>
                            <div class="text-2xl font-bold text-purple-700">
                                {{ $finances->formatted_transfer_budget }}
                            </div>
                        </div>
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
                                    <span class="font-semibold">{{ \App\Game\Services\ContractService::formatWage($finances->performance_bonus) }}</span>
                                </div>
                                @endif
                                @if($finances->cup_bonus > 0)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">Cup Prize Money</span>
                                    <span class="font-semibold">{{ \App\Game\Services\ContractService::formatWage($finances->cup_bonus) }}</span>
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
                                    <span class="font-semibold">{{ \App\Game\Services\ContractService::formatWage($finances->transfer_expense) }}</span>
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

                    {{-- Two Column: Highest Earners & Most Valuable --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        {{-- Highest Earners --}}
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-slate-900 mb-4">Highest Earners</h4>
                            @if($highestEarners->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($highestEarners as $player)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600">
                                            {{ $loop->iteration }}
                                        </span>
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $player->position }} &middot; {{ $player->age }} years</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-slate-900">{{ $player->formatted_wage }}/yr</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-4 pt-4 border-t text-sm text-slate-600">
                                <div class="flex justify-between">
                                    <span>Total Annual Wage Bill</span>
                                    <span class="font-semibold">{{ \App\Game\Services\ContractService::formatWage($wageBill) }}</span>
                                </div>
                            </div>
                            @else
                            <p class="text-slate-500">No players found.</p>
                            @endif
                        </div>

                        {{-- Most Valuable --}}
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-slate-900 mb-4">Most Valuable Players</h4>
                            @if($mostValuable->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($mostValuable as $player)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600">
                                            {{ $loop->iteration }}
                                        </span>
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $player->position }} &middot; {{ $player->age }} years</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-blue-700">{{ $player->formatted_market_value }}</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-4 pt-4 border-t text-sm text-slate-600">
                                <div class="flex justify-between">
                                    <span>Total Squad Value</span>
                                    <span class="font-semibold text-blue-700">{{ \App\Game\Services\ContractService::formatWage($squadValue) }}</span>
                                </div>
                            </div>
                            @else
                            <p class="text-slate-500">No players found.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Expiring Contracts --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Expiring This Season --}}
                        <div class="border border-red-200 rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-red-700 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Expiring End of {{ $game->season }}
                            </h4>
                            @if($expiringThisSeason->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($expiringThisSeason as $player)
                                <div class="flex items-center justify-between py-2 @if(!$loop->last) border-b border-slate-100 @endif">
                                    <div>
                                        <div class="font-medium text-slate-900">{{ $player->player->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $player->position }} &middot; {{ $player->age }} years</div>
                                    </div>
                                    <div class="text-right text-sm">
                                        <div class="text-slate-600">{{ $player->formatted_wage }}/yr</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <p class="text-slate-500 text-sm">No contracts expiring this season.</p>
                            @endif
                        </div>

                        {{-- Expiring Next Season --}}
                        <div class="border border-amber-200 rounded-lg p-6">
                            <h4 class="font-semibold text-lg text-amber-700 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Expiring End of {{ (int)$game->season + 1 }}
                            </h4>
                            @if($expiringNextSeason->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($expiringNextSeason as $player)
                                <div class="flex items-center justify-between py-2 @if(!$loop->last) border-b border-slate-100 @endif">
                                    <div>
                                        <div class="font-medium text-slate-900">{{ $player->player->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $player->position }} &middot; {{ $player->age }} years</div>
                                    </div>
                                    <div class="text-right text-sm">
                                        <div class="text-slate-600">{{ $player->formatted_wage }}/yr</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <p class="text-slate-500 text-sm">No contracts expiring next season.</p>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
