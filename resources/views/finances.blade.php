@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var App\Models\GameInvestment|null $investment */
/** @var array $tierThresholds */
/** @var int $availableBudget */
/** @var int $initialTransferBudget */
/** @var int $salesRevenue */
/** @var int $purchaseSpending */
/** @var int $infrastructureSpending */
/** @var bool $hasTransferActivity */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        @if($finances)

        {{-- Page Title --}}
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('finances.finances') }}</h2>
        </div>

        {{-- Post-season results banner --}}
        @if($finances->actual_total_revenue > 0)
        <div class="bg-surface-800 border border-border-default rounded-xl p-4 md:p-5 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex flex-wrap items-center gap-4 md:gap-6">
                    <div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.projected_revenue') }}</div>
                        <div class="font-heading text-lg font-bold text-text-body">{{ $finances->formatted_projected_total_revenue }}</div>
                    </div>
                    <svg class="w-4 h-4 text-text-faint hidden md:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    <div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.actual_revenue') }}</div>
                        <div class="font-heading text-lg font-bold text-text-body">{{ $finances->formatted_actual_total_revenue }}</div>
                    </div>
                    <svg class="w-4 h-4 text-text-faint hidden md:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    <div>
                        <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.variance') }}</div>
                        <div class="font-heading text-lg font-bold {{ $finances->variance >= 0 ? 'text-accent-green' : 'text-accent-red' }}">{{ $finances->formatted_variance }}</div>
                    </div>
                </div>
                <div class="md:text-right">
                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.actual_surplus') }}</div>
                    <div class="font-heading text-2xl font-bold text-text-primary">{{ $finances->formatted_actual_surplus }}</div>
                </div>
            </div>
        </div>
        @endif

        {{-- KPI Cards --}}
        <div class="flex flex-wrap gap-3 mb-6">
            <x-summary-card :label="__('finances.squad_value')" :value="\App\Support\Money::format($squadValue)" />
            <x-summary-card :label="__('finances.annual_wage_bill')" :value="\App\Support\Money::format($wageBill) . __('squad.per_year')" />
            <x-summary-card :label="__('finances.wage_revenue_ratio')">
                <div class="flex items-center gap-2 mt-1">
                    <div class="w-16 h-1.5 bg-surface-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $wageRevenueRatio > 70 ? 'bg-accent-red' : ($wageRevenueRatio > 55 ? 'bg-accent-gold' : 'bg-accent-green') }}" style="width: {{ min($wageRevenueRatio, 100) }}%"></div>
                    </div>
                    <span class="font-heading text-xl font-bold {{ $wageRevenueRatio > 70 ? 'text-accent-red' : ($wageRevenueRatio > 55 ? 'text-accent-gold' : 'text-text-primary') }}">{{ $wageRevenueRatio }}%</span>
                </div>
            </x-summary-card>
            @if($investment)
            <x-summary-card :label="__('finances.transfer_budget')" :value="$investment->formatted_transfer_budget" value-class="text-accent-blue" />
            @endif
        </div>

        {{-- 2-Column Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- LEFT COLUMN (2/3) --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Budget Flow / Budget Not Set --}}
                @if($investment)
                <x-section-card :title="__('finances.budget_flow')" :badge="__('finances.season_budget', ['season' => $game->formatted_season])">
                    <div class="px-5 py-4 space-y-0 text-sm">
                        {{-- Revenue line items --}}
                        @php
                            $revenueLines = [
                                ['label' => __('finances.tv_rights'), 'tooltip' => __('finances.tooltip_tv_rights'), 'value' => $finances->formatted_projected_tv_revenue, 'show' => true],
                                ['label' => __('finances.commercial'), 'tooltip' => __('finances.tooltip_commercial'), 'value' => $finances->formatted_projected_commercial_revenue, 'show' => true],
                                ['label' => __('finances.matchday'), 'tooltip' => __('finances.tooltip_matchday'), 'value' => $finances->formatted_projected_matchday_revenue, 'show' => true],
                                ['label' => __('finances.solidarity_funds'), 'tooltip' => __('finances.tooltip_solidarity_funds'), 'value' => $finances->formatted_projected_solidarity_funds_revenue, 'show' => $finances->projected_solidarity_funds_revenue > 0],
                                ['label' => __('finances.public_subsidy'), 'tooltip' => __('finances.tooltip_public_subsidy'), 'value' => $finances->formatted_projected_subsidy_revenue, 'show' => $finances->projected_subsidy_revenue > 0],
                            ];
                        @endphp
                        @foreach($revenueLines as $line)
                            @if($line['show'])
                            <div class="flex items-center justify-between py-2">
                                <span class="text-text-muted pl-5 flex items-center gap-1.5">{{ $line['label'] }} <svg x-data="" x-tooltip.raw="{{ $line['tooltip'] }}" class="w-3.5 h-3.5 text-text-faint hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                                <span class="text-accent-green font-medium">+{{ $line['value'] }}</span>
                            </div>
                            @endif
                        @endforeach
                        <div class="border-t border-border-default pt-2 mt-1">
                            <div class="flex items-center justify-between py-1">
                                <span class="font-semibold text-text-body pl-5">{{ __('finances.total_revenue') }}</span>
                                <span class="font-semibold text-accent-green">+{{ $finances->formatted_projected_total_revenue }}</span>
                            </div>
                        </div>

                        {{-- Deductions --}}
                        @php
                            $deductionLines = [
                                ['label' => __('finances.projected_wages'), 'tooltip' => __('finances.tooltip_wages'), 'value' => $finances->formatted_projected_wages, 'show' => true],
                                ['label' => __('finances.operating_expenses'), 'tooltip' => __('finances.tooltip_operating_expenses'), 'value' => $finances->formatted_projected_operating_expenses, 'show' => true],
                                ['label' => __('finances.taxes'), 'tooltip' => __('finances.tooltip_taxes'), 'value' => \App\Support\Money::format($finances->projected_taxes), 'show' => $finances->projected_taxes > 0],
                            ];
                        @endphp
                        @foreach($deductionLines as $line)
                            @if($line['show'])
                            <div class="flex items-center justify-between py-2">
                                <span class="text-text-muted pl-5 flex items-center gap-1.5">{{ $line['label'] }} <svg x-data="" x-tooltip.raw="{{ $line['tooltip'] }}" class="w-3.5 h-3.5 text-text-faint hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                                <span class="text-accent-red font-medium">-{{ $line['value'] }}</span>
                            </div>
                            @endif
                        @endforeach

                        {{-- Surplus line --}}
                        <div class="border-t border-border-default pt-2 mt-1">
                            <div class="flex items-center justify-between py-1">
                                <span class="font-semibold text-text-body pl-5 flex items-center gap-1.5">{{ __('finances.projected_surplus') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_surplus') }}" class="w-3.5 h-3.5 text-text-faint hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                                <span class="font-semibold text-text-body">{{ $finances->formatted_projected_surplus }}</span>
                            </div>
                        </div>

                        {{-- Carried debt --}}
                        @if($finances->carried_debt > 0)
                        <div class="flex items-center justify-between py-2">
                            <span class="text-text-muted pl-5 flex items-center gap-1.5">{{ __('finances.carried_debt') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_carried_debt') }}" class="w-3.5 h-3.5 text-text-faint hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                            <span class="text-accent-red font-medium">-{{ $finances->formatted_carried_debt }}</span>
                        </div>
                        @endif

                        {{-- Carried surplus --}}
                        @if($finances->carried_surplus > 0)
                        <div class="flex items-center justify-between py-2">
                            <span class="text-text-muted pl-5 flex items-center gap-1.5">{{ __('finances.carried_surplus') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_carried_surplus') }}" class="w-3.5 h-3.5 text-text-faint hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                            <span class="text-accent-green font-medium">+{{ $finances->formatted_carried_surplus }}</span>
                        </div>
                        @endif

                        {{-- Infrastructure deduction --}}
                        <div class="flex items-center justify-between py-2">
                            <span class="text-text-muted pl-5 flex items-center gap-1.5">{{ __('finances.infrastructure_investment') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_infrastructure') }}" class="w-3.5 h-3.5 text-text-faint hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                            <span class="text-accent-red font-medium">-{{ \App\Support\Money::format($investment->total_infrastructure - $infrastructureSpending) }}</span>
                        </div>

                        @if($hasTransferActivity)
                        {{-- Season Allocation line --}}
                        <div class="border-t-2 border-border-strong pt-2 mt-1">
                            <div class="flex items-center justify-between py-1">
                                <span class="font-semibold text-text-body flex items-center gap-1.5">= {{ __('finances.season_allocation') }}</span>
                                <span class="font-semibold text-text-body">{{ \App\Support\Money::format($initialTransferBudget) }}</span>
                            </div>
                        </div>

                        {{-- Transfer Activity section --}}
                        <div class="mt-3 pt-3 border-t border-dashed border-border-strong">
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('finances.transfer_activity') }}</span>
                            </div>
                            @if($salesRevenue > 0)
                            <div class="flex items-center justify-between py-1.5">
                                <span class="text-text-muted pl-5">{{ __('finances.player_sales') }}</span>
                                <span class="text-accent-green font-medium">+{{ \App\Support\Money::format($salesRevenue) }}</span>
                            </div>
                            @endif
                            @if($purchaseSpending > 0)
                            <div class="flex items-center justify-between py-1.5">
                                <span class="text-text-muted pl-5">{{ __('finances.player_purchases') }}</span>
                                <span class="text-accent-red font-medium">-{{ \App\Support\Money::format($purchaseSpending) }}</span>
                            </div>
                            @endif
                            @if($infrastructureSpending > 0)
                            <div class="flex items-center justify-between py-1.5">
                                <span class="text-text-muted pl-5">{{ __('finances.infrastructure_upgrades') }}</span>
                                <span class="text-accent-red font-medium">-{{ \App\Support\Money::format($infrastructureSpending) }}</span>
                            </div>
                            @endif
                        </div>

                        {{-- Final: Current Transfer Budget --}}
                        <div class="border-t-2 border-border-strong pt-2 mt-1">
                            <div class="flex items-center justify-between py-1">
                                <span class="font-heading font-semibold text-lg text-text-primary flex items-center gap-1.5">= {{ __('finances.current_transfer_budget') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_transfer_budget') }}" class="w-3.5 h-3.5 text-text-muted hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                                <span class="font-heading font-bold text-lg text-text-primary">{{ $investment->formatted_transfer_budget }}</span>
                            </div>
                        </div>
                        @else
                        {{-- No transfer activity: simple Transfer Budget line --}}
                        <div class="border-t-2 border-border-strong pt-2 mt-1">
                            <div class="flex items-center justify-between py-1">
                                <span class="font-heading font-semibold text-lg text-text-primary flex items-center gap-1.5">= {{ __('finances.transfer_budget') }} <svg x-data="" x-tooltip.raw="{{ __('finances.tooltip_transfer_budget') }}" class="w-3.5 h-3.5 text-text-muted hover:text-text-secondary cursor-help shrink-0" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                                <span class="font-heading font-bold text-lg text-text-primary">{{ $investment->formatted_transfer_budget }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </x-section-card>
                @else
                {{-- Budget not allocated --}}
                <div class="bg-surface-800 border-2 border-dashed border-accent-gold/30 rounded-xl text-center py-8 px-6">
                    <div class="text-sm text-accent-gold font-medium mb-2">{{ __('finances.budget_not_set') }}</div>
                    <div class="font-heading text-3xl font-bold text-text-primary mb-1">{{ $finances->formatted_available_surplus }}</div>
                    <div class="text-sm text-text-muted mb-4">{{ __('finances.surplus_to_allocate') }}</div>
                    <x-primary-button-link :href="route('game.budget', $game->id)" class="gap-2">
                        {{ __('finances.setup_season_budget') }} &rarr;
                    </x-primary-button-link>
                </div>
                @endif

                {{-- Transaction History --}}
                <x-section-card :title="__('finances.transaction_history')" x-data="{ filter: 'all' }">
                    @if($transactions->isNotEmpty())
                    <x-slot name="badge">
                        <div class="flex items-center gap-4 text-xs">
                            <span class="text-accent-green font-medium">+{{ \App\Support\Money::format($totalIncome) }} {{ __('finances.income') }}</span>
                            <span class="text-accent-red font-medium">-{{ \App\Support\Money::format($totalExpenses) }} {{ __('finances.expenses') }}</span>
                        </div>
                    </x-slot>
                    @endif

                    @if($transactions->isNotEmpty())
                    {{-- Filter tabs --}}
                    <div class="px-5 pt-3 flex gap-2 border-b border-border-default">
                        <x-tab-button size="xs" @click="filter = 'all'" class="rounded-t"
                                x-bind:class="filter === 'all' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted hover:text-text-body'">
                            {{ __('finances.filter_all') }}
                        </x-tab-button>
                        <x-tab-button size="xs" @click="filter = 'income'" class="rounded-t"
                                x-bind:class="filter === 'income' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted hover:text-text-body'">
                            {{ __('finances.filter_income') }}
                        </x-tab-button>
                        <x-tab-button size="xs" @click="filter = 'expense'" class="rounded-t"
                                x-bind:class="filter === 'expense' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted hover:text-text-body'">
                            {{ __('finances.filter_expenses') }}
                        </x-tab-button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] text-text-muted uppercase tracking-widest border-b border-border-default">
                                    <th class="px-5 py-2.5 font-semibold">{{ __('finances.date') }}</th>
                                    <th class="py-2.5 font-semibold">{{ __('finances.type') }}</th>
                                    <th class="py-2.5 font-semibold hidden md:table-cell">{{ __('finances.description') }}</th>
                                    <th class="py-2.5 pr-5 font-semibold text-right">{{ __('finances.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                <tr class="border-b border-border-default"
                                    x-show="filter === 'all' || filter === '{{ $transaction->type }}'"
                                    x-transition>
                                    <td class="px-5 py-2.5 text-text-muted whitespace-nowrap">{{ $transaction->transaction_date->format('d M') }}</td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $transaction->isIncome() ? 'bg-accent-green/10 text-accent-green' : 'bg-accent-red/10 text-accent-red' }}">
                                            {{ $transaction->category_label }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-text-secondary hidden md:table-cell">{{ $transaction->description }}</td>
                                    <td class="py-2.5 pr-5 text-right font-heading font-semibold {{ $transaction->amount == 0 ? 'text-text-muted' : ($transaction->isIncome() ? 'text-accent-green' : 'text-accent-red') }}">
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
                    <div class="p-8 text-center">
                        <p class="text-text-muted">{{ __('finances.no_transactions') }}</p>
                        <p class="text-sm text-text-faint mt-1">{{ __('finances.transactions_hint') }}</p>
                    </div>
                    @endif
                </x-section-card>
            </div>

            {{-- RIGHT COLUMN (1/3) --}}
            <div class="space-y-6">

                {{-- Infrastructure --}}
                @if($investment)
                <x-section-card :title="__('finances.infrastructure_investment')">
                    <div class="p-4 space-y-3">
                        @foreach([
                            ['key' => 'youth_academy', 'tier' => $investment->youth_academy_tier, 'amount' => $investment->formatted_youth_academy_amount],
                            ['key' => 'medical', 'tier' => $investment->medical_tier, 'amount' => $investment->formatted_medical_amount],
                            ['key' => 'scouting', 'tier' => $investment->scouting_tier, 'amount' => $investment->formatted_scouting_amount],
                            ['key' => 'facilities', 'tier' => $investment->facilities_tier, 'amount' => $investment->formatted_facilities_amount],
                        ] as $area)
                        <div class="border border-border-default rounded-lg p-3 bg-surface-700/30" x-data="{ showUpgrade: false }">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-text-body">{{ __('finances.' . $area['key']) }}</span>
                                <span class="text-[10px] text-text-muted">{{ $area['amount'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    @for($i = 1; $i <= 4; $i++)
                                        <span class="w-2.5 h-2.5 rounded-full {{ $i <= $area['tier'] ? 'bg-accent-green' : 'bg-surface-600' }}"></span>
                                    @endfor
                                    <span class="text-[10px] text-text-muted ml-1">{{ __('finances.tier', ['level' => $area['tier']]) }}</span>
                                </div>
                                @if($area['tier'] < 4)
                                <x-ghost-button color="green" size="xs" @click="showUpgrade = true" x-show="!showUpgrade" class="gap-1 font-semibold px-2.5">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                                    {{ __('finances.upgrade') }}
                                </x-ghost-button>
                                <x-ghost-button color="slate" size="xs" @click="showUpgrade = false" x-show="showUpgrade" x-cloak class="gap-1 font-semibold px-2.5">
                                    {{ __('finances.upgrade_cancel') }}
                                </x-ghost-button>
                                @else
                                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-accent-green/10 text-accent-green uppercase tracking-wider">MAX</span>
                                @endif
                            </div>
                            <div class="text-[10px] text-text-muted mt-1">{{ __('finances.' . $area['key'] . '_tier_' . $area['tier']) }}</div>

                            {{-- Upgrade options --}}
                            @if($area['tier'] < 4)
                            <div x-show="showUpgrade" x-collapse x-cloak class="mt-3 pt-3 border-t border-border-default space-y-2">
                                @for($t = $area['tier'] + 1; $t <= 4; $t++)
                                    @php
                                        $currentAmount = $investment->{$area['key'] . '_amount'};
                                        $targetAmount = $tierThresholds[$area['key']][$t];
                                        $cost = $targetAmount - $currentAmount;
                                        $canAfford = $cost <= $availableBudget;
                                    @endphp
                                    <form method="POST" action="{{ route('game.infrastructure.upgrade', $game->id) }}" class="flex items-center justify-between gap-2 p-2 rounded-lg {{ $canAfford ? 'bg-surface-700/50' : '' }}">
                                        @csrf
                                        <input type="hidden" name="area" value="{{ $area['key'] }}">
                                        <input type="hidden" name="target_tier" value="{{ $t }}">
                                        <div class="min-w-0 flex items-center gap-2">
                                            <div class="flex items-center gap-1">
                                                @for($dot = 1; $dot <= 4; $dot++)
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $dot <= $t ? 'bg-accent-green' : 'bg-surface-600' }}"></span>
                                                @endfor
                                            </div>
                                            <div>
                                                <div class="text-xs font-medium text-text-body">{{ __('finances.tier', ['level' => $t]) }}</div>
                                                <div class="text-[10px] text-text-muted truncate">{{ \App\Support\Money::format($cost) }}</div>
                                            </div>
                                        </div>
                                        <x-primary-button size="xs" class="shrink-0" :disabled="!$canAfford">
                                            {{ __('finances.upgrade_confirm') }}
                                        </x-primary-button>
                                    </form>
                                @endfor
                                @if($availableBudget <= 0)
                                <p class="text-[10px] text-accent-gold px-2">{{ __('finances.upgrade_insufficient_budget') }}</p>
                                @endif
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </x-section-card>
                @endif

            </div>
        </div>

        @else
        <div class="mt-12 text-center py-12 text-text-muted">
            <p>{{ __('finances.no_financial_data') }}</p>
        </div>
        @endif

    </div>
</x-app-layout>
