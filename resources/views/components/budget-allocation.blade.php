@props([
    'availableSurplus',
    'tiers',
    'tierThresholds',
    'minimumTier' => 1,
    'isLocked' => false,
    'formAction',
    'submitLabel' => null,
    'compact' => false,
])

@php
$submitLabel = $submitLabel ?? __('finances.confirm_budget_allocation');
@endphp

<div x-data="budgetAllocation({
    availableSurplus: @js((int) $availableSurplus),
    thresholds: @js($tierThresholds),
    minimumTier: @js((int) $minimumTier),
    tiers: {
        youth_academy: @js((int) $tiers['youth_academy']),
        medical: @js((int) $tiers['medical']),
        scouting: @js((int) $tiers['scouting']),
    },
})">

    {{-- Budget breakdown — one live summary (available → infrastructure → transfers),
         so the figure isn't repeated across separate banners. --}}
    <div class="mb-6 grid grid-cols-3 divide-x divide-border-default bg-surface-800 border border-border-default rounded-xl overflow-hidden">
        <div class="px-4 py-3">
            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('game.available') }}</div>
            <div class="font-heading text-lg md:text-xl font-bold text-text-primary mt-0.5">{{ \App\Support\Money::format($availableSurplus) }}</div>
        </div>
        <div class="px-4 py-3">
            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.total_infrastructure') }}</div>
            <div class="font-heading text-lg md:text-xl font-bold mt-0.5" :class="exceedsBudget ? 'text-accent-red' : 'text-text-primary'" x-text="formatMoney(infrastructureTotal)"></div>
        </div>
        <div class="px-4 py-3">
            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('finances.transfer_budget') }}</div>
            <div class="font-heading text-lg md:text-xl font-bold text-accent-blue mt-0.5" x-text="formatMoney(transfer_budget)"></div>
        </div>
    </div>

    @if($isLocked)
    <div class="mb-6 p-4 bg-accent-gold/10 border border-accent-gold/20 rounded-lg text-accent-gold">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span class="font-semibold">{{ __('finances.budget_locked') }}</span>
        </div>
        <p class="text-sm mt-1 text-amber-300/80">{{ __('finances.budget_locked_desc') }}</p>
    </div>
    @endif

    <form action="{{ $formAction }}" method="POST">
        @csrf

        {{-- Infrastructure Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            {{-- Youth Academy --}}
            <div class="bg-surface-700/50 border border-border-default rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-heading text-xs font-semibold uppercase tracking-widest text-text-secondary">{{ __('finances.youth_academy') }}</h4>
                    <div class="font-heading text-xs font-semibold" :class="getTierColor(youth_academy_tier)">{{ __('finances.tier_n') }} <span x-text="youth_academy_tier"></span></div>
                </div>
                <div class="font-heading text-lg font-bold text-text-primary mb-1" x-text="formatMoney(youth_academy_amount)"></div>
                <div class="text-xs text-text-muted mb-2 h-4">
                    <span x-show="youth_academy_tier == 0">{{ __('finances.youth_academy_tier_0') }}</span>
                    <span x-show="youth_academy_tier == 1">{{ __('finances.youth_academy_tier_1') }}</span>
                    <span x-show="youth_academy_tier == 2">{{ __('finances.youth_academy_tier_2') }}</span>
                    <span x-show="youth_academy_tier == 3">{{ __('finances.youth_academy_tier_3') }}</span>
                    <span x-show="youth_academy_tier == 4">{{ __('finances.youth_academy_tier_4') }}</span>
                </div>
                <div class="tier-range">
                    <div class="track"></div>
                    <div class="track-fill" :style="'width:' + fillPercent(youth_academy_tier) + '%'"></div>
                    <input type="range" x-model="youth_academy_tier" min="{{ (int) $minimumTier }}" max="4" step="1" {{ $isLocked ? 'disabled' : '' }}>
                </div>
                <div class="flex justify-between text-[10px] text-text-faint mt-1">
                    @if((int) $minimumTier === 0)<span>T0</span>@endif
                    <span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="youth_academy" :value="youth_academy_amount / 100">
            </div>

            {{-- Medical --}}
            <div class="bg-surface-700/50 border border-border-default rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-heading text-xs font-semibold uppercase tracking-widest text-text-secondary">{{ __('finances.medical') }}</h4>
                    <div class="font-heading text-xs font-semibold" :class="getTierColor(medical_tier)">{{ __('finances.tier_n') }} <span x-text="medical_tier"></span></div>
                </div>
                <div class="font-heading text-lg font-bold text-text-primary mb-1" x-text="formatMoney(medical_amount)"></div>
                <div class="text-xs text-text-muted mb-2 h-4">
                    <span x-show="medical_tier == 0">{{ __('finances.medical_tier_0') }}</span>
                    <span x-show="medical_tier == 1">{{ __('finances.medical_tier_1') }}</span>
                    <span x-show="medical_tier == 2">{{ __('finances.medical_tier_2') }}</span>
                    <span x-show="medical_tier == 3">{{ __('finances.medical_tier_3') }}</span>
                    <span x-show="medical_tier == 4">{{ __('finances.medical_tier_4') }}</span>
                </div>
                <div class="tier-range">
                    <div class="track"></div>
                    <div class="track-fill" :style="'width:' + fillPercent(medical_tier) + '%'"></div>
                    <input type="range" x-model="medical_tier" min="{{ (int) $minimumTier }}" max="4" step="1" {{ $isLocked ? 'disabled' : '' }}>
                </div>
                <div class="flex justify-between text-[10px] text-text-faint mt-1">
                    @if((int) $minimumTier === 0)<span>T0</span>@endif
                    <span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="medical" :value="medical_amount / 100">
            </div>

            {{-- Scouting --}}
            <div class="bg-surface-700/50 border border-border-default rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-heading text-xs font-semibold uppercase tracking-widest text-text-secondary">{{ __('finances.scouting') }}</h4>
                    <div class="font-heading text-xs font-semibold" :class="getTierColor(scouting_tier)">{{ __('finances.tier_n') }} <span x-text="scouting_tier"></span></div>
                </div>
                <div class="font-heading text-lg font-bold text-text-primary mb-1" x-text="formatMoney(scouting_amount)"></div>
                <div class="text-xs text-text-muted mb-2 h-4">
                    <span x-show="scouting_tier == 0">{{ __('finances.scouting_tier_0') }}</span>
                    <span x-show="scouting_tier == 1">{{ __('finances.scouting_tier_1') }}</span>
                    <span x-show="scouting_tier == 2">{{ __('finances.scouting_tier_2') }}</span>
                    <span x-show="scouting_tier == 3">{{ __('finances.scouting_tier_3') }}</span>
                    <span x-show="scouting_tier == 4">{{ __('finances.scouting_tier_4') }}</span>
                </div>
                <div class="tier-range">
                    <div class="track"></div>
                    <div class="track-fill" :style="'width:' + fillPercent(scouting_tier) + '%'"></div>
                    <input type="range" x-model="scouting_tier" min="{{ (int) $minimumTier }}" max="4" step="1" {{ $isLocked ? 'disabled' : '' }}>
                </div>
                <div class="flex justify-between text-[10px] text-text-faint mt-1">
                    @if((int) $minimumTier === 0)<span>T0</span>@endif
                    <span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="scouting" :value="scouting_amount / 100">
            </div>
        </div>

        {{-- Transfer budget is shown live in the breakdown above; just submit the value. --}}
        <input type="hidden" name="transfer_budget" :value="transfer_budget / 100">

        {{-- Warnings --}}
        <div x-show="exceedsBudget" x-cloak class="mb-6 p-3 bg-accent-red/10 border border-accent-red/20 rounded-lg text-accent-red text-sm">
            {{ __('finances.budget_exceeds_surplus') }}
        </div>
        <div x-show="!meetsMinimumRequirements && !exceedsBudget" x-cloak class="mb-6 p-3 bg-accent-red/10 border border-accent-red/20 rounded-lg text-accent-red text-sm">
            {{ __('finances.tier_minimum_warning') }}
        </div>

        {{-- Submit --}}
        @unless($isLocked)
        <x-primary-button x-bind:disabled="!meetsMinimumRequirements || exceedsBudget" class="w-full uppercase">
            {{ $submitLabel }}
        </x-primary-button>
        @endunless
    </form>
</div>
