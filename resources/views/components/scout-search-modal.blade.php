@props(['game', 'canSearchInternationally'])

<x-modal name="scout-search" maxWidth="3xl">
    <div class="p-4 md:p-8"
        x-data="{
            ageMin: 16,
            ageMax: 40,
            abilityMin: 50,
            abilityMax: 99,
            valueStepMin: 0,
            valueStepMax: 9,
            valueSteps: [0, 500000, 1000000, 2000000, 5000000, 10000000, 20000000, 50000000, 100000000, 200000000],
            valueMin() { return this.valueSteps[this.valueStepMin]; },
            valueMax() { return this.valueSteps[this.valueStepMax]; },
            enforceValueMin() { if (this.valueStepMin > this.valueStepMax) this.valueStepMax = this.valueStepMin; },
            enforceValueMax() { if (this.valueStepMax < this.valueStepMin) this.valueStepMin = this.valueStepMax; },
            scopeDomestic: true,
            scopeInternational: {{ $canSearchInternationally ? 'true' : 'false' }},
            formatValue(val) {
                if (val === 0) return '€0';
                if (val >= 1000000) return '€' + (val / 1000000) + 'M';
                if (val >= 1000) return '€' + (val / 1000) + 'K';
                return '€' + val;
            },
            ageTrackLeft() { return ((this.ageMin - 16) / (40 - 16)) * 100 + '%'; },
            ageTrackWidth() { return ((this.ageMax - this.ageMin) / (40 - 16)) * 100 + '%'; },
            abilityTrackLeft() { return ((this.abilityMin - 50) / (99 - 50)) * 100 + '%'; },
            abilityTrackWidth() { return ((this.abilityMax - this.abilityMin) / (99 - 50)) * 100 + '%'; },
            valueTrackLeft() { return (this.valueStepMin / 9) * 100 + '%'; },
            valueTrackWidth() { return ((this.valueStepMax - this.valueStepMin) / 9) * 100 + '%'; },
            enforceAgeMin() { if (this.ageMin > this.ageMax) this.ageMax = this.ageMin; },
            enforceAgeMax() { if (this.ageMax < this.ageMin) this.ageMin = this.ageMax; },
            enforceAbilityMin() { if (this.abilityMin > this.abilityMax) this.abilityMax = this.abilityMin; },
            enforceAbilityMax() { if (this.abilityMax < this.abilityMin) this.abilityMin = this.abilityMax; },
        }">

        <div class="flex items-start justify-between gap-4 mb-2">
            <h3 class="font-semibold text-xl text-slate-900">{{ __('transfers.new_scout_search') }}</h3>
            <button onclick="window.dispatchEvent(new CustomEvent('close-modal', {detail: 'scout-search'}))" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <p class="text-sm text-slate-600 mb-6">{{ __('transfers.scout_search_desc') }}</p>

        <form method="post" action="{{ route('game.scouting.search', $game->id) }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                {{-- LEFT: Dropdowns & checkboxes --}}
                <div class="space-y-5">
                    {{-- Position --}}
                    <div>
                        <label for="position" class="block text-sm font-semibold text-slate-700 mb-1">{{ __('transfers.position_required') }}</label>
                        <x-select-input name="position" id="position" required class="w-full">
                            <option value="">{{ __('transfers.select_position') }}</option>
                            <optgroup label="{{ __('transfers.specific_positions') }}">
                                <option value="GK">{{ __('positions.goalkeeper_label') }}</option>
                                <option value="CB">{{ __('positions.centre_back_label') }}</option>
                                <option value="LB">{{ __('positions.left_back_label') }}</option>
                                <option value="RB">{{ __('positions.right_back_label') }}</option>
                                <option value="DM">{{ __('positions.defensive_midfield_label') }}</option>
                                <option value="CM">{{ __('positions.central_midfield_label') }}</option>
                                <option value="AM">{{ __('positions.attacking_midfield_label') }}</option>
                                <option value="LW">{{ __('positions.left_winger_label') }}</option>
                                <option value="RW">{{ __('positions.right_winger_label') }}</option>
                                <option value="CF">{{ __('positions.centre_forward_label') }}</option>
                            </optgroup>
                            <optgroup label="{{ __('transfers.position_groups') }}">
                                <option value="any_defender">{{ __('positions.any_defender') }}</option>
                                <option value="any_midfielder">{{ __('positions.any_midfielder') }}</option>
                                <option value="any_forward">{{ __('positions.any_forward') }}</option>
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
                            @if($canSearchInternationally)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <x-checkbox-input name="scope[]" value="international" x-model="scopeInternational" />
                                <span class="text-sm text-slate-700">{{ __('transfers.scope_international') }}</span>
                            </label>
                            @else
                            <label class="flex items-center gap-2 opacity-40 cursor-not-allowed">
                                <x-checkbox-input name="scope[]" value="international" x-model="scopeInternational" disabled />
                                <span class="text-sm text-slate-700">{{ __('transfers.scope_international') }}</span>
                            </label>
                            @endif
                        </div>
                        @unless($canSearchInternationally)
                            <p class="text-xs text-slate-400 mt-1">{{ __('transfers.scope_international_locked') }}</p>
                        @endunless
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
                </div>

                {{-- RIGHT: Sliders --}}
                <div class="space-y-5">
                    {{-- Age Range Slider --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-slate-700">{{ __('transfers.age_range') }}</label>
                            <span class="text-sm font-semibold text-slate-900" x-text="ageMin + ' – ' + ageMax"></span>
                        </div>
                        <div class="dual-range">
                            <div class="track"></div>
                            <div class="track-fill" :style="'left:' + ageTrackLeft() + ';width:' + ageTrackWidth()"></div>
                            <input type="range" min="16" max="40" step="1" x-model.number="ageMin" @input="enforceAgeMin()">
                            <input type="range" min="16" max="40" step="1" x-model.number="ageMax" @input="enforceAgeMax()">
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
                            <input type="range" min="50" max="99" step="1" x-model.number="abilityMin" @input="enforceAbilityMin()">
                            <input type="range" min="50" max="99" step="1" x-model.number="abilityMax" @input="enforceAbilityMax()">
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
            </div>

            <div class="pt-5">
                <x-primary-button class="w-full py-3">
                    {{ __('transfers.start_scout_search') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>
