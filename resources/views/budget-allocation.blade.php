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
                <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}" class="w-12 h-12">
                <div>
                    <h2 class="font-semibold text-xl text-slate-800">Budget Allocation</h2>
                    <p class="text-sm text-slate-500">{{ $game->team->name }} - Season {{ $game->season }}</p>
                </div>
            </div>
            <a href="{{ route('game.preseason', $game->id) }}" class="text-sm text-slate-600 hover:text-slate-900">
                Back to Pre-Season
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif
            @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm p-8">
                {{-- Available Surplus Header --}}
                <div class="mb-8 text-center">
                    <div class="text-sm text-slate-500 uppercase tracking-wide mb-1">Available Surplus</div>
                    <div class="text-4xl font-bold text-slate-900">{{ \App\Support\Money::format($availableSurplus) }}</div>
                    @if($finances->carried_debt > 0)
                    <div class="text-sm text-red-600 mt-1">
                        (After {{ \App\Support\Money::format($finances->carried_debt) }} debt deduction)
                    </div>
                    @endif
                </div>

                <x-budget-allocation
                    :available-surplus="$availableSurplus"
                    :tiers="$tiers"
                    :tier-thresholds="$tierThresholds"
                    :is-locked="$isLocked"
                    :form-action="route('game.budget.save', $game->id)"
                    submit-label="Confirm Budget Allocation"
                />
            </div>
        </div>
    </div>
</x-app-layout>
