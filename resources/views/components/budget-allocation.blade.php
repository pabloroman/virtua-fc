@props([
    'availableSurplus',
    'tiers',
    'tierThresholds',
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

    get meetsMinimumRequirements() {
        return this.youth_academy_tier >= 1 && this.medical_tier >= 1 && this.scouting_tier >= 1 && this.facilities_tier >= 1;
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
        const colors = { 0: 'text-red-600', 1: 'text-amber-600', 2: 'text-green-600', 3: 'text-blue-600', 4: 'text-purple-600' };
        return colors[t] || 'text-slate-600';
    }
}">

    {{-- Allocation Summary --}}
    <div class="mb-6 p-3 bg-slate-50 rounded-lg flex items-center justify-between text-sm">
        <div class="flex items-center gap-2">
            <span class="text-slate-500">{{ __('finances.infrastructure') }}</span>
            <span class="font-bold text-slate-900" x-text="formatMoney(infrastructureTotal)"></span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-slate-500">{{ __('finances.transfers') }}</span>
            <span class="font-bold text-sky-600" x-text="formatMoney(transfer_budget)"></span>
        </div>
    </div>

    @if($isLocked)
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span class="font-semibold">{{ __('finances.budget_locked') }}</span>
        </div>
        <p class="text-sm mt-1">{{ __('finances.budget_locked_desc') }}</p>
    </div>
    @endif

    <form action="{{ $formAction }}" method="POST">
        @csrf

        {{-- Infrastructure Grid --}}
        <div class="grid grid-cols-2 gap-4 mb-4">
            {{-- Youth Academy --}}
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-medium text-slate-900">{{ __('finances.youth_academy') }}</h4>
                    <div class="text-xs font-semibold" :class="getTierColor(youth_academy_tier)">{{ __('finances.tier_n') }} <span x-text="youth_academy_tier"></span></div>
                </div>
                <div class="text-lg font-bold text-slate-900 mb-1" x-text="formatMoney(youth_academy_amount)"></div>
                <div class="text-xs text-slate-500 mb-2 h-4">
                    <span x-show="youth_academy_tier == 0">{{ __('finances.youth_academy_tier_0') }}</span>
                    <span x-show="youth_academy_tier == 1">{{ __('finances.youth_academy_tier_1') }}</span>
                    <span x-show="youth_academy_tier == 2">{{ __('finances.youth_academy_tier_2') }}</span>
                    <span x-show="youth_academy_tier == 3">{{ __('finances.youth_academy_tier_3') }}</span>
                    <span x-show="youth_academy_tier == 4">{{ __('finances.youth_academy_tier_4') }}</span>
                </div>
                <input type="range" x-model="youth_academy_tier" min="0" max="4" step="1"
                       class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                       {{ $isLocked ? 'disabled' : '' }}>
                <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                    <span>T0</span><span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="youth_academy" :value="youth_academy_amount / 100">
            </div>

            {{-- Medical --}}
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-medium text-slate-900">{{ __('finances.medical') }}</h4>
                    <div class="text-xs font-semibold" :class="getTierColor(medical_tier)">{{ __('finances.tier_n') }} <span x-text="medical_tier"></span></div>
                </div>
                <div class="text-lg font-bold text-slate-900 mb-1" x-text="formatMoney(medical_amount)"></div>
                <div class="text-xs text-slate-500 mb-2 h-4">
                    <span x-show="medical_tier == 0">{{ __('finances.medical_tier_0') }}</span>
                    <span x-show="medical_tier == 1">{{ __('finances.medical_tier_1') }}</span>
                    <span x-show="medical_tier == 2">{{ __('finances.medical_tier_2') }}</span>
                    <span x-show="medical_tier == 3">{{ __('finances.medical_tier_3') }}</span>
                    <span x-show="medical_tier == 4">{{ __('finances.medical_tier_4') }}</span>
                </div>
                <input type="range" x-model="medical_tier" min="0" max="4" step="1"
                       class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                       {{ $isLocked ? 'disabled' : '' }}>
                <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                    <span>T0</span><span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="medical" :value="medical_amount / 100">
            </div>

            {{-- Scouting --}}
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-medium text-slate-900">{{ __('finances.scouting') }}</h4>
                    <div class="text-xs font-semibold" :class="getTierColor(scouting_tier)">{{ __('finances.tier_n') }} <span x-text="scouting_tier"></span></div>
                </div>
                <div class="text-lg font-bold text-slate-900 mb-1" x-text="formatMoney(scouting_amount)"></div>
                <div class="text-xs text-slate-500 mb-2 h-4">
                    <span x-show="scouting_tier == 0">{{ __('finances.scouting_tier_0') }}</span>
                    <span x-show="scouting_tier == 1">{{ __('finances.scouting_tier_1') }}</span>
                    <span x-show="scouting_tier == 2">{{ __('finances.scouting_tier_2') }}</span>
                    <span x-show="scouting_tier == 3">{{ __('finances.scouting_tier_3') }}</span>
                    <span x-show="scouting_tier == 4">{{ __('finances.scouting_tier_4') }}</span>
                </div>
                <input type="range" x-model="scouting_tier" min="0" max="4" step="1"
                       class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                       {{ $isLocked ? 'disabled' : '' }}>
                <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                    <span>T0</span><span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="scouting" :value="scouting_amount / 100">
            </div>

            {{-- Facilities --}}
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-medium text-slate-900">{{ __('finances.facilities') }}</h4>
                    <div class="text-xs font-semibold" :class="getTierColor(facilities_tier)">{{ __('finances.tier_n') }} <span x-text="facilities_tier"></span></div>
                </div>
                <div class="text-lg font-bold text-slate-900 mb-1" x-text="formatMoney(facilities_amount)"></div>
                <div class="text-xs text-slate-500 mb-2 h-4">
                    <span x-show="facilities_tier == 0">{{ __('finances.facilities_tier_0') }}</span>
                    <span x-show="facilities_tier == 1">{{ __('finances.facilities_tier_1') }}</span>
                    <span x-show="facilities_tier == 2">{{ __('finances.facilities_tier_2') }}</span>
                    <span x-show="facilities_tier == 3">{{ __('finances.facilities_tier_3') }}</span>
                    <span x-show="facilities_tier == 4">{{ __('finances.facilities_tier_4') }}</span>
                </div>
                <input type="range" x-model="facilities_tier" min="0" max="4" step="1"
                       class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                       {{ $isLocked ? 'disabled' : '' }}>
                <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                    <span>T0</span><span>T1</span><span>T2</span><span>T3</span><span>T4</span>
                </div>
                <input type="hidden" name="facilities" :value="facilities_amount / 100">
            </div>
        </div>

        {{-- Transfer Budget (Auto-calculated) --}}
        <div class="border-2 border-sky-300 rounded-lg p-4 bg-sky-50 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-slate-900">{{ __('finances.transfer_budget') }}</h4>
                    <p class="text-xs text-slate-500">{{ __('finances.remainder_after_infrastructure') }}</p>
                </div>
                <div class="text-xl font-bold text-sky-700" x-text="formatMoney(transfer_budget)"></div>
            </div>
            <input type="hidden" name="transfer_budget" :value="transfer_budget / 100">
        </div>

        {{-- Warning --}}
        <div x-show="!meetsMinimumRequirements" x-cloak class="mb-6 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
            {{ __('finances.tier_minimum_warning') }}
        </div>

        {{-- Submit --}}
        @unless($isLocked)
        <button type="submit"
                class="w-full uppercase py-3 bg-red-600 text-white font-semibold rounded-lg tracking-wide hover:bg-red-700 focus:bg-red-700 active:bg-red-900 ease-in-out duration-150 transition disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!meetsMinimumRequirements">
            {{ $submitLabel }}
        </button>
        @endunless
    </form>
</div>
