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
                <div class="p-8">
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
                    <x-transfers-nav :game="$game" active="scouting" />

                    {{-- State: No active search → Show search form --}}
                    @if(!$report)
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
                                        <select name="position" id="position" required class="w-full border-slate-300 rounded-lg shadow-sm text-sm focus:ring-red-500 focus:border-red-500">
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
                                        </select>
                                        @error('position')
                                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Scope --}}
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('transfers.scope') }}</label>
                                        <div class="flex items-center gap-5 mt-2">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="scope[]" value="domestic" x-model="scopeDomestic" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                                <span class="text-sm text-slate-700">{{ __('transfers.scope_domestic') }}</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="scope[]" value="international" x-model="scopeInternational" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                                <span class="text-sm text-slate-700">{{ __('transfers.scope_international') }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- Expiring contract --}}
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ __('transfers.contract') }}</label>
                                        <div class="mt-2">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="expiring_contract" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                                <span class="text-sm text-slate-700">{{ __('transfers.expiring_contract') }}</span>
                                            </label>
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
                                    <button type="submit" class="w-full uppercase py-3 bg-red-600 text-white font-semibold rounded-lg tracking-wide hover:bg-red-700 focus:bg-red-700 active:bg-red-900 ease-in-out duration-150 transition">
                                        {{ __('transfers.start_scout_search') }}
                                    </button>
                                </div>
                            </form>
                        </div>

                    {{-- State: Search in progress --}}
                    @elseif($report->isSearching())
                        <div class="mt-6">
                            <div class="text-center py-12 border rounded-lg bg-slate-50">
                                <svg class="w-16 h-16 mx-auto mb-4 text-sky-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <h4 class="text-lg font-semibold text-slate-900 mb-2">{{ __('transfers.scout_searching') }}</h4>
                                <p class="text-slate-600 mb-1">
                                    {{ trans_choice('game.weeks_remaining', $report->weeks_remaining, ['count' => $report->weeks_remaining]) }}
                                </p>
                                <p class="text-sm text-slate-500 mb-6">
                                    {{ __('transfers.looking_for') }}: <span class="font-medium">{{ $report->filters['position'] }}</span>
                                    @if(isset($report->filters['scope']) && count($report->filters['scope']) === 1)
                                        — <span class="font-medium">{{ in_array('domestic', $report->filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international') }}</span>
                                    @endif
                                </p>
                                <div class="w-48 mx-auto bg-slate-200 rounded-full h-2 mb-6">
                                    @php $progress = (($report->weeks_total - $report->weeks_remaining) / $report->weeks_total) * 100; @endphp
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

                    {{-- State: Results ready --}}
                    @elseif($report->isCompleted())
                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-lg text-slate-900">{{ __('transfers.scout_results') }}</h4>
                                <form method="post" action="{{ route('game.scouting.cancel', $game->id) }}">
                                    @csrf
                                    <button type="submit" class="text-sm text-sky-600 hover:text-sky-800">
                                        {{ __('transfers.new_search') }}
                                    </button>
                                </form>
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
                                                    $positionDisplay = $player->position_display;
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
                                                        <span class="px-1.5 py-0.5 text-xs font-medium rounded {{ $positionDisplay['bg'] }} {{ $positionDisplay['text'] }}">
                                                            {{ $positionDisplay['abbreviation'] }}
                                                        </span>
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
                                                        <a href="{{ route('game.scouting.player', [$game->id, $player->id]) }}"
                                                           class="px-3 py-1.5 text-xs font-semibold text-sky-600 hover:text-sky-800 hover:bg-sky-50 rounded-lg transition-colors">
                                                            {{ __('transfers.view_report') }}
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
