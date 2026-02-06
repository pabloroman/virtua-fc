@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var int $availableSurplus */
/** @var array $allocations */
/** @var array $tiers */
/** @var array $tierThresholds */
@endphp

<x-app-layout>
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Welcome Header --}}
            <div class="text-center mb-8">
                <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}" class="w-20 h-20 mx-auto mb-4">
                <h1 class="text-3xl font-bold text-white mb-1">Welcome to {{ $game->team->name }}</h1>
                <p class="text-slate-500">Season {{ $game->season }}</p>
            </div>

            {{-- Flash Messages --}}
            @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            {{-- Club Briefing --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
                <div class="grid grid-cols-2 gap-8">
                    {{-- Left Column --}}
                    <div class="space-y-6">
                        {{-- Stadium --}}
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Home Ground</h3>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $game->team->stadium_name ?? 'Club Stadium' }}</div>
                                    <div class="text-sm text-slate-500">{{ number_format($game->team->stadium_seats ?? 0) }} seats</div>
                                </div>
                            </div>
                        </div>

                        {{-- Squad Stats --}}
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Squad</h3>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $squadSize }}</div>
                                    <div class="text-xs text-slate-500">Players</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $averageAge }}</div>
                                    <div class="text-xs text-slate-500">Avg Age</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-slate-900">{{ \App\Support\Money::format($squadValue) }}</div>
                                    <div class="text-xs text-slate-500">Value</div>
                                </div>
                            </div>
                        </div>

                        {{-- Board Expectations --}}
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Board Expectations</h3>
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-amber-700 font-bold text-sm">{{ $finances->projected_position ?? '?' }}</span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-amber-900">Finish {{ $finances->projected_position ?? '?' }}{{ $finances->projected_position == 1 ? 'st' : ($finances->projected_position == 2 ? 'nd' : ($finances->projected_position == 3 ? 'rd' : 'th')) }} or better</div>
                                        <div class="text-xs text-amber-700 mt-0.5">Based on squad strength and financial projections</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right Column --}}
                    <div class="space-y-6">
                        {{-- Key Players --}}
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Key Players</h3>
                            <div class="space-y-2">
                                @foreach($keyPlayers as $player)
                                <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-slate-100' : '' }}">
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-semibold {{ $player->position_display['text'] }}">
                                            {{ $player->position_display['abbreviation'] }}
                                        </span>
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $player->player->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $player->age }} years old</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-slate-900">{{ (int) round(($player->game_technical_ability + $player->game_physical_ability) / 2) }}</div>
                                        <div class="text-xs text-slate-400">OVR</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- First Match --}}
                        @if($nextMatch)
                        <div>
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">First Match</h3>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium px-2 py-0.5 rounded {{ $isHomeMatch ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                                            {{ $isHomeMatch ? 'HOME' : 'AWAY' }}
                                        </span>
                                        <span class="text-sm text-slate-500">vs</span>
                                        <img src="{{ $opponent->image }}" alt="{{ $opponent->name }}" class="w-6 h-6">
                                        <span class="font-medium text-slate-900">{{ $opponent->name }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 mt-2 text-xs text-slate-500">
                                    <span>{{ $nextMatch->scheduled_date->format('D, M j') }}</span>
                                    <span>&middot;</span>
                                    <span>{{ $nextMatch->competition->name }}</span>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Budget Allocation --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6"
                 x-data="budgetAllocation({{ $availableSurplus }}, {{ json_encode($allocations) }}, {{ json_encode($tierThresholds) }})">

                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Season Budget</h2>
                        <p class="text-sm text-slate-500">Allocate funds across infrastructure and transfers</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-slate-900">{{ \App\Support\Money::format($availableSurplus) }}</div>
                        <div class="text-xs text-slate-500">Available</div>
                    </div>
                </div>

                {{-- Allocation Summary --}}
                <div class="mb-6 p-3 bg-slate-50 rounded-lg flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500">Allocated:</span>
                        <span class="font-bold" :class="totalAllocated > availableSurplus ? 'text-red-600' : 'text-slate-900'"
                              x-text="formatMoney(totalAllocated)"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500">Remaining:</span>
                        <span class="font-bold" :class="remaining < 0 ? 'text-red-600' : 'text-green-600'"
                              x-text="formatMoney(remaining)"></span>
                    </div>
                </div>

                <form action="{{ route('game.onboarding.complete', $game->id) }}" method="POST">
                    @csrf

                    {{-- Infrastructure Grid --}}
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        {{-- Youth Academy --}}
                        <div class="border border-slate-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-slate-900">Youth Academy</h4>
                                <div class="text-xs font-semibold" :class="getTierColor(youthTier)">Tier <span x-text="youthTier"></span></div>
                            </div>
                            <div class="text-lg font-bold text-slate-900 mb-2" x-text="formatMoney(youth_academy)"></div>
                            <input type="range" x-model="youth_academy" min="0" :max="availableSurplus" step="10000000"
                                   class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('youth_academy')">
                            <input type="hidden" name="youth_academy" :value="youth_academy / 100">
                        </div>

                        {{-- Medical --}}
                        <div class="border border-slate-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-slate-900">Medical</h4>
                                <div class="text-xs font-semibold" :class="getTierColor(medicalTier)">Tier <span x-text="medicalTier"></span></div>
                            </div>
                            <div class="text-lg font-bold text-slate-900 mb-2" x-text="formatMoney(medical)"></div>
                            <input type="range" x-model="medical" min="0" :max="availableSurplus" step="10000000"
                                   class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('medical')">
                            <input type="hidden" name="medical" :value="medical / 100">
                        </div>

                        {{-- Scouting --}}
                        <div class="border border-slate-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-slate-900">Scouting</h4>
                                <div class="text-xs font-semibold" :class="getTierColor(scoutingTier)">Tier <span x-text="scoutingTier"></span></div>
                            </div>
                            <div class="text-lg font-bold text-slate-900 mb-2" x-text="formatMoney(scouting)"></div>
                            <input type="range" x-model="scouting" min="0" :max="availableSurplus" step="10000000"
                                   class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('scouting')">
                            <input type="hidden" name="scouting" :value="scouting / 100">
                        </div>

                        {{-- Facilities --}}
                        <div class="border border-slate-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-slate-900">Facilities</h4>
                                <div class="text-xs font-semibold" :class="getTierColor(facilitiesTier)">Tier <span x-text="facilitiesTier"></span></div>
                            </div>
                            <div class="text-lg font-bold text-slate-900 mb-2" x-text="formatMoney(facilities)"></div>
                            <input type="range" x-model="facilities" min="0" :max="availableSurplus" step="10000000"
                                   class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-500"
                                   @input="clampField('facilities')">
                            <input type="hidden" name="facilities" :value="facilities / 100">
                        </div>
                    </div>

                    {{-- Transfer Budget --}}
                    <div class="border-2 border-sky-300 rounded-lg p-4 bg-sky-50 mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-medium text-slate-900">Transfer Budget</h4>
                            <div class="text-lg font-bold text-sky-700" x-text="formatMoney(transfer_budget)"></div>
                        </div>
                        <input type="range" x-model="transfer_budget" min="0" :max="availableSurplus" step="10000000"
                               class="w-full h-1.5 bg-sky-200 rounded-lg appearance-none cursor-pointer accent-sky-600"
                               @input="clampField('transfer_budget')">
                        <input type="hidden" name="transfer_budget" :value="transfer_budget / 100">
                    </div>

                    {{-- Warning --}}
                    <div x-show="!meetsMinimumRequirements" x-cloak class="mb-6 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                        All infrastructure areas must be at least Tier 1 to maintain professional status.
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                            class="w-full uppercase py-3 bg-red-600 text-white font-semibold rounded-lg tracking-wide hover:bg-red-700 focus:bg-red-700 active:bg-red-900 ease-in-out duration-150 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!meetsMinimumRequirements || totalAllocated > availableSurplus">
                        Begin Season
                    </button>
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

                get youthTier() { return this.calculateTier('youth_academy', this.youth_academy); },
                get medicalTier() { return this.calculateTier('medical', this.medical); },
                get scoutingTier() { return this.calculateTier('scouting', this.scouting); },
                get facilitiesTier() { return this.calculateTier('facilities', this.facilities); },

                get meetsMinimumRequirements() {
                    return this.youthTier >= 1 && this.medicalTier >= 1 && this.scoutingTier >= 1 && this.facilitiesTier >= 1;
                },

                calculateTier(area, amount) {
                    const areaThresholds = this.thresholds[area];
                    for (let tier = 4; tier >= 1; tier--) {
                        if (amount >= areaThresholds[tier]) return tier;
                    }
                    return 0;
                },

                clampField(field) {
                    const otherTotal = this.totalAllocated - parseInt(this[field]);
                    const maxForField = this.availableSurplus - otherTotal;
                    if (this[field] > maxForField) {
                        this[field] = Math.max(0, maxForField);
                    }
                },

                formatMoney(cents) {
                    const euros = cents / 100;
                    if (euros >= 1000000000) return '€' + (euros / 1000000000).toFixed(1) + 'B';
                    if (euros >= 1000000) return '€' + (euros / 1000000).toFixed(1) + 'M';
                    if (euros >= 1000) return '€' + (euros / 1000).toFixed(0) + 'K';
                    return '€' + euros.toFixed(0);
                },

                getTierColor(tier) {
                    const colors = { 0: 'text-red-600', 1: 'text-amber-600', 2: 'text-green-600', 3: 'text-blue-600', 4: 'text-purple-600' };
                    return colors[tier] || 'text-slate-600';
                }
            };
        }
    </script>
</x-app-layout>
