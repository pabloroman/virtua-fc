@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var int $availableSurplus */
/** @var array $tiers */
/** @var array $tierThresholds */
/** @var bool $isLocked */
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <x-team-crest :team="$game->team" class="w-12 h-12" />
                <div>
                    <h2 class="font-semibold text-xl text-white">{{ __('finances.budget_allocation') }}</h2>
                    <p class="text-sm text-slate-500">{{ __('finances.season_budget', ['season' => $game->formatted_season]) }}</p>
                </div>
            </div>
            <a href="{{ route('game.finances', $game->id) }}" class="text-sm text-slate-400 hover:text-white">
                ← {{ __('app.back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('error'))
            <div class="mb-4 p-4 bg-accent-red/10 border border-accent-red/20 rounded-lg text-accent-red">
                {{ session('error') }}
            </div>
            @endif
            @if(session('success'))
            <div class="mb-4 p-4 bg-accent-green/10 border border-accent-green/20 rounded-lg text-accent-green">
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-surface-800 rounded-lg shadow-sm p-6 sm:p-8">
                {{-- Available Surplus Header --}}
                <div class="mb-8 text-center">
                    <div class="text-sm text-slate-500 uppercase tracking-wide mb-1">{{ __('finances.available_surplus') }}</div>
                    <div class="text-4xl font-bold text-white">{{ \App\Support\Money::format($availableSurplus) }}</div>
                    @if($finances->carried_debt > 0)
                    <div class="text-sm text-accent-red mt-1">
                        ({{ __('finances.after_debt_deduction', ['amount' => \App\Support\Money::format($finances->carried_debt)]) }})
                    </div>
                    @endif
                    @if($finances->carried_surplus > 0)
                    <div class="text-sm text-accent-green mt-1">
                        ({{ __('finances.includes_carried_surplus', ['amount' => \App\Support\Money::format($finances->carried_surplus)]) }})
                    </div>
                    @endif
                </div>

                <x-budget-allocation
                    :available-surplus="$availableSurplus"
                    :tiers="$tiers"
                    :tier-thresholds="$tierThresholds"
                    :is-locked="$isLocked"
                    :form-action="route('game.budget.save', $game->id)"
                    :submit-label="__('finances.confirm_budget_allocation')"
                />
            </div>
        </div>
    </div>
</x-app-layout>
