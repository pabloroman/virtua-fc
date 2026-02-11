@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ __('transfers.title') }}</h3>
                        <div class="flex items-center gap-6 text-sm">
                            <div class="text-slate-600">
                                @if($isTransferWindow)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                        {{ __('transfers.window_open', ['window' => $currentWindow]) }}
                                    </span>
                                @else
                                    {{ __('transfers.window') }}: <span class="font-semibold text-slate-900">{{ __('app.window_closed') }}</span>
                                @endif
                            </div>
                            @if($game->currentInvestment)
                            <div class="text-slate-600">
                                {{ __('transfers.budget') }}: <span class="font-semibold text-slate-900">{{ $game->currentInvestment->formatted_transfer_budget }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tab Navigation --}}
                    <x-section-nav :items="[
                        ['href' => route('game.transfers', $game->id), 'label' => __('transfers.market'), 'active' => false],
                        ['href' => route('game.scouting', $game->id), 'label' => __('transfers.scouting'), 'active' => true],
                        ['href' => route('game.loans', $game->id), 'label' => __('transfers.loans'), 'active' => false],
                    ]" />

                    {{-- State: Show search form --}}
                    @if($showForm)
                        <div class="mt-6" x-data="{
                            ageMin: 16,
                            ageMax: 45,
                            abilityMin: 1,
                            abilityMax: 99,
                            valueStepMin: 0,
                            valueStepMax: 9,
                            valueSteps: [0, 500000, 1000000, 2000000, 5000000, 10000000, 20000000, 50000000, 100000000, 200000000],
                            valueMin() { return this.valueSteps[this.valueStepMin]; },
                            valueMax() { return this.valueSteps[this.valueStepMax]; },
                            enforceValueMin() { if (this.valueStepMin > this.valueStepMax) this.valueStepMax = this.valueStepMin; },
                            enforceValueMax() { if (this.valueStepMax < this.valueStepMin) this.valueStepMin = this.valueStepMax; },
                            scopeDomestic: true,
                            scopeInternational: true,
                            formatValue(val) {
                                if (val === 0) return '€0';
                                if (val >= 1000000) return '€' + (val / 1000000) + 'M';
                                if (val >= 1000) return '€' + (val / 1000) + 'K';
                                return '€' + val;
                            },
                            ageTrackLeft() { return ((this.ageMin - 16) / (45 - 16)) * 100 + '%'; },
                            ageTrackWidth() { return ((this.ageMax - this.ageMin) / (45 - 16)) * 100 + '%'; },
                            abilityTrackLeft() { return ((this.abilityMin - 1) / (99 - 1)) * 100 + '%'; },
                            abilityTrackWidth() { return ((this.abilityMax - this.abilityMin) / (99 - 1)) * 100 + '%'; },
                            valueTrackLeft() { return (this.valueStepMin / 9) * 100 + '%'; },
                            valueTrackWidth() { return ((this.valueStepMax - this.valueStepMin) / 9) * 100 + '%'; },
                            enforceAgeMin() { if (this.ageMin > this.ageMax) this.ageMax = this.ageMin; },
                            enforceAgeMax() { if (this.ageMax < this.ageMin) this.ageMin = this.ageMax; },
                            enforceAbilityMin() { if (this.abilityMin > this.abilityMax) this.abilityMax = this.abilityMin; },
                            enforceAbilityMax() { if (this.abilityMax < this.abilityMin) this.abilityMin = this.abilityMax; },
                        }">
                            <p class="text-sm text-slate-600 mb-6">{{ __('transfers.scout_search_desc') }}</p>

                            <form method="post" action="{{ route('game.scouting.search', $game->id) }}">
                                @csrf

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-5">
                                    {{-- Position --}}
                                    <div>
                                        <label for="position" class="block text-sm font-semibold text-slate-700 mb-1">{{ __('transfers.position_required') }}</label>
                                        <x-select-input name="position" id="position" required class="w-full">
                                            <option value="">{{ __('transfers.select_position') }}</option>
                                            <optgroup label="{{ __('transfers.specific_positions') }}">
                                                <option value="GK">{{ __('transfers.position_gk') }}</option>
                                                <option value="CB">{{ __('transfers.position_cb') }}</option>
                                                <option value="LB">{{ __('transfers.position_lb') }}</option>
                                                <option value="RB">{{ __('transfers.position_rb') }}</option>
                                                <option value="DM">{{ __('transfers.position_dm') }}</option>
                                                <option value="CM">{{ __('transfers.position_cm') }}</option>
                                                <option value="AM">{{ __('transfers.position_am') }}</option>
                                                <option value="LW">{{ __('transfers.position_lw') }}</option>
                                                <option value="RW">{{ __('transfers.position_rw') }}</option>
                                                <option value="CF">{{ __('transfers.position_cf') }}</option>
                                            </optgroup>
                                            <optgroup label="{{ __('transfers.position_groups') }}">
                                                <option value="any_defender">{{ __('transfers.any_defender') }}</option>
                                                <option value="any_midfielder">{{ __('transfers.any_midfielder') }}</option>
                                                <option value="any_forward">{{ __('transfers.any_forward') }}</option>
                                            </optgroup>
                                        </x-select-input>
                                        @error('position')
                                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Scope --}}
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('transfers.scope') }}</label>
                                        <div class="flex items-center gap-5 mt-2">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <x-checkbox-input name="scope[]" value="domestic" x-model="scopeDomestic" />
                                                <span class="text-sm text-slate-700">{{ __('transfers.scope_domestic') }}</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <x-checkbox-input name="scope[]" value="international" x-model="scopeInternational" />
                                                <span class="text-sm text-slate-700">{{ __('transfers.scope_international') }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- Expiring contract --}}
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('transfers.contract') }}</label>
                                        <div class="mt-2">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <x-checkbox-input name="expiring_contract" value="1" />
                                                <span class="text-sm text-slate-700">{{ __('transfers.expiring_contract') }}</span>
                                            </label>
                                            <p class="text-xs text-slate-500 mt-1.5 ml-6">{{ __('transfers.expiring_contract_hint') }}</p>
                                        </div>
                                    </div>

                                    {{-- Age Range Slider --}}
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="text-sm font-semibold text-slate-700">{{ __('transfers.age_range') }}</label>
                                            <span class="text-sm font-semibold text-slate-900" x-text="ageMin + ' – ' + ageMax"></span>
                                        </div>
                                        <div class="dual-range">
                                            <div class="track"></div>
                                            <div class="track-fill" :style="'left:' + ageTrackLeft() + ';width:' + ageTrackWidth()"></div>
                                            <input type="range" min="16" max="45" step="1" x-model.number="ageMin" @input="enforceAgeMin()">
                                            <input type="range" min="16" max="45" step="1" x-model.number="ageMax" @input="enforceAgeMax()">
                                        </div>
                                        <input type="hidden" name="age_min" :value="ageMin">
                                        <input type="hidden" name="age_max" :value="ageMax">
                                    </div>

                                    {{-- Ability Range Slider --}}
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="text-sm font-semibold text-slate-700">{{ __('transfers.ability_range') }}</label>
                                            <span class="text-sm font-semibold text-slate-900" x-text="abilityMin + ' – ' + abilityMax"></span>
                                        </div>
                                        <div class="dual-range">
                                            <div class="track"></div>
                                            <div class="track-fill" :style="'left:' + abilityTrackLeft() + ';width:' + abilityTrackWidth()"></div>
                                            <input type="range" min="1" max="99" step="1" x-model.number="abilityMin" @input="enforceAbilityMin()">
                                            <input type="range" min="1" max="99" step="1" x-model.number="abilityMax" @input="enforceAbilityMax()">
                                        </div>
                                        <input type="hidden" name="ability_min" :value="abilityMin">
                                        <input type="hidden" name="ability_max" :value="abilityMax">
                                    </div>

                                    {{-- Market Value Range Slider --}}
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="text-sm font-semibold text-slate-700">{{ __('transfers.value_range') }}</label>
                                            <span class="text-sm font-semibold text-slate-900" x-text="formatValue(valueMin()) + ' – ' + formatValue(valueMax())"></span>
                                        </div>
                                        <div class="dual-range">
                                            <div class="track"></div>
                                            <div class="track-fill" :style="'left:' + valueTrackLeft() + ';width:' + valueTrackWidth()"></div>
                                            <input type="range" min="0" max="9" step="1" x-model.number="valueStepMin" @input="enforceValueMin()">
                                            <input type="range" min="0" max="9" step="1" x-model.number="valueStepMax" @input="enforceValueMax()">
                                        </div>
                                        <input type="hidden" name="value_min" :value="valueMin()">
                                        <input type="hidden" name="value_max" :value="valueMax()">
                                    </div>
                                </div>

                                <div class="pt-5">
                                    <x-primary-button class="w-full py-3">
                                        {{ __('transfers.start_scout_search') }}
                                    </x-primary-button>
                                </div>
                            </form>
                        </div>

                    {{-- State: Search in progress --}}
                    @elseif($searchingReport)
                        <div class="mt-6">
                            <div class="text-center py-12 border rounded-lg bg-slate-50">
                                <svg class="w-16 h-16 mx-auto mb-4 text-sky-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <h4 class="text-lg font-semibold text-slate-900 mb-2">{{ __('transfers.scout_searching') }}</h4>
                                <p class="text-slate-600 mb-1">
                                    {{ trans_choice('game.weeks_remaining', $searchingReport->weeks_remaining, ['count' => $searchingReport->weeks_remaining]) }}
                                </p>
                                <p class="text-sm text-slate-500 mb-6">
                                    {{ __('transfers.looking_for') }}: <span class="font-medium">{{ $searchingReport->filters['position'] }}</span>
                                    @if(isset($searchingReport->filters['scope']) && count($searchingReport->filters['scope']) === 1)
                                        — <span class="font-medium">{{ in_array('domestic', $searchingReport->filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international') }}</span>
                                    @endif
                                </p>
                                <div class="w-48 mx-auto bg-slate-200 rounded-full h-2 mb-6">
                                    @php $progress = (($searchingReport->weeks_total - $searchingReport->weeks_remaining) / $searchingReport->weeks_total) * 100; @endphp
                                    <div class="bg-sky-500 h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                                </div>
                                <form method="post" action="{{ route('game.scouting.cancel', $game->id) }}">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">
                                        {{ __('transfers.cancel_search') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                    {{-- State: Viewing results --}}
                    @elseif($selectedReport)
                        <div class="mt-6" x-data>
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-lg text-slate-900">{{ __('transfers.scout_results') }}</h4>
                                <a href="{{ route('game.scouting', ['gameId' => $game->id, 'new' => 1]) }}" class="text-sm text-sky-600 hover:text-sky-800">
                                    {{ __('transfers.new_search') }}
                                </a>
                            </div>

                            @if($scoutedPlayers->isEmpty())
                                <div class="text-center py-8 text-slate-500 border rounded-lg bg-slate-50">
                                    <p>{{ __('transfers.no_players_found') }}</p>
                                    <p class="text-sm mt-1">{{ __('transfers.try_broadening') }}</p>
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="text-left border-b border-slate-200">
                                            <tr>
                                                <th class="font-medium text-slate-500 pb-2">{{ __('app.player') }}</th>
                                                <th class="font-medium text-slate-500 pb-2">{{ __('app.position') }}</th>
                                                <th class="font-medium text-slate-500 pb-2 text-center">{{ __('app.age') }}</th>
                                                <th class="font-medium text-slate-500 pb-2">{{ __('app.team') }}</th>
                                                <th class="font-medium text-slate-500 pb-2 text-right">{{ __('app.value') }}</th>
                                                <th class="font-medium text-slate-500 pb-2 text-center">{{ __('app.contract') }}</th>
                                                <th class="font-medium text-slate-500 pb-2 text-center">{{ __('transfers.ability') }}</th>
                                                <th class="font-medium text-slate-500 pb-2 text-right"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($scoutedPlayers as $player)
                                                @php
                                                    $fuzz = rand(3, 7);
                                                    $avgAbility = (int)(($player->current_technical_ability + $player->current_physical_ability) / 2);
                                                    $abilityLow = max(1, $avgAbility - $fuzz);
                                                    $abilityHigh = min(99, $avgAbility + $fuzz);
                                                @endphp
                                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                                    <td class="py-3">
                                                        <div class="font-medium text-slate-900">{{ $player->name }}</div>
                                                        @if($player->nationality_flag)
                                                            <div class="flex items-center gap-1 mt-0.5">
                                                                <img src="/flags/{{ $player->nationality_flag['code'] }}.svg" class="w-4 h-3 rounded shadow-sm">
                                                                <span class="text-xs text-slate-500">{{ $player->nationality_flag['name'] }}</span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="py-3">
                                                        <x-position-badge :position="$player->position" size="lg" />
                                                    </td>
                                                    <td class="py-3 text-center text-slate-600">{{ $player->age }}</td>
                                                    <td class="py-3">
                                                        <div class="flex items-center gap-2">
                                                            <img src="{{ $player->team->image }}" class="w-5 h-5">
                                                            <span class="text-slate-600">{{ $player->team->name }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 text-right text-slate-900">{{ $player->formatted_market_value }}</td>
                                                    <td class="py-3 text-center text-slate-600">{{ $player->contract_expiry_year ?? '-' }}</td>
                                                    <td class="py-3 text-center">
                                                        <span class="text-slate-700 font-medium">{{ $abilityLow }}-{{ $abilityHigh }}</span>
                                                    </td>
                                                    <td class="py-3 text-right">
                                                        <button @click="$dispatch('open-modal', 'scout-player-{{ $player->id }}')"
                                                           class="px-3 py-1.5 text-xs font-semibold text-sky-600 hover:text-sky-800 hover:bg-sky-50 rounded-lg transition-colors cursor-pointer">
                                                            {{ __('transfers.view_report') }}
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Player Detail Modals --}}
                                @foreach($scoutedPlayers as $scoutPlayer)
                                    @php
                                        $detail = $playerDetails[$scoutPlayer->id];
                                        $existingOffer = $existingOffers[$scoutPlayer->id] ?? null;
                                        $isExpiring = $scoutPlayer->isContractExpiring($seasonEndDate);
                                    @endphp
                                    <x-modal name="scout-player-{{ $scoutPlayer->id }}" maxWidth="2xl">
                                        <div class="p-8">
                                            {{-- Player Header --}}
                                            <div class="flex items-start justify-between mb-8">
                                                <div>
                                                    <h3 class="font-semibold text-2xl text-slate-900">{{ $scoutPlayer->name }}</h3>
                                                    <div class="flex items-center gap-3 mt-1 text-sm text-slate-600">
                                                        <x-position-badge :position="$scoutPlayer->position" size="lg" />
                                                        <span>{{ $scoutPlayer->position }}</span>
                                                        <span>&middot;</span>
                                                        <span>{{ $scoutPlayer->age }} {{ __('transfers.years') }}</span>
                                                        @if($scoutPlayer->nationality_flag)
                                                            <span>&middot;</span>
                                                            <img src="/flags/{{ $scoutPlayer->nationality_flag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm inline">
                                                            <span>{{ $scoutPlayer->nationality_flag['name'] }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <img src="{{ $scoutPlayer->team->image }}" class="w-10 h-10">
                                                    <div class="text-right">
                                                        <div class="font-semibold text-slate-900">{{ $scoutPlayer->team->name }}</div>
                                                        <div class="text-sm text-slate-500">{{ __('transfers.contract_until') }} {{ $scoutPlayer->contract_expiry_year ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Scouting Report --}}
                                            <div class="grid grid-cols-2 gap-6 mb-8">
                                                {{-- Abilities --}}
                                                <div class="border rounded-lg p-4">
                                                    <h4 class="font-semibold text-sm text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.scouting_assessment') }}</h4>
                                                    <div class="space-y-3">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-sm text-slate-600">{{ __('transfers.technical') }}</span>
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-24 bg-slate-200 rounded-full h-2">
                                                                    <div class="bg-sky-500 h-2 rounded-full" style="width: {{ (($detail['tech_range'][0] + $detail['tech_range'][1]) / 2) }}%"></div>
                                                                </div>
                                                                <span class="text-sm font-semibold text-slate-700 w-16 text-right">{{ $detail['tech_range'][0] }}-{{ $detail['tech_range'][1] }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-sm text-slate-600">{{ __('transfers.physical') }}</span>
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-24 bg-slate-200 rounded-full h-2">
                                                                    <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ (($detail['phys_range'][0] + $detail['phys_range'][1]) / 2) }}%"></div>
                                                                </div>
                                                                <span class="text-sm font-semibold text-slate-700 w-16 text-right">{{ $detail['phys_range'][0] }}-{{ $detail['phys_range'][1] }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Financials --}}
                                                <div class="border rounded-lg p-4">
                                                    <h4 class="font-semibold text-sm text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.financial_details') }}</h4>
                                                    <div class="space-y-2 text-sm">
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-600">{{ __('transfers.market_value') }}</span>
                                                            <span class="font-semibold text-slate-900">{{ $scoutPlayer->formatted_market_value }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-600">{{ __('transfers.estimated_asking_price') }}</span>
                                                            <span class="font-bold text-lg text-slate-900">{{ $detail['formatted_asking_price'] }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-600">{{ __('transfers.wage_demand') }}</span>
                                                            <span class="font-semibold text-slate-900">{{ $detail['formatted_wage_demand'] }}/{{ __('transfers.year_abbr') }}</span>
                                                        </div>
                                                        <div class="border-t pt-2 mt-2">
                                                            <div class="flex justify-between">
                                                                <span class="text-slate-600">{{ __('transfers.your_transfer_budget') }}</span>
                                                                <span class="font-semibold {{ $detail['can_afford_fee'] ? 'text-green-600' : 'text-red-600' }}">
                                                                    {{ $detail['formatted_transfer_budget'] }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Affordability Warnings --}}
                                            @if(!$detail['can_afford_fee'])
                                                <div class="mb-6 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                                                    {{ __('transfers.transfer_fee_exceeds_budget') }}
                                                </div>
                                            @endif
                                            @if(!$detail['can_afford_wage'])
                                                <div class="mb-6 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
                                                    {{ __('transfers.wage_demand_warning') }}
                                                </div>
                                            @endif

                                            {{-- Pre-Contract Section (for expiring contracts) --}}
                                            @if($isExpiring)
                                                @if($isPreContractPeriod)
                                                    @if($existingOffer && $existingOffer->isPreContract() && $existingOffer->isAgreed())
                                                        {{-- Already have agreed pre-contract --}}
                                                        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                                                            <div class="flex items-center gap-2 text-green-700 font-semibold">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                {{ __('transfers.pre_contract_offer') }} — {{ __('transfers.deal_agreed') }}!
                                                            </div>
                                                            <p class="text-sm text-green-600 mt-1">
                                                                {{ __('transfers.player_will_join', ['player' => $scoutPlayer->name]) }} {{ __('transfers.next_transfer_window') }}.
                                                            </p>
                                                        </div>
                                                    @else
                                                        <div class="mb-6 p-5 bg-sky-50 border border-sky-200 rounded-lg">
                                                            <h4 class="font-semibold text-sky-900 mb-1">{{ __('transfers.pre_contract_offer') }}</h4>
                                                            <p class="text-sm text-sky-700 mb-4">{{ __('transfers.pre_contract_description') }}</p>
                                                            <form method="post" action="{{ route('game.scouting.pre-contract', [$game->id, $scoutPlayer->id]) }}">
                                                                @csrf
                                                                <div class="mb-3">
                                                                    <label for="offered_wage_{{ $scoutPlayer->id }}" class="block text-sm font-medium text-sky-800 mb-1">{{ __('transfers.offered_wage_euros') }}</label>
                                                                    <x-text-input type="number" name="offered_wage" id="offered_wage_{{ $scoutPlayer->id }}" min="0" step="100000"
                                                                           value="{{ (int)($detail['wage_demand'] / 100) }}"
                                                                           class="w-full" />
                                                                    <p class="text-xs text-sky-600 mt-1">{{ __('transfers.wage_demand') }}: {{ $detail['formatted_wage_demand'] }}/{{ __('transfers.year_abbr') }}</p>
                                                                </div>
                                                                <x-primary-button color="sky" class="w-full py-2.5">{{ __('transfers.submit_pre_contract') }}</x-primary-button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                @else
                                                    {{-- Not in pre-contract period --}}
                                                    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                                        <div class="flex items-center gap-2">
                                                            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            <p class="text-sm text-amber-800">{{ __('transfers.pre_contract_available_from_jan') }}</p>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif

                                            {{-- Existing Offer Status --}}
                                            @if($existingOffer && !($existingOffer->isPreContract()))
                                                <div class="mb-6 p-4 border rounded-lg {{ $existingOffer->isAgreed() ? 'bg-green-50 border-green-200' : 'bg-sky-50 border-sky-200' }}">
                                                    @if($existingOffer->isAgreed())
                                                        <div class="flex items-center gap-2 text-green-700 font-semibold">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                            {{ __('transfers.deal_agreed') }}!
                                                        </div>
                                                        <p class="text-sm text-green-600 mt-1">
                                                            @if($existingOffer->offer_type === 'loan_in')
                                                                {{ __('transfers.loan_deal_agreed', ['player' => $scoutPlayer->name]) }}
                                                                {{ $game->isTransferWindowOpen() ? __('transfers.immediately') : __('transfers.next_transfer_window') }}.
                                                            @else
                                                                {{ __('transfers.transfer_fee') }}: {{ $existingOffer->formatted_transfer_fee }}.
                                                                {{ __('transfers.player_will_join', ['player' => $scoutPlayer->name]) }}
                                                                {{ $game->isTransferWindowOpen() ? __('transfers.immediately') : __('transfers.next_transfer_window') }}.
                                                            @endif
                                                        </p>
                                                    @elseif($existingOffer->isPending() && $existingOffer->asking_price && $existingOffer->transfer_fee < $existingOffer->asking_price)
                                                        {{-- Counter-offer --}}
                                                        <div class="font-semibold text-sky-700">{{ __('transfers.counter_offer_received') }}</div>
                                                        <p class="text-sm text-sky-600 mt-1">
                                                            {{ __('transfers.team_counter_with', ['team' => $scoutPlayer->team->name, 'amount' => \App\Support\Money::format($existingOffer->asking_price), 'your_bid' => $existingOffer->formatted_transfer_fee]) }}
                                                        </p>
                                                        <div class="flex gap-2 mt-3">
                                                            <form method="post" action="{{ route('game.scouting.counter.accept', [$game->id, $existingOffer->id]) }}">
                                                                @csrf
                                                                <x-primary-button color="green">{{ __('transfers.accept_counter') }}</x-primary-button>
                                                            </form>
                                                        </div>
                                                    @else
                                                        <div class="font-semibold text-sky-700">{{ __('transfers.bid_pending') }}</div>
                                                        <p class="text-sm text-sky-600 mt-1">{{ __('transfers.your_bid_being_considered', ['amount' => $existingOffer->formatted_transfer_fee]) }}</p>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Action Buttons (Transfer Bid / Loan) --}}
                                            @if(!$existingOffer || (!$existingOffer->isAgreed() && !$existingOffer->isPending()))
                                                <div class="grid grid-cols-2 gap-6">
                                                    {{-- Transfer Bid --}}
                                                    <div class="border rounded-lg p-6">
                                                        <h4 class="font-semibold text-slate-900 mb-3">{{ __('transfers.make_transfer_offer') }}</h4>
                                                        <p class="text-sm text-slate-600 mb-4">{{ __('transfers.submit_bid_description') }}</p>
                                                        @if($detail['can_afford_fee'])
                                                            <form method="post" action="{{ route('game.scouting.bid', [$game->id, $scoutPlayer->id]) }}">
                                                                @csrf
                                                                <div class="mb-4">
                                                                    <label for="bid_amount_{{ $scoutPlayer->id }}" class="block text-sm font-medium text-slate-700 mb-1">{{ __('transfers.your_bid_euros') }}</label>
                                                                    <x-text-input type="number" name="bid_amount" id="bid_amount_{{ $scoutPlayer->id }}" min="0" step="100000"
                                                                           value="{{ (int)($detail['asking_price'] / 100) }}"
                                                                           class="w-full" />
                                                                    <p class="text-xs text-slate-500 mt-1">{{ __('transfers.asking_price') }}: {{ $detail['formatted_asking_price'] }}</p>
                                                                </div>
                                                                <x-primary-button color="sky" class="w-full py-2.5">{{ __('transfers.submit_bid') }}</x-primary-button>
                                                            </form>
                                                        @else
                                                            <p class="text-sm text-red-600">{{ __('transfers.insufficient_transfer_budget') }}</p>
                                                        @endif
                                                    </div>

                                                    {{-- Loan Request --}}
                                                    <div class="border rounded-lg p-6">
                                                        <h4 class="font-semibold text-slate-900 mb-3">{{ __('transfers.request_loan') }}</h4>
                                                        <p class="text-sm text-slate-600 mb-4">{{ __('transfers.request_loan_description') }}</p>
                                                        <form method="post" action="{{ route('game.scouting.loan', [$game->id, $scoutPlayer->id]) }}">
                                                            @csrf
                                                            <x-primary-button color="emerald" class="w-full py-2.5">{{ __('transfers.request_loan') }}</x-primary-button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </x-modal>
                                @endforeach
                            @endif
                        </div>
                    @endif

                    {{-- Search History --}}
                    @if($searchHistory->isNotEmpty())
                        <div class="mt-8 border-t pt-6">
                            <h4 class="font-semibold text-sm text-slate-500 uppercase tracking-wide mb-3">{{ __('transfers.search_history') }}</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-left border-b border-slate-200">
                                        <tr>
                                            <th class="font-medium text-slate-500 pb-2">{{ __('app.position') }}</th>
                                            <th class="font-medium text-slate-500 pb-2">{{ __('transfers.scope') }}</th>
                                            <th class="font-medium text-slate-500 pb-2">{{ __('app.date') }}</th>
                                            <th class="font-medium text-slate-500 pb-2 text-center">{{ __('transfers.scout_results') }}</th>
                                            <th class="font-medium text-slate-500 pb-2 text-right"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($searchHistory as $historyReport)
                                            @php
                                                $isActive = $selectedReport && $selectedReport->id === $historyReport->id;
                                                $filters = $historyReport->filters;
                                                $scopeLabel = isset($filters['scope']) && count($filters['scope']) === 1
                                                    ? (in_array('domestic', $filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international'))
                                                    : __('transfers.scope_domestic') . ' + ' . __('transfers.scope_international');
                                                $resultCount = is_array($historyReport->player_ids) ? count($historyReport->player_ids) : 0;
                                            @endphp
                                            <tr class="border-b border-slate-100 {{ $isActive ? 'bg-sky-50' : 'hover:bg-slate-50' }}">
                                                <td class="py-2.5">
                                                    <span class="font-medium text-slate-900">{{ $filters['position'] ?? '-' }}</span>
                                                </td>
                                                <td class="py-2.5 text-slate-600">{{ $scopeLabel }}</td>
                                                <td class="py-2.5 text-slate-600">{{ $historyReport->game_date->format('d M') }}</td>
                                                <td class="py-2.5 text-center text-slate-600">{{ __('transfers.results_count', ['count' => $resultCount]) }}</td>
                                                <td class="py-2.5 text-right">
                                                    @if($isActive)
                                                        <span class="px-3 py-1 text-xs font-semibold text-sky-700 bg-sky-100 rounded-full">{{ __('transfers.view_results') }}</span>
                                                    @else
                                                        <a href="{{ route('game.scouting', ['gameId' => $game->id, 'report' => $historyReport->id]) }}"
                                                           class="px-3 py-1.5 text-xs font-semibold text-sky-600 hover:text-sky-800 hover:bg-sky-50 rounded-lg transition-colors">
                                                            {{ __('transfers.view_results') }}
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
