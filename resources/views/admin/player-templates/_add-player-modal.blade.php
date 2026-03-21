<x-modal name="add-player" maxWidth="3xl">
    <div x-data="{
        form: {
            season: @js($selectedSeason),
            team_id: @js($team->id),
            name: '',
            date_of_birth: '',
            nationality: '',
            number: '',
            position: 'Centre-Forward',
            market_value_cents: 0,
            contract_until: '',
            annual_wage: 0,
            durability: 50,
            game_technical_ability: 50,
            game_physical_ability: 50,
            potential: 50,
            potential_low: 40,
            potential_high: 60,
            tier: 1,
        },
    }" class="p-6">
        <h2 class="font-heading text-lg font-bold uppercase tracking-wide text-text-primary mb-4">
            {{ __('admin.add_player') }}
        </h2>

        <form method="POST" action="{{ route('admin.player-templates.store') }}">
            @csrf
            <input type="hidden" name="season" x-bind:value="form.season">
            <input type="hidden" name="team_id" x-bind:value="form.team_id">

            {{-- Section: Personal Details --}}
            <div class="text-[10px] text-text-muted uppercase tracking-wider font-semibold mb-2">{{ __('admin.section_personal') }}</div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3">
                <div>
                    <x-input-label value="{{ __('admin.tpl_player') }}" />
                    <x-text-input type="text" name="name" x-model="form.name" class="w-full" required />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_date_of_birth') }}" />
                    <x-text-input type="date" name="date_of_birth" x-model="form.date_of_birth" class="w-full" required />
                    <x-input-error :messages="$errors->get('date_of_birth')" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_nationality') }}" />
                    <x-select-input name="nationality" x-model="form.nationality" class="w-full">
                        <option value="">—</option>
                        @foreach($countries as $country)
                            <option value="{{ $country }}">{{ $country }}</option>
                        @endforeach
                    </x-select-input>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div>
                    <x-input-label value="{{ __('admin.tpl_number') }}" />
                    <x-text-input type="number" name="number" x-model="form.number" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_position') }}" />
                    <x-select-input name="position" x-model="form.position" class="w-full">
                        @foreach($positions as $pos)
                            <option value="{{ $pos }}">{{ $pos }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_contract_until') }}" />
                    <x-text-input type="date" name="contract_until" x-model="form.contract_until" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_tier') }}" />
                    <x-select-input name="tier" x-model="form.tier" class="w-full">
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </x-select-input>
                </div>
            </div>

            <hr class="border-border-default my-4">

            {{-- Section: Game Parameters --}}
            <div class="text-[10px] text-text-muted uppercase tracking-wider font-semibold mb-2">{{ __('admin.section_game_params') }}</div>
            <div class="grid grid-cols-3 md:grid-cols-6 gap-2 mb-4">
                <div>
                    <x-input-label value="{{ __('admin.tpl_tech') }}" />
                    <x-text-input type="number" name="game_technical_ability" x-model="form.game_technical_ability" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_phys') }}" />
                    <x-text-input type="number" name="game_physical_ability" x-model="form.game_physical_ability" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_potential') }}" />
                    <x-text-input type="number" name="potential" x-model="form.potential" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_potential_low') }}" />
                    <x-text-input type="number" name="potential_low" x-model="form.potential_low" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_potential_high') }}" />
                    <x-text-input type="number" name="potential_high" x-model="form.potential_high" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_durability') }}" />
                    <x-text-input type="number" name="durability" x-model="form.durability" min="0" max="100" class="w-full" />
                </div>
            </div>

            <hr class="border-border-default my-4">

            {{-- Section: Financial --}}
            <div class="text-[10px] text-text-muted uppercase tracking-wider font-semibold mb-2">{{ __('admin.section_financial') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                <div>
                    <x-input-label value="{{ __('admin.tpl_market_value') }}" />
                    <x-money-input name="market_value_euros" :value="0" size="md" :presets="[500000, 2000000, 5000000, 10000000, 20000000, 50000000, 100000000]" />
                </div>
                <div>
                    <div class="flex items-center gap-1.5 mb-1">
                        <x-input-label value="{{ __('admin.tpl_annual_wage') }}" class="mb-0" />
                        @include('admin.player-templates._wage-tooltip')
                    </div>
                    <x-money-input name="annual_wage_euros" :value="0" size="md" :presets="[100000, 500000, 1000000, 3000000, 5000000, 10000000, 20000000]" />
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-border-default">
                <x-ghost-button type="button" color="slate" size="xs" @click="$dispatch('close-modal', 'add-player')">
                    {{ __('admin.cancel') }}
                </x-ghost-button>
                <x-primary-button type="submit" color="blue" size="xs" x-bind:disabled="!form.name">
                    {{ __('admin.add_player') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>
