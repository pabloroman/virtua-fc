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
        <x-game-header :game="$game" :next-match="$game->next_match ?? null"></x-game-header>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 pb-8">
        {{-- Page Header --}}
        <div class="mt-6 mb-6 flex items-center justify-between">
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('finances.budget_allocation') }}</h2>
                <p class="text-sm text-text-muted mt-0.5">{{ __('finances.season_budget', ['season' => $game->formatted_season]) }}</p>
            </div>
            <a href="{{ route('game.finances', $game->id) }}" class="text-sm text-text-muted hover:text-text-primary transition-colors">
                &larr; {{ __('app.back') }}
            </a>
        </div>

        {{-- Flash Messages --}}
        <x-flash-message type="error" :message="session('error')" class="mb-4" />
        <x-flash-message type="success" :message="session('success')" class="mb-4" />

        <div class="bg-surface-800 border border-border-default rounded-xl p-6 sm:p-8">
            {{-- Available Surplus Header --}}
            <div class="mb-8 text-center">
                <div class="text-[10px] text-text-muted uppercase tracking-widest mb-1">{{ __('finances.available_surplus') }}</div>
                <div class="font-heading text-4xl font-bold text-text-primary">{{ \App\Support\Money::format($availableSurplus) }}</div>
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
                :min-tiers="$minTiers"
                :tier-thresholds="$tierThresholds"
                :is-locked="$isLocked"
                :form-action="route('game.budget.save', $game->id)"
                :submit-label="__('finances.confirm_budget_allocation')"
            />
        </div>
    </div>
</x-app-layout>
