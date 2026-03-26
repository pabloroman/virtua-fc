@props([
    'availableSurplus',
    'tiers',
    'tierThresholds',
    'minTiers' => ['youth_academy' => 1, 'medical' => 1, 'scouting' => 1, 'facilities' => 1],
    'isLocked' => false,
    'formAction',
    'submitLabel' => null,
    'compact' => false,
])

@php
$submitLabel = $submitLabel ?? __('finances.confirm_budget_allocation');
@endphp

<div x-data="{
    availableSurplus: {{ $availableSurplus }},
    thresholds: {{ json_encode($tierThresholds) }},
    minTiers: @js($minTiers),
    youth_academy_tier: {{ $tiers['youth_academy'] }},
    medical_tier: {{ $tiers['medical'] }},
    scouting_tier: {{ $tiers['scouting'] }},
    facilities_tier: {{ $tiers['facilities'] }},

    getAmount(area, tier) {
        if (tier === 0) return 0;
        return this.thresholds[area][tier] || 0;
    },

    get youth_academy_amount() { return this.getAmount('youth_academy', parseInt(this.youth_academy_tier)); },
    get medical_amount() { return this.getAmount('medical', parseInt(this.medical_tier)); },
    get scouting_amount() { return this.getAmount('scouting', parseInt(this.scouting_tier)); },
    get facilities_amount() { return this.getAmount('facilities', parseInt(this.facilities_tier)); },

    get infrastructureTotal() {
        return this.youth_academy_amount + this.medical_amount + this.scouting_amount + this.facilities_amount;
    },

    get transfer_budget() {
        return Math.max(0, this.availableSurplus - this.infrastructureTotal);
    },

    get exceedsBudget() {
        return this.infrastructureTotal > this.availableSurplus;
    },

    get meetsMinimumRequirements() {
        return this.youth_academy_tier >= this.minTiers.youth_academy
            && this.medical_tier >= this.minTiers.medical
            && this.scouting_tier >= this.minTiers.scouting
            && this.facilities_tier >= this.minTiers.facilities;
    },

    formatMoney(cents) {
        const euros = cents / 100;
        if (euros >= 1000000000) return '€' + (euros / 1000000000).toFixed(1) + 'B';
        if (euros >= 1000000) return '€' + (euros / 1000000).toFixed(1) + 'M';
        if (euros >= 1000) return '€' + (euros / 1000).toFixed(0) + 'K';
        return '€' + euros.toFixed(0);
    },

    getTierColor(tier) {
        const t = parseInt(tier);
        const colors = { 0: 'text-accent-red', 1: 'text-accent-gold', 2: 'text-accent-green', 3: 'text-accent-blue', 4: 'text-purple-400' };
        return colors[t] || 'text-text-secondary';
    }
}">

    {{-- Allocation Summary --}}
    <div class="mb-6 p-3 rounded-lg flex items-center justify-between text-sm transition-colors duration-200"
         :class="exceedsBudget ? 'bg-accent-red/10 ring-1 ring-accent-red/20' : 'bg-surface-700/50'">
        <div class="flex items-center gap-2">
            <span class="text-text-muted">{{ __('finances.infrastructure') }}</span>
            <span class="font-semibold" :class="exceedsBudget ? 'text-accent-red' : 'text-text-primary'" x-text="formatMoney(infrastructureTotal)"></span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-text-muted">{{ __('finances.available_remaining') }}</span>
            <span class="font-semibold" :class="exceedsBudget ? 'text-accent-red' : 'text-accent-green'" x-text="exceedsBudget ? '-' + formatMoney(infrastructureTotal - availableSurplus) : formatMoney(availableSurplus - infrastructureTotal)"></span>
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
                    <div class="track-fill" :style="'width:' + (youth_academy_tier / 4 * 100) + '%'"></div>
                    <input type="range" x-model="youth_academy_tier" :min="minTiers.youth_academy" max="4" step="1" {{ $isLocked ? 'disabled' : '' }}>
                </div>
                <div class="flex justify-between text-[10px] text-text-faint mt-1">
                    <span>T0</span><span>T1</span><span>T2</span><span>T3</span><span>T4</span>
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
                    <div class="track-fill" :style="'width:' + (medical_tier / 4 * 100) + '%'"></div>
                    <input type="range" x-model="medical_tier" :min="minTiers.medical" max="4" step="1" {{ $isLocked ? 'disabled' : '' }}>
                </div>
                <div class="flex justify-between text-[10px] text-text-faint mt-1">
                    <span>T0</span><span>T1</span><span>T2</span><span>T3</span><span>T4</span>
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
                    <div class="track-fill" :style="'width:' + (scouting_tier / 4 * 100) + '%'"></div>
                    <input type="range" x-model="scouting_tier" :min="minTiers.scouting" max="4" step="1" {{ $isLocked ? 'disabled' : '' }}>
                </div>
                <div class="flex justify-between text-[10px] text-text-faint mt-1">
                    <span>T0</span><span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="scouting" :value="scouting_amount / 100">
            </div>

            {{-- Facilities --}}
            <div class="bg-surface-700/50 border border-border-default rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-heading text-xs font-semibold uppercase tracking-widest text-text-secondary">{{ __('finances.facilities') }}</h4>
                    <div class="font-heading text-xs font-semibold" :class="getTierColor(facilities_tier)">{{ __('finances.tier_n') }} <span x-text="facilities_tier"></span></div>
                </div>
                <div class="font-heading text-lg font-bold text-text-primary mb-1" x-text="formatMoney(facilities_amount)"></div>
                <div class="text-xs text-text-muted mb-2 h-4">
                    <span x-show="facilities_tier == 0">{{ __('finances.facilities_tier_0') }}</span>
                    <span x-show="facilities_tier == 1">{{ __('finances.facilities_tier_1') }}</span>
                    <span x-show="facilities_tier == 2">{{ __('finances.facilities_tier_2') }}</span>
                    <span x-show="facilities_tier == 3">{{ __('finances.facilities_tier_3') }}</span>
                    <span x-show="facilities_tier == 4">{{ __('finances.facilities_tier_4') }}</span>
                </div>
                <div class="tier-range">
                    <div class="track"></div>
                    <div class="track-fill" :style="'width:' + (facilities_tier / 4 * 100) + '%'"></div>
                    <input type="range" x-model="facilities_tier" :min="minTiers.facilities" max="4" step="1" {{ $isLocked ? 'disabled' : '' }}>
                </div>
                <div class="flex justify-between text-[10px] text-text-faint mt-1">
                    <span>T0</span><span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="facilities" :value="facilities_amount / 100">
            </div>
        </div>

        {{-- Transfer Budget (Auto-calculated) --}}
        <div class="border border-accent-blue/20 rounded-lg bg-accent-blue/10 p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-heading text-sm font-semibold text-text-primary">{{ __('finances.transfer_budget') }}</h4>
                    <p class="text-xs text-text-muted">{{ __('finances.remainder_after_infrastructure') }}</p>
                </div>
                <div class="font-heading text-xl font-bold text-accent-blue" x-text="formatMoney(transfer_budget)"></div>
            </div>
            <input type="hidden" name="transfer_budget" :value="transfer_budget / 100">
        </div>

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
