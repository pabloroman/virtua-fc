@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var int $availableSurplus */
/** @var array $allocations */
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

            <div class="bg-white rounded-lg shadow-sm p-8"
                 x-data="budgetAllocation({{ $availableSurplus }}, {{ json_encode($allocations) }}, {{ json_encode($tierThresholds) }})">

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

                {{-- Allocation Summary --}}
                <div class="mb-8 p-4 bg-slate-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-600">Total Allocated:</span>
                        <span class="text-xl font-bold" :class="totalAllocated > availableSurplus ? 'text-red-600' : 'text-slate-900'"
                              x-text="formatMoney(totalAllocated)"></span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-slate-600">Remaining:</span>
                        <span class="text-xl font-bold" :class="remaining < 0 ? 'text-red-600' : 'text-green-600'"
                              x-text="formatMoney(remaining)"></span>
                    </div>
                    <div class="mt-3">
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all duration-300"
                                 :class="percentUsed > 100 ? 'bg-red-500' : 'bg-sky-500'"
                                 :style="'width: ' + Math.min(percentUsed, 100) + '%'"></div>
                        </div>
                        <div class="text-xs text-slate-500 mt-1 text-right" x-text="percentUsed.toFixed(0) + '% allocated'"></div>
                    </div>
                </div>

                @if($isLocked)
                <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <span class="font-semibold">Budget Locked</span>
                    </div>
                    <p class="text-sm mt-1">Budget allocation is fixed for the season. Changes can be made next pre-season.</p>
                </div>
                @endif

                <form action="{{ route('game.budget.save', $game->id) }}" method="POST">
                    @csrf

                    {{-- Infrastructure Sliders --}}
                    <div class="space-y-8 mb-8">
                        {{-- Youth Academy --}}
                        <div class="border rounded-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="font-semibold text-lg text-slate-900">Youth Academy</h4>
                                    <p class="text-sm text-slate-500">Develop homegrown talent</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-slate-900" x-text="formatMoney(youth_academy)"></div>
                                    <div class="text-sm font-semibold" :class="getTierColor(youthTier)">
                                        Tier <span x-text="youthTier"></span>
                                    </div>
                                </div>
                            </div>
                            <input type="range"
                                   x-model="youth_academy"
                                   min="0"
                                   :max="availableSurplus"
                                   step="10000000"
                                   class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('youth_academy')"
                                   {{ $isLocked ? 'disabled' : '' }}>
                            <input type="hidden" name="youth_academy" :value="youth_academy / 100">
                            <div class="flex justify-between text-xs text-slate-400 mt-1">
                                <span>€0</span>
                                <span class="text-amber-600">Tier 1: €500K</span>
                                <span class="text-green-600">Tier 2: €2M</span>
                                <span class="text-blue-600">Tier 3: €8M</span>
                                <span class="text-purple-600">Tier 4: €20M</span>
                            </div>
                        </div>

                        {{-- Medical --}}
                        <div class="border rounded-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="font-semibold text-lg text-slate-900">Medical & Sports Science</h4>
                                    <p class="text-sm text-slate-500">Injury prevention and recovery</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-slate-900" x-text="formatMoney(medical)"></div>
                                    <div class="text-sm font-semibold" :class="getTierColor(medicalTier)">
                                        Tier <span x-text="medicalTier"></span>
                                    </div>
                                </div>
                            </div>
                            <input type="range"
                                   x-model="medical"
                                   min="0"
                                   :max="availableSurplus"
                                   step="10000000"
                                   class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('medical')"
                                   {{ $isLocked ? 'disabled' : '' }}>
                            <input type="hidden" name="medical" :value="medical / 100">
                            <div class="flex justify-between text-xs text-slate-400 mt-1">
                                <span>€0</span>
                                <span class="text-amber-600">Tier 1: €300K</span>
                                <span class="text-green-600">Tier 2: €1.5M</span>
                                <span class="text-blue-600">Tier 3: €5M</span>
                                <span class="text-purple-600">Tier 4: €12M</span>
                            </div>
                        </div>

                        {{-- Scouting --}}
                        <div class="border rounded-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="font-semibold text-lg text-slate-900">Scouting Network</h4>
                                    <p class="text-sm text-slate-500">Discover talent worldwide</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-slate-900" x-text="formatMoney(scouting)"></div>
                                    <div class="text-sm font-semibold" :class="getTierColor(scoutingTier)">
                                        Tier <span x-text="scoutingTier"></span>
                                    </div>
                                </div>
                            </div>
                            <input type="range"
                                   x-model="scouting"
                                   min="0"
                                   :max="availableSurplus"
                                   step="10000000"
                                   class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('scouting')"
                                   {{ $isLocked ? 'disabled' : '' }}>
                            <input type="hidden" name="scouting" :value="scouting / 100">
                            <div class="flex justify-between text-xs text-slate-400 mt-1">
                                <span>€0</span>
                                <span class="text-amber-600">Tier 1: €200K</span>
                                <span class="text-green-600">Tier 2: €1M</span>
                                <span class="text-blue-600">Tier 3: €4M</span>
                                <span class="text-purple-600">Tier 4: €10M</span>
                            </div>
                        </div>

                        {{-- Facilities --}}
                        <div class="border rounded-lg p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="font-semibold text-lg text-slate-900">Facilities</h4>
                                    <p class="text-sm text-slate-500">Stadium and matchday experience</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-slate-900" x-text="formatMoney(facilities)"></div>
                                    <div class="text-sm font-semibold" :class="getTierColor(facilitiesTier)">
                                        Tier <span x-text="facilitiesTier"></span>
                                    </div>
                                </div>
                            </div>
                            <input type="range"
                                   x-model="facilities"
                                   min="0"
                                   :max="availableSurplus"
                                   step="10000000"
                                   class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('facilities')"
                                   {{ $isLocked ? 'disabled' : '' }}>
                            <input type="hidden" name="facilities" :value="facilities / 100">
                            <div class="flex justify-between text-xs text-slate-400 mt-1">
                                <span>€0</span>
                                <span class="text-amber-600">Tier 1: €500K</span>
                                <span class="text-green-600">Tier 2: €3M</span>
                                <span class="text-blue-600">Tier 3: €10M</span>
                                <span class="text-purple-600">Tier 4: €25M</span>
                            </div>
                        </div>

                        {{-- Transfer Budget --}}
                        <div class="border-2 border-sky-200 rounded-lg p-6 bg-sky-50">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="font-semibold text-lg text-slate-900">Transfer Budget</h4>
                                    <p class="text-sm text-slate-500">Available for player transfers</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-sky-700" x-text="formatMoney(transfer_budget)"></div>
                                </div>
                            </div>
                            <input type="range"
                                   x-model="transfer_budget"
                                   min="0"
                                   :max="availableSurplus"
                                   step="10000000"
                                   class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('transfer_budget')"
                                   {{ $isLocked ? 'disabled' : '' }}>
                            <input type="hidden" name="transfer_budget" :value="transfer_budget / 100">
                        </div>
                    </div>

                    {{-- Minimum Requirements Warning --}}
                    <div x-show="!meetsMinimumRequirements" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span class="font-semibold">Minimum Requirements Not Met</span>
                        </div>
                        <p class="text-sm mt-1">All infrastructure areas must be at least Tier 1 to maintain professional status.</p>
                    </div>

                    {{-- Submit Button --}}
                    @unless($isLocked)
                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-6 py-3 bg-sky-600 text-white font-semibold rounded-lg hover:bg-sky-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!meetsMinimumRequirements || totalAllocated > availableSurplus">
                            Confirm Budget Allocation
                        </button>
                    </div>
                    @endunless
                </form>
            </div>
        </div>
    </div>

    <script>
        function budgetAllocation(availableSurplus, initialAllocations, thresholds) {
            return {
                availableSurplus: availableSurplus,
                thresholds: thresholds,
                youth_academy: initialAllocations.youth_academy,
                medical: initialAllocations.medical,
                scouting: initialAllocations.scouting,
                facilities: initialAllocations.facilities,
                transfer_budget: initialAllocations.transfer_budget,

                get totalAllocated() {
                    return parseInt(this.youth_academy) + parseInt(this.medical) + parseInt(this.scouting) + parseInt(this.facilities) + parseInt(this.transfer_budget);
                },

                get remaining() {
                    return this.availableSurplus - this.totalAllocated;
                },

                get percentUsed() {
                    return this.availableSurplus > 0 ? (this.totalAllocated / this.availableSurplus) * 100 : 0;
                },

                get youthTier() {
                    return this.calculateTier('youth_academy', this.youth_academy);
                },

                get medicalTier() {
                    return this.calculateTier('medical', this.medical);
                },

                get scoutingTier() {
                    return this.calculateTier('scouting', this.scouting);
                },

                get facilitiesTier() {
                    return this.calculateTier('facilities', this.facilities);
                },

                get meetsMinimumRequirements() {
                    return this.youthTier >= 1 && this.medicalTier >= 1 && this.scoutingTier >= 1 && this.facilitiesTier >= 1;
                },

                calculateTier(area, amount) {
                    const areaThresholds = this.thresholds[area];
                    for (let tier = 4; tier >= 1; tier--) {
                        if (amount >= areaThresholds[tier]) {
                            return tier;
                        }
                    }
                    return 0;
                },

                clampTotal() {
                    // If total exceeds available, reduce the excess from the current allocation
                    const total = this.totalAllocated;
                    if (total > this.availableSurplus) {
                        const excess = total - this.availableSurplus;
                        // Try to reduce transfer budget first
                        if (this.transfer_budget >= excess) {
                            this.transfer_budget = parseInt(this.transfer_budget) - excess;
                        } else {
                            // Not enough in transfer budget, cap the last changed value
                            this.transfer_budget = 0;
                        }
                    }
                },

                // Clamp a specific field to not exceed remaining budget
                clampField(field) {
                    const otherTotal = this.totalAllocated - parseInt(this[field]);
                    const maxForField = this.availableSurplus - otherTotal;
                    if (this[field] > maxForField) {
                        this[field] = Math.max(0, maxForField);
                    }
                },

                formatMoney(cents) {
                    const euros = cents / 100;
                    if (euros >= 1000000000) {
                        return '€' + (euros / 1000000000).toFixed(1) + 'B';
                    }
                    if (euros >= 1000000) {
                        return '€' + (euros / 1000000).toFixed(1) + 'M';
                    }
                    if (euros >= 1000) {
                        return '€' + (euros / 1000).toFixed(0) + 'K';
                    }
                    return '€' + euros.toFixed(0);
                },

                getTierColor(tier) {
                    switch(tier) {
                        case 0: return 'text-red-600';
                        case 1: return 'text-amber-600';
                        case 2: return 'text-green-600';
                        case 3: return 'text-blue-600';
                        case 4: return 'text-purple-600';
                        default: return 'text-slate-600';
                    }
                }
            };
        }
    </script>
</x-app-layout>
